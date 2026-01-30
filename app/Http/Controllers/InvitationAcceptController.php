<?php

namespace App\Http\Controllers;

use App\Models\Invitation;
use App\Models\User;
use App\Models\Guest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use App\Support\AuditLogger;

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

        // 方針A：既存ユーザーがいるメールは承諾不可（上書き事故防止）
        $exists = User::query()->where('email', $inv->email)->exists();
        if ($exists) {
            throw ValidationException::withMessages([
                'email' => 'このメールアドレスは既に登録されています。管理者に連絡してください。',
            ]);
        }

        // client の場合：対象guestが必須
        $role = strtolower((string)($inv->role ?? 'member'));
        $targetGuest = null;
        if ($role === 'client') {
            $gid = (int)($inv->guest_id ?? 0);

            if ($gid > 0) {
                // existing：既存guestに紐付け
                $targetGuest = Guest::query()
                    ->where('company_id', (int)$inv->company_id)
                    ->where('id', $gid)
                    ->first();
                if (!$targetGuest) abort(410, 'Invitation invalid (guest not found).');
                if ((int)($targetGuest->client_user_id ?? 0) > 0) abort(410, 'Invitation invalid (guest already linked).');
            } else {
                // new：招待に保持された guest_name と group_id から guest を作る
                $gn = trim((string)($inv->guest_name ?? ''));
                $gGroupId = (int)($inv->group_id ?? 0);
                if ($gn === '' || $gGroupId <= 0) {
                    abort(410, 'Invitation invalid (missing guest_name/group).');
                }
                $targetGuest = new Guest();
                $targetGuest->name = $gn;
                $targetGuest->company_id = (int)$inv->company_id;
                $targetGuest->group_id = $gGroupId;
                // guests.user_id は作成者（招待者）で保持
                $targetGuest->user_id = (int)($inv->invited_by ?? 0) ?: null;
                $targetGuest->save();
            }
        }

        $user = new User();
        $user->name = (string)($data['name'] ?? '') ?: ($targetGuest?->name ?? '');
        $user->email = $inv->email;
        $user->company_id = (int) $inv->company_id;
        $user->role = (string) $inv->role;

        if ($role === 'client') {
            // group は guest と一致させる
            $user->group_id = (int)($targetGuest->group_id ?? 0) ?: null;
        } else {
            $user->group_id = $inv->group_id !== null ? (int)$inv->group_id : null;
        }

        if (Schema::hasColumn($user->getTable(), 'is_active')) {
            $user->is_active = true;
        }
        $user->password = Hash::make($data['password']);
        $user->save();

        // client は guest に紐付ける（1:1）
        if ($role === 'client' && $targetGuest) {
            $targetGuest->client_user_id = (int)$user->id;
            // guestの部署と整合させたので、user.group_id も一致している前提
            $targetGuest->save();
        }

        // 招待を「承諾済」にする（カラムがある場合のみ）
        $table = $inv->getTable();
        if (Schema::hasColumn($table, 'accepted_at')) {
            $inv->accepted_at = now();
        }
        // token は保持。accepted_at をSoTにする
        $inv->save();

        if (class_exists(AuditLogger::class)) {
            AuditLogger::log('invitation.accepted', [
                'invitation_id' => (int)$inv->id,
                'email' => (string)$inv->email,
                'role' => (string)$inv->role,
                'guest_id' => $inv->guest_id ?? null,
                'user_id' => (int)$user->id,
            ], $user);
        }

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

        if (Schema::hasColumn($table, 'expired_at') && $inv->expired_at) {
            abort(410, 'Invitation expired.');
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
