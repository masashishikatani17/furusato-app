<?php

namespace App\Http\Controllers\Billing;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\CompanyBillingSetting;
use App\Models\User;
use App\Services\Billing\IssueInvoiceService;
use App\Services\BillingRobo\BillingRoboClient;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\URL;
use RuntimeException;
use Throwable;

class CreditCardRegistrationController extends Controller
{
    public function show(Request $request, Company $company)
    {
        abort_unless($request->hasValidSignature(), 403);

        $context = $this->loadContext($company);
        $creditAid = trim((string) config('billing_robo.credit_aid', ''));
        if ($creditAid === '') {
            abort(500, 'BILLING_ROBO_CREDIT_AID が未設定です。');
        }

        $postUrl = URL::temporarySignedRoute(
            'billing.credit-card.store',
            now()->addMinutes((int) config('billing_robo.credit_registration_link_expire_minutes', 1440)),
            ['company' => (int) $company->id]
        );

        return view('billing.credit_card_register', [
            'company' => $company,
            'billingCode' => $context['billing_code'],
            'paymentMethodCode' => $context['payment_method_code'],
            'email' => $context['email'],
            'tel' => $context['tel'],
            'creditAid' => $creditAid,
            'postUrl' => $postUrl,
            'jqueryJsUrl' => (string) config('billing_robo.credit_jquery_js_url'),
            'tokenJsUrl' => (string) config('billing_robo.credit_token_js_url'),
            'emv3dsJsUrl' => (string) config('billing_robo.credit_emv3ds_js_url'),
        ]);
    }

    public function store(
        Request $request,
        Company $company,
        BillingRoboClient $client,
        IssueInvoiceService $issuer
    ) {
        abort_unless($request->hasValidSignature(), 403);

        $validated = $request->validate([
            'token' => ['required', 'string', 'max:255'],
        ], [
            'token.required' => 'クレジットカードトークンが取得できませんでした。',
        ]);

        $context = $this->loadContext($company);
        /** @var CompanyBillingSetting $setting */
        $setting = $context['setting'];

        try {
            Log::info('BillingRobo credit token register request', [
                'company_id' => (int) $company->id,
                'billing_code' => $context['billing_code'],
                'payment_method_code' => $context['payment_method_code'],
                'email' => $context['email'],
                'tel' => $context['tel'],
            ]);

            $registerRes = $client->creditCardTokenRegister(
                billingCode: $context['billing_code'],
                billingPaymentMethodCode: $context['payment_method_code'],
                token: (string) $validated['token'],
                email: $context['email'],
                tel: $context['tel'],
            );

            Log::info('BillingRobo credit token register response', [
                'company_id' => (int) $company->id,
                'billing_code' => $context['billing_code'],
                'payment_method_code' => $context['payment_method_code'],
                'raw_response' => $registerRes,
            ]);

            [$registerStatus, $paymentMethodNumber] = $this->waitForCreditRegistrationCompletion(
                client: $client,
                billingCode: $context['billing_code'],
                paymentMethodCode: $context['payment_method_code'],
            );

            $setting->credit_register_status = $registerStatus;
            $setting->credit_registered_at = $registerStatus === 5 ? now('Asia/Tokyo') : null;
            $setting->credit_last_error_code = null;
            $setting->credit_last_error_message = null;
            $setting->save();

            if ($registerStatus !== 5) {
                return back()->withErrors([
                    'credit' => 'クレジットカード登録結果を確認中です。しばらく待ってから再度お試しください。',
                ]);
            }

            Log::info('BillingRobo credit register completed', [
                'company_id' => (int) $company->id,
                'billing_code' => $context['billing_code'],
                'payment_method_code' => $context['payment_method_code'],
                'payment_method_number' => $paymentMethodNumber,
                'register_status' => $registerStatus,
            ]);

            $invoice = $issuer->issueInitialCreditAfterRegistration((int) $company->id);

            Log::info('BillingRobo initial credit invoice handled after card registration.', [
                'company_id' => (int) $company->id,
                'billing_code' => $context['billing_code'],
                'payment_method_code' => $context['payment_method_code'],
                'invoice_id' => (int) $invoice->id,
                'bill_number' => (string) ($invoice->bill_number ?? ''),
                'invoice_status' => (string) $invoice->status,
                'last_sync_error' => (string) ($invoice->last_sync_error ?? ''),
            ]);

            if ((string) $invoice->status === 'failed') {
                $setting->credit_last_error_code = 'initial_charge_failed';
                $setting->credit_last_error_message = mb_substr((string) ($invoice->last_sync_error ?? 'BillingRobo で初回決済に失敗しました。'), 0, 255);
                $setting->save();

                return back()->withErrors([
                    'credit' => 'クレジットカード登録は完了しましたが、初回決済に失敗しました。別のカードでもう一度お試しください。',
                ]);
            }

            if ((string) $invoice->status === 'paid') {
                return redirect()
                    ->route('login')
                    ->with('status', 'クレジットカード登録と初回決済が完了しました。ログインしてください。');
            }
        } catch (Throwable $e) {
            $setting->credit_last_error_code = 'local';
            $setting->credit_last_error_message = mb_substr($e->getMessage(), 0, 255);
            $setting->save();

            Log::error('Credit card registration failed.', [
                'company_id' => (int) $company->id,
                'billing_code' => $context['billing_code'],
                'payment_method_code' => $context['payment_method_code'],
                'exception_message' => $e->getMessage(),
                'exception' => $e,
            ]);

            return back()->withErrors([
                'credit' => 'クレジットカード登録に失敗しました。時間をおいて再度お試しください。',
            ]);
        }

        return redirect()
            ->route('login')
            ->with('status', 'クレジットカード登録が完了しました。初回決済の確認後に利用開始します。ログインしてください。');
    }

    /**
     * @return array{setting:CompanyBillingSetting,billing_code:string,payment_method_code:string,email:string,tel:string}
     */
    private function loadContext(Company $company): array
    {
        $setting = CompanyBillingSetting::query()->where('company_id', (int) $company->id)->first();
        if (!$setting instanceof CompanyBillingSetting) {
            throw new RuntimeException("CompanyBillingSetting が見つかりません。 company_id={$company->id}");
        }

        if ((string) $setting->payment_method !== 'credit') {
            throw new RuntimeException("この請求先はクレジットカード登録対象ではありません。 company_id={$company->id}");
        }

        $billingCode = trim((string) $setting->billing_code);
        $paymentMethodCode = trim((string) $setting->payment_method_code);
        if ($billingCode === '' || $paymentMethodCode === '') {
            throw new RuntimeException("クレジットカード登録に必要な billing_code / payment_method_code が不足しています。 company_id={$company->id}");
        }

        $owner = User::query()->find((int) $company->owner_user_id);
        $email = trim((string) ($owner?->email ?? ''));
        $tel = preg_replace('/\D+/', '', (string) ($setting->billing_tel ?? ''));

        if ($email === '') {
            throw new RuntimeException("クレジットカード登録に必要なメールアドレスが不足しています。 company_id={$company->id}");
        }

        if (!is_string($tel) || $tel === '') {
            throw new RuntimeException("クレジットカード登録に必要な電話番号が不足しています。 company_id={$company->id}");
        }

        return [
            'setting' => $setting,
            'billing_code' => $billingCode,
            'payment_method_code' => $paymentMethodCode,
            'email' => $email,
            'tel' => $tel,
        ];
    }

    /**
     * @return array{0:?int,1:?int}
     */
    private function waitForCreditRegistrationCompletion(
        BillingRoboClient $client,
        string $billingCode,
        string $paymentMethodCode
    ): array {
        $lastStatus = null;
        $lastNumber = null;

        for ($i = 0; $i < 5; $i++) {
            $searchRes = $client->billingPaymentMethodSearch([
                'billing_code' => $billingCode,
                'code' => $paymentMethodCode,
                'payment_method' => 1,
                'valid_flg' => 1,
            ]);

            Log::info('BillingRobo credit payment search response', [
                'billing_code' => $billingCode,
                'payment_method_code' => $paymentMethodCode,
                'attempt' => $i + 1,
                'raw_response' => $searchRes,
            ]);

            $rows = $searchRes['billing_payment_method'] ?? [];
            foreach ($rows as $row) {
                if (!is_array($row)) {
                    continue;
                }

                if ((string) ($row['code'] ?? '') !== $paymentMethodCode) {
                    continue;
                }

                $lastStatus = isset($row['register_status']) && $row['register_status'] !== null
                    ? (int) $row['register_status']
                    : null;
                $lastNumber = isset($row['number']) && $row['number'] !== null
                    ? (int) $row['number']
                    : null;

                if (in_array($lastStatus, [5, 6, 7], true)) {
                    return [$lastStatus, $lastNumber];
                }
            }

            usleep(500000);
        }

        return [$lastStatus, $lastNumber];
    }
}