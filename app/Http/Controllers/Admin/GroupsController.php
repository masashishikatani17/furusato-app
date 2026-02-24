<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Group;
use App\Services\Admin\GroupTransferService;
use App\Support\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class GroupsController extends Controller
{
    public function index(Request $request): View
    {
        $actor = $request->user();
        $this->authorize('viewAny', Group::class);

        $companyId = (int)($actor->company_id ?? 0);
        if ($companyId <= 0) {
            abort(403);
        }

        $q = Group::query()
            ->where('company_id', $companyId)
            ->whereNull('deleted_at')
            ->orderBy('is_active', 'desc')
            ->orderBy('name')
            ->orderBy('id');

        // group_admin / member は自部署のみ閲覧
        if ($actor->isGroupAdmin() || ((string)($actor->getDisplayRoleAttribute() ?? '') === 'member')) {
            $q->where('id', (int)($actor->group_id ?? 0));
        }

        $groups = $q->get();

        // 一覧用集計（最小構成：DB集計→配列で付与）
        $groupIds = $groups->pluck('id')->all();
        $counts = [
            'users_group_admin' => [],
            'users_member' => [],
            'users_client' => [],
            'guests' => [],
            'datas' => [],
            'invitations_pending' => [],
        ];

        if (!empty($groupIds)) {
            $counts['users_group_admin'] = \DB::table('users')
                ->selectRaw('group_id, COUNT(*) as c')
                ->where('company_id', $companyId)
                ->whereIn('group_id', $groupIds)
                ->whereIn('role', ['group_admin', 'groupadmin', 'group-admin'])
                ->groupBy('group_id')
                ->pluck('c', 'group_id')
                ->all();

            $counts['users_member'] = \DB::table('users')
                ->selectRaw('group_id, COUNT(*) as c')
                ->where('company_id', $companyId)
                ->whereIn('group_id', $groupIds)
                ->where('role', 'member')
                ->groupBy('group_id')
                ->pluck('c', 'group_id')
                ->all();

            $counts['users_client'] = \DB::table('users')
                ->selectRaw('group_id, COUNT(*) as c')
                ->where('company_id', $companyId)
                ->whereIn('group_id', $groupIds)
                ->where('role', 'client')
                ->groupBy('group_id')
                ->pluck('c', 'group_id')
                ->all();

            $counts['guests'] = \DB::table('guests')
                ->selectRaw('group_id, COUNT(*) as c')
                ->where('company_id', $companyId)
                ->whereIn('group_id', $groupIds)
                ->whereNull('deleted_at')
                ->groupBy('group_id')
                ->pluck('c', 'group_id')
                ->all();

            $counts['datas'] = \DB::table('datas')
                ->selectRaw('group_id, COUNT(*) as c')
                ->where('company_id', $companyId)
                ->whereIn('group_id', $groupIds)
                ->whereNull('deleted_at')
                ->groupBy('group_id')
                ->pluck('c', 'group_id')
                ->all();

            $counts['invitations_pending'] = \DB::table('invitations')
                ->selectRaw('group_id, COUNT(*) as c')
                ->where('company_id', $companyId)
                ->whereIn('group_id', $groupIds)
                ->whereNull('accepted_at')
                ->whereNull('cancelled_at')
                ->whereNull('revoked_at')
                ->whereNull('expired_at')
                ->whereNull('deleted_at')
                ->groupBy('group_id')
                ->pluck('c', 'group_id')
                ->all();
        }

        // 移動先候補：同一company、稼働中、未削除
        $activeGroups = Group::query()
            ->where('company_id', $companyId)
            ->whereNull('deleted_at')
            ->where('is_active', 1)
            ->orderBy('name')
            ->orderBy('id')
            ->get(['id', 'name']);

        $canManage = $actor->isOwner() || $actor->isRegistrar();

        return view('admin.groups.index', [
            'groups' => $groups,
            'counts' => $counts,
            'activeGroups' => $activeGroups,
            'canManage' => $canManage,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $actor = $request->user();
        $this->authorize('create', Group::class);

        $companyId = (int)($actor->company_id ?? 0);
        if ($companyId <= 0) abort(403);

        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('groups')->where(fn($q) => $q->where('company_id', $companyId)->whereNull('deleted_at')),
            ],
        ], [
            'name.required' => '部署名を入力してください。',
            'name.max' => '部署名は255文字以内で入力してください。',
            'name.unique' => '同じ部署名が既に存在します。',
        ]);

        $g = new Group();
        $g->company_id = $companyId;
        $g->name = trim((string)$validated['name']);
        $g->is_active = true;
        $g->save();

        AuditLogger::log('group.created', [
            'company_id' => (int)$g->company_id,
            'group_id' => (int)$g->id,
            'name' => (string)$g->name,
        ], $g);

        return redirect()->route('admin.groups.index')->with('success', '部署を作成しました。');
    }

    public function update(Request $request, Group $group): RedirectResponse
    {
        $this->authorize('update', $group);

        $actor = $request->user();
        $companyId = (int)($actor->company_id ?? 0);
        if ((int)$group->company_id !== $companyId) abort(403);
        if ($group->deleted_at !== null) abort(404);

        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('groups')
                    ->ignore($group->id)
                    ->where(fn($q) => $q->where('company_id', $companyId)->whereNull('deleted_at')),
            ],
        ], [
            'name.required' => '部署名を入力してください。',
            'name.max' => '部署名は255文字以内で入力してください。',
            'name.unique' => '同じ部署名が既に存在します。',
        ]);

        $oldName = (string)$group->name;
        $group->name = trim((string)$validated['name']);
        $group->save();

        AuditLogger::log('group.renamed', [
            'company_id' => (int)$group->company_id,
            'group_id' => (int)$group->id,
            'from' => $oldName,
            'to' => (string)$group->name,
        ], $group);

        return redirect()->route('admin.groups.index')->with('success', '部署名を更新しました。');
    }

    public function activate(Request $request, Group $group): RedirectResponse
    {
        $this->authorize('activate', $group);

        $actor = $request->user();
        if ((int)$group->company_id !== (int)($actor->company_id ?? 0)) abort(403);
        if ($group->deleted_at !== null) abort(404);

        $group->is_active = true;
        $group->save();

        AuditLogger::log('group.activated', [
            'company_id' => (int)$group->company_id,
            'group_id' => (int)$group->id,
            'name' => (string)$group->name,
        ], $group);

        return redirect()->route('admin.groups.index')->with('success', '部署を復活しました。');
    }

    public function deactivate(Request $request, Group $group): RedirectResponse
    {
        // 停止は transfer に統一（モーダルからPOSTする運用）。直叩きは transfer を案内。
        $this->authorize('deactivate', $group);
        return redirect()->route('admin.groups.index')->with('info', '停止は「異動」実行とセットで行ってください。');
    }

    public function destroy(Request $request, Group $group): RedirectResponse
    {
        // 削除は transfer に統一（モーダルからPOSTする運用）。直叩きは transfer を案内。
        $this->authorize('delete', $group);
        return redirect()->route('admin.groups.index')->with('info', '削除は「異動」実行とセットで行ってください。');
    }

    public function transfer(Request $request, Group $group, GroupTransferService $svc): RedirectResponse
    {
        $this->authorize('transfer', $group);

        $actor = $request->user();
        if ((int)$group->company_id !== (int)($actor->company_id ?? 0)) abort(403);
        if ($group->deleted_at !== null) abort(404);

        $validated = $request->validate([
            'to_group_id' => ['required', 'integer'],
            'action' => ['required', 'in:deactivate,destroy'],
        ], [
            'to_group_id.required' => '移動先部署を選択してください。',
            'action.required' => '処理種別が不正です。',
            'action.in' => '処理種別が不正です。',
        ]);

        // 移動先候補が無い場合（明示メッセージ）
        $companyId = (int)($actor->company_id ?? 0);
        $hasDest = Group::query()
            ->where('company_id', $companyId)
            ->whereNull('deleted_at')
            ->where('is_active', 1)
            ->where('id', '!=', (int)$group->id)
            ->exists();
        if (!$hasDest) {
            return redirect()->route('admin.groups.index')
                ->withErrors(['to_group_id' => '移動先となる稼働中の部署がありません。先に部署を作成してください。']);
        }

        $toGroupId = (int)$validated['to_group_id'];
        $action = (string)$validated['action'];

        try {
            $moved = $svc->transferAndAction($actor, $group, $toGroupId, $action);
        } catch (ValidationException $e) {
            return redirect()->route('admin.groups.index')
                ->withErrors($e->errors())
                ->withInput();
        }

        // 監査ログ（異動＋停止/削除）
        AuditLogger::log('group.transferred', [
            'company_id' => (int)$group->company_id,
            'from_group_id' => (int)$group->id,
            'to_group_id' => $toGroupId,
            'action' => $action,
            'moved' => $moved,
        ], $group);

        if ($action === 'deactivate') {
            AuditLogger::log('group.deactivated', [
                'company_id' => (int)$group->company_id,
                'group_id' => (int)$group->id,
                'name' => (string)$group->name,
            ], $group);
        } else {
            AuditLogger::log('group.deleted', [
                'company_id' => (int)$group->company_id,
                'group_id' => (int)$group->id,
                'name' => (string)$group->name,
            ], $group);
        }

        $msg = ($action === 'deactivate')
            ? '異動後に部署を停止しました。'
            : '異動後に部署を削除しました。';

        return redirect()->route('admin.groups.index')->with('success', $msg);
    }
}