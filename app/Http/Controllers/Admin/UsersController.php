<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Mail\UserInvitationMail;
use App\Models\Group;
use App\Models\Guest;
use App\Models\Invitation;
use App\Models\User;
use App\Services\License\SeatService;
use App\Support\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class UsersController extends Controller
{
    public function index(): View
    {
        $this->authorize('viewAny', User::class);

        $actor = Auth::user();
        $companyId = $actor->company_id;
        $role = strtolower((string) ($actor->role ?? 'member'));
        $isOwner = method_exists($actor, 'isOwner') && $actor->isOwner();
        $isRegistrar = ($role === 'registrar');
        $limitToGroup = ! ($isOwner || $isRegistrar);

        $users = User::query()
            ->where('users.company_id', $companyId)
            ->when($limitToGroup, fn ($q) => $q->where('users.group_id', $actor->group_id))
            ->leftJoin('groups', 'groups.id', '=', 'users.group_id')
            ->select('users.*', DB::raw('groups.name as group_name'))
            ->orderBy('users.id')
            ->paginate(20);

        $seatSvc = app(SeatService::class);
        $seatUsage = (array) $seatSvc->getSeatUsage($companyId);

        $companyOwnerId = (int) DB::table('companies')->where('id', $companyId)->value('owner_user_id');
        $invitations = [];

        return view('admin.users.index', compact('users', 'seatUsage', 'invitations', 'companyOwnerId'));
    }

    public function create(): View
    {
        $this->authorize('invite', User::class);

        $actor = Auth::user();
        [$groups, $roleOptions] = $this->formOptionsForActor($actor);

        // client 招待用：選択可能な顧客（guest）
        $guestQuery = Guest::query()
            ->select('id','name','company_id','group_id','client_user_id')
            ->where('company_id', (int)$actor->company_id)
            ->orderBy('name');
        if (method_exists($actor, 'isGroupAdmin') && $actor->isGroupAdmin()) {
            $guestQuery->where('group_id', (int)$actor->group_id);
        }
        $guestsForClient = $guestQuery->get();

        return view('admin.users.create', [
            'groups' => $groups,
            'roleOptions' => $roleOptions,
            'guestsForClient' => $guestsForClient,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('invite', User::class);

        $actor = Auth::user();
        [$groups, $roleOptions] = $this->formOptionsForActor($actor);

        $roleKeys = array_keys($roleOptions);
        $groupIds = $groups->pluck('id')->map(fn ($id) => (int) $id)->all();

        $data = $request->validate([
            'name' => ['nullable', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'role' => ['required', Rule::in($roleKeys)],
            'group_id' => ['nullable', Rule::in($groupIds)],
            // client招待のみ使用
            'client_mode' => ['nullable', 'in:existing,new'],
            'guest_id' => ['nullable', 'integer'],
            'guest_name' => ['nullable', 'string', 'max:25'],
        ]);

        $role = $data['role'];
        $groupId = $data['group_id'] ?? null;
        $clientMode = (string)($data['client_mode'] ?? '');
        $guestId = isset($data['guest_id']) ? (int)$data['guest_id'] : 0;
        $guestName = trim((string)($data['guest_name'] ?? ''));

        // client招待：existing（既存紐付け） or new（新規顧問先作成）
        if ($role === 'client') {
            if (!in_array($clientMode, ['existing','new'], true)) {
                throw ValidationException::withMessages([
                    'client_mode' => __('顧問先の指定方法を選択してください。'),
                ]);
            }

            if ($clientMode === 'existing') {
                if ($guestId <= 0) {
                    throw ValidationException::withMessages([
                        'guest_id' => __('既存の顧問先（お客様）を選択してください。'),
                    ]);
                }
                $guest = Guest::query()
                    ->where('company_id', (int)$actor->company_id)
                    ->where('id', $guestId)
                    ->first();
                if (!$guest) {
                    throw ValidationException::withMessages([
                        'guest_id' => __('顧問先（お客様）が見つかりません。'),
                    ]);
                }
                // GroupAdminは自部署のguestのみ
                if (method_exists($actor, 'isGroupAdmin') && $actor->isGroupAdmin()) {
                    if ((int)$guest->group_id !== (int)($actor->group_id ?? 0)) {
                        throw ValidationException::withMessages([
                            'guest_id' => __('自部署のお客様のみ招待できます。'),
                        ]);
                    }
                }
                // 既に client が紐付いているなら招待不可
                if ((int)($guest->client_user_id ?? 0) > 0) {
                    throw ValidationException::withMessages([
                        'guest_id' => __('このお客様には既に顧客アカウントが紐付いています。'),
                    ]);
                }
                // 部署は guest に従属（ユーザー入力は無視）
                $groupId = (int)($guest->group_id ?? 0);
                if ($groupId <= 0) {
                    throw ValidationException::withMessages([
                        'guest_id' => __('このお客様の部署が未設定です。先に部署を設定してください。'),
                    ]);
                }
            } else { // new
                if ($guestName === '') {
                    throw ValidationException::withMessages([
                        'guest_name' => __('顧問先名（お客様名）を入力してください。'),
                    ]);
                }
                // 部署：GroupAdminは自部署固定、Owner/Registrarは選択必須
                if (method_exists($actor, 'isGroupAdmin') && $actor->isGroupAdmin()) {
                    $groupId = (int)($actor->group_id ?? 0);
                }
                if (!$groupId) {
                    throw ValidationException::withMessages([
                        'group_id' => __('部署を選択してください。'),
                    ]);
                }
                $groupId = (int)$groupId;
                // guestId は使わない
                $guestId = 0;
            }
        }      

        if ($role === 'registrar') {
            $groupId = null;
        } elseif ($groupId === null) {
            throw ValidationException::withMessages([
                'group_id' => __('部署を選択してください。'),
            ]);
        }

        if ($groupId !== null) {
            $groupId = (int) $groupId;
        }

        $invitationModel = new Invitation();
        $invitationTable = $invitationModel->getTable();

        $duplicateQuery = Invitation::query()
            ->where('company_id', $actor->company_id)
            ->where('email', $data['email']);

        if (Schema::hasColumn($invitationTable, 'accepted_at')) {
            $duplicateQuery->whereNull('accepted_at');
        }

        if (Schema::hasColumn($invitationTable, 'expires_at')) {
            $duplicateQuery->where(function ($query) {
                $query->whereNull('expires_at')->orWhere('expires_at', '>', now());
            });
        }

        foreach (['cancelled_at', 'revoked_at', 'deleted_at'] as $column) {
            if (Schema::hasColumn($invitationTable, $column)) {
                $duplicateQuery->whereNull($column);
            }
        }

        if ($duplicateQuery->exists()) {
            throw ValidationException::withMessages([
                'email' => __('このメールアドレスには未承諾の招待が存在します。'),
            ]);
        }

        // Seat：client は対象外。社員招待のみチェック。
        if ($role !== 'client') {
            $seatService = app(SeatService::class);
            $seatLimit = $seatService->getActiveSeats((int) $actor->company_id);
            try {
                $seatService->assertCanInvite((int) $actor->company_id, $seatLimit, 1);
            } catch (\Throwable $e) {
                // ここで「次に必要なプラン」を案内（自動アップグレードしない）
                $usage = $seatService->getSeatUsage((int) $actor->company_id);
                $need = (int)$usage['active_users'] + (int)$usage['pending_invites'] + 1;
                $suggest = $seatService->suggestPlanForHeadcount($need);
                $msg = "現在のプランではユーザー数の上限に達しています。"
                    . "（在籍{$usage['active_users']}名＋招待中{$usage['pending_invites']}名＋今回1名＝合計{$need}名）\n"
                    . "ユーザーを追加する前に、プラン変更を行ってください。\n"
                    . "次に必要なプラン目安：{$suggest['label']}";
                throw ValidationException::withMessages([
                    'email' => $msg,
                ]);
            }
        }

        $invitation = Invitation::create([
            'company_id' => $actor->company_id,
            'group_id' => $groupId,
            'guest_id' => ($role === 'client' && $clientMode === 'existing') ? $guestId : null,
            'guest_name' => ($role === 'client' && $clientMode === 'new') ? $guestName : null,
            'email' => $data['email'],
            'role' => $role,
            'token' => Str::random(40),
            'expires_at' => now()->addDays(7),
            'invited_by' => $actor->id,
        ]);

        // メール環境未設定でもユーザー追加操作自体は成功させる（開発環境の500回避）
        try {
            Mail::to($invitation->email)->send(new UserInvitationMail($invitation));
        } catch (\Throwable $e) {
            // 送信失敗はログだけ残す（本番ではMAIL設定を整える）
            \Log::warning('[InvitationMail] send failed', [
                'invitation_id' => $invitation->id ?? null,
                'email' => $invitation->email ?? null,
                'error' => $e->getMessage(),
            ]);
        }

        if (class_exists(AuditLogger::class)) {
            AuditLogger::log('invitation.created', [
                'actor_id' => $actor->id,
                'invitation_id' => $invitation->id ?? null,
                'email' => $invitation->email,
                'role' => $invitation->role,
                'guest_id' => $invitation->guest_id ?? null,
                'guest_name' => $invitation->guest_name ?? null,
            ]);
        }

        return redirect()->route('admin.users.index')->with('status', __('Invitation created.'));
    }

    public function edit(Request $request, $userId): View
    {
        $actor = Auth::user();
        $target = User::query()
            ->where('company_id', $actor->company_id)
            ->findOrFail($userId);

        $this->authorize('update', $target);

        [$groups, $roleOptions] = $this->formOptionsForActor($actor, $target);

        $hasIsActive = Schema::hasColumn($target->getTable(), 'is_active');

        return view('admin.users.edit', [
            'user' => $target,
            'groups' => $groups,
            'roleOptions' => $roleOptions,
            'hasIsActive' => $hasIsActive,
        ]);
    }

    public function update(Request $request, $userId): RedirectResponse
    {
        $actor = Auth::user();
        $target = User::query()
            ->where('company_id', $actor->company_id)
            ->findOrFail($userId);

        $this->authorize('update', $target);

        if (method_exists($actor, 'isRegistrar') && $actor->isRegistrar() && method_exists($target, 'isOwner') && $target->isOwner()) {
            abort(403, 'Registrar cannot update owner.');
        }

        [$groups, $roleOptions] = $this->formOptionsForActor($actor, $target);

        $roleKeys = array_keys($roleOptions);
        $groupIds = $groups->pluck('id')->map(fn ($id) => (int) $id)->all();

        $data = $request->validate([
            'name' => ['nullable', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users', 'email')->ignore($target->id)],
            'role' => ['required', Rule::in($roleKeys)],
            'group_id' => ['nullable', Rule::in($groupIds)],
        ],[
            'email.email' => __('メールアドレスの形式が正しくありません。'),
            'role.in' => __('役割の指定が不正です。'),
            'group_id.in' => __('部署の指定が不正です。'),
        ]);

        $oldRole = strtolower((string)($target->role ?? 'member'));

        $role = strtolower((string)($data['role'] ?? 'member'));
        $groupId = $data['group_id'] ?? null;
        $groupId = ($groupId === '' || $groupId === null) ? null : (int)$groupId;

        // Ownerは固定（companies.owner_user_id がSoT）
        if (method_exists($target, 'isOwner') && $target->isOwner()) {
            $role = strtolower((string)($target->role ?? 'owner'));
            $groupId = $target->group_id ? (int)$target->group_id : null;
        }

        // GroupAdmin は registrar/owner への昇格をサーバ側でも禁止
        if (method_exists($actor, 'isGroupAdmin') && $actor->isGroupAdmin()) {
            if (in_array($role, ['owner','registrar'], true)) {
                throw ValidationException::withMessages([
                    'role' => __('GroupAdmin は Owner / Registrar への昇格はできません。'),
                ]);
            }
            // GroupAdmin は自部署固定（formOptionsで絞っているが、改ざん対策で再検証）
            if ((int)($actor->group_id ?? 0) <= 0 || (int)($target->group_id ?? 0) !== (int)($actor->group_id ?? 0)) {
                abort(403);
            }
            if ($groupId !== null && (int)$groupId !== (int)($actor->group_id ?? 0)) {
                throw ValidationException::withMessages([
                    'group_id' => __('GroupAdmin は自部署以外へ変更できません。'),
                ]);
            }
        }

        // Registrar は部署なし
        if ($role === 'registrar') {
            $groupId = null;
        }

        // GroupAdmin/Member/Client は部署必須
        if (in_array($role, ['group_admin','member','client'], true)) {
            if ($groupId === null || (int)$groupId <= 0) {
                throw ValidationException::withMessages([
                    'group_id' => __('部署を選択してください。'),
                ]);
            }
        }

        if (array_key_exists('name', $data)) {
            $target->name = $data['name'] ?? $target->name;
        }
        $target->email = $data['email'];
        $target->role = $role;
        $target->group_id = $groupId;

        DB::transaction(function () use ($actor, $target, $oldRole, $role, $groupId) {
            $target->save();

            // client にしたら guest を必ず作成/更新（SoT: guests.client_user_id）
            if ($role === 'client') {
                Guest::updateOrCreate(
                    ['company_id' => (int)$actor->company_id, 'client_user_id' => (int)$target->id],
                    [
                        'name' => (string)$target->name,
                        'group_id' => (int)$groupId,
                        'user_id' => (int)$actor->id,
                    ]
                );
            }

            // client → 非client は紐付け解除
            if ($oldRole === 'client' && $role !== 'client') {
                Guest::query()
                    ->where('company_id', (int)$actor->company_id)
                    ->where('client_user_id', (int)$target->id)
                    ->update(['client_user_id' => null]);
            }
        });

        if (class_exists(AuditLogger::class)) {
            AuditLogger::log('user.updated', [
                'actor_id' => $actor->id,
                'user_id' => $target->id,
                'from_role' => $oldRole,
                'to_role' => $role,
                'to_group_id' => $groupId,
            ]);
        }

        return redirect()->route('admin.users.index')->with('status', __('User updated.'));
    }

    public function deactivate(Request $request, $userId): RedirectResponse
    {
        $actor = Auth::user();
        $target = User::query()
            ->where('company_id', $actor->company_id)
            ->findOrFail($userId);

        $this->authorize('deactivate', $target);

        if (! Schema::hasColumn($target->getTable(), 'is_active')) {
            abort(400, 'The users table does not have an is_active column.');
        }

        $target->is_active = false;
        $target->save();

        if (class_exists(AuditLogger::class)) {
            AuditLogger::log('user.deactivated', [
                'actor_id' => $actor->id,
                'user_id' => $target->id,
            ]);
        }

        return back()->with('status', __('User deactivated.'));
    }

    public function activate(Request $request, $userId): RedirectResponse
    {
        $actor = Auth::user();
        $target = User::query()
            ->where('company_id', $actor->company_id)
            ->findOrFail($userId);

        $this->authorize('activate', $target);

        if (! Schema::hasColumn($target->getTable(), 'is_active')) {
            abort(400, 'The users table does not have an is_active column.');
        }

        $target->is_active = true;
        $target->save();

        if (class_exists(AuditLogger::class)) {
            AuditLogger::log('user.activated', [
                'actor_id' => $actor->id,
                'user_id' => $target->id,
            ]);
        }

        return back()->with('status', __('User activated.'));
    }

    /**
     * @return array{0:Collection,1:array<string,string>}
     */
    protected function formOptionsForActor(User $actor, ?User $target = null): array
    {
        $groupQuery = Group::query()
            ->where('company_id', $actor->company_id)
            ->orderBy('name');

        if (method_exists($actor, 'isGroupAdmin') && $actor->isGroupAdmin()) {
            $groupQuery->where('id', $actor->group_id);
        }

        $groups = $groupQuery->get();

        $roleOptions = [
            'member' => 'Member（一般）',
            'group_admin' => 'GroupAdmin（部署管理者）',
            'registrar' => 'Registrar（統括管理者）',
            'client' => 'Client（顧問先）',
        ];

        if (method_exists($actor, 'isGroupAdmin') && $actor->isGroupAdmin()) {
            unset($roleOptions['registrar']);
        }

        if ($target && method_exists($target, 'isOwner') && $target->isOwner()) {
            $roleOptions = ['owner' => 'Owner（代表者）'];
        }

        return [$groups, $roleOptions];
    }
}