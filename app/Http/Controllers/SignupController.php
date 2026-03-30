<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\CompanyBillingSetting;
use App\Models\User;
use App\Services\Billing\IssueInvoiceService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

class SignupController extends Controller
{
    public function show(Request $request)
    {
        $paymentUrl = (string) config('billing_robo.payment_url', '');

        return view('signup.index', [
            'paymentUrl' => $paymentUrl,
        ]);
    }

    public function submit(Request $request, IssueInvoiceService $issuer)
    {
        $validated = $request->validate([
            'company_name' => ['required', 'string', 'max:255'],
            'branch_name'  => ['required', 'string', 'max:255'],
            'owner_name'   => ['required', 'string', 'max:255'],
            'email'        => ['required', 'string', 'email:rfc,filter', 'regex:/^[^@\s]+@[^@\s]+\.[^@\s]+$/', 'max:255', 'unique:users,email'],
            'tel' => [
                'nullable',
                'required_if:payment_method,クレジットカード',
                'string',
                'max:15',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    $digits = preg_replace('/\D+/', '', (string) $value);
                    if ($digits === null || !preg_match('/^\d{10,11}$/', $digits)) {
                        $fail('電話番号は10桁または11桁の数字で入力してください。');
                    }
                },
            ],
            'password'     => ['required', 'string', 'min:8', 'max:255', 'confirmed'],
            'payment_method' => ['required', 'string', Rule::in(['クレジットカード', '銀行振込'])],
            'quantity' => ['required', 'integer', 'min:1', 'max:999'],
        ], [
            'company_name.required' => '会社名を入力してください。',
            'branch_name.required'  => '支店名を入力してください。',
            'owner_name.required'   => '代表者名を入力してください。',
            'email.required'        => 'メールアドレスを入力してください。',
            'email.email'           => 'メールアドレスの形式が不正です。',
            'email.regex'           => 'メールアドレスの形式が不正です。',
            'email.unique'          => 'このメールアドレスは既に登録されています。',
            'tel.required_if'       => 'クレジットカード登録には電話番号が必要です。',
            'password.required'     => 'パスワードを入力してください。',
            'password.confirmed'    => 'パスワード（確認）が一致しません。',
            'payment_method.required' => '支払方法を選択してください。',
            'payment_method.in' => '支払方法の指定が不正です。',
            'quantity.required' => '口数を入力してください。',
            'quantity.integer' => '口数は整数で入力してください。',
            'quantity.min' => '口数は1以上で入力してください。',
            'quantity.max' => '口数は999以下で入力してください。',
        ]);

        $companyName = trim((string) $validated['company_name']);
        $branchName  = trim((string) $validated['branch_name']);
        $ownerName   = trim((string) $validated['owner_name']);
        $email       = trim((string) $validated['email']);
        $billingTel  = $this->normalizeTel((string) ($validated['tel'] ?? ''));
        $paymentUi   = trim((string) $validated['payment_method']);
        $quantity    = (int) ($validated['quantity'] ?? 1);

        if ($companyName === '' || $branchName === '' || $ownerName === '' || $email === '' || $paymentUi === '') {
            throw ValidationException::withMessages([
                'company_name' => '入力内容を確認してください。',
            ]);
        }

        $paymentMethod = match ($paymentUi) {
            'クレジットカード' => 'credit',
            '銀行振込' => 'bank_transfer',
        };

        $initialQuantity = max(1, min(999, (int)$quantity));
        $createdCompanyId = null;

        try {
            DB::transaction(function () use (
                $companyName,
                $branchName,
                $ownerName,
                $email,
                $billingTel,
                $validated,
                $paymentMethod,
                $initialQuantity,
                $issuer,
                &$createdCompanyId
            ) {
                $company = Company::create([
                    'name' => $companyName,
                    'branch_name' => $branchName,
                    'signup_plan' => 'p5',
                    'signup_payment_method' => $paymentMethod,
                ]);

                $user = User::create([
                    'company_id' => (int) $company->id,
                    'group_id'   => null,
                    'name'       => $ownerName,
                    'email'      => $email,
                    'password'   => Hash::make((string) $validated['password']),
                    'role'       => 'owner',
                    'is_active'  => true,
                ]);

                $company->owner_user_id = (int) $user->id;
                $company->save();

                $billingCode = $this->makeBillingCode(
                    (string) $company->name,
                    (string) $company->branch_name,
                    (int) $company->id
                );

                CompanyBillingSetting::updateOrCreate(
                    ['company_id' => (int)$company->id],
                    [
                        'payment_method' => $paymentMethod,
                        'billing_code' => $billingCode,
                        'billing_tel' => $billingTel !== '' ? $billingTel : null,
                        'credit_register_status' => null,
                        'credit_registered_at' => null,
                        'credit_last_error_code' => null,
                        'credit_last_error_message' => null,
                        'bank_account_type' => null,
                        'bank_code' => null,
                        'branch_code' => null,
                        'bank_account_number' => null,
                        'bank_account_name' => null,
                    ]
                );

                $issuer->issueInitial($company, $billingCode, $paymentMethod, $initialQuantity);
                $createdCompanyId = (int) $company->id;
            });
        } catch (Throwable $e) {
            Log::error('Signup submit failed during initial billing flow.', [
                'email' => $email,
                'company_name' => $companyName,
                'branch_name' => $branchName,
                'payment_method' => $paymentMethod,
                'quantity' => $initialQuantity,
                'exception' => $e,
            ]);

            return back()
                ->withInput($request->except(['password', 'password_confirmation']))
                ->withErrors([
                    'signup' => 'お申し込み処理中にエラーが発生しました。入力内容をご確認のうえ、再度お試しください。',
                ]);
        }
        if ($paymentMethod === 'credit') {
            if (!is_int($createdCompanyId) || $createdCompanyId <= 0) {
                throw new RuntimeException('クレジットカード登録画面への遷移に必要な company_id が取得できませんでした。');
            }

            $creditRegistrationUrl = URL::temporarySignedRoute(
                'billing.credit-card.show',
                now()->addMinutes((int) config('billing_robo.credit_registration_link_expire_minutes', 1440)),
                ['company' => $createdCompanyId]
            );

            return redirect()->to($creditRegistrationUrl);
        }

        $paymentUrl = (string) config('billing_robo.payment_url', '');
        if ($paymentUrl !== '') {
            return redirect()->away($paymentUrl);
        }

        return redirect()
            ->route('login')
            ->with('status', 'お申し込み情報を登録しました。続いてお支払い手続きを行ってください（支払いURLが未設定のためログインへ戻りました）。');
    }

    private function makeBillingCode(string $companyName, string $branchName, int $companyId): string
    {
        $base = trim($companyName) . '|' . trim($branchName) . '|' . $companyId;
        $hash = strtoupper(substr(hash('sha256', $base), 0, 12));
        return 'FURU-' . $hash;
    }

    private function normalizeTel(string $value): string
    {
        $digits = preg_replace('/\D+/', '', trim($value));
        return is_string($digits) ? $digits : '';
    }
}
