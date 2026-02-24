<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\Group;
use App\Models\User;
use App\Support\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class OwnerTransferController extends Controller
{
    public function form(): View
    {
        $actor = Auth::user();
        if (! $actor) {
            abort(403);
        }
        // Ownerのみ（companies.owner_user_id がSoT）
        if (!method_exists($actor, 'isOwner') || !$actor->isOwner()) {
            abort(403);
        }

        $companyId = (int)($actor->company_id ?? 0);
        if ($companyId <= 0) {
            abort(403);
        }

        $company = Company::query()->findOrFail($companyId);
        if ((int)($company->owner_user_id ?? 0) !== (int)$actor->id) {
            abort(403);
        }

        // 新Owner候補：同一company、is_active=1、role!=client、かつ現Owner以外
        $eligibleUsers = User::query()
            ->where('company_id', $companyId)
            ->where('id', '!=', (int)$actor->id)
            ->where(function ($q) {
                $q->whereNull('is_active')->orWhere('is_active', 1);
            })
            ->where(function ($q) {
                // roleがNULLの場合はmember扱いなので許可
                $q->whereNull('role')->orWhereRaw('LOWER(role) != ?', ['client']);
            })
            ->orderBy('name')
            ->get(['id', 'name', 'email', 'role', 'group_id', 'is_active']);

        // 旧Ownerの譲渡後：group_admin/member の場合に必要な部署候補（稼働中のみ）
        $activeGroups = Group::query()
            ->where('company_id', $companyId)
            ->whereNull('deleted_at')
            ->where('is_active', 1)
            ->orderBy('name')
            ->get(['id', 'name']);

        return view('admin.owner_transfer.form', [
            'eligibleUsers' => $eligibleUsers,
            'activeGroups' => $activeGroups,
            'company' => $company,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $actor = Auth::user();
        if (! $actor) {
            abort(403);
        }
        if (!method_exists($actor, 'isOwner') || !$actor->isOwner()) {
            abort(403);
        }

        $companyId = (int)($actor->company_id ?? 0);
        if ($companyId <= 0) {
            abort(403);
        }

        $company = Company::query()->findOrFail($companyId);
        // 実行時点でも「現Owner」が自分であることを再確認（競合対策）
        if ((int)($company->owner_user_id ?? 0) !== (int)$actor->id) {
            abort(403);
        }

        $validated = $request->validate([
            'new_owner_user_id' => [
                'required',
                'integer',
                Rule::exists('users', 'id')->where(function ($q) use ($companyId) {
                    $q->where('company_id', $companyId)
                      ->where(function ($qq) {
                          $qq->whereNull('is_active')->orWhere('is_active', 1);
                      })
                      ->where(function ($qq) {
                          $qq->whereNull('role')->orWhereRaw('LOWER(role) != ?', ['client']);
                      });
                }),
            ],
            'old_owner_new_role' => ['required', Rule::in(['registrar', 'group_admin', 'member'])],
            'old_owner_new_group_id' => ['nullable', 'integer'],
            'confirm' => ['required', Rule::in(['1'])],
        ], [
            'new_owner_user_id.required' => '新しい代表者を選択してください。',
            'new_owner_user_id.exists' => '新しい代表者の指定が不正です。',
            'old_owner_new_role.required' => '譲渡後の役割を選択してください。',
            'old_owner_new_role.in' => '譲渡後の役割の指定が不正です。',
            'confirm.required' => '確認チェックを入れてください。',
            'confirm.in' => '確認チェックを入れてください。',
        ]);

        $newOwnerId = (int)$validated['new_owner_user_id'];
        if ($newOwnerId === (int)$actor->id) {
            return back()->withErrors(['new_owner_user_id' => '新しい代表者に自分自身は指定できません。'])->withInput();
        }

        $oldNewRole = (string)$validated['old_owner_new_role'];
        $oldNewGroupIdRaw = $request->input('old_owner_new_group_id');
        $oldNewGroupId = ($oldNewGroupIdRaw === '' || $oldNewGroupIdRaw === null) ? null : (int)$oldNewGroupIdRaw;

        // 旧Ownerが group_admin/member の場合は部署必須（稼働中のみ）
        if (in_array($oldNewRole, ['group_admin', 'member'], true)) {
            if (!$oldNewGroupId || $oldNewGroupId <= 0) {
                return back()->withErrors(['old_owner_new_group_id' => '譲渡後の部署を選択してください。'])->withInput();
            }
            $ok = Group::query()
                ->where('company_id', $companyId)
                ->whereNull('deleted_at')
                ->where('is_active', 1)
                ->where('id', $oldNewGroupId)
                ->exists();
            if (!$ok) {
                return back()->withErrors(['old_owner_new_group_id' => '譲渡後の部署の指定が不正です。'])->withInput();
            }
        } else {
            // registrar は部署なし
            $oldNewGroupId = null;
        }

        DB::transaction(function () use ($company, $companyId, $actor, $newOwnerId, $oldNewRole, $oldNewGroupId) {
            /** @var User $oldOwner */
            $oldOwner = User::query()
                ->where('company_id', $companyId)
                ->where('id', (int)$actor->id)
                ->lockForUpdate()
                ->firstOrFail();

            /** @var User $newOwner */
            $newOwner = User::query()
                ->where('company_id', $companyId)
                ->where('id', $newOwnerId)
                ->lockForUpdate()
                ->firstOrFail();

            // 新Ownerが client でない＆有効であることを再確認（直前改ざん対策）
            $newRole = strtolower((string)($newOwner->role ?? 'member'));
            if ($newRole === 'client') {
                throw ValidationException::withMessages(['new_owner_user_id' => 'Client は代表者にできません。']);
            }
            if (property_exists($newOwner, 'is_active') && (bool)($newOwner->is_active ?? true) === false) {
                throw ValidationException::withMessages(['new_owner_user_id' => '停止中ユーザーは代表者にできません。']);
            }

            // companies.owner_user_id がSoT：これを更新
            if ((int)($company->owner_user_id ?? 0) !== (int)$oldOwner->id) {
                throw ValidationException::withMessages(['conflict' => '処理中に代表者情報が更新されました。ページを更新して再度お試しください。']);
            }
            $company->owner_user_id = (int)$newOwner->id;
            $company->save();

            // 旧Owner：譲渡後ロールへ（owner判定はcompanyのSoTなので role=ownerのままでもよいが、表示整合のため更新）
            $oldOwner->role = $oldNewRole;
            $oldOwner->group_id = $oldNewGroupId; // registrar は null, それ以外は必須
            $oldOwner->save();

            // 新Owner：role=owner、group_id は強制NULL（横断）
            $newOwner->role = 'owner';
            $newOwner->group_id = null;
            $newOwner->save();

            if (class_exists(AuditLogger::class)) {
                AuditLogger::log('owner.transferred', [
                    'company_id' => (int)$company->id,
                    'from_owner_user_id' => (int)$oldOwner->id,
                    'to_owner_user_id' => (int)$newOwner->id,
                    'old_owner_new_role' => $oldNewRole,
                    'old_owner_new_group_id' => $oldNewGroupId,
                ], $company);
            }
        });

        // 譲渡後は旧Ownerが403になり得るため、設定TOPへ遷移させる
        return redirect()->route('admin.settings')->with('success', '代表者権限を譲渡しました。');
    }
}