<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\User;
use App\Services\Billing\IssueInvoiceService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class SignupController extends Controller
{
    public function show(Request $request)
    {
        // 支払URLは現時点では「表示するだけ」（請求管理ロボ導入時に差し替え想定）
        $paymentUrl = (string) config('billing_robo.payment_url', '');

        return view('signup.index', [
            'paymentUrl' => $paymentUrl,
        ]);
    }

    /**
     * 最終確定：Company + Owner を作成する
     * - Owner は role=owner 固定
     * - Owner の group_id は null（方針通り）
     * - プランは 5人プランのみ（固定）
     * - 支払方法はふるさと側で確定し、ロボの請求書発行にも反映する
     */
    public function submit(Request $request, IssueInvoiceService $issuer)
    {
        $validated = $request->validate([
            'company_name' => ['required', 'string', 'max:255'],
            'branch_name'  => ['required', 'string', 'max:255'],
            'owner_name'   => ['required', 'string', 'max:255'],
            'email'        => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password'     => ['required', 'string', 'min:8', 'max:255', 'confirmed'],
            // 支払方法（UI文字列）→内部コードへ変換して確定
            'payment_method' => ['required', 'string', 'max:255'],
            'quantity' => ['required', 'integer', 'min:1', 'max:999'],
        ], [
            'company_name.required' => '会社名を入力してください。',
            'branch_name.required'  => '支店名を入力してください。',
            'owner_name.required'   => '代表者名を入力してください。',
            'email.required'        => 'メールアドレスを入力してください。',
            'email.email'           => 'メールアドレスの形式が不正です。',
            'email.unique'          => 'このメールアドレスは既に登録されています。',
            'password.required'     => 'パスワードを入力してください。',
            'password.confirmed'    => 'パスワード（確認）が一致しません。',
            'payment_method.required' => '支払方法を選択してください。',
            'quantity.required' => '口数を入力してください。',
            'quantity.integer' => '口数は整数で入力してください。',
            'quantity.min' => '口数は1以上で入力してください。',
            'quantity.max' => '口数は999以下で入力してください。',
        ]);

        // 念のため空白トリム
        $companyName = trim((string) $validated['company_name']);
        $branchName  = trim((string) $validated['branch_name']);
        $ownerName   = trim((string) $validated['owner_name']);
        $email       = trim((string) $validated['email']);
        $paymentUi   = trim((string) $validated['payment_method']);
        $quantity    = (int) ($validated['quantity'] ?? 1);

        if ($companyName === '' || $branchName === '' || $ownerName === '' || $email === '' || $paymentUi === '') {
            throw ValidationException::withMessages([
                'company_name' => '入力内容を確認してください。',
            ]);
        }

        // UI文字列 → 内部コード（確定）
        $paymentMethod = match ($paymentUi) {
            'クレジットカード' => 'credit',
            'キャッシュカード' => 'debit',
            '銀行振込' => 'bank_transfer',
            default => throw ValidationException::withMessages([
                'payment_method' => '支払方法の指定が不正です。',
            ]),
        };

        // 申込は「5人プラン（年額3万円）×口数」
        $initialQuantity = max(1, min(999, (int)$quantity));

        DB::transaction(function () use (
            $companyName,
            $branchName,
            $ownerName,
            $email,
            $validated,
            $paymentMethod,
            $initialQuantity,
            $issuer
        ) {
            // 1) Company 作成（owner_user_id は後で埋める）
            $company = Company::create([
                'name' => $companyName,
                'branch_name' => $branchName,
                // 監査用に残す（UIには出さない想定）
                'signup_plan' => 'p5',
                'signup_payment_method' => $paymentMethod,
            ]);

            // 2) Owner User 作成（role=owner固定、group_id=null）
            $user = User::create([
                'company_id' => (int) $company->id,
                'group_id'   => null,
                'name'       => $ownerName,
                'email'      => $email,
                'password'   => Hash::make((string) $validated['password']),
                'role'       => 'owner',
                'is_active'  => true,
            ]);

            // 3) company.owner_user_id を紐付け
            $company->owner_user_id = (int) $user->id;
            $company->save();

            // 4) billing_code を固定生成（初回に確定、以後は変更しない運用）
            $billingCode = $this->makeBillingCode(
                (string) $company->name,
                (string) $company->branch_name,
                (int) $company->id
            );

            // 5) 発行フロー
            // subscription作成
            // invoice(pending)作成
            // demand/bulk_upsert
            // bulk_issue_bill_select（bill_number取得）
            // invoiceをissuedへ
            $issuer->issueInitial($company, $billingCode, $paymentMethod, $initialQuantity);
        });

        // 申込完了後：ロボ支払い手続きURLへ誘導
        // ※ payment_url が未設定なら従来通り login へ戻す
        $paymentUrl = (string) config('billing_robo.payment_url', '');
        if ($paymentUrl !== '') {
            return redirect()->away($paymentUrl);
        }

        return redirect()
            ->route('login')
            ->with('status', 'お申し込み情報を登録しました。続いてお支払い手続きを行ってください（支払いURLが未設定のためログインへ戻りました）。');
    }

    /**
     * 会社＋支店＋company_id から固定の請求先コードを生成する
     * - 初回作成時に確定し、以後は変更しない運用
     * - ロボ側の制約が不明なため英数+ハイフンに寄せる
     */
    private function makeBillingCode(string $companyName, string $branchName, int $companyId): string
    {
        $base = trim($companyName) . '|' . trim($branchName) . '|' . $companyId;
        $hash = strtoupper(substr(hash('sha256', $base), 0, 12));
        return 'FURU-' . $hash;
    }
}
