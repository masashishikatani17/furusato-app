<?php

namespace App\Services\Admin;

use App\Models\Group;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class GroupTransferService
{
    /**
     * 異動＋（停止/削除）を1トランザクションで実行
     *
     * @param  User   $actor   実行者（owner/registrar）
     * @param  Group  $from    対象部署（同一company、deleted_at null 前提）
     * @param  int    $toGroupId 移動先部署ID（同一company、is_active=1、deleted_at null）
     * @param  string $action  'deactivate'|'destroy'
     */
    public function transferAndAction(User $actor, Group $from, int $toGroupId, string $action): void
    {
        $action = strtolower((string)$action);
        if (!in_array($action, ['deactivate', 'destroy'], true)) {
            throw ValidationException::withMessages(['action' => '処理種別が不正です。']);
        }

        $companyId = (int)($actor->company_id ?? 0);
        if ($companyId <= 0) {
            throw ValidationException::withMessages(['company' => '会社情報が不正です。']);
        }

        if ((int)$from->company_id !== $companyId) {
            throw ValidationException::withMessages(['group' => '部署の会社スコープが不正です。']);
        }

        if ((int)$from->id === (int)$toGroupId) {
            throw ValidationException::withMessages(['to_group_id' => '移動先部署が不正です。']);
        }

        $to = Group::query()
            ->where('company_id', $companyId)
            ->whereNull('deleted_at')
            ->where('is_active', 1)
            ->find($toGroupId);

        if (!$to) {
            throw ValidationException::withMessages(['to_group_id' => '移動先部署が不正です。']);
        }

        // owner/registrar を部署異動対象に含めない（companies.owner_user_id + role=registrar/owner）
        $ownerUserId = (int)optional($actor->company)->owner_user_id;
        $excludeOwnerId = $ownerUserId > 0 ? $ownerUserId : null;
        $excludeRoles = ['owner', 'registrar'];
        $excludeRoles2 = ['groupadmin', 'group-admin']; // 参考（除外ではなく、対象ロール補正に使う）

        // 異動対象ロール（users）
        $targetRoles = ['group_admin', 'member', 'client', 'groupadmin', 'group-admin'];

        DB::transaction(function () use ($companyId, $from, $to, $action, $excludeOwnerId, $excludeRoles, $targetRoles) {
            $fromId = (int)$from->id;
            $toId   = (int)$to->id;

            // 1) users 異動（owner/registrar除外）
            $u = DB::table('users')
                ->where('company_id', $companyId)
                ->where('group_id', $fromId)
                ->where(function ($q) use ($targetRoles) {
                    $q->whereIn('role', $targetRoles);
                })
                ->where(function ($q) use ($excludeOwnerId, $excludeRoles) {
                    // role=owner/registrar を除外
                    $q->whereNull('role')->orWhereNotIn('role', $excludeRoles);
                });
            if ($excludeOwnerId !== null) {
                $u->where('id', '!=', $excludeOwnerId);
            }
            $u->update(['group_id' => $toId]);

            // 2) guests 異動
            $guestIds = DB::table('guests')
                ->where('company_id', $companyId)
                ->where('group_id', $fromId)
                ->pluck('id')
                ->all();

            DB::table('guests')
                ->where('company_id', $companyId)
                ->where('group_id', $fromId)
                ->update(['group_id' => $toId]);

            // 3) datas 異動（guest追随 + 保険でgroup_id=fromも寄せる）
            if (!empty($guestIds)) {
                DB::table('datas')
                    ->where('company_id', $companyId)
                    ->whereIn('guest_id', $guestIds)
                    ->update(['group_id' => $toId]);
            }
            DB::table('datas')
                ->where('company_id', $companyId)
                ->where('group_id', $fromId)
                ->update(['group_id' => $toId]);

            // 4) invitations（未完了のみ）異動
            DB::table('invitations')
                ->where('company_id', $companyId)
                ->where('group_id', $fromId)
                ->whereNull('accepted_at')
                ->whereNull('cancelled_at')
                ->whereNull('revoked_at')
                ->whereNull('expired_at')
                ->whereNull('deleted_at')
                ->update(['group_id' => $toId]);

            // 5) group の状態変更
            if ($action === 'deactivate') {
                DB::table('groups')
                    ->where('company_id', $companyId)
                    ->where('id', $fromId)
                    ->whereNull('deleted_at')
                    ->update(['is_active' => 0, 'updated_at' => now()]);
            } else {
                // SoftDelete
                DB::table('groups')
                    ->where('company_id', $companyId)
                    ->where('id', $fromId)
                    ->whereNull('deleted_at')
                    ->update(['deleted_at' => now(), 'updated_at' => now()]);
            }

            // 6) 残存チェック（競合対策）
            $remainUsers = DB::table('users')
                ->where('company_id', $companyId)
                ->where('group_id', $fromId)
                ->whereIn('role', ['group_admin', 'member', 'client', 'groupadmin', 'group-admin'])
                ->count();
            $remainGuests = DB::table('guests')
                ->where('company_id', $companyId)
                ->where('group_id', $fromId)
                ->count();
            $remainDatas = DB::table('datas')
                ->where('company_id', $companyId)
                ->where('group_id', $fromId)
                ->count();
            $remainInv = DB::table('invitations')
                ->where('company_id', $companyId)
                ->where('group_id', $fromId)
                ->whereNull('accepted_at')
                ->whereNull('cancelled_at')
                ->whereNull('revoked_at')
                ->whereNull('expired_at')
                ->whereNull('deleted_at')
                ->count();

            if ($remainUsers + $remainGuests + $remainDatas + $remainInv > 0) {
                throw ValidationException::withMessages([
                    'conflict' => '処理中に所属状況が更新されました。ページを更新して再度お試しください。',
                ]);
            }
        });
    }
}
