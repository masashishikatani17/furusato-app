<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Mail\UserInvitationMail;
use App\Models\Guest;
use App\Models\Invitation;
use App\Models\User;
use App\Services\License\SeatService;
use App\Support\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class InvitationsController extends Controller
{
    private function isOwnerOrRegistrar(User $u): bool
    {
        $role = strtolower((string)($u->role ?? ''));
        if (method_exists($u, 'isOwner') && $u->isOwner()) return true;
        return in_array($role, ['owner','registrar'], true);
    }

    private function isGroupAdmin(User $u): bool
    {
        return method_exists($u, 'isGroupAdmin') && $u->isGroupAdmin();
    }

    private function scopeForActor($q, User $actor)
    {
        $q->where('company_id', (int)$actor->company_id);
        if (!$this->isOwnerOrRegistrar($actor)) {
            // GroupAdmin は自部署のみ
            $q->where('group_id', (int)($actor->group_id ?? 0));
        }
        return $q;
    }

    public function index(Request $request): View
    {
        $this->authorize('invite', User::class);

        $actor = Auth::user();
        $q = Invitation::query();
        $this->scopeForActor($q, $actor);

        // status filter
        $status = (string)$request->string('status', 'pending');
        $now = now();
        if ($status === 'pending') {
            $q->whereNull('accepted_at')
              ->whereNull('cancelled_at')
              ->whereNull('revoked_at')
              ->whereNull('deleted_at')
              ->where(function($qq) use ($now){
                  $qq->whereNull('expires_at')->orWhere('expires_at', '>', $now);
              });
        } elseif ($status === 'accepted') {
            $q->whereNotNull('accepted_at');
        } elseif ($status === 'cancelled') {
            $q->whereNotNull('cancelled_at');
        } elseif ($status === 'revoked') {
            $q->whereNotNull('revoked_at');
        } elseif ($status === 'expired') {
            $q->whereNull('accepted_at')
              ->whereNull('cancelled_at')
              ->whereNull('revoked_at')
              ->whereNull('deleted_at')
              ->whereNotNull('expires_at')
              ->where('expires_at', '<=', $now);
        }

        // keyword
        $kw = trim((string)$request->string('q',''));
        if ($kw !== '') {
            $q->where(function($qq) use ($kw){
                $qq->where('email', 'like', '%'.$kw.'%')
                   ->orWhere('guest_name', 'like', '%'.$kw.'%');
            });
        }

        $invitations = $q->orderByDesc('id')->paginate(30)->withQueryString();

        // group name map
        $groupIds = $invitations->getCollection()->pluck('group_id')->filter()->unique()->values()->all();
        $groupMap = DB::table('groups')->select('id','name')->whereIn('id', $groupIds)->get()->keyBy('id');

        // guest map（guest_idを持つもののみ）
        $guestIds = $invitations->getCollection()->pluck('guest_id')->filter()->unique()->values()->all();
        $guestMap = Guest::query()->select('id','name')->whereIn('id', $guestIds)->get()->keyBy('id');

        return view('admin.invitations.index', compact('invitations','status','kw','groupMap','guestMap'));
    }

    private function assertPending(Invitation $inv): void
    {
        $now = now();
        if ($inv->accepted_at) abort(400, 'Already accepted.');
        if ($inv->cancelled_at) abort(400, 'Already cancelled.');
        if ($inv->revoked_at) abort(400, 'Already revoked.');
        if ($inv->expires_at && $inv->expires_at->lte($now)) abort(400, 'Already expired.');
    }

    public function cancel(Request $request, Invitation $invitation)
    {
        $this->authorize('invite', User::class);
        $actor = Auth::user();
        $this->scopeForActor(Invitation::query()->where('id', $invitation->id), $actor)->firstOrFail();
        $this->assertPending($invitation);

        $invitation->cancelled_at = now();
        $invitation->save();

        AuditLogger::log('invitation.cancelled', [
            'actor_id' => (int)$actor->id,
            'invitation_id' => (int)$invitation->id,
            'email' => (string)$invitation->email,
            'role' => (string)$invitation->role,
            'guest_id' => $invitation->guest_id ?? null,
            'guest_name' => $invitation->guest_name ?? null,
        ]);

        return back()->with('status', '招待を取消しました。');
    }

    public function revoke(Request $request, Invitation $invitation)
    {
        $this->authorize('invite', User::class);
        $actor = Auth::user();
        $this->scopeForActor(Invitation::query()->where('id', $invitation->id), $actor)->firstOrFail();
        $this->assertPending($invitation);

        $invitation->revoked_at = now();
        $invitation->save();

        AuditLogger::log('invitation.revoked', [
            'actor_id' => (int)$actor->id,
            'invitation_id' => (int)$invitation->id,
            'email' => (string)$invitation->email,
            'role' => (string)$invitation->role,
            'guest_id' => $invitation->guest_id ?? null,
            'guest_name' => $invitation->guest_name ?? null,
        ]);

        return back()->with('status', '招待を失効しました。');
    }

    public function resend(Request $request, Invitation $invitation)
    {
        $this->authorize('invite', User::class);
        $actor = Auth::user();
        $this->scopeForActor(Invitation::query()->where('id', $invitation->id), $actor)->firstOrFail();
        $this->assertPending($invitation);

        // 既に users に email があるなら再送不可（方針A）
        if (User::query()->where('email', $invitation->email)->exists()) {
            throw ValidationException::withMessages([
                'email' => 'このメールアドレスは既に登録されています。',
            ]);
        }

        // Seat：clientは対象外。社員招待のみチェック。
        $role = strtolower((string)($invitation->role ?? ''));
        if ($role !== 'client') {
            $seatService = app(SeatService::class);
            $seatLimit = $seatService->getActiveSeats((int)$actor->company_id);
            $seatService->assertCanInvite((int)$actor->company_id, $seatLimit, 1);
        }

        // 再送A：旧招待を revoked にして、新しい招待レコードを作る
        DB::transaction(function () use ($actor, $invitation) {
            $invitation->revoked_at = now();
            $invitation->save();

            $new = Invitation::create([
                'company_id' => $invitation->company_id,
                'group_id' => $invitation->group_id,
                'guest_id' => $invitation->guest_id,
                'guest_name' => $invitation->guest_name,
                'email' => $invitation->email,
                'role' => $invitation->role,
                'token' => Str::random(40),
                'expires_at' => now()->addDays(7),
                'invited_by' => (int)$actor->id,
            ]);

            try {
                Mail::to($new->email)->send(new UserInvitationMail($new));
            } catch (\Throwable $e) {
                \Log::warning('[InvitationMail] resend failed', [
                    'invitation_id' => $new->id ?? null,
                    'email' => $new->email ?? null,
                    'error' => $e->getMessage(),
                ]);
            }

            AuditLogger::log('invitation.resent', [
                'actor_id' => (int)$actor->id,
                'from_invitation_id' => (int)$invitation->id,
                'to_invitation_id' => (int)$new->id,
                'email' => (string)$new->email,
                'role' => (string)$new->role,
                'guest_id' => $new->guest_id ?? null,
                'guest_name' => $new->guest_name ?? null,
            ]);
        });

        return redirect()->route('admin.invitations.index', ['status' => 'pending'])
            ->with('status', '招待を再送しました（旧招待は失効）。');
    }
}
