<?php

namespace App\Console\Commands\Billing;

use App\Models\Company;
use App\Models\Subscription;
use App\Models\User;
use App\Services\BillingRobo\BillingRoboClient;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Str;
use Throwable;

class RescueLegacySubscriptionsCommand extends Command
{
    protected $signature = 'billing:rescue-legacy-subscriptions
        {--dry-run : 更新せず対象だけ確認する}
        {--force : 既存値も上書きする}
        {--company_id=* : 対象会社IDを限定する}
        {--term-start=2026-03-01 : 一律で入れる契約開始日}
        {--term-end=2027-02-28 : 一律で入れる契約終了日}
        {--payment-method=credit : 一律で入れる支払方法}
        {--limit=500 : 対象上限}';

    protected $description = 'Rescue legacy subscriptions by backfilling contract fields and remote billing codes.';

    public function handle(BillingRoboClient $client): int
    {
        $tz = 'Asia/Tokyo';
        $now = Carbon::now($tz);
        $dryRun = (bool) $this->option('dry-run');
        $force = (bool) $this->option('force');
        $termStart = Carbon::parse((string) $this->option('term-start'), $tz)->startOfDay()->toDateString();
        $termEnd = Carbon::parse((string) $this->option('term-end'), $tz)->startOfDay()->toDateString();
        $paymentMethod = (string) $this->option('payment-method');
        $limit = max(1, min(5000, (int) $this->option('limit')));
        $companyIds = collect((array) $this->option('company_id'))
            ->filter(fn ($v) => (string) $v !== '')
            ->map(fn ($v) => (int) $v)
            ->filter(fn ($v) => $v > 0)
            ->values()
            ->all();

        // 対象会社:
        // - 明示指定があればその会社
        // - 無ければ owner/registrar/group_admin/member が1人でもいる会社
        $targetCompanyQuery = Company::query()->orderBy('id');
        if (!empty($companyIds)) {
            $targetCompanyQuery->whereIn('id', $companyIds);
        } else {
            $targetCompanyQuery->whereIn('id', function ($q) {
                $q->select('company_id')
                    ->from((new User())->getTable())
                    ->whereNotNull('company_id')
                    ->whereIn('role', ['owner', 'registrar', 'group_admin', 'member'])
                    ->groupBy('company_id');
            });
        }
        $companies = $targetCompanyQuery->limit($limit)->get();

        $scanned = 0;
        $updated = 0;
        $skipped = 0;
        $remoteCreated = 0;
        $errors = 0;

        foreach ($companies as $company) {
        $scanned++;
            /** @var Subscription|null $sub */
            $sub = Subscription::query()->where('company_id', (int) $company->id)->first();

            // subscription が無い会社は新規作成する
            if (!$sub) {
                $sub = new Subscription();
                $sub->company_id = (int) $company->id;
            }

            $seatLimit = (int) ($sub->seat_limit ?? 0);
            $targetQuantity = $seatLimit > 0
                ? (int) ceil($seatLimit / 5)
                : 1;
            $targetQuantity = max(1, $targetQuantity);

            $targetBillingCode = (string) ($sub->billing_code ?: $this->makeBillingCode((int) $company->id));

            $needRemoteBillingCreate = empty($sub->billing_code);

            if ($dryRun) {
                $this->line(sprintf(
                    '[dry-run] subscription_id=%s company_id=%d quantity:%s=>%d billing_code:%s=>%s',
                    $sub->exists ? (string) $sub->id : 'new',
                    (int) $company->id,
                    (string) ($sub->quantity ?? 'null'),
                    $targetQuantity,
                    (string) ($sub->billing_code ?? 'null'),
                    $targetBillingCode
                ));
                continue;
            }

            try {
                if ($needRemoteBillingCreate) {
                    $this->createRemoteBilling($client, (int) $company->id, $targetBillingCode, (string) $company->name);
                    $remoteCreated++;
                }

                if ($force || empty($sub->status)) {
                    $sub->status = 'active';
                }
                if ($force || empty($sub->applied_at)) {
                    $sub->applied_at = $now->copy();
                }
                if ($force || empty($sub->term_start)) {
                    $sub->term_start = $termStart;
                }
                if ($force || empty($sub->term_end)) {
                    $sub->term_end = $termEnd;
                }
                if ($force || empty($sub->paid_through)) {
                    $sub->paid_through = $termEnd;
                }
                if ($force || empty($sub->payment_method)) {
                    $sub->payment_method = $paymentMethod;
                }
                if ($force || empty($sub->billing_code)) {
                    $sub->billing_code = $targetBillingCode;
                }
                if ($force || empty($sub->quantity) || (int) $sub->quantity <= 0) {
                    $sub->quantity = $targetQuantity;
                }

                if ($sub->isDirty()) {
                    $sub->save();
                    $updated++;
                } else {
                    $skipped++;
                }
            } catch (Throwable $e) {
                $errors++;
                $sid = $sub->exists ? (string) $sub->id : 'new';
                $this->error("subscription_id={$sid} company_id={$company->id} failed: {$e->getMessage()}");
            }
        }

        $this->info("scanned={$scanned} updated={$updated} skipped={$skipped} remote_created={$remoteCreated} errors={$errors}");
        return $errors > 0 ? self::FAILURE : self::SUCCESS;
    }

    private function makeBillingCode(int $companyId): string
    {
        // なるべく短く、会社ごとに一意で安定したコード
        return 'LEGACY' . str_pad((string) $companyId, 8, '0', STR_PAD_LEFT);
    }

    private function createRemoteBilling(BillingRoboClient $client, int $companyId, string $billingCode, string $companyName): void
    {
        $name = trim($companyName) !== '' ? $companyName : ('Company-' . $companyId);
        $individualCode = $billingCode . '-01';
        $email = 'billing+company' . $companyId . '@example.com';

        $client->billingBulkUpsert([
            [
                'code' => $billingCode,
                'name' => mb_substr($name, 0, 100),
                'individual' => [
                    [
                        'code' => $individualCode,
                        'name' => '本社',
                        'address1' => mb_substr($name, 0, 90) . ' 御中',
                        'zip_code' => '1000001',
                        'pref' => '東京都',
                        'city_address' => '千代田区千代田1-1',
                        'email' => $email,
                    ],
                ],
            ],
        ]);
    }
}