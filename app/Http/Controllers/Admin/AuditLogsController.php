<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Guest;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AuditLogsController extends Controller
{
    private function isOwnerOrRegistrar(User $u): bool
    {
        $role = strtolower((string)($u->role ?? ''));
        if (method_exists($u, 'isOwner') && $u->isOwner()) return true;
        return in_array($role, ['owner','registrar'], true);
    }

    public function index(Request $request): View
    {
        $me = auth()->user();
        if (!$me || !$this->isOwnerOrRegistrar($me)) {
            abort(403);
        }

        $q = AuditLog::query()
            ->where('company_id', (int)$me->company_id)
            ->orderByDesc('id');

        // 操作種別（action）
        $action = (string)$request->string('action', '');
        if ($action !== '') {
            $q->where('action', $action);
        }

        // 操作ユーザー（actor_user_id）
        $actorId = $request->integer('actor_user_id');
        if (!is_null($actorId)) {
            $q->where('actor_user_id', (int)$actorId);
        }

        // 期間（開始日/終了日）
        $dateFrom = (string)$request->string('date_from', '');
        if ($dateFrom !== '') {
            $q->whereDate('created_at', '>=', $dateFrom);
        }
        $dateTo = (string)$request->string('date_to', '');
        if ($dateTo !== '') {
            $q->whereDate('created_at', '<=', $dateTo);
        }

        // 対象：顧客（guest_id） ※ meta JSON の guest_id / to_guest_id / from_guest_id のどれかで一致
        $guestId = $request->integer('guest_id');
        if (!is_null($guestId)) {
            $gid = (int)$guestId;
            $q->where(function($qq) use ($gid){
                $qq->whereRaw("JSON_EXTRACT(meta, '$.guest_id') = ?", [$gid])
                   ->orWhereRaw("JSON_EXTRACT(meta, '$.to_guest_id') = ?", [$gid])
                   ->orWhereRaw("JSON_EXTRACT(meta, '$.from_guest_id') = ?", [$gid]);
            });
        }

        // 対象：年度（year）
        $year = $request->integer('year');
        if (!is_null($year)) {
            $yy = (int)$year;
            $q->where(function($qq) use ($yy){
                $qq->whereRaw("JSON_EXTRACT(meta, '$.kihu_year') = ?", [$yy])
                   ->orWhereRaw("JSON_EXTRACT(meta, '$.to_year') = ?", [$yy])
                   ->orWhereRaw("JSON_EXTRACT(meta, '$.from_year') = ?", [$yy]);
            });
        }

        $logs = $q->paginate(50)->withQueryString();

        // actor名表示用（軽量）
        $actorIds = $logs->getCollection()->pluck('actor_user_id')->filter()->unique()->values()->all();
        $actorMap = User::query()
            ->select('id','name','email')
            ->whereIn('id', $actorIds)
            ->get()
            ->keyBy('id');

        // guest名表示用（meta.guest_id などから抽出）
        $guestIds = [];
        foreach ($logs->getCollection() as $log) {
            $meta = is_array($log->meta) ? $log->meta : [];
            foreach (['guest_id','from_guest_id','to_guest_id'] as $k) {
                if (isset($meta[$k]) && is_numeric($meta[$k])) {
                    $guestIds[] = (int)$meta[$k];
                }
            }
        }
        $guestIds = array_values(array_unique(array_filter($guestIds, fn($v)=>$v>0)));
        $guestMap = Guest::query()
            ->select('id','name')
            ->whereIn('id', $guestIds)
            ->get()
            ->keyBy('id');

        // フィルタ用：会社内ユーザー一覧（Owner/Registrar が見る前提）
        $usersForFilter = User::query()
            ->select('id','name','email')
            ->where('company_id', (int)$me->company_id)
            ->orderBy('name')
            ->get();

        // フィルタ用：顧客一覧
        $guestsForFilter = Guest::query()
            ->select('id','name')
            ->where('company_id', (int)$me->company_id)
            ->orderBy('name')
            ->get();

        // フィルタ用：年度（固定レンジ）
        $yearsForFilter = [];
        for ($y = 2035; $y >= 2025; $y--) $yearsForFilter[] = $y;

        // 操作種別プルダウン（日本語）
        $actionOptions = [
            '' => '（すべて）',
            'data.created' => '新規作成',
            'data.copied' => 'コピー作成',
            'data.updated' => '情報更新',
            'data.year_moved' => '年度変更',
            'data.overwritten' => '年度変更（上書き）',
            'data.deleted' => '削除',
            'data.year_select.existing' => '年度選択（既存へ移動）',
        ];

        return view('admin.audit_logs.index', compact(
            'logs', 'actorMap', 'guestMap',
            'usersForFilter', 'guestsForFilter', 'yearsForFilter', 'actionOptions'
        ));
    }

    public function show(Request $request, int $id): View
    {
        $me = auth()->user();
        if (!$me || !$this->isOwnerOrRegistrar($me)) {
            abort(403);
        }

        $log = AuditLog::query()
            ->where('company_id', (int)$me->company_id)
            ->findOrFail($id);

        $actor = null;
        if ($log->actor_user_id) {
            $actor = User::query()->select('id','name','email')->find($log->actor_user_id);
        }

        $meta = is_array($log->meta) ? $log->meta : [];
        $guest = null;
        if (isset($meta['guest_id']) && is_numeric($meta['guest_id'])) {
            $guest = Guest::query()->select('id','name')->find((int)$meta['guest_id']);
        } elseif (isset($meta['to_guest_id']) && is_numeric($meta['to_guest_id'])) {
            $guest = Guest::query()->select('id','name')->find((int)$meta['to_guest_id']);
        } elseif (isset($meta['from_guest_id']) && is_numeric($meta['from_guest_id'])) {
            $guest = Guest::query()->select('id','name')->find((int)$meta['from_guest_id']);
        }

        return view('admin.audit_logs.show', compact('log', 'actor', 'guest'));
    }
}
