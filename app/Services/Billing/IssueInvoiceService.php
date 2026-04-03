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
    private const PLAN_UNIT_PRICE_YEN = 30000;
    private const PLAN_MONTHLY_PRICE_YEN = 2500;
    private const ROBO_DEMAND_TYPE_ONE_TIME = 0;
    private const ROBO_DEMAND_TYPE_RECURRING_FIXED = 1;
    private const ROBO_REPETITION_PERIOD_NUMBER_ANNUAL = 12;
    private const ROBO_REPETITION_PERIOD_UNIT_MONTH = 1;
    private const ROBO_REPEAT_COUNT_UNLIMITED = 0;

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

            $contractWindow = $this->resolveInitialContractWindow(Carbon::now('Asia/Tokyo'));

            $sub->status = 'pending';
            if (empty($sub->applied_at)) {
                $sub->applied_at = Carbon::now('Asia/Tokyo');
            }
            $sub->quantity = $quantity;
            $sub->term_start = $contractWindow['period_start']->toDateString();
            $sub->term_end = $contractWindow['period_end']->toDateString();
            $sub->paid_through = null;
            $sub->payment_method = $paymentMethod;
            $sub->billing_code = $billingCode;
            $sub->save();

            $issueDate = Carbon::now('Asia/Tokyo')->startOfDay();
            $dueDate = $issueDate->copy()->addDays(7);

            $invoice = $this->createAndIssueInvoice(
                companyId: (int) $company->id,
                subscriptionId: (int) $sub->id,
                kind: 'initial',
                billingCode: $billingCode,
                paymentMethod: $paymentMethod,
                quantity: $quantity,
                unitPriceYen: self::PLAN_UNIT_PRICE_YEN,
                monthsCharged: $contractWindow['months_charged'],
                amountYen: $contractWindow['per_seat_amount_yen'] * $quantity,
                periodStart: $contractWindow['period_start']->toDateString(),
                periodEnd: $contractWindow['period_end']->toDateString(),
                issueDate: $issueDate,
                dueDate: $dueDate,
                attachBillingIndividual: true,
                demandUnitPriceYen: $contractWindow['per_seat_amount_yen'],
            );

            if (
                $paymentMethod === 'bank_transfer'
                && $invoice->status === 'issued'
            ) {
                $this->upsertRoboRecurringDemandForNextFiscalYear(
                    subscriptionId: (int) $sub->id,
                    companyId: (int) $sub->company_id,
                    billingCode: (string) $sub->billing_code,
                    paymentMethod: $paymentMethod,
                    quantity: $quantity,
                    recurringStart: $contractWindow['next_recurring_start'],
                );
            }

            return $invoice;
        });
    }

    public function issueInitialCreditAfterRegistration(int $companyId): SubscriptionInvoice
    {
        return DB::transaction(function () use ($companyId) {
            $sub = Subscription::query()
                ->where('company_id', $companyId)
                ->lockForUpdate()
                ->first();

            if (!$sub || (string) $sub->payment_method !== 'credit' || empty($sub->billing_code) || empty($sub->term_start) || empty($sub->term_end)) {
                throw new RuntimeException("クレジットカード登録後の請求作成対象 subscription が見つかりません。 company_id={$companyId}");
            }

            $alreadyIssued = SubscriptionInvoice::query()
                ->where('subscription_id', (int) $sub->id)
                ->where('kind', 'initial')
                ->where(function ($query) {
                    $query->where('status', 'paid')
                        ->orWhere(function ($issuedQuery) {
                            $issuedQuery
                                ->where('status', 'issued')
                                ->whereNotNull('bill_number');
                        });
                })
                ->latest('id')
                ->first();

            if ($alreadyIssued instanceof SubscriptionInvoice) {
                return $alreadyIssued;
            }

            SubscriptionInvoice::query()
                ->where('subscription_id', (int) $sub->id)
                ->where('kind', 'initial')
                ->where('status', 'pending')
                ->whereNull('bill_number')
                ->update([
                    'status' => 'canceled',
                ]);

            $quantity = max(1, (int) $sub->quantity);
            $periodStart = Carbon::parse((string) $sub->term_start, 'Asia/Tokyo')->startOfDay();
            $periodEnd = Carbon::parse((string) $sub->term_end, 'Asia/Tokyo')->startOfDay();
            $monthsCharged = $this->calculateInclusiveMonths($periodStart, $periodEnd);
            $issueDate = Carbon::now('Asia/Tokyo')->startOfDay();
            $useImmediateCreditBulkRegister = $this->resolveCreditInitialChargeMode() === 'bulk_register';
            $dueDate = $useImmediateCreditBulkRegister
                ? $issueDate->copy()
                : $issueDate->copy()->addDays(7);
            $perSeatAmountYen = $this->calculatePerSeatAmountForMonths($monthsCharged);

            return $this->createAndIssueInvoice(
                companyId: (int) $sub->company_id,
                subscriptionId: (int) $sub->id,
                kind: 'initial',
                billingCode: (string) $sub->billing_code,
                paymentMethod: 'credit',
                quantity: $quantity,
                unitPriceYen: self::PLAN_UNIT_PRICE_YEN,
                monthsCharged: $monthsCharged,
                amountYen: $perSeatAmountYen * $quantity,
                periodStart: $periodStart->toDateString(),
                periodEnd: $periodEnd->toDateString(),
                issueDate: $issueDate,
                dueDate: $dueDate,
                attachBillingIndividual: false,
                demandUnitPriceYen: $perSeatAmountYen,
                useImmediateCreditBulkRegister: $useImmediateCreditBulkRegister,
            );
        });
    }
 
    public function issueInitialCreditRecurringAfterRegistration(int $companyId): SubscriptionInvoice
    {
        return $this->issueInitialCreditAfterRegistration($companyId);
    }

    public function issueRenewal(Subscription $sub, Carbon $issueDate): ?SubscriptionInvoice
    {
        $issueDate = $issueDate->copy()->tz('Asia/Tokyo')->startOfDay();

        return DB::transaction(function () use ($sub, $issueDate) {
            $sub = Subscription::query()->lockForUpdate()->find((int) $sub->id);
            if (!$sub || empty($sub->term_end) || empty($sub->billing_code) || empty($sub->payment_method)) {
                return null;
            }

            if ($this->isRoboManagedRecurringSubscription($sub)) {
                Log::info('Skip local renewal because subscription is managed by BillingRobo recurring demand.', [
                    'subscription_id' => (int) $sub->id,
                    'company_id' => (int) $sub->company_id,
                    'billing_code' => (string) $sub->billing_code,
                    'payment_method' => (string) $sub->payment_method,
                    'billing_robo_master_demand_code' => (string) ($sub->billing_robo_master_demand_code ?? ''),
                ]);

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

            $monthsCharged = $this->calculateInclusiveMonths($periodStart, $termEnd);
            $perSeatAmountYen = $this->calculatePerSeatAmountForMonths($monthsCharged);
            $amountYen = $perSeatAmountYen * $addQuantity;

            $dueDate = $requestedAt->copy()->addDays(7);

            return $this->createAndIssueInvoice(
                companyId: (int) $sub->company_id,
                subscriptionId: (int) $sub->id,
                kind: 'add_quantity',
                billingCode: (string) $sub->billing_code,
                paymentMethod: (string) $sub->payment_method,
                quantity: $addQuantity,
                unitPriceYen: self::PLAN_UNIT_PRICE_YEN,
                monthsCharged: $monthsCharged,
                amountYen: $amountYen,
                periodStart: $periodStart->toDateString(),
                periodEnd: $termEnd->toDateString(),
                issueDate: $requestedAt,
                dueDate: $dueDate,
                demandUnitPriceYen: $perSeatAmountYen,
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
        ?int $demandUnitPriceYen = null,
        bool $useImmediateCreditBulkRegister = false,
    ): SubscriptionInvoice {
        $this->assertSupportedPaymentMethod($paymentMethod);

        $billingIndividualCode = $billingCode . '-01';
        $paymentMethodCode = null;
        $paymentRegisterStatus = null;
        $paymentCod = null;

        $shouldUseSavedBillingReference = !$attachBillingIndividual
            && $kind === 'initial'
            && $paymentMethod === 'credit';

        if ($shouldUseSavedBillingReference) {
            $existingBillingSetting = CompanyBillingSetting::query()
                ->where('company_id', $companyId)
                ->first();

            if ($existingBillingSetting instanceof CompanyBillingSetting) {
                $savedBillingIndividualCode = trim((string) ($existingBillingSetting->billing_individual_code ?? ''));
                $savedPaymentMethodCode = trim((string) ($existingBillingSetting->payment_method_code ?? ''));

                if ($savedBillingIndividualCode !== '') {
                    $billingIndividualCode = $savedBillingIndividualCode;
                }

                if ($savedPaymentMethodCode !== '') {
                    $paymentMethodCode = $savedPaymentMethodCode;
                }
            }
        }

        $inv = new SubscriptionInvoice();
        $inv->company_id = $companyId;
        $inv->subscription_id = $subscriptionId;
        $inv->kind = $kind;
        $inv->status = 'pending';
        $inv->demand_code = 'FURU' . strtoupper(Str::random(16));
        $inv->billing_code = $billingCode;
        $inv->item_code = $this->resolveInvoiceItemCode($kind);
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

            $this->attachPaymentMethodCodeToBillingIndividual(
                companyId: $companyId,
                billingCode: $billingCode,
                billingIndividualCode: $billingIndividualCode,
                paymentMethodCode: $paymentMethodCode,
            );
        }

        $periodStartDate = Carbon::parse($periodStart, 'Asia/Tokyo')->startOfDay();

        if ($useImmediateCreditBulkRegister) {
            if ($paymentMethod !== 'credit') {
                throw new RuntimeException('即時決済APIはクレジットカード初回請求のみ利用できます。');
            }

            if (trim((string) $billingIndividualCode) === '') {
                throw new RuntimeException('即時決済APIの実行に必要な billing_individual_code が未確定です。');
            }

            if (!is_string($paymentMethodCode) || trim($paymentMethodCode) === '') {
                Log::warning('BillingRobo immediate settlement continues without explicit billing individual payment_method_code binding because payment_method_code is empty.', [
                    'company_id' => $companyId,
                    'subscription_id' => $subscriptionId,
                    'billing_code' => $billingCode,
                    'billing_individual_code' => $billingIndividualCode,
                    'invoice_id' => (int) $inv->id,
                ]);
            } else {
                try {
                    $this->attachPaymentMethodCodeToBillingIndividual(
                        companyId: $companyId,
                        billingCode: $billingCode,
                        billingIndividualCode: $billingIndividualCode,
                        paymentMethodCode: $paymentMethodCode,
                    );
                } catch (Throwable $e) {
                    Log::warning('BillingRobo immediate settlement skipped billing individual payment_method_code binding because the update was rejected.', [
                        'company_id' => $companyId,
                        'subscription_id' => $subscriptionId,
                        'billing_code' => $billingCode,
                        'billing_individual_code' => $billingIndividualCode,
                        'payment_method_code' => $paymentMethodCode,
                        'invoice_id' => (int) $inv->id,
                        'exception_message' => $e->getMessage(),
                        'exception' => $this->extractThrowableContext($e),
                    ]);
                }
            }

            return $this->registerAndCaptureInitialCreditInvoiceImmediately(
                invoice: $inv,
                companyId: $companyId,
                billingCode: $billingCode,
                billingIndividualCode: $billingIndividualCode,
                demandUnitPriceYen: $demandUnitPriceYen ?? $unitPriceYen,
                monthsCharged: $monthsCharged,
                periodStartDate: $periodStartDate,
                issueDate: $issueDate,
                dueDate: $dueDate,
            );
        }

        $issueMonthOffset = $this->calculateMonthOffset($periodStartDate, $issueDate);
        $sendingDate = $issueDate->copy();
        $sendingMonthOffset = $this->calculateMonthOffset($periodStartDate, $sendingDate);
        $deadlineMonthOffset = $this->calculateMonthOffset($periodStartDate, $dueDate);
        $billingMethod = $defaultBillingMethod;
        $billTemplateCode = $this->resolveDemandBillTemplateCode((string) $inv->item_code);
        $demandPriceYen = $demandUnitPriceYen ?? $unitPriceYen;

        $demand = [
            'code' => $inv->demand_code,
            'billing_code' => $billingCode,
            'item_code' => $inv->item_code,
            'type' => self::ROBO_DEMAND_TYPE_ONE_TIME,
            'price' => $demandPriceYen,
            'quantity' => (int) $inv->quantity,
            'tax_category' => (int) config('billing_robo.tax_category'),
            'tax' => (int) config('billing_robo.tax'),
            'billing_method' => $billingMethod,
            'start_date' => $periodStartDate->format('Y/m/d'),
            'sales_recorded_month' => 0,
            'sales_recorded_day' => 1,
            'period_format' => 3,
            'period_value' => max(1, $monthsCharged),
            'period_unit' => self::ROBO_REPETITION_PERIOD_UNIT_MONTH,
            'period_criterion' => 0,
            'issue_month' => $issueMonthOffset,
            'issue_day' => $this->normalizeRoboDay($issueDate),
            'sending_month' => $sendingMonthOffset,
            'sending_day' => $this->normalizeRoboDay($sendingDate),
            'deadline_month' => $deadlineMonthOffset,
            'deadline_day' => $this->normalizeRoboDay($dueDate),
            'bill_template_code' => $billTemplateCode,
        ];

        if ($attachBillingIndividual || $shouldUseSavedBillingReference) {
            if (trim((string) $billingIndividualCode) === '') {
                throw new RuntimeException('demand作成失敗: billing_individual_code が未確定です。');
            }
            $demand['billing_individual_code'] = $billingIndividualCode;
        }

        if ($attachBillingIndividual || $shouldUseSavedBillingReference) {
            if (!is_string($paymentMethodCode) || trim($paymentMethodCode) === '') {
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
            Log::info('BillingRobo issue bill response', [
                'company_id' => $companyId,
                'billing_code' => $billingCode,
                'demand_code' => $inv->demand_code,
                'raw_response' => $issueRes,
            ]);
        } catch (Throwable $e) {
            throw new RuntimeException('請求書発行失敗', previous: $e);
        }

        $billNumber = $this->extractBillNumberFromIssueResponse($issueRes);
        if ($billNumber === '') {
            $issueError = $this->extractIssueBillErrorFromResponse($issueRes);
            if ($issueError !== '') {
                throw new RuntimeException("請求書発行失敗: {$issueError}");
            }
            throw new RuntimeException('bulk_issue_bill_select did not return bill.number.');
        }

        $inv->bill_number = $billNumber;
        $inv->status = 'issued';
        $inv->save();

        try {
            $billRes = $this->client->billSearchByNumber($billNumber);
            Log::info('BillingRobo bill search response', [
                'company_id' => $companyId,
                'billing_code' => $billingCode,
                'demand_code' => $inv->demand_code,
                'bill_number' => $billNumber,
                'raw_response' => $billRes,
            ]);
        } catch (Throwable $e) {
            Log::warning('BillingRobo bill search failed after issue', [
                'company_id' => $companyId,
                'billing_code' => $billingCode,
                'demand_code' => $inv->demand_code,
                'bill_number' => $billNumber,
                'exception_message' => $e->getMessage(),
            ]);
        }

        return $inv;
    }
 
    private function resolveCreditInitialChargeMode(): string
    {
        $mode = trim((string) config('billing_robo.credit_initial_charge_mode', 'bulk_register'));

        if (!in_array($mode, ['bulk_register', 'issue_bill'], true)) {
            throw new RuntimeException("billing_robo.credit_initial_charge_mode is invalid: {$mode}");
        }

        return $mode;
    }

    private function attachPaymentMethodCodeToBillingIndividual(
        int $companyId,
        string $billingCode,
        string $billingIndividualCode,
        string $paymentMethodCode,
    ): void {
        $billingPayload = $this->buildInitialBillingPayload($companyId, $billingCode, $billingIndividualCode);

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

    private function registerAndCaptureInitialCreditInvoiceImmediately(
        SubscriptionInvoice $invoice,
        int $companyId,
        string $billingCode,
        string $billingIndividualCode,
        int $demandUnitPriceYen,
        int $monthsCharged,
        Carbon $periodStartDate,
        Carbon $issueDate,
        Carbon $dueDate,
    ): SubscriptionInvoice {
        $requestStartedAt = now('Asia/Tokyo');
        $itemCode = (string) $invoice->item_code;
        $goodsName = $this->resolveGoodsNameByItemCode($itemCode);
        $billTemplateCode = $this->resolveDemandBillTemplateCode($itemCode);

        $billPayload = [
            'billing_code' => $billingCode,
            'billing_individual_code' => $billingIndividualCode,
            'billing_method' => $this->resolveDemandBillingMethod(),
            'bill_template_code' => $billTemplateCode,
            'tax' => (int) config('billing_robo.tax'),
            'issue_date' => $issueDate->format('Y/m/d'),
            'sending_date' => $issueDate->format('Y/m/d'),
            'deadline_date' => $dueDate->format('Y/m/d'),
            'jb' => 'CAPTURE',
            'bill_detail' => [[
                'demand_type' => self::ROBO_DEMAND_TYPE_ONE_TIME,
                'item_code' => $itemCode,
                'goods_name' => $goodsName,
                'price' => $demandUnitPriceYen,
                'quantity' => (int) $invoice->quantity,
                'tax_category' => (int) config('billing_robo.tax_category'),
                'tax' => (int) config('billing_robo.tax'),
                'start_date' => $periodStartDate->format('Y/m/d'),
                'period_format' => 3,
                'period_value' => max(1, $monthsCharged),
                'period_unit' => self::ROBO_REPETITION_PERIOD_UNIT_MONTH,
                'period_criterion' => 0,
                'sales_recorded_date' => $periodStartDate->format('Y/m/d'),
            ]],
        ];

        Log::info('BillingRobo initial credit immediate settlement request', [
            'company_id' => $companyId,
            'billing_code' => $billingCode,
            'invoice_id' => (int) $invoice->id,
            'bill_payload' => $billPayload,
        ]);

        $response = $this->client->demandBulkRegister([$billPayload]);

        Log::info('BillingRobo initial credit immediate settlement response', [
            'company_id' => $companyId,
            'billing_code' => $billingCode,
            'invoice_id' => (int) $invoice->id,
            'raw_response' => $response,
        ]);

        $error = $this->extractImmediateChargeErrorFromResponse($response);
        if ($error !== null) {
            $errorCode = trim((string) ($error['error_code'] ?? ''));
            $errorMessage = trim((string) ($error['error_message'] ?? 'Immediate credit settlement failed.'));
            $errorEc = trim((string) ($error['ec'] ?? ''));

            if ($errorCode === '234') {
                $invoice->status = 'failed';
                $invoice->last_synced_at = now();
                $invoice->last_sync_error = $this->formatImmediateChargeFailureMessage($errorCode, $errorMessage, $errorEc);
                $invoice->save();

                Log::warning('BillingRobo initial credit immediate settlement failed.', [
                    'company_id' => $companyId,
                    'billing_code' => $billingCode,
                    'invoice_id' => (int) $invoice->id,
                    'error_code' => $errorCode,
                    'error_message' => $errorMessage,
                    'ec' => $errorEc,
                ]);

                return $invoice;
            }

            throw new RuntimeException(sprintf(
                'BillingRobo demandBulkRegister failed: error_code=%s message=%s ec=%s',
                $errorCode !== '' ? $errorCode : 'unknown',
                $errorMessage !== '' ? $errorMessage : 'unknown',
                $errorEc !== '' ? $errorEc : 'n/a',
            ));
        }

        $remoteDemandCode = $this->extractImmediateChargeDemandCodeFromResponse($response);
        if ($remoteDemandCode !== '') {
            $invoice->demand_code = $remoteDemandCode;
        }

        $billNumber = $this->extractImmediateChargeBillNumberFromResponse($response);
        if ($billNumber === '') {
            $billNumber = $this->findImmediateChargeBillNumber(
                billingCode: $billingCode,
                issueDate: $issueDate,
                subtotalAmountYen: (int) $invoice->amount_yen,
                requestedAt: $requestStartedAt,
            );
        }

        if ($billNumber !== '') {
            $invoice->bill_number = $billNumber;
        }

        $invoice->last_synced_at = now();
        $invoice->last_sync_error = null;
        $invoice->save();

        $this->markInitialCreditInvoicePaid($invoice);

        $freshInvoice = SubscriptionInvoice::query()->find((int) $invoice->id);
        if ($freshInvoice instanceof SubscriptionInvoice) {
            $this->ensureCreditRecurringDemandForPaidInitialInvoice($freshInvoice);

            Log::info('BillingRobo initial credit immediate settlement succeeded.', [
                'company_id' => $companyId,
                'billing_code' => $billingCode,
                'invoice_id' => (int) $freshInvoice->id,
                'bill_number' => (string) ($freshInvoice->bill_number ?? ''),
                'subscription_id' => (int) $freshInvoice->subscription_id,
            ]);

            return $freshInvoice;
        }

        return $invoice;
    }

    private function extractImmediateChargeErrorFromResponse(array $response): ?array
    {
        $bill = $this->extractImmediateChargeBillResponseRow($response);
        if (!is_array($bill)) {
            return null;
        }

        $errorCode = $bill['error_code'] ?? null;
        $errorMessage = trim((string) ($bill['error_message'] ?? ''));
        $ec = trim((string) ($bill['ec'] ?? ''));

        if ($errorCode === null || trim((string) $errorCode) === '') {
            return null;
        }

        return [
            'error_code' => trim((string) $errorCode),
            'error_message' => $errorMessage,
            'ec' => $ec,
        ];
    }

    private function formatImmediateChargeFailureMessage(string $errorCode, string $errorMessage, string $ec): string
    {
        $parts = [
            'BillingRobo 初回クレジット即時決済に失敗しました。',
            "error_code={$errorCode}",
        ];

        if ($errorMessage !== '') {
            $parts[] = "message={$errorMessage}";
        }

        if ($ec !== '') {
            $parts[] = "ec={$ec}";
        }

        return implode(' ', $parts);
    }

    private function extractImmediateChargeDemandCodeFromResponse(array $response): string
    {
        $demand = $this->extractImmediateChargeDemandResponseRow($response);
        if (!is_array($demand)) {
            return '';
        }

        $code = $demand['code'] ?? null;

        return is_scalar($code) ? trim((string) $code) : '';
    }

    private function extractImmediateChargeBillNumberFromResponse(array $response): string
    {
        $bill = $this->extractImmediateChargeBillResponseRow($response);
        if (!is_array($bill)) {
            return '';
        }

        $number = $bill['number'] ?? null;

        return is_scalar($number) ? trim((string) $number) : '';
    }

    private function extractImmediateChargeDemandResponseRow(array $response): ?array
    {
        $user = $response['user'] ?? null;
        if (is_array($user) && isset($user['demand']) && is_array($user['demand'])) {
            return $user['demand'];
        }

        $demands = $response['demand'] ?? null;
        if (is_array($demands)) {
            if (isset($demands[0]) && is_array($demands[0])) {
                return $demands[0];
            }

            return $demands;
        }

        return null;
    }

    private function extractImmediateChargeBillResponseRow(array $response): ?array
    {
        $user = $response['user'] ?? null;
        if (is_array($user)) {
            $userBills = $user['bill'] ?? null;
            if (is_array($userBills)) {
                if (isset($userBills[0]) && is_array($userBills[0])) {
                    return $userBills[0];
                }

                return $userBills;
            }
        }

        $bills = $response['bill'] ?? null;
        if (is_array($bills)) {
            if (isset($bills[0]) && is_array($bills[0])) {
                return $bills[0];
            }

            return $bills;
        }

        return null;
    }

    private function findImmediateChargeBillNumber(
        string $billingCode,
        Carbon $issueDate,
        int $subtotalAmountYen,
        Carbon $requestedAt,
    ): string {
        $windowSeconds = max(30, (int) config('billing_robo.credit_initial_immediate_bill_search_window_seconds', 120));
        $from = $requestedAt->copy()->subSeconds(30);
        $to = now('Asia/Tokyo')->addSeconds($windowSeconds);

        try {
            $response = $this->client->billSearch(
                criteria: [
                    'billing_code' => $billingCode,
                    'payment_method' => self::CREDIT_PAYMENT_METHOD,
                    'update_date_from' => $from->format('Y/m/d H:i:s'),
                    'update_date_to' => $to->format('Y/m/d H:i:s'),
                    'valid_flg' => 1,
                ],
                limitCount: 20,
                pageCount: 0,
                sort: [
                    'key' => 'update_date,bill_id',
                    'order' => 0,
                ],
            );
        } catch (Throwable $e) {
            Log::warning('BillingRobo immediate settlement bill search failed while resolving bill number.', [
                'billing_code' => $billingCode,
                'invoice_issue_date' => $issueDate->toDateString(),
                'subtotal_amount_yen' => $subtotalAmountYen,
                'exception_message' => $e->getMessage(),
                'exception' => $this->extractThrowableContext($e),
            ]);

            return '';
        }

        $billRows = array_reverse($this->extractBillRowsFromBillSearchResponse($response));
        foreach ($billRows as $billRow) {
            $billNumber = trim((string) ($billRow['number'] ?? ''));
            if ($billNumber === '') {
                continue;
            }

            if (trim((string) ($billRow['billing_code'] ?? '')) !== $billingCode) {
                continue;
            }

            if ((int) ($billRow['payment_method'] ?? -1) !== self::CREDIT_PAYMENT_METHOD) {
                continue;
            }

            if ($this->parseRoboDate((string) ($billRow['issue_date'] ?? '')) !== $issueDate->toDateString()) {
                continue;
            }

            if (isset($billRow['subtotal_amount_billed']) && (int) $billRow['subtotal_amount_billed'] !== $subtotalAmountYen) {
                continue;
            }

            return $billNumber;
        }

        Log::warning('BillingRobo immediate settlement succeeded but bill number could not be resolved.', [
            'billing_code' => $billingCode,
            'invoice_issue_date' => $issueDate->toDateString(),
            'subtotal_amount_yen' => $subtotalAmountYen,
            'search_response' => $response,
        ]);

        return '';
    }

    private function resolveGoodsNameByItemCode(string $itemCode): string
    {
        try {
            $goodsRes = $this->client->goodsSearchByItemCode($itemCode);
            $goodsRows = $goodsRes['goods'] ?? null;

            if (is_array($goodsRows) && isset($goodsRows[0]) && is_array($goodsRows[0])) {
                $goods = $goodsRows[0];
                $goodsName = trim((string) ($goods['name'] ?? $goods['item_name'] ?? ''));
                if ($goodsName !== '') {
                    return $goodsName;
                }
            }
        } catch (Throwable $e) {
            Log::warning('BillingRobo goods search failed while resolving goods name. Fallback to item_code.', [
                'item_code' => $itemCode,
                'exception_message' => $e->getMessage(),
                'exception' => $this->extractThrowableContext($e),
            ]);
        }

        return $itemCode;
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

    private function makeDeterministicRecurringDemandCode(int $subscriptionId): string
    {
        return 'FURR' . str_pad((string) max(0, $subscriptionId), 16, '0', STR_PAD_LEFT);
    }

    private function resolveInvoiceItemCode(string $kind): string
    {
        return match ($kind) {
            'initial', 'add_quantity' => $this->resolveInitialDemandItemCode(),
            'renewal' => $this->resolveRecurringDemandItemCode(),
            default => $this->resolveInitialDemandItemCode(),
        };
    }

    private function resolveInitialDemandItemCode(): string
    {
        $itemCode = trim((string) config('billing_robo.initial_item_code_5seats', config('billing_robo.item_code_5seats')));
        if ($itemCode === '') {
            throw new RuntimeException('billing_robo.initial_item_code_5seats is invalid.');
        }

        return $itemCode;
    }

    private function resolveRecurringDemandItemCode(): string
    {
        $itemCode = trim((string) config('billing_robo.recurring_item_code_5seats', config('billing_robo.item_code_5seats')));
        if ($itemCode === '') {
            throw new RuntimeException('billing_robo.recurring_item_code_5seats is invalid.');
        }

        return $itemCode;
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

    /**
     * 初回クレカ請求の決済結果を請求書番号単位で同期する。
     *
     * @return 'paid'|'pending'|'failed'|'missing'
     */
    public function syncInitialCreditSettlementByBillNumber(string $billNumber): string
    {
        $billNumber = trim($billNumber);
        if ($billNumber === '') {
            return 'missing';
        }

        $invoice = SubscriptionInvoice::query()
            ->where('bill_number', $billNumber)
            ->where('kind', 'initial')
            ->where('payment_method', 'credit')
            ->first();

        if (!$invoice) {
            return 'missing';
        }

        if ($invoice->status === 'paid') {
            $this->ensureCreditRecurringDemandForPaidInitialInvoice($invoice);

            return 'paid';
        }

        $billSearchResponse = $this->client->billSearchByNumber($billNumber);
        $bill = $this->extractBillRowFromBillSearchResponse($billSearchResponse);

        if ($bill === null) {
            Log::warning('BillingRobo bill search returned no bill row while syncing initial credit settlement.', [
                'bill_number' => $billNumber,
                'raw_response' => $billSearchResponse,
            ]);

            return 'missing';
        }

        $result = DB::transaction(function () use ($invoice, $bill, $billNumber): string {
            $lockedInvoice = SubscriptionInvoice::query()
                ->lockForUpdate()
                ->find((int) $invoice->id);

            if (!$lockedInvoice) {
                return 'missing';
            }

            $lockedInvoice->clearing_status = isset($bill['clearing_status']) && $bill['clearing_status'] !== ''
                ? (int) $bill['clearing_status']
                : null;
            $lockedInvoice->unclearing_amount = isset($bill['unclearing_amount']) && $bill['unclearing_amount'] !== ''
                ? (int) $bill['unclearing_amount']
                : null;
            $lockedInvoice->transfer_date = $this->parseRoboDate((string) ($bill['transfer_date'] ?? ''));
            $lockedInvoice->last_synced_at = now();
            $lockedInvoice->last_sync_error = null;

            if ($lockedInvoice->status === 'paid') {
                $lockedInvoice->save();

                return 'paid';
            }

            $settlementResult = (int) ($bill['settlement_result'] ?? -1);
            $settlementSucceeded = $settlementResult === 2
                || (
                    $lockedInvoice->clearing_status !== null
                    && in_array((int) $lockedInvoice->clearing_status, [1, 2], true)
                    && $lockedInvoice->unclearing_amount !== null
                    && (int) $lockedInvoice->unclearing_amount === 0
                );

            if ($settlementSucceeded) {
                $this->markInitialCreditInvoicePaid($lockedInvoice);

                Log::info('BillingRobo initial credit settlement marked invoice as paid.', [
                    'company_id' => (int) $lockedInvoice->company_id,
                    'subscription_id' => (int) $lockedInvoice->subscription_id,
                    'invoice_id' => (int) $lockedInvoice->id,
                    'bill_number' => $billNumber,
                    'settlement_result' => $settlementResult,
                    'clearing_status' => $lockedInvoice->clearing_status,
                    'unclearing_amount' => $lockedInvoice->unclearing_amount,
                ]);

                return 'paid';
            }
            if ($settlementResult === 3) {
                $lockedInvoice->status = 'failed';
                $lockedInvoice->save();

                Log::warning('BillingRobo initial credit settlement failed.', [
                    'company_id' => (int) $lockedInvoice->company_id,
                    'subscription_id' => (int) $lockedInvoice->subscription_id,
                    'invoice_id' => (int) $lockedInvoice->id,
                    'bill_number' => $billNumber,
                    'settlement_result' => $settlementResult,
                ]);

                return 'failed';
            }

            if ($this->isInitialCreditSettlementGraceExpired($lockedInvoice)) {
                $lockedInvoice->status = 'failed';
                $lockedInvoice->save();

                Log::warning('BillingRobo initial credit settlement grace expired.', [
                    'company_id' => (int) $lockedInvoice->company_id,
                    'subscription_id' => (int) $lockedInvoice->subscription_id,
                    'invoice_id' => (int) $lockedInvoice->id,
                    'bill_number' => $billNumber,
                    'settlement_result' => $settlementResult,
                    'due_date' => (string) $lockedInvoice->due_date,
                    'grace_days' => (int) config('billing_robo.credit_initial_grace_days', 7),
                ]);

                return 'failed';
            }
            $lockedInvoice->save();

            return 'pending';
        });

        if ($result === 'paid') {
            $freshInvoice = SubscriptionInvoice::query()->find((int) $invoice->id);
            if ($freshInvoice instanceof SubscriptionInvoice) {
                $this->ensureCreditRecurringDemandForPaidInitialInvoice($freshInvoice);
            }
        }

        return $result;
    }

    /**
     * @return array<string,string>
     */
    public function syncPendingInitialCreditSettlements(?int $companyId = null): array
    {
        $query = SubscriptionInvoice::query()
            ->select(['bill_number'])
            ->where('kind', 'initial')
            ->where('payment_method', 'credit')
            ->whereIn('status', ['pending', 'issued', 'failed'])
            ->whereNotNull('bill_number');

        if ($companyId !== null) {
            $query->where('company_id', $companyId);
        }

        $results = [];
        foreach ($query->orderBy('id')->get() as $invoice) {
            $billNumber = trim((string) $invoice->bill_number);
            if ($billNumber === '') {
                continue;
            }
            $results[$billNumber] = $this->syncInitialCreditSettlementByBillNumber($billNumber);
        }

        return $results;
    }

    /**
     * bill/search の update_date 差分から初回クレカ請求の状態変化だけを取り込み、
     * local の初回請求に該当する bill_number だけを同期する。
     *
     * @return array{matched:int,synced:int,paid:int,pending:int,failed:int,missing:int,max_update_at:?string}
     */
    public function syncInitialCreditSettlementsByUpdatedWindow(Carbon $updatedFrom, Carbon $updatedTo, int $limitCount = 100): array
    {
        $from = $updatedFrom->copy()->tz('Asia/Tokyo');
        $to = $updatedTo->copy()->tz('Asia/Tokyo');
        $limitCount = max(1, min(200, $limitCount));
        $pageCount = 0;

        $stats = [
            'matched' => 0,
            'synced' => 0,
            'paid' => 0,
            'pending' => 0,
            'failed' => 0,
            'missing' => 0,
            'max_update_at' => null,
        ];

        do {
            $response = $this->client->billSearch(
                criteria: [
                    'payment_method' => self::CREDIT_PAYMENT_METHOD,
                    'update_date_from' => $from->format('Y/m/d H:i:s'),
                    'update_date_to' => $to->format('Y/m/d H:i:s'),
                    'valid_flg' => 1,
                ],
                limitCount: $limitCount,
                pageCount: $pageCount,
                sort: [
                    'key' => 'update_date,bill_id',
                    'order' => 0,
                ],
            );

            $billRows = $this->extractBillRowsFromBillSearchResponse($response);
            if ($billRows === []) {
                break;
            }

            $billNumbers = [];
            foreach ($billRows as $billRow) {
                $billNumber = trim((string) ($billRow['number'] ?? ''));
                if ($billNumber !== '') {
                    $billNumbers[] = $billNumber;
                }

                $updateDate = trim((string) ($billRow['update_date'] ?? ''));
                if ($updateDate !== '' && ($stats['max_update_at'] === null || strcmp($updateDate, (string) $stats['max_update_at']) > 0)) {
                    $stats['max_update_at'] = $updateDate;
                }
            }

            if ($billNumbers !== []) {
                $localBillNumbers = SubscriptionInvoice::query()
                    ->whereIn('bill_number', array_values(array_unique($billNumbers)))
                    ->where('kind', 'initial')
                    ->where('payment_method', 'credit')
                    ->pluck('bill_number')
                    ->map(static fn ($value) => trim((string) $value))
                    ->filter()
                    ->all();

                $localBillNumberMap = array_fill_keys($localBillNumbers, true);

                foreach ($billRows as $billRow) {
                    $billNumber = trim((string) ($billRow['number'] ?? ''));
                    if ($billNumber === '' || !isset($localBillNumberMap[$billNumber])) {
                        continue;
                    }

                    $stats['matched']++;
                    $result = $this->syncInitialCreditSettlementByBillNumber($billNumber);
                    $stats['synced']++;

                    if (array_key_exists($result, $stats) && is_int($stats[$result])) {
                        $stats[$result]++;
                    }
                }
            }

            $totalPageCount = max(1, (int) ($response['total_page_count'] ?? 1));
            $pageCount++;
        } while ($pageCount < $totalPageCount);

        return $stats;
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

    /**
     * @param array<string,mixed> $res
     * @return array<int,array<string,mixed>>
     */
    private function extractBillRowsFromBillSearchResponse(array $res): array
    {
        $bills = $res['bill'] ?? null;
        if (!is_array($bills)) {
            return [];
        }

        $rows = [];
        foreach ($bills as $bill) {
            if (is_array($bill)) {
                $rows[] = $bill;
            }
        }

        return $rows;
    }

    private function resolveDemandBillingMethod(): int
    {
        $billingMethod = (int) config('billing_robo.billing_method', 1);

        if (!in_array($billingMethod, [0, 1, 2, 3, 4, 5, 6, 7, 8], true)) {
            throw new RuntimeException("billing_robo.billing_method is invalid: {$billingMethod}");
        }

        return $billingMethod;
    }

    private function upsertRoboRecurringDemandForNextFiscalYear(
        int $subscriptionId,
        int $companyId,
        string $billingCode,
        string $paymentMethod,
        int $quantity,
        Carbon $recurringStart
    ): void {
        $billingSetting = CompanyBillingSetting::query()
            ->where('company_id', $companyId)
            ->first();

        if (!$billingSetting instanceof CompanyBillingSetting) {
            throw new RuntimeException("CompanyBillingSetting が見つかりません。 company_id={$companyId}");
        }

        $billingIndividualCode = trim((string) ($billingSetting->billing_individual_code ?? ''));
        $paymentMethodCode = trim((string) ($billingSetting->payment_method_code ?? ''));

        if ($billingIndividualCode === '' || $paymentMethodCode === '') {
            throw new RuntimeException("定期請求作成に必要な billing_individual_code / payment_method_code が不足しています。 company_id={$companyId}");
        }

        $subscription = Subscription::query()->find($subscriptionId);
        $masterDemandCode = trim((string) ($subscription?->billing_robo_master_demand_code ?? ''));
        if ($masterDemandCode === '') {
            $masterDemandCode = $this->makeDeterministicRecurringDemandCode($subscriptionId);
        }

        [$issueDay, $deadlineDay] = $this->resolveAnnualRecurringSchedule($paymentMethod);
        $recurringItemCode = $this->resolveRecurringDemandItemCode();
        $billTemplateCode = $this->resolveDemandBillTemplateCode($recurringItemCode);

        $demand = [
            'code' => $masterDemandCode,
            'billing_code' => $billingCode,
            'item_code' => $recurringItemCode,
            'type' => self::ROBO_DEMAND_TYPE_RECURRING_FIXED,
            'price' => self::PLAN_UNIT_PRICE_YEN,
            'quantity' => $quantity,
            'tax_category' => (int) config('billing_robo.tax_category'),
            'tax' => (int) config('billing_robo.tax'),
            'billing_method' => $this->resolveDemandBillingMethod(),
            'start_date' => $recurringStart->format('Y/m/d'),
            'sales_recorded_month' => 0,
            'sales_recorded_day' => 1,
            'period_format' => 3,
            'period_value' => 12,
            'period_unit' => self::ROBO_REPETITION_PERIOD_UNIT_MONTH,
            'period_criterion' => 0,
            'issue_month' => -1,
            'issue_day' => $issueDay,
            'sending_month' => -1,
            'sending_day' => $issueDay,
            'deadline_month' => -1,
            'deadline_day' => $deadlineDay,
            'bill_template_code' => $billTemplateCode,
            'repetition_period_number' => self::ROBO_REPETITION_PERIOD_NUMBER_ANNUAL,
            'repetition_period_unit' => self::ROBO_REPETITION_PERIOD_UNIT_MONTH,
            'repeat_count' => self::ROBO_REPEAT_COUNT_UNLIMITED,
            'billing_individual_code' => $billingIndividualCode,
            'payment_method_code' => $paymentMethodCode,
        ];

        Log::info('BillingRobo recurring demand upsert request', [
            'company_id' => $companyId,
            'billing_code' => $billingCode,
            'subscription_id' => $subscriptionId,
            'demand_payload' => $demand,
        ]);

        $response = $this->client->demandBulkUpsert([$demand]);

        Log::info('BillingRobo recurring demand upsert response', [
            'company_id' => $companyId,
            'billing_code' => $billingCode,
            'subscription_id' => $subscriptionId,
            'raw_response' => $response,
        ]);

        $this->markSubscriptionAsRoboManagedRecurring(
            subscriptionId: $subscriptionId,
            demandCode: $masterDemandCode,
        );
    }

    private function resolveAnnualRecurringSchedule(string $paymentMethod): array
    {
        return match ($paymentMethod) {
            'bank_transfer' => [
                (int) config('billing_robo.bank_transfer_recurring_issue_day', 15),
                (int) config('billing_robo.recurring_deadline_day', 99),
            ],
            'credit' => [
                (int) config('billing_robo.credit_recurring_issue_day', 25),
                (int) config('billing_robo.recurring_deadline_day', 99),
            ],
            default => throw new RuntimeException("Unsupported payment method for annual recurring schedule: {$paymentMethod}"),
        };
    }

    private function markSubscriptionAsRoboManagedRecurring(int $subscriptionId, string $demandCode): void
    {
        $updated = Subscription::query()
            ->whereKey($subscriptionId)
            ->update([
                'billing_robo_managed_recurring' => true,
                'billing_robo_master_demand_code' => $demandCode,
            ]);

        if ($updated !== 1) {
            Log::warning('Failed to mark subscription as BillingRobo recurring managed.', [
                'subscription_id' => $subscriptionId,
                'billing_robo_master_demand_code' => $demandCode,
            ]);
        }
    }

    private function isRoboManagedRecurringSubscription(Subscription $sub): bool
    {
        return (bool) ($sub->billing_robo_managed_recurring ?? false)
            && trim((string) ($sub->billing_robo_master_demand_code ?? '')) !== '';
    }
    
    private function resolveInitialContractWindow(Carbon $requestedAt): array
    {
        $requestedAt = $requestedAt->copy()->tz('Asia/Tokyo')->startOfDay();
        $currentFiscalStart = $this->resolveFiscalYearStartDate($requestedAt);
        $currentFiscalEnd = $currentFiscalStart->copy()->addYear()->subDay()->startOfDay();

        if ((int) $requestedAt->format('n') === (int) $currentFiscalEnd->format('n')) {
            $periodStart = $currentFiscalEnd->copy()->addDay()->startOfDay();
            $periodEnd = $periodStart->copy()->addYear()->subDay()->startOfDay();
            $monthsCharged = 12;
        } else {
            $periodStart = $requestedAt->copy()->startOfMonth();
            $periodEnd = $currentFiscalEnd;
            $monthsCharged = $this->calculateInclusiveMonths($periodStart, $periodEnd);
        }

        return [
            'period_start' => $periodStart,
            'period_end' => $periodEnd,
            'months_charged' => $monthsCharged,
            'per_seat_amount_yen' => $this->calculatePerSeatAmountForMonths($monthsCharged),
            'next_recurring_start' => $periodEnd->copy()->addDay()->startOfDay(),
        ];
    }

    private function resolveFiscalYearStartDate(Carbon $date): Carbon
    {
        $startMonth = (int) config('billing_robo.fiscal_year_start_month', 4);
        $year = (int) $date->format('Y');
        if ((int) $date->format('n') < $startMonth) {
            $year--;
        }

        return Carbon::create($year, $startMonth, 1, 0, 0, 0, 'Asia/Tokyo')->startOfDay();
    }

    private function calculateInclusiveMonths(Carbon $startDate, Carbon $endDate): int
    {
        return (($endDate->year - $startDate->year) * 12) + ($endDate->month - $startDate->month) + 1;
    }

    private function calculatePerSeatAmountForMonths(int $monthsCharged): int
    {
        return self::PLAN_MONTHLY_PRICE_YEN * max(1, min(12, $monthsCharged));
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

    private function markInitialCreditInvoicePaid(SubscriptionInvoice $invoice): void
    {
        if ($invoice->status !== 'paid') {
            $invoice->status = 'paid';
        }
        $invoice->save();

        $subscription = Subscription::query()
            ->lockForUpdate()
            ->find((int) $invoice->subscription_id);

        if (!$subscription) {
            return;
        }

        $subscription->status = 'active';
        $subscription->term_start = (string) $invoice->period_start;
        $subscription->term_end = (string) $invoice->period_end;
        $subscription->paid_through = (string) $invoice->period_end;
        $subscription->last_synced_at = now();
        $subscription->save();
    }

    private function ensureCreditRecurringDemandForPaidInitialInvoice(SubscriptionInvoice $invoice): void
    {
        if ((string) $invoice->kind !== 'initial' || (string) $invoice->payment_method !== 'credit') {
            return;
        }

        $subscription = Subscription::query()->find((int) $invoice->subscription_id);
        if (!$subscription || (string) $subscription->payment_method !== 'credit') {
            return;
        }

        if ($this->isRoboManagedRecurringSubscription($subscription)) {
            return;
        }

        $billingCode = trim((string) ($subscription->billing_code ?? ''));
        if ($billingCode === '') {
            return;
        }

        $periodEndSource = trim((string) ($invoice->period_end ?? $subscription->term_end ?? ''));
        if ($periodEndSource === '') {
            return;
        }

        try {
            $this->upsertRoboRecurringDemandForNextFiscalYear(
                subscriptionId: (int) $subscription->id,
                companyId: (int) $subscription->company_id,
                billingCode: $billingCode,
                paymentMethod: 'credit',
                quantity: max(1, (int) ($subscription->quantity ?? $invoice->quantity ?? 1)),
                recurringStart: Carbon::parse($periodEndSource, 'Asia/Tokyo')->addDay()->startOfDay(),
            );
        } catch (Throwable $e) {
            Log::error('Failed to ensure BillingRobo recurring demand after initial credit payment.', [
                'company_id' => (int) $subscription->company_id,
                'subscription_id' => (int) $subscription->id,
                'invoice_id' => (int) $invoice->id,
                'bill_number' => (string) ($invoice->bill_number ?? ''),
                'exception_message' => $e->getMessage(),
                'exception' => $this->extractThrowableContext($e),
            ]);
        }
    }

    private function isInitialCreditSettlementGraceExpired(SubscriptionInvoice $invoice): bool
    {
        $graceDays = max(0, (int) config('billing_robo.credit_initial_grace_days', 7));
        $graceDeadline = Carbon::parse((string) $invoice->due_date, 'Asia/Tokyo')
            ->endOfDay()
            ->addDays($graceDays);

        return Carbon::now('Asia/Tokyo')->gt($graceDeadline);
    }

    private function parseRoboDate(string $value): ?string
    {
        $v = trim($value);
        if ($v === '') {
            return null;
        }

        $v = substr($v, 0, 10);
        $v = str_replace('-', '/', $v);
        if (!preg_match('/^\d{4}\/\d{2}\/\d{2}$/', $v)) {
            return null;
        }

        return str_replace('/', '-', $v);
    }

    /**
     * @param array<string,mixed> $res
     * @return array<string,mixed>|null
     */
    private function extractBillRowFromBillSearchResponse(array $res): ?array
    {
        $bills = $res['bill'] ?? null;
        if (!is_array($bills)) {
            return null;
        }

        $first = $bills[0] ?? null;
        if (!is_array($first)) {
            return null;
        }

        return $first;
    }

    private function extractBillNumberFromIssueResponse(array $res): string
    {
        if (isset($res['demand']) && is_array($res['demand'])) {
            foreach ($res['demand'] as $demand) {
                if (!is_array($demand)) {
                    continue;
                }

                $salesList = $demand['sales'] ?? null;
                if (!is_array($salesList)) {
                    continue;
                }

                foreach ($salesList as $sales) {
                    if (!is_array($sales)) {
                        continue;
                    }

                    $bills = $sales['bill'] ?? null;
                    if (!is_array($bills)) {
                        continue;
                    }

                    foreach ($bills as $bill) {
                        if (is_array($bill) && isset($bill['number']) && (string) $bill['number'] !== '') {
                            return (string) $bill['number'];
                        }
                    }
                }
            }
        }

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

    private function extractIssueBillErrorFromResponse(array $res): string
    {
        $demands = $res['demand'] ?? null;
        if (!is_array($demands)) {
            return '';
        }

        foreach ($demands as $demand) {
            if (!is_array($demand)) {
                continue;
            }

            $errorCode = $demand['error_code'] ?? null;
            if ($errorCode === null || $errorCode === '' || (string) $errorCode === '0') {
                continue;
            }

            $errorMessage = (string) ($demand['error_message'] ?? '');
            $code = (string) ($demand['code'] ?? '');

            return "demand.code={$code} error_code={$errorCode} message={$errorMessage}";
        }

        return '';
    }
}