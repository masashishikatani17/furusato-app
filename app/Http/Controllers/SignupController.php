<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\User;
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
     * - companies に plan / payment_method を文字列で保存（正規化は後でOK）
     */
    public function submit(Request $request)
    {
        $validated = $request->validate([
            'company_name' => ['required', 'string', 'max:255'],
            'owner_name'   => ['required', 'string', 'max:255'],
            'email'        => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password'     => ['required', 'string', 'min:8', 'max:255', 'confirmed'],
            // step2: 文字列のまま保存
            'plan'         => ['required', 'string', 'max:255'],
            'payment_method' => ['required', 'string', 'max:255'],
        ], [
            'company_name.required' => '会社名を入力してください。',
            'owner_name.required'   => '代表者名を入力してください。',
            'email.required'        => 'メールアドレスを入力してください。',
            'email.email'           => 'メールアドレスの形式が不正です。',
            'email.unique'          => 'このメールアドレスは既に登録されています。',
            'password.required'     => 'パスワードを入力してください。',
            'password.confirmed'    => 'パスワード（確認）が一致しません。',
            'plan.required'         => '申込内容を選択してください。',
            'payment_method.required' => '支払方法を選択してください。',
        ]);

        // 念のため空白トリム
        $companyName = trim((string) $validated['company_name']);
        $ownerName   = trim((string) $validated['owner_name']);
        $email       = trim((string) $validated['email']);
        $plan        = trim((string) $validated['plan']);
        $payment     = trim((string) $validated['payment_method']);

        if ($companyName === '' || $ownerName === '' || $email === '' || $plan === '' || $payment === '') {
            throw ValidationException::withMessages([
                'company_name' => '入力内容を確認してください。',
            ]);
        }

        DB::transaction(function () use ($companyName, $ownerName, $email, $validated, $plan, $payment) {
            // 1) Company 作成（owner_user_id は後で埋める）
            $company = Company::create([
                'name' => $companyName,
                'signup_plan' => $plan,
                'signup_payment_method' => $payment,
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
        });

        // いったんログインさせずに login へ戻す（請求導入時に遷移方針を決める）
        return redirect()
            ->route('login')
            ->with('status', 'お申し込み情報を登録しました。続いてお支払い手続きを行ってください。');
    }
}
