<?php

namespace App\Services\Billing;

use App\Models\Company;
use App\Models\CompanyBillingSetting;
use App\Models\Subscription;
use App\Models\SubscriptionInvoice;
use App\Models\User;
use App\Services\BillingRobo\BillingRoboClient;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

/**
 * 発行フロー（初回契約）
 * - subscription 作成/更新（pending）
 * - invoice(pending) 作成
 * - demand/bulk_upsert
 * - bulk_issue_bill_select（bill.number 取得）
 * - invoice を issued へ
 */
class IssueInvoiceService
{
    private const BANK_TRANSFER_PAYMENT_METHOD = 0;
    private const CREDIT_PAYMENT_METHOD = 1;
    private const RP_PAYMENT_METHOD = 3;

    public function __construct(private BillingRoboClient $client)
    {
    }

    /**
     * 初回契約の請求書を発行する（1口=5席/年額3万円）
     *
     * @param Company $company
     * @param string $billingCode 会社＋支店から生成済みの固定コード
     * @param string $paymentMethod credit/debit/bank_transfer 等（ふるさと側の確定値）
     * @param int $quantity 口数（初回は1想定）
     * @return SubscriptionInvoice
     */
    public function issueInitial(Company $company, string $billingCode, string $paymentMethod, int $quantity = 1): SubscriptionInvoice
    {
        $quantity = max(1, (int)$quantity);

        return DB::transaction(function () use ($company, $billingCode, $paymentMethod, $quantity) {
            // 1) subscription（1社=1行）
            $sub = Subscription::query()
                ->where('company_id', (int)$company->id)
                ->lockForUpdate()
                ->first();

            if (!$sub) {
                $sub = new Subscription();
                $sub->company_id = (int)$company->id;
            }

            // 契約開始は「当月1日」
            $termStart = Carbon::now('Asia/Tokyo')->startOfMonth()->toDateString();
            $termEnd = Carbon::parse($termStart, 'Asia/Tokyo')->addYear()->subDay()->toDateString(); // 期末＝翌年同日前日（うるう年含め自然）

            $sub->status = 'pending';
            if (empty($sub->applied_at)) {
                $sub->applied_at = Carbon::now('Asia/Tokyo');
            }
            $sub->quantity = $quantity;
            $sub->term_start = $termStart;
            $sub->term_end = $termEnd;
            $sub->paid_through = null;
            $sub->payment_method = $paymentMethod;
            $sub->billing_code = $billingCode;
            $sub->save();

            // 2) invoice(pending) 作成
            // 請求書発行日＝「今日」（契約は月初扱いでも、請求書を過去日にしない）
            $issueDate = Carbon::now('Asia/Tokyo')->startOfDay();
            $dueDate = $issueDate->copy()->addDays(7);

            return $this->createAndIssueInvoice(
                companyId: (int)$company->id,
                subscriptionId: (int)$sub->id,
                kind: 'initial',
                billingCode: $billingCode,
                paymentMethod: $paymentMethod,
                quantity: $quantity,
                unitPriceYen: 30000,
                monthsCharged: 12,
                amountYen: 30000 * $quantity,
                periodStart: $termStart,
                periodEnd: $termEnd,
                issueDate: $issueDate,
                dueDate: $dueDate,
                attachBillingIndividual: true,
            );
        });
    }

    /**
     * 更新請求（renewal）を作成・発行する。
     * 同一 subscription_id + 同一期間 に未取消の renewal がある場合は作成しない。
     */
    public function issueRenewal(Subscription $sub, Carbon $issueDate): ?SubscriptionInvoice
    {
        $issueDate = $issueDate->copy()->tz('Asia/Tokyo')->startOfDay();

        return DB::transaction(function () use ($sub, $issueDate) {
            $sub = Subscription::query()->lockForUpdate()->find((int)$sub->id);
            if (!$sub || empty($sub->term_end) || empty($sub->billing_code) || empty($sub->payment_method)) {
                return null;
            }

            $nextStart = Carbon::parse((string)$sub->term_end, 'Asia/Tokyo')->addDay()->startOfDay();
            $nextEnd = $nextStart->copy()->addYear()->subDay()->startOfDay();

            // renewal の再発行方針:
            // - 同一期間に pending/issued/paid/failed があれば再発行しない（重複防止）
            // - failed は billing:sync-outstanding-invoices による再同期対象として扱う
            // - 人手で再発行する場合のみ既存を canceled にしてから再作成する
            $exists = SubscriptionInvoice::query()
                ->where('subscription_id', (int)$sub->id)
                ->where('kind', 'renewal')
                ->whereDate('period_start', $nextStart->toDateString())
                ->whereDate('period_end', $nextEnd->toDateString())
                ->where('status', '!=', 'canceled')
                ->exists();
            if ($exists) {
                return null;
            }

            $quantity = max(1, (int)$sub->quantity);
            $paymentMethod = (string)$sub->payment_method;
            $dueDate = $paymentMethod === 'bank_transfer'
                ? Carbon::parse((string)$sub->term_end, 'Asia/Tokyo')->subDays(2)->startOfDay()
                : Carbon::parse((string)$sub->term_end, 'Asia/Tokyo')->startOfDay();

            return $this->createAndIssueInvoice(
                companyId: (int)$sub->company_id,
                subscriptionId: (int)$sub->id,
                kind: 'renewal',
                billingCode: (string)$sub->billing_code,
                paymentMethod: $paymentMethod,
                quantity: $quantity,
                unitPriceYen: 30000,
                monthsCharged: 12,
                amountYen: 30000 * $quantity,
                periodStart: $nextStart->toDateString(),
                periodEnd: $nextEnd->toDateString(),
                issueDate: $issueDate,
                dueDate: $dueDate,
            );
        });
    }

    /**
     * 追加請求（add_quantity）を作成・発行する。
     * subscriptions.quantity は入金確認後にのみ反映するため、ここでは変更しない。
     */
    public function issueAddQuantity(Subscription $sub, int $addQuantity, Carbon $requestedAt): ?SubscriptionInvoice
    {
        $addQuantity = max(1, $addQuantity);
        $requestedAt = $requestedAt->copy()->tz('Asia/Tokyo')->startOfDay();

        return DB::transaction(function () use ($sub, $addQuantity, $requestedAt) {
            $sub = Subscription::query()->lockForUpdate()->find((int)$sub->id);
            if (!$sub || empty($sub->term_end) || empty($sub->billing_code) || empty($sub->payment_method)) {
                return null;
            }

            $termEnd = Carbon::parse((string)$sub->term_end, 'Asia/Tokyo')->startOfDay();
            $periodStart = $requestedAt->copy()->startOfMonth();
            if ($periodStart->gt($termEnd)) {
                return null;
            }

            // 月初起算 / 月割 / 日割なし
            $monthsCharged = (($termEnd->year - $periodStart->year) * 12) + ($termEnd->month - $periodStart->month) + 1;
            $monthsCharged = max(1, min(12, $monthsCharged));

            $unitPerMonth = intdiv(30000, 12); // 2,500
            $amountYen = $unitPerMonth * $monthsCharged * $addQuantity;

            $dueDate = $requestedAt->copy()->addDays(7);

            return $this->createAndIssueInvoice(
                companyId: (int)$sub->company_id,
                subscriptionId: (int)$sub->id,
                kind: 'add_quantity',
                billingCode: (string)$sub->billing_code,
                paymentMethod: (string)$sub->payment_method,
                quantity: $addQuantity,
                unitPriceYen: 30000,
                monthsCharged: $monthsCharged,
                amountYen: $amountYen,
                periodStart: $periodStart->toDateString(),
                periodEnd: $termEnd->toDateString(),
                issueDate: $requestedAt,
                dueDate: $dueDate,
            );
        });
    }

    private function createAndIssueInvoice(
        int $companyId,
        int $subscriptionId,
        string $kind,
        string $billingCode,
        string $paymentMethod,
        int $quantity,
        int $unitPriceYen,
        int $monthsCharged,
        int $amountYen,
        string $periodStart,
        string $periodEnd,
        Carbon $issueDate,
        Carbon $dueDate,
        bool $attachBillingIndividual = false,
    ): SubscriptionInvoice {
        $billingIndividualCode = $billingCode . '-01';
        $paymentMethodCode = null;

        $inv = new SubscriptionInvoice();
            $inv->company_id = $companyId;
            $inv->subscription_id = $subscriptionId;
            $inv->kind = $kind;
            $inv->status = 'pending';

            // demand_code は最大20桁（半角英数+記号）なので20以内で生成する
            // 例）"FURU" + 16桁 = 20桁
            $inv->demand_code = 'FURU' . strtoupper(Str::random(16));

            $inv->billing_code = $billingCode;
            $inv->item_code = (string) config('billing_robo.item_code_5seats');
            $inv->payment_method = $paymentMethod;

            $inv->quantity = $quantity;
            $inv->unit_price_yen = $unitPriceYen;
            $inv->months_charged = $monthsCharged;
            $inv->amount_yen = $amountYen;

            $inv->period_start = $periodStart;
            $inv->period_end = $periodEnd;
            $inv->issue_date = $issueDate->toDateString();
            $inv->due_date = $dueDate->toDateString();
            $inv->save();

        if ($attachBillingIndividual) {
            $billingPayload = $this->buildInitialBillingPayload($companyId, $billingCode, $billingIndividualCode);

            // 3) 請求先（billing + individual）作成
            try {
                $this->client->billingBulkUpsert([[
                    'code' => $billingCode,
                    'name' => $billingPayload['billing_name'],
                    'individual' => [[
                        'code' => $billingIndividualCode,
                        'name' => $billingPayload['individual_name'],
                        'address1' => $billingPayload['individual_address1'],
                        'zip_code' => '1000001',
                        'pref' => '東京都',
                        'city_address' => '千代田区千代田1-1',
                        'email' => $billingPayload['individual_email'],
                    ]],
                ]]);
            } catch (Throwable $e) {
                throw new RuntimeException('ロボ請求先作成失敗', previous: $e);
            }

            $billingSetting = CompanyBillingSetting::query()->where('company_id', $companyId)->first();
            if ($billingSetting) {
                $billingSetting->billing_code = $billingCode;
                $billingSetting->billing_individual_code = $billingIndividualCode;
                $billingSetting->save();
            }

            $deterministicPaymentCode = $this->makeDeterministicPaymentCode($billingCode);
            $paymentPayload = $this->buildInitialPaymentPayload(
                companyId: $companyId,
                paymentMethod: $paymentMethod,
                deterministicPaymentCode: $deterministicPaymentCode
            );

            // 4) payment 作成（credit/bank_transfer/debit 共通）
            try {
                $paymentRes = $this->client->billingBulkUpsert([[
                    'code' => $billingCode,
                    'name' => $billingPayload['billing_name'],
                    'payment' => [$paymentPayload],
                ]]);
                $paymentMethodCode = $this->extractPaymentMethodCode($paymentRes, $deterministicPaymentCode);
            } catch (Throwable $e) {
                throw new RuntimeException('payment作成失敗', previous: $e);
            }

            if ($paymentMethodCode === '') {
                throw new RuntimeException('payment作成失敗: payment_method_code が取得できませんでした。');
            }

            // 5) individual へ既定決済を紐付け
            try {
                $this->client->billingBulkUpsert([[
                    'code' => $billingCode,
                    'name' => $billingPayload['billing_name'],
                    'individual' => [[
                        'code' => $billingIndividualCode,
                        'name' => $billingPayload['individual_name'],
                        'payment_method_code' => $paymentMethodCode,
                    ]],
                ]]);
            } catch (Throwable $e) {
                throw new RuntimeException('individualへのpayment_method_code関連付け失敗', previous: $e);
            }

            $billingSetting = CompanyBillingSetting::query()->firstOrNew([
                'company_id' => $companyId,
            ]);
            $billingSetting->payment_method = $paymentMethod;
            $billingSetting->billing_code = $billingCode;
            $billingSetting->billing_individual_code = $billingIndividualCode;
            $billingSetting->payment_method_code = $paymentMethodCode;
            $billingSetting->save();

        }
            // 4) demand/bulk_upsert
            // - issue_month/day と deadline_month/day は月跨ぎがあり得るので、invoiceで確定した日付から算出
            // month offset（0=当月, 1=翌月, -1=前月）
            $deadlineMonthOffset = ((int)$dueDate->format('Y') - (int)$issueDate->format('Y')) * 12
                + ((int)$dueDate->format('n') - (int)$issueDate->format('n'));

            $demand = [
                // upsert用キー
                'code' => $inv->demand_code,
                // 必須
                'billing_code' => $billingCode,
                'item_code' => $inv->item_code,
                'type' => 0, // 0:単発（今回の運用は請求ごとに単発で発行）

                // 金額：price×quantity（amount直指定は無い仕様）
                'price' => (int)$inv->unit_price_yen,
                'quantity' => (int)$inv->quantity,
                'tax_category' => (int) config('billing_robo.tax_category'),
                'tax' => (int) config('billing_robo.tax'),

                // サービス提供開始日（yyyy/mm/dd）
                'start_date' => Carbon::parse($periodStart, 'Asia/Tokyo')->format('Y/m/d'),

                // 発行日（月オフセット/日）
                'issue_month' => 0,
                'issue_day' => (int)$issueDate->format('j'),
                // 期限（月オフセット/日）
                'deadline_month' => $deadlineMonthOffset,
                'deadline_day' => (int)$dueDate->format('j'),
            ];

        if ($attachBillingIndividual) {
            // 公開仕様項目: billing_individual_code
            $demand['billing_individual_code'] = $billingIndividualCode;
        }

        if ($attachBillingIndividual) {
            if (!is_string($paymentMethodCode) || $paymentMethodCode === '') {
                throw new RuntimeException('demand作成失敗: payment_method_code が未確定です。');
            }
            $demand['payment_method_code'] = $paymentMethodCode;
        }

            try {
                $this->client->demandBulkUpsert([$demand]);
            } catch (Throwable $e) {
                throw new RuntimeException('demand作成失敗', previous: $e);
            }

            // 5) bulk_issue_bill_select（請求書発行 → bill.number を取得）
            try {
                $issueRes = $this->client->demandBulkIssueBillSelect([$inv->demand_code]);
            } catch (Throwable $e) {
                throw new RuntimeException('請求書発行失敗', previous: $e);
            }
            $billNumber = $this->extractBillNumberFromIssueResponse($issueRes);
            if ($billNumber === '') {
                throw new RuntimeException('bulk_issue_bill_select did not return bill.number.');
            }

            // 6) invoice を issued へ
            $inv->bill_number = $billNumber;
            $inv->status = 'issued';
            $inv->save();

        return $inv;
    }

    private function requireDebitBillingSetting(int $companyId): CompanyBillingSetting
    {
        $setting = CompanyBillingSetting::query()->where('company_id', $companyId)->first();
        if (!$setting) {
            throw new RuntimeException("Company billing setting is missing. company_id={$companyId}");
        }

        if (
            empty($setting->bank_account_type)
            || empty($setting->bank_code)
            || empty($setting->branch_code)
            || empty($setting->bank_account_number)
            || empty($setting->bank_account_name)
        ) {
            throw new RuntimeException("Debit account information is incomplete. company_id={$companyId}");
        }

        return $setting;
    }

    /**
     * @return array<string,mixed>
     */
    private function buildInitialPaymentPayload(int $companyId, string $paymentMethod, string $deterministicPaymentCode): array
    {
        $payload = [
            'code' => $deterministicPaymentCode,
            'name' => match ($paymentMethod) {
                'credit' => 'クレジットカード',
                'bank_transfer' => '銀行振込',
                'debit' => 'RP口座振替',
                default => throw new RuntimeException("Unsupported payment method for initial billing payment payload: {$paymentMethod}"),
            },
            'payment_method' => $this->resolveRoboPaymentMethod($paymentMethod),
        ];

        if ($paymentMethod === 'bank_transfer') {
            $payload['bank_transfer_pattern_code'] = (string) config('billing_robo.bank_transfer_pattern_code', '01');
            return $payload;
        }

        if ($paymentMethod !== 'debit') {
            return $payload;
        }

        $billingSetting = $this->requireDebitBillingSetting($companyId);
        $payload['bank_account_type'] = (int)$billingSetting->bank_account_type;
        $payload['bank_code'] = (string)$billingSetting->bank_code;
        $payload['branch_code'] = (string)$billingSetting->branch_code;
        $payload['bank_account_number'] = (string)$billingSetting->bank_account_number;
        $payload['bank_account_name'] = (string)$billingSetting->bank_account_name;

        return $payload;
    }

    private function resolveRoboPaymentMethod(string $paymentMethod): int
    {
        return match ($paymentMethod) {
            'bank_transfer' => self::BANK_TRANSFER_PAYMENT_METHOD,
            'credit' => self::CREDIT_PAYMENT_METHOD,
            'debit' => self::RP_PAYMENT_METHOD,
            default => throw new RuntimeException("Unsupported payment method for initial billing payment: {$paymentMethod}"),
        };
    }

    /**
     * @param array<string,mixed> $response
     */
    private function extractPaymentMethodCode(array $response, string $fallbackCode = ''): string
    {
        $billings = $response['billing'] ?? null;
        if (!is_array($billings)) {
            return '';
        }

        foreach ($billings as $billing) {
            if (!is_array($billing)) {
                continue;
            }
            $payments = $billing['payment'] ?? null;
            if (!is_array($payments)) {
                continue;
            }

            foreach ($payments as $payment) {
                if (!is_array($payment)) {
                    continue;
                }
                $code = $payment['payment_method_code'] ?? $payment['code'] ?? null;
                if (is_scalar($code) && (string)$code !== '') {
                    return (string)$code;
                }
            }
        }

        return $fallbackCode;
    }

    private function makeDeterministicPaymentCode(string $billingCode): string
    {
        return $billingCode . '-PM01';
    }

    /**
     * @return array{billing_name:string,individual_name:string,individual_address1:string,individual_email:string}
     */
    private function buildInitialBillingPayload(int $companyId, string $billingCode, string $billingIndividualCode): array
    {
        // 前提: SignupController の新規申込トランザクション内で owner_user_id 保存後に
        // issueInitial() が呼ばれるため、ここでは owner_user_id が設定済みであることを期待する。
        $company = Company::query()->find($companyId);
        if (!$company) {
            throw new RuntimeException("Company not found for initial billing. company_id={$companyId} billing_code={$billingCode}");
        }

        $companyName = trim((string)$company->name);
        $branchName = trim((string)($company->branch_name ?? ''));
        if ($companyName === '') {
            throw new RuntimeException("Company name is empty for initial billing. company_id={$companyId} billing_code={$billingCode}");
        }

        $ownerUserId = (int)($company->owner_user_id ?? 0);
        if ($ownerUserId <= 0) {
            throw new RuntimeException("Owner user is missing for initial billing. company_id={$companyId} billing_code={$billingCode} billing_individual_code={$billingIndividualCode}");
        }

        $owner = User::query()->find($ownerUserId);
        $ownerName = trim((string)($owner?->name ?? ''));
        $ownerEmail = trim((string)($owner?->email ?? ''));

        if ($ownerName === '' || $ownerEmail === '') {
            throw new RuntimeException("Owner profile is incomplete for initial billing. company_id={$companyId} owner_user_id={$ownerUserId} billing_code={$billingCode}");
        }

        return [
            'billing_name' => mb_substr($companyName, 0, 100),
            'individual_name' => mb_substr($branchName !== '' ? $branchName : '本社', 0, 100),
            'individual_address1' => mb_substr($companyName . ' ' . $ownerName . ' 御中', 0, 100),
            'individual_email' => mb_substr($ownerEmail, 0, 100),
        ];
    }

    /**
     * bulk_issue_bill_select のレスポンスから請求書番号を抽出
     * 仕様差分に耐えるため複数パターンで見る。
     */
    private function extractBillNumberFromIssueResponse(array $res): string
    {
        // パターン1：{ bill: [ { number: "..." } ] }
        if (isset($res['bill']) && is_array($res['bill'])) {
            $first = $res['bill'][0] ?? null;
            if (is_array($first) && isset($first['number'])) {
                return (string)$first['number'];
            }
        }
        // パターン2：{ bills: [ { number: "..." } ] }
        if (isset($res['bills']) && is_array($res['bills'])) {
            $first = $res['bills'][0] ?? null;
            if (is_array($first) && isset($first['number'])) {
                return (string)$first['number'];
            }
        }
        // パターン3：{ bill: { number: "..." } }
        if (isset($res['bill']) && is_array($res['bill']) && isset($res['bill']['number'])) {
            return (string)$res['bill']['number'];
        }
        return '';
    }
}
