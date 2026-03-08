<?php

namespace App\Jobs\Billing;

use App\Models\Subscription;
use App\Models\SubscriptionInvoice;
use App\Services\BillingRobo\BillingRoboClient;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * 請求管理ロボの bill/search を使って入金状況を同期し、
 * - invoice.status を paid へ
 * - subscription.status / paid_through を更新
 *
 * 仕様：
 * - bill_number（請求書番号）で検索
 * - clearing_status in (1,2) かつ unclearing_amount == 0 を入金完了とみなす
 */
class SyncSubscriptionInvoiceJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;
    public array $backoff = [60, 300, 900, 1800, 3600];

    public function __construct(public int $invoiceId)
    {
        $this->onQueue('default');
    }

    public function handle(BillingRoboClient $client): void
    {
        /** @var SubscriptionInvoice|null $inv */
        $inv = SubscriptionInvoice::query()->find($this->invoiceId);
        if (!$inv) {
            return;
        }

        // bill_number が無い（発行前）なら同期できない
        if (!$inv->bill_number) {
            $inv->last_synced_at = now();
            $inv->last_sync_error = 'bill_number is empty (not issued yet).';
            $inv->save();
            return;
        }

        try {
            $res = $client->billSearchByNumber((string)$inv->bill_number);

            // レスポンス構造は契約により若干差があり得るため、防御的に取り出す
            $bills = $res['bill'] ?? $res['bills'] ?? null;
            if (!is_array($bills) || empty($bills)) {
                throw new \RuntimeException('bill/search returned empty.');
            }
            $bill = is_array($bills[0] ?? null) ? $bills[0] : (is_array($bills) ? $bills : null);
            if (!is_array($bill)) {
                throw new \RuntimeException('bill/search response shape is unexpected.');
            }

            $clearingStatus = isset($bill['clearing_status']) ? (int)$bill['clearing_status'] : null;
            $unclearingAmount = isset($bill['unclearing_amount']) ? (int)$bill['unclearing_amount'] : null;
            $transferDateRaw = (string)($bill['transfer_date'] ?? '');

            $inv->clearing_status = $clearingStatus;
            $inv->unclearing_amount = $unclearingAmount;
            $inv->transfer_date = $this->parseYmd($transferDateRaw);
            $inv->last_synced_at = now();
            $inv->last_sync_error = null;

            $isPaid = ($clearingStatus !== null && in_array($clearingStatus, [1, 2], true))
                && ($unclearingAmount !== null && $unclearingAmount === 0);

            if (!$isPaid) {
                $inv->save();
                return;
            }

            DB::transaction(function () use ($inv) {
                $inv->status = 'paid';
                $inv->save();

                /** @var Subscription|null $sub */
                $sub = Subscription::query()->lockForUpdate()->find($inv->subscription_id);
                if (!$sub) {
                    return;
                }

                // 追加口数（kind=add_quantity）は、入金確認後に口数反映（厳格）
                if ((string)$inv->kind === 'add_quantity') {
                    $sub->quantity = max(1, (int)$sub->quantity + max(0, (int)$inv->quantity));
                }

                // 初回/追加/更新いずれでも「入金確認が取れたら active」
                $sub->status = 'active';

                // paid_through は「その期のterm_end」をSoT（今回の仕様）
                // ※ termが未設定なら、transfer_date を基準に 1年-1日 を仮計算（保険）
                if (!empty($sub->term_end)) {
                    $sub->paid_through = $sub->term_end;
                } else {
                    $base = $inv->transfer_date ? Carbon::parse($inv->transfer_date) : now();
                    $sub->paid_through = $base->copy()->addYear()->subDay()->toDateString();
                }

                $sub->last_synced_at = now();
                $sub->save();
            });
        } catch (Throwable $e) {
            $inv->last_synced_at = now();
            $inv->last_sync_error = $e->getMessage();
            $inv->save();
            throw $e; // retry
        }
    }

    private function parseYmd(string $value): ?string
    {
        $v = trim($value);
        if ($v === '') return null;
        // bill/search は yyyy/mm/dd が多い（date cast用に Y-m-d に正規化）
        $v = str_replace('-', '/', $v);
        if (!preg_match('/^\d{4}\/\d{2}\/\d{2}$/', $v)) {
            return null;
        }
        return str_replace('/', '-', $v);
    }
}
