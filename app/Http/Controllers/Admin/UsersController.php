<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Mail\UserInvitationMail;
use App\Models\Group;
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

        return view('admin.users.create', [
            'groups' => $groups,
            'roleOptions' => $roleOptions,
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
        ]);

        $role = $data['role'];
        $groupId = $data['group_id'] ?? null;

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

        $seatService = app(SeatService::class);
        $seatLimit = $seatService->getActiveSeats((int) $actor->company_id);

        try {
            $seatService->assertCanInvite((int) $actor->company_id, $seatLimit, 1);
        } catch (\Throwable $e) {
            throw ValidationException::withMessages([
                'email' => __('招待可能な席数の上限に達しています。'),
            ]);
        }

        $invitation = Invitation::create([
            'company_id' => $actor->company_id,
            'group_id' => $groupId,
            'email' => $data['email'],
            'role' => $role,
            'token' => Str::random(40),
            'expires_at' => now()->addDays(7),
            'invited_by' => $actor->id,
        ]);

        Mail::to($invitation->email)->send(new UserInvitationMail($invitation));

        if (class_exists(AuditLogger::class)) {
            AuditLogger::log('invitation.created', [
                'actor_id' => $actor->id,
                'invitation_id' => $invitation->id ?? null,
                'email' => $invitation->email,
                'role' => $invitation->role,
            ]);
        }

        return redirect()->route('admin.users.index')->with('status', __('Invitation sent.'));
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
        ]);

        $role = $data['role'];
        $groupId = $data['group_id'] ?? null;

        if ($target->isOwner()) {
            $role = $target->role ?? 'owner';
            $groupId = $target->group_id;
        } elseif ($role === 'registrar') {
            $groupId = null;
        } elseif ($groupId === null) {
            throw ValidationException::withMessages([
                'group_id' => __('部署を選択してください。'),
            ]);
        }

        if ($groupId !== null) {
            $groupId = (int) $groupId;
        }

        if (array_key_exists('name', $data)) {
            $target->name = $data['name'] ?? $target->name;
        }

        $target->email = $data['email'];
        $target->role = $role;
        $target->group_id = $groupId;
        $target->save();

        if (class_exists(AuditLogger::class)) {
            AuditLogger::log('user.updated', [
                'actor_id' => $actor->id,
                'user_id' => $target->id,
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