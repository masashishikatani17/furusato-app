<?php

namespace App\Http\Controllers;

use App\Models\Invitation;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class InvitationAcceptController extends Controller
{
    public function show(Request $request, string $token)
    {
        $inv = Invitation::query()->where('token', $token)->first();
        $inv = $this->validateInvitationOrThrow($inv);

        return view('auth.invitation_accept', [
            'invitation' => $inv,
        ]);
    }

    public function store(Request $request, string $token)
    {
        $inv = Invitation::query()->where('token', $token)->first();
        $inv = $this->validateInvitationOrThrow($inv);

        $data = $request->validate([
            'name' => ['nullable', 'string', 'max:255'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ], [
            'password.confirmed' => 'パスワード確認が一致しません。',
            'password.min' => 'パスワードは8文字以上で入力してください。',
        ]);

        // 既に同メールでユーザーが存在する場合は「ログイン連携」扱いにする（要件次第）
        $user = User::query()->where('email', $inv->email)->first();
        if (!$user) {
            $user = new User();
            $user->name = $data['name'] ?? '';
            $user->email = $inv->email;
            $user->company_id = (int) $inv->company_id;
            $user->group_id = $inv->group_id !== null ? (int)$inv->group_id : null;
            $user->role = (string) $inv->role;

            // users に is_active があれば true にしておく
            if (Schema::hasColumn($user->getTable(), 'is_active')) {
                $user->is_active = true;
            }

            $user->password = Hash::make($data['password']);
            $user->save();
        } else {
            // 既存ユーザーが別会社だったら危険なので拒否
            if ((int)$user->company_id !== (int)$inv->company_id) {
                throw ValidationException::withMessages([
                    'email' => '同じメールアドレスのユーザーが別会社に存在します。管理者に連絡してください。',
                ]);
            }
            // パスワード設定のみ更新（必要なら）
            $user->password = Hash::make($data['password']);
            $user->save();
        }

        // 招待を「承諾済」にする（カラムがある場合のみ）
        $table = $inv->getTable();
        if (Schema::hasColumn($table, 'accepted_at')) {
            $inv->accepted_at = now();
        }
        // 使い捨てにしたいなら token を無効化
        $inv->token = 'accepted_' . $inv->id . '_' . now()->format('YmdHis');
        $inv->save();

        Auth::login($user);

        // 招待承諾後は data へ
        return redirect('/data')->with('status', '招待を承諾しました。ログインしました。');
    }

    private function validateInvitationOrThrow(?Invitation $inv): Invitation
    {
        if (!$inv) {
            abort(404, 'Invitation not found.');
        }

        $table = $inv->getTable();
        if (Schema::hasColumn($table, 'accepted_at') && $inv->accepted_at) {
            abort(410, 'Invitation already accepted.');
        }

        if (Schema::hasColumn($table, 'expires_at') && $inv->expires_at && $inv->expires_at->isPast()) {
            abort(410, 'Invitation expired.');
        }

        foreach (['cancelled_at', 'revoked_at', 'deleted_at'] as $col) {
            if (Schema::hasColumn($table, $col) && $inv->{$col}) {
                abort(410, 'Invitation not active.');
            }
        }

        return $inv;
    }
}
