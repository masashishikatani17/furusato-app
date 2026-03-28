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
use Illuminate\Support\Facades\Log;
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

    public function __construct(private BillingRoboClient $client)
    {
    }

    /**
     * 初回契約の請求書を発行する（1口=5席/年額3万円）
     *
     * @param Company $company
     * @param string $billingCode 会社＋支店から生成済みの固定コード
     * @param string $paymentMethod credit/bank_transfer（ふるさと側の確定値）
     * @param int $quantity 口数（初回は1想定）
     * @return SubscriptionInvoice
     */
    public function issueInitial(Company $company, string $billingCode, string $paymentMethod, int $quantity = 1): SubscriptionInvoice
    {
        $quantity = max(1, (int) $quantity);

        return DB::transaction(function () use ($company, $billingCode, $paymentMethod, $quantity) {
            $sub = Subscription::query()
                ->where('company_id', (int) $company->id)
                ->lockForUpdate()
                ->first();

            if (!$sub) {
                $sub = new Subscription();
                $sub->company_id = (int) $company->id;
            }

            $termStart = Carbon::now('Asia/Tokyo')->startOfMonth()->toDateString();
            $termEnd = Carbon::parse($termStart, 'Asia/Tokyo')->addYear()->subDay()->toDateString();

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

            $issueDate = Carbon::now('Asia/Tokyo')->startOfDay();
            $dueDate = $issueDate->copy()->addDays(7);

            return $this->createAndIssueInvoice(
                companyId: (int) $company->id,
                subscriptionId: (int) $sub->id,
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

    public function issueRenewal(Subscription $sub, Carbon $issueDate): ?SubscriptionInvoice
    {
        $issueDate = $issueDate->copy()->tz('Asia/Tokyo')->startOfDay();

        return DB::transaction(function () use ($sub, $issueDate) {
            $sub = Subscription::query()->lockForUpdate()->find((int) $sub->id);
            if (!$sub || empty($sub->term_end) || empty($sub->billing_code) || empty($sub->payment_method)) {
                return null;
            }

            $nextStart = Carbon::parse((string) $sub->term_end, 'Asia/Tokyo')->addDay()->startOfDay();
            $nextEnd = $nextStart->copy()->addYear()->subDay()->startOfDay();

            $exists = SubscriptionInvoice::query()
                ->where('subscription_id', (int) $sub->id)
                ->where('kind', 'renewal')
                ->whereDate('period_start', $nextStart->toDateString())
                ->whereDate('period_end', $nextEnd->toDateString())
                ->where('status', '!=', 'canceled')
                ->exists();

            if ($exists) {
                return null;
            }

            $quantity = max(1, (int) $sub->quantity);
            $paymentMethod = (string) $sub->payment_method;
            $dueDate = $paymentMethod === 'bank_transfer'
                ? Carbon::parse((string) $sub->term_end, 'Asia/Tokyo')->subDays(2)->startOfDay()
                : Carbon::parse((string) $sub->term_end, 'Asia/Tokyo')->startOfDay();

            return $this->createAndIssueInvoice(
                companyId: (int) $sub->company_id,
                subscriptionId: (int) $sub->id,
                kind: 'renewal',
                billingCode: (string) $sub->billing_code,
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

    public function issueAddQuantity(Subscription $sub, int $addQuantity, Carbon $requestedAt): ?SubscriptionInvoice
    {
        $addQuantity = max(1, $addQuantity);
        $requestedAt = $requestedAt->copy()->tz('Asia/Tokyo')->startOfDay();

        return DB::transaction(function () use ($sub, $addQuantity, $requestedAt) {
            $sub = Subscription::query()->lockForUpdate()->find((int) $sub->id);
            if (!$sub || empty($sub->term_end) || empty($sub->billing_code) || empty($sub->payment_method)) {
                return null;
            }

            $termEnd = Carbon::parse((string) $sub->term_end, 'Asia/Tokyo')->startOfDay();
            $periodStart = $requestedAt->copy()->startOfMonth();
            if ($periodStart->gt($termEnd)) {
                return null;
            }

            $monthsCharged = (($termEnd->year - $periodStart->year) * 12) + ($termEnd->month - $periodStart->month) + 1;
            $monthsCharged = max(1, min(12, $monthsCharged));

            $unitPerMonth = intdiv(30000, 12);
            $amountYen = $unitPerMonth * $monthsCharged * $addQuantity;

            $dueDate = $requestedAt->copy()->addDays(7);

            return $this->createAndIssueInvoice(
                companyId: (int) $sub->company_id,
                subscriptionId: (int) $sub->id,
                kind: 'add_quantity',
                billingCode: (string) $sub->billing_code,
                paymentMethod: (string) $sub->payment_method,
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
        $this->assertSupportedPaymentMethod($paymentMethod);

        $billingIndividualCode = $billingCode . '-01';
        $paymentMethodCode = null;
        $paymentRegisterStatus = null;
        $paymentCod = null;

        $inv = new SubscriptionInvoice();
        $inv->company_id = $companyId;
        $inv->subscription_id = $subscriptionId;
        $inv->kind = $kind;
        $inv->status = 'pending';
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

        $defaultBillingMethod = $this->resolveDemandBillingMethod();
        $defaultBillTemplateCode = $this->resolveDemandBillTemplateCode((string) $inv->item_code);

        if ($attachBillingIndividual) {
            $billingPayload = $this->buildInitialBillingPayload($companyId, $billingCode, $billingIndividualCode);

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
                        'billing_method' => $defaultBillingMethod,
                        'bill_template_code' => $defaultBillTemplateCode,
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
                paymentMethod: $paymentMethod,
                deterministicPaymentCode: $deterministicPaymentCode
            );

            try {
                Log::info('BillingRobo payment create request', [
                    'company_id' => $companyId,
                    'billing_code' => $billingCode,
                    'payment_method' => $paymentMethod,
                    'payment_payload' => $paymentPayload,
                ]);

                $paymentRes = $this->client->billingBulkUpsert([[
                    'code' => $billingCode,
                    'name' => $billingPayload['billing_name'],
                    'payment' => [$paymentPayload],
                ]]);

                Log::info('BillingRobo payment create response', [
                    'company_id' => $companyId,
                    'billing_code' => $billingCode,
                    'payment_method' => $paymentMethod,
                    'payment_payload' => $paymentPayload,
                    'raw_response' => $paymentRes,
                ]);

                $paymentMethodCode = $this->extractPaymentMethodCode($paymentRes, $deterministicPaymentCode);
                $paymentRegisterStatus = $this->extractPaymentRegisterStatus($paymentRes);
                $paymentCod = $this->extractPaymentCod($paymentRes);
            } catch (Throwable $e) {
                Log::error('BillingRobo payment create failed', [
                    'company_id' => $companyId,
                    'billing_code' => $billingCode,
                    'payment_method' => $paymentMethod,
                    'payment_payload' => $paymentPayload,
                    'exception_message' => $e->getMessage(),
                    'exception' => $this->extractThrowableContext($e),
                ]);

                throw new RuntimeException('payment作成失敗', previous: $e);
            }

            if ($paymentMethodCode === '') {
                throw new RuntimeException('payment作成失敗: payment_method_code が取得できませんでした。');
            }

            $billingSetting = CompanyBillingSetting::query()->firstOrNew([
                'company_id' => $companyId,
            ]);
            $billingSetting->payment_method = $paymentMethod;
            $billingSetting->billing_code = $billingCode;
            $billingSetting->billing_individual_code = $billingIndividualCode;
            $billingSetting->payment_method_code = $paymentMethodCode;
            $billingSetting->save();

            if ($paymentMethod === 'credit' && (int) $paymentRegisterStatus !== 5) {
                Log::info('BillingRobo credit payment registration is pending. Skip demand issue for now.', [
                    'company_id' => $companyId,
                    'billing_code' => $billingCode,
                    'billing_individual_code' => $billingIndividualCode,
                    'payment_method_code' => $paymentMethodCode,
                    'payment_register_status' => $paymentRegisterStatus,
                    'cod' => $paymentCod,
                ]);

                return $inv;
            }

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
        }

        $baseMonthDate = Carbon::now('Asia/Tokyo')->startOfDay();
        $issueMonthOffset = $this->calculateMonthOffset($baseMonthDate, $issueDate);
        $sendingDate = $issueDate->copy();
        $sendingMonthOffset = $this->calculateMonthOffset($baseMonthDate, $sendingDate);
        $deadlineMonthOffset = $this->calculateMonthOffset($baseMonthDate, $dueDate);
        $billingMethod = $defaultBillingMethod;
        $billTemplateCode = $defaultBillTemplateCode;

        $demand = [
            'code' => $inv->demand_code,
            'billing_code' => $billingCode,
            'item_code' => $inv->item_code,
            'type' => 0,
            'price' => (int) $inv->unit_price_yen,
            'quantity' => (int) $inv->quantity,
            'tax_category' => (int) config('billing_robo.tax_category'),
            'tax' => (int) config('billing_robo.tax'),
            'billing_method' => $billingMethod,
            'start_date' => Carbon::parse($periodStart, 'Asia/Tokyo')->format('Y/m/d'),
            'period_format' => 0,
            'issue_month' => $issueMonthOffset,
            'issue_day' => $this->normalizeRoboDay($issueDate),
            'sending_month' => $sendingMonthOffset,
            'sending_day' => $this->normalizeRoboDay($sendingDate),
            'deadline_month' => $deadlineMonthOffset,
            'deadline_day' => $this->normalizeRoboDay($dueDate),
            'bill_template_code' => $billTemplateCode,
        ];

        if ($attachBillingIndividual) {
            $demand['billing_individual_code'] = $billingIndividualCode;
        }

        if ($attachBillingIndividual) {
            if (!is_string($paymentMethodCode) || $paymentMethodCode === '') {
                throw new RuntimeException('demand作成失敗: payment_method_code が未確定です。');
            }
            $demand['payment_method_code'] = $paymentMethodCode;
        }

        try {
            Log::info('BillingRobo demand create request', [
                'company_id' => $companyId,
                'billing_code' => $billingCode,
                'demand_payload' => $demand,
            ]);

            $demandRes = $this->client->demandBulkUpsert([$demand]);

            Log::info('BillingRobo demand create response', [
                'company_id' => $companyId,
                'billing_code' => $billingCode,
                'raw_response' => $demandRes,
            ]);
        } catch (Throwable $e) {
            $message = $e->getMessage();
            if (str_contains($message, 'error_code=1334')) {
                throw new RuntimeException(
                    "ロボ側の商品/請求先部署の請求書テンプレート設定が不正です。item_code={$inv->item_code} の bill_template_code をロボ管理画面で確認してください。",
                    previous: $e
                );
            }

            throw new RuntimeException('demand作成失敗', previous: $e);
        }

        try {
            $issueRes = $this->client->demandBulkIssueBillSelect([$inv->demand_code]);
        } catch (Throwable $e) {
            throw new RuntimeException('請求書発行失敗', previous: $e);
        }

        $billNumber = $this->extractBillNumberFromIssueResponse($issueRes);
        if ($billNumber === '') {
            throw new RuntimeException('bulk_issue_bill_select did not return bill.number.');
        }

        $inv->bill_number = $billNumber;
        $inv->status = 'issued';
        $inv->save();

        return $inv;
    }

    /**
     * @return array<string,mixed>
     */
    private function buildInitialPaymentPayload(string $paymentMethod, string $deterministicPaymentCode): array
    {
        return match ($paymentMethod) {
            'bank_transfer' => [
                'code' => $deterministicPaymentCode,
                'name' => '銀行振込',
                'payment_method' => self::BANK_TRANSFER_PAYMENT_METHOD,
                'bank_transfer_pattern_code' => (string) config('billing_robo.bank_transfer_pattern_code'),
                'source_bank_account_name' => '',
            ],
            'credit' => [
                'code' => $deterministicPaymentCode,
                'name' => 'クレジットカード',
                'payment_method' => self::CREDIT_PAYMENT_METHOD,
                'credit_card_regist_kind' => 1,
            ],
            default => throw new RuntimeException("Unsupported payment method for BillingRobo contract: {$paymentMethod}"),
        };
    }

    /**
     * @return array<string,mixed>
     */
    private function extractThrowableContext(Throwable $e): array
    {
        $context = [
            'class' => $e::class,
            'message' => $e->getMessage(),
        ];

        $previous = $e->getPrevious();
        if ($previous instanceof Throwable) {
            $context['previous'] = [
                'class' => $previous::class,
                'message' => $previous->getMessage(),
            ];
        }

        if (method_exists($e, 'getResponse')) {
            try {
                $response = $e->getResponse();
                if (is_object($response) && method_exists($response, 'getBody')) {
                    $body = $response->getBody();
                    if (is_object($body) && method_exists($body, '__toString')) {
                        $context['response_body'] = (string) $body;
                    } elseif (is_scalar($body)) {
                        $context['response_body'] = (string) $body;
                    }
                }
            } catch (Throwable) {
                // ignore response extraction errors
            }
        }

        return $context;
    }

    private function assertSupportedPaymentMethod(string $paymentMethod): void
    {
        if (!in_array($paymentMethod, ['credit', 'bank_transfer'], true)) {
            throw new RuntimeException("Unsupported payment method for BillingRobo contract: {$paymentMethod}");
        }
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
                if (is_scalar($code) && (string) $code !== '') {
                    return (string) $code;
                }
            }
        }

        return $fallbackCode;
    }

    private function makeDeterministicPaymentCode(string $billingCode): string
    {
        $suffix = '-P1';
        $maxLength = 20;
        $base = substr($billingCode, 0, $maxLength - strlen($suffix));

        return $base . $suffix;
    }

    private function extractPaymentRegisterStatus(array $response): ?int
    {
        $billings = $response['billing'] ?? null;
        if (!is_array($billings)) {
            return null;
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
                if (isset($payment['register_status']) && $payment['register_status'] !== null && $payment['register_status'] !== '') {
                    return (int) $payment['register_status'];
                }
            }
        }

        return null;
    }

    private function extractPaymentCod(array $response): ?string
    {
        $billings = $response['billing'] ?? null;
        if (!is_array($billings)) {
            return null;
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
                $cod = $payment['cod'] ?? null;
                if (is_scalar($cod) && (string) $cod !== '') {
                    return (string) $cod;
                }
            }
        }

        return null;
    }

    private function resolveDemandBillTemplateCode(string $itemCode): int
    {
        $envTemplateCode = (int) config('billing_robo.bill_template_code', 0);

        try {
            $goodsRes = $this->client->goodsSearchByItemCode($itemCode);
            $goodsRows = $goodsRes['goods'] ?? null;

            if (is_array($goodsRows) && isset($goodsRows[0]) && is_array($goodsRows[0])) {
                $goods = $goodsRows[0];
                $goodsTemplateCode = (int) ($goods['bill_template_code'] ?? 0);
                $goodsTemplateName = (string) ($goods['bill_template_name'] ?? '');

                Log::info('BillingRobo goods search template resolved', [
                    'item_code' => $itemCode,
                    'goods_bill_template_code' => $goodsTemplateCode,
                    'goods_bill_template_name' => $goodsTemplateName,
                    'env_bill_template_code' => $envTemplateCode,
                ]);

                if ($goodsTemplateCode > 0) {
                    return $goodsTemplateCode;
                }
            } else {
                Log::warning('BillingRobo goods search returned no rows', [
                    'item_code' => $itemCode,
                    'raw_response' => $goodsRes,
                    'env_bill_template_code' => $envTemplateCode,
                ]);
            }
        } catch (Throwable $e) {
            Log::warning('BillingRobo goods search failed. Fallback to env bill_template_code.', [
                'item_code' => $itemCode,
                'env_bill_template_code' => $envTemplateCode,
                'exception_message' => $e->getMessage(),
                'exception' => $this->extractThrowableContext($e),
            ]);
        }

        if ($envTemplateCode <= 0) {
            throw new RuntimeException("billing_robo.bill_template_code is invalid: {$envTemplateCode}");
        }

        return $envTemplateCode;
    }

    private function resolveDemandBillingMethod(): int
    {
        $billingMethod = (int) config('billing_robo.billing_method', 2);

        if (!in_array($billingMethod, [0, 1, 2, 3, 4, 5, 6, 7, 8], true)) {
            throw new RuntimeException("billing_robo.billing_method is invalid: {$billingMethod}");
        }

        return $billingMethod;
    }

    private function calculateMonthOffset(Carbon $baseDate, Carbon $targetDate): int
    {
        return ((int) $targetDate->format('Y') - (int) $baseDate->format('Y')) * 12
            + ((int) $targetDate->format('n') - (int) $baseDate->format('n'));
    }

    private function normalizeRoboDay(Carbon $date): int
    {
        $day = (int) $date->format('j');
        $lastDay = (int) $date->copy()->endOfMonth()->format('j');

        if ($day === $lastDay) {
            return 99;
        }

        return min($day, 30);
    }

    /**
     * @return array{billing_name:string,individual_name:string,individual_address1:string,individual_email:string}
     */
    private function buildInitialBillingPayload(int $companyId, string $billingCode, string $billingIndividualCode): array
    {
        $company = Company::query()->find($companyId);
        if (!$company) {
            throw new RuntimeException("Company not found for initial billing. company_id={$companyId} billing_code={$billingCode}");
        }

        $companyName = trim((string) $company->name);
        $branchName = trim((string) ($company->branch_name ?? ''));
        if ($companyName === '') {
            throw new RuntimeException("Company name is empty for initial billing. company_id={$companyId} billing_code={$billingCode}");
        }

        $ownerUserId = (int) ($company->owner_user_id ?? 0);
        if ($ownerUserId <= 0) {
            throw new RuntimeException("Owner user is missing for initial billing. company_id={$companyId} billing_code={$billingCode} billing_individual_code={$billingIndividualCode}");
        }

        $owner = User::query()->find($ownerUserId);
        $ownerName = trim((string) ($owner?->name ?? ''));
        $ownerEmail = trim((string) ($owner?->email ?? ''));

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

    private function extractBillNumberFromIssueResponse(array $res): string
    {
        if (isset($res['bill']) && is_array($res['bill'])) {
            $first = $res['bill'][0] ?? null;
            if (is_array($first) && isset($first['number'])) {
                return (string) $first['number'];
            }
        }

        if (isset($res['bills']) && is_array($res['bills'])) {
            $first = $res['bills'][0] ?? null;
            if (is_array($first) && isset($first['number'])) {
                return (string) $first['number'];
            }
        }

        if (isset($res['bill']) && is_array($res['bill']) && isset($res['bill']['number'])) {
            return (string) $res['bill']['number'];
        }

        return '';
    }
}