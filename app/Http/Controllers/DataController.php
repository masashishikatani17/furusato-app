<?php

namespace App\Http\Controllers;

use App\Models\Data;
use App\Models\Guest;
use App\Models\User;
use DateTimeInterface;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use App\Support\AuditLogger;
use Illuminate\Validation\ValidationException;

class DataController extends Controller
{
    /** Owner / Registrar 判定（簡易版） */
    private function isOwnerOrRegistrar(): bool
    {
        $u = auth()->user();
        $role = strtolower((string)($u->role ?? ''));
        // 既存 isOwner() 実装があれば尊重
        if (method_exists($u, 'isOwner') && $u->isOwner()) return true;
        return in_array($role, ['owner', 'registrar'], true);
    }

    /** 部署IDの決定（furusato最小：Owner/Registrar=横断→null、その他=自分のgroup_id） */
    private function ctxGroupIdOrNull(): ?int
    {
        $u = auth()->user();
        if (!$u) {
            return null;
        }
        if ($this->isOwnerOrRegistrar()) {
            return null; // 横断
        }
        $gid = $u->group_id ?? null;
        return ($gid === null) ? null : (int)$gid;
    }

    /** Dataの閲覧/編集スコープ（簡易） */
    private function assertCanAccessData(Data $data): void
    {
        $me = auth()->user();
        if (!$me) abort(403);
        if ((int)$data->company_id !== (int)$me->company_id) abort(403);
        if (!$this->isOwnerOrRegistrar() && (int)$data->group_id !== (int)($me->group_id ?? 0)) abort(403);
    }

    /** 削除可否：shared=部署内OK / private=作成者のみ */
    private function canDeleteData(Data $data): bool
    {
        $me = auth()->user();
        if (!$me) return false;
        if ((int)$data->company_id !== (int)$me->company_id) return false;
        if (!$this->isOwnerOrRegistrar() && (int)$data->group_id !== (int)($me->group_id ?? 0)) return false;

        $vis = (string)($data->visibility ?? 'shared');
        if ($vis !== 'private') {
            // shared（または未設定）は部署内なら削除OK
            return true;
        }
        // private は作成者のみ（owner_user_id優先、なければuser_id）
        $creatorId = (int)($data->owner_user_id ?? 0) ?: (int)($data->user_id ?? 0);
        return (int)$me->id === $creatorId;
    }

    /**
     * 画面本体：/data
     * - 左ペイン：お客様一覧（会社＋必要なら部署でスコープ）
     * - 右ペイン：初期ゲストの年度リスト（id / kihu_year）
     */
    public function index(Request $request)
    {
        $user       = auth()->user();
        $companyId  = (int)($user?->company_id);
        $ctxGroupId = $this->ctxGroupIdOrNull(); // null=横断

        // お客様一覧
        $guestQuery = Guest::query()->where('company_id', $companyId);
        if ($ctxGroupId !== null) {
            $guestQuery->where('group_id', $ctxGroupId);
        }
        $guests = $guestQuery->orderBy('created_at')->get();

        // 初期選択 guest_id
        $guestId = $request->integer('guest_id') ?: session('selected_guest_id');
        $guest   = $guestId ? $guests->firstWhere('id', (int)$guestId) : null;
        if (!$guest) {
            $guest = $guests->first();
            $guestId = $guest?->id;
        }
        if ($guest) {
            session(['selected_guest_id' => $guest->id]);
        }

        // 右ペイン初期データ（id / kihu_year のみ）
        $datas = collect();
        if ($guest) {
            $datas = Data::query()
                ->select('id', 'guest_id', 'kihu_year', 'owner_user_id', 'user_id', 'visibility')
                ->where('guest_id', $guest->id)
                ->whereNotNull('kihu_year')
                ->orderByDesc('kihu_year')->orderByDesc('id')
                ->get();
        }

        return view('data.data_master', [
            'guests'     => $guests,
            'datas'      => $datas,
            'guestId'    => $guestId,
            'companyId'  => $companyId,
            'ctxGroupId' => $ctxGroupId, // null=横断
        ]);
    }

    /** 右ペイン用データ取得：GET /api/guest/{guest}/datas  */
    public function datasJson(Guest $guest): JsonResponse
    {
        $me = auth()->user();
        // 会社一致＆必要なら部署一致（簡易認可）
        if ((int)$guest->company_id !== (int)$me->company_id) {
            abort(403);
        }
        if (!$this->isOwnerOrRegistrar() && (int)$guest->group_id !== (int)$me->group_id) {
            abort(403);
        }

        $list = Data::query()
            ->select('id', 'guest_id', 'kihu_year', 'owner_user_id', 'user_id', 'visibility')
            ->where('guest_id', $guest->id)
            ->whereNotNull('kihu_year')
            ->orderByDesc('kihu_year')->orderByDesc('id')
            ->get();

        // 選択保持
        session(['selected_guest_id' => $guest->id]);
        return response()->json($list, 200);
    }

    /**
     * Data作成日/提案日の初期値（パターンB）
     * - data_created_on: 実際に作成した日（当日）
     * - proposal_date  : 初期は作成日と同日（当日）、後でユーザーが変更できる想定
     */
    private function defaultCreatedAndProposalDates(): array
    {
        $today = now()->toDateString();
        return [$today, $today];
    }

    /**
     * 年度変更（複製＋年度置換）：POST /api/data/{data}/clone-year
     * Request: { kihu_year:int(2010..2100) }
     * Response: { id, guest_id }
     */
    public function cloneWithYear(Request $request, Data $data): JsonResponse
    {
        $me = auth()->user();
        // 簡易認可
        if ((int)$data->company_id !== (int)$me->company_id) abort(403);
        if (!$this->isOwnerOrRegistrar() && (int)$data->group_id !== (int)$me->group_id) abort(403);

        $validated = $request->validate([
            'kihu_year' => ['required','integer','between:2025,2035'],
        ],[
            'kihu_year.required' => '年度を選択してください。',
            'kihu_year.between'  => '年度の指定が不正です（2025〜2035）。',
        ]);
        $year = (int)$validated['kihu_year'];

        // 同一ゲスト×同一年が既にある場合は「既存へ遷移」させたいのでIDを返す
        $existing = Data::query()
            ->where('guest_id', $data->guest_id)
            ->where('kihu_year', $year)
            ->first();
        if ($existing) {
            AuditLogger::log('data.year_select.existing', [
                'guest_id' => (int)$data->guest_id,
                'from_data_id' => (int)$data->id,
                'to_data_id' => (int)$existing->id,
                'to_year' => (int)$year,
            ], $existing);
            return response()->json([
                'id'       => $existing->id,
                'guest_id' => $existing->guest_id,
                'action'   => 'existing',
            ], 200);
        }

        // 入力だけコピーして、結果は再計算で生成する
        $new = DB::transaction(function () use ($data, $year) {
            // 1) Data本体を複製（datasテーブルの1行）
            $cloned = $data->replicate();
            $cloned->exists = false;
            $cloned->save(); // ← まずIDを確定させる

            $cloned->kihu_year = $year;
            $cloned->owner_user_id = Auth::id();

            // 複製で作られる新データの「データ作成日」は複製した日（当日）
            [$createdOn, $proposalDate] = $this->defaultCreatedAndProposalDates();
            if (property_exists($cloned, 'data_created_on') || array_key_exists('data_created_on', $cloned->getAttributes())) {
                $cloned->data_created_on = $createdOn;
            } else {
                // Eloquentの属性が未ロードでも setAttribute は効くので保険
                $cloned->setAttribute('data_created_on', $createdOn);
            }
            $cloned->setAttribute('proposal_date', $proposalDate);

            $cloned->save();

            // 2) 入力テーブルを旧data_id→新data_idへコピー
            $this->copyTableByDataId('furusato_inputs', (int)$data->id, (int)$cloned->id);
            $this->copyTableByDataId('furusato_syori_settings', (int)$data->id, (int)$cloned->id);

            // 3) 結果はコピーしない（保険で削除）
            DB::table('furusato_results')->where('data_id', (int)$cloned->id)->delete();

            return $cloned;
        });

        return response()->json([
            'id'       => $new->id,
            'guest_id' => $new->guest_id,
            'action'   => 'cloned',
        ], 201);
    }

    /**
     * data_id を持つ「入力テーブル」を、旧data_id→新data_idへコピーする（結果系は対象外）
     * - テーブル列は Schema から自動取得（ハードコードしない）
     * - id / data_id / timestamps はコピーしない（新規行として作る）
     */
    private function copyTableByDataId(string $table, int $fromDataId, int $toDataId): void
    {
        if (!Schema::hasTable($table)) {
            return;
        }
        if (!Schema::hasColumn($table, 'data_id')) {
            return;
        }

        // 既存があれば削除（基本は新規data_idなので0件だが、冪等性のため）
        DB::table($table)->where('data_id', $toDataId)->delete();

        $columns = Schema::getColumnListing($table);
        if (empty($columns)) {
            return;
        }

        // コピー対象列（id/data_id/timestamps等は除外）
        $exclude = ['id', 'data_id', 'created_at', 'updated_at', 'deleted_at'];
        $copyCols = array_values(array_filter($columns, fn($c) => !in_array($c, $exclude, true)));

        // 元データ取得
        $rows = DB::table($table)->where('data_id', $fromDataId)->get();
        if ($rows->isEmpty()) {
            return;
        }

        $now = now();
        foreach ($rows as $r) {
            $ins = ['data_id' => $toDataId];

            foreach ($copyCols as $c) {
                // stdClass → 配列化
                $ins[$c] = $r->{$c} ?? null;
            }

            // timestamps があるなら埋める
            if (in_array('created_at', $columns, true)) $ins['created_at'] = $now;
            if (in_array('updated_at', $columns, true)) $ins['updated_at'] = $now;

            DB::table($table)->insert($ins);
        }
    }

    /** 編集画面：GET /data/{data}/edit */
    public function edit(Request $request, Data $data)
    {
        $this->assertCanAccessData($data);

        $data->loadMissing('guest');

        $minY = 2025;
        $maxY = 2035;
        $years = [];
        for ($y = $maxY; $y >= $minY; $y--) $years[] = $y;

        $canDelete = $this->canDeleteData($data);

        return view('data.data_edit', [
            'data' => $data,
            'guest' => $data->guest,
            'years' => $years,
            'canDelete' => $canDelete,
        ]);
    }

    /**
     * 更新：PUT /data/{data}
     * - proposal_date 必須
     * - visibility 更新
     * - 年度変更：存在しなければ移動 / 存在すれば上書き確認→承諾でA→B上書き＆A削除（Bを残す）
     */
    public function update(Request $request, Data $data)
    {
        $this->assertCanAccessData($data);
        $data->loadMissing('guest');

        $rules = [
            'proposal_date' => ['required','date_format:Y-m-d'],
            'kihu_year'     => ['required','integer','between:2025,2035'],
        ];
        if (config('feature.data_privacy')) {
            $rules['visibility'] = ['required','in:shared,private'];
        }
        $messages = [
            'proposal_date.required' => '提案書日を入力してください。',
            'proposal_date.date_format' => '提案書日は YYYY-MM-DD 形式で入力してください。',
            'kihu_year.required' => '年度を選択してください。',
            'kihu_year.between'  => '年度の指定が不正です（2025〜2035）。',
            'visibility.required' => '共有設定を選択してください。',
            'visibility.in' => '共有設定が不正です。',
        ];
        $validated = $request->validate($rules, $messages);

        $newYear = (int)$validated['kihu_year'];
        $oldYear = (int)($data->kihu_year ?? 0);
        $newVis  = config('feature.data_privacy') ? (string)$validated['visibility'] : (string)($data->visibility ?? 'shared');
        $newProposal = (string)$validated['proposal_date'];

        $confirmOverwrite = (int)$request->input('confirm_overwrite', 0) === 1;

        // 年度変更なし：メタ更新のみ
        if ($newYear === $oldYear) {
            $data->proposal_date = $newProposal;
            if (config('feature.data_privacy')) {
                $data->visibility = $newVis;
            }
            $data->save();

            AuditLogger::log('data.updated', [
                'guest_id' => (int)$data->guest_id,
                'data_id' => (int)$data->id,
                'kihu_year' => (int)$data->kihu_year,
                'visibility' => (string)($data->visibility ?? 'shared'),
                'proposal_date' => (string)($data->proposal_date ?? ''),
            ], $data);

            return redirect()
                ->route('data.index', ['guest_id' => $data->guest_id])
                ->with('success', 'データ情報を更新しました。');
        }

        // 年度変更あり：同一guest内のターゲットを探す
        $target = Data::query()
            ->where('guest_id', (int)$data->guest_id)
            ->where('kihu_year', $newYear)
            ->first();

        // 変更先が存在しない → 移動（data_idはそのまま、結果は削除）
        if (!$target) {
            DB::transaction(function () use ($data, $newYear, $newProposal, $newVis) {
                $data->kihu_year = $newYear;
                $data->proposal_date = $newProposal;
                if (config('feature.data_privacy')) {
                    $data->visibility = $newVis;
                }
                $data->save();

                // 年度が変わるので結果は破棄（未計算へ）
                DB::table('furusato_results')->where('data_id', (int)$data->id)->delete();
            });

            AuditLogger::log('data.year_moved', [
                'guest_id' => (int)$data->guest_id,
                'data_id' => (int)$data->id,
                'from_year' => $oldYear,
                'to_year' => $newYear,
                'visibility' => (string)($data->visibility ?? 'shared'),
                'proposal_date' => (string)($data->proposal_date ?? ''),
                'note' => 'results cleared; inputs/syori kept (same data_id)',
            ], $data);

            return redirect()
                ->to('/furusato/syori?data_id='.$data->id)
                ->with('success', '年度を変更しました（計算結果は再計算されます）。');
        }

        // 変更先が存在する → 上書き確認
        if (!$confirmOverwrite) {
            return back()
                ->withInput()
                ->with('overwrite_conflict', [
                    'from_data_id' => (int)$data->id,
                    'from_year' => $oldYear,
                    'to_data_id' => (int)$target->id,
                    'to_year' => $newYear,
                    'guest_id' => (int)$data->guest_id,
                ]);
        }

        // 上書き実行：B（target）を残してA（data）を削除
        DB::transaction(function () use ($data, $target, $newProposal, $newVis) {
            // 1) Bの入力/設定をAで全置換
            $this->copyTableByDataId('furusato_inputs', (int)$data->id, (int)$target->id);
            $this->copyTableByDataId('furusato_syori_settings', (int)$data->id, (int)$target->id);

            // 2) Bの結果は破棄（未計算へ）
            DB::table('furusato_results')->where('data_id', (int)$target->id)->delete();

            // 3) BのメタもAで上書き（ユーザー承諾済み）
            $target->proposal_date = $newProposal;
            if (config('feature.data_privacy')) {
                $target->visibility = $newVis;
            }
            // data_created_on もAの値で上書き（編集不可項目だが、A→Bの完全上書き要件）
            if (!empty($data->data_created_on)) {
                $target->data_created_on = (string)$data->data_created_on;
            }
            // private削除権限の基準（作成者）はA側に寄せるのが自然なので引き継ぐ
            if (Schema::hasColumn($target->getTable(), 'owner_user_id')) {
                $target->owner_user_id = $data->owner_user_id ?? $data->user_id;
            }
            $target->user_id = $data->user_id;
            $target->save();

            // 4) A配下を削除してA本体を削除（Bを残す）
            DB::table('furusato_inputs')->where('data_id', (int)$data->id)->delete();
            DB::table('furusato_syori_settings')->where('data_id', (int)$data->id)->delete();
            DB::table('furusato_results')->where('data_id', (int)$data->id)->delete();
            $data->delete();
        });

        AuditLogger::log('data.overwritten', [
            'guest_id' => (int)$target->guest_id,
            'from_data_id' => (int)$request->input('source_data_id', (int)$data->id),
            'to_data_id' => (int)$target->id,
            'from_year' => $oldYear,
            'to_year' => $newYear,
            'overwrite' => [
                'inputs' => true,
                'syori_settings' => true,
                'results_cleared' => true,
                'meta_overwritten' => ['proposal_date','visibility','data_created_on','user_id','owner_user_id'],
            ],
        ], $target);

        return redirect()
            ->to('/furusato/syori?data_id='.$target->id)
            ->with('success', '年度データを上書きしました（計算結果は再計算されます）。');
    }

    /** 削除（Web）: DELETE /data/{data} */
    public function destroy(Request $request, Data $data)
    {
        $this->assertCanAccessData($data);
        if (!$this->canDeleteData($data)) {
            abort(403);
        }

        $guestId = (int)$data->guest_id;
        $year = (int)($data->kihu_year ?? 0);
        $vis = (string)($data->visibility ?? 'shared');

        DB::transaction(function () use ($data) {
            DB::table('furusato_inputs')->where('data_id', (int)$data->id)->delete();
            DB::table('furusato_syori_settings')->where('data_id', (int)$data->id)->delete();
            DB::table('furusato_results')->where('data_id', (int)$data->id)->delete();
            $data->delete();
        });

        AuditLogger::log('data.deleted', [
            'guest_id' => $guestId,
            'data_id' => (int)$data->id,
            'kihu_year' => $year,
            'visibility' => $vis,
        ], $data);

        return redirect()
            ->route('data.index', ['guest_id' => $guestId])
            ->with('success', "{$year}年のデータを削除しました。");
    }
    /** 担当者プルダウン：GET /api/group/{group}/users  */
    public function groupUsersJson(Request $request, int $group): JsonResponse
    {
        $me = auth()->user();
        $companyId = (int)$me->company_id;

        // Owner/Registrar 以外は自部署のみ
        if (!$this->isOwnerOrRegistrar() && (int)$group !== (int)$me->group_id) {
            abort(403);
        }

        $users = User::query()
            ->select('id','name')
            ->where('company_id', $companyId)
            ->where('group_id', (int)$group)
            ->where(function($q){ $q->whereNull('is_active')->orWhere('is_active',1); })
            ->orderBy('name')
            ->get();

        return response()->json($users, 200);
    }

    /** お客様削除：DELETE /api/guest/{guest}（子Dataも削除） */
    public function destroyGuest(Guest $guest): JsonResponse
    {
        $me = auth()->user();
        if ((int)$guest->company_id !== (int)$me->company_id) abort(403);
        if (!$this->isOwnerOrRegistrar() && (int)$guest->group_id !== (int)$me->group_id) abort(403);

        DB::transaction(function () use ($guest) {
            $guest->datas()->delete();
            $guest->delete();
        });
        return response()->json(['message'=>'deleted'], 200);
    }

    /** データ削除：DELETE /api/data/{data}（残0件なら親Guestも削除） */
    public function destroyData(Data $data): JsonResponse
    {
        $me = auth()->user();
        if ((int)$data->company_id !== (int)$me->company_id) abort(403);
        if (!$this->isOwnerOrRegistrar() && (int)$data->group_id !== (int)$me->group_id) abort(403);

        // visibility=private は作成者のみ削除OK（owner_user_id優先、無ければuser_id）
        $vis = (string)($data->visibility ?? 'shared');
        if ($vis === 'private') {
            $creatorId = (int)($data->owner_user_id ?? 0) ?: (int)($data->user_id ?? 0);
            if ((int)$me->id !== $creatorId) {
                abort(403);
            }
        }

        DB::transaction(function () use ($data) {
            $guest = $data->guest;
            // 入力・設定・結果も削除（data_id配下を掃除）
            DB::table('furusato_inputs')->where('data_id', (int)$data->id)->delete();
            DB::table('furusato_syori_settings')->where('data_id', (int)$data->id)->delete();
            DB::table('furusato_results')->where('data_id', (int)$data->id)->delete();
            $data->delete();
            if ($guest && $guest->datas()->count() === 0) {
                $guest->delete();
            }
        });

        AuditLogger::log('data.deleted', [
            'guest_id' => (int)$data->guest_id,
            'data_id' => (int)$data->id,
            'kihu_year' => (int)($data->kihu_year ?? 0),
            'visibility' => (string)($data->visibility ?? 'shared'),
        ], $data);

        return response()->json(['message'=>'deleted'], 200);
    }

    /* ===== 新規作成：/data/create（画面） ===== */
    public function create()
    {
        $me = auth()->user();
        $companyId = (int)($me?->company_id);
        $ctxGroupId = $this->ctxGroupIdOrNull(); // Owner/Registrar=null(横断), 他ロールは自部署

        $q = Guest::query()
            ->select('id','name','company_id','group_id','user_id','birth_date')
            ->where('company_id', $companyId);
        if ($ctxGroupId !== null) {
            $q->where('group_id', (int)$ctxGroupId);
        }
        $guests = $q->orderBy('created_at','asc')->get();
        $defaultBirthDate = null;

        return view('data.data_create', compact('guests', 'defaultBirthDate'));
    }

    /* ===== 新規作成：POST /data（保存） ===== */
    public function store(Request $request)
    {
        // 1) バリデーション（基本）
        $rules = [
            'guest_mode' => ['required','in:new,existing'],
            'guest_id'   => ['required_if:guest_mode,existing','nullable','integer','exists:guests,id'],
            'guest_name' => ['required_if:guest_mode,new','nullable','string','max:25'],
            'kihu_year'  => ['required','integer','between:2025,2035'],
            'birth_date' => ['nullable','date_format:Y-m-d'],
            'proposal_date' => ['nullable','date_format:Y-m-d'],
        ];
        if (config('feature.data_privacy')) {
            $rules['visibility'] = ['nullable','in:shared,private'];
        }
        $messages = [
            'guest_mode.required' => 'お客様の指定を選択してください。',
            'guest_mode.in'       => 'お客様の指定が不正です。',
            'guest_id.required_if'=> '登録済から選択する場合は、お客様を選択してください。',
            'guest_id.exists'     => '選択したお客様が見つかりません。',
            'guest_name.required_if' => 'お客様名を入力してください。',
            'guest_name.max'         => 'お客様名は25文字以内で入力してください。',
            'kihu_year.required'  => '年度を選択してください。',
            'kihu_year.between'   => '年度の指定が不正です（2025〜2035）。',
            'visibility.in'       => '共有設定が不正です。',
            'birth_date.date_format' => '生年月日は YYYY-MM-DD 形式で入力してください。',
            'proposal_date.date_format' => '提案書日は YYYY-MM-DD 形式で入力してください。',
        ];
        $validated = $request->validate($rules, $messages);

        $me = auth()->user();
        $companyId = (int)$me->company_id;
        $groupIdOfUser = (int)($me->group_id ?? 0);

        // 2) お客様の決定（スコープ整合）
        $birthDate = $validated['birth_date'] ?? null;

        if ($validated['guest_mode'] === 'new') {
            // 作成者の company/group を継承
            $guest = new Guest();
            $guest->name       = (string)$validated['guest_name'];
            $guest->company_id = $companyId;
            $guest->group_id   = $this->isOwnerOrRegistrar() ? $groupIdOfUser : $groupIdOfUser;
            $guest->user_id    = $me->id;
            $guest->save();
        } else {
            $guest = Guest::findOrFail((int)$validated['guest_id']);
            // 会社一致
            if ((int)$guest->company_id !== $companyId) abort(403);
            // 所属チェック（Owner/Registrar は横断可、他ロールは自部署のみ）
            if (!$this->isOwnerOrRegistrar() && (int)$guest->group_id !== $groupIdOfUser) abort(403);
        }

        $this->updateGuestBirthDate($guest, $birthDate);

        // 3) 同一年度ユニークのサーバ検証（422は返さず、302戻り＋モーダル）
        $exists = Data::query()
            ->where('guest_id', $guest->id)
            ->where('kihu_year', (int)$validated['kihu_year'])
            ->exists();
        if ($exists) {
            return back()
                ->withInput()
                ->with('modal_error', [
                    'duplicate_year' => true,
                    'message' => '同じお客様について 同一の年度 のデータは登録できません。',
                ]);
        }

        // 4) Data 作成（親から company/group を強制継承）
        $data = new Data();
        $data->guest_id   = $guest->id;
        $data->company_id = (int)$guest->company_id;
        $data->group_id   = (int)$guest->group_id;
        $data->user_id    = $me->id;
        $data->kihu_year  = (int)$validated['kihu_year'];

        // データ作成日（当日・編集不可）／提案日（初期＝当日・後で編集可）
        [$createdOn, $proposalDate] = $this->defaultCreatedAndProposalDates();
        $data->setAttribute('data_created_on', $createdOn);
        $proposalIn = $validated['proposal_date'] ?? null;
        $data->setAttribute('proposal_date', $proposalIn ?: $proposalDate);

        if (config('feature.data_privacy')) {
            $vis = strtolower((string)($request->input('visibility','shared')));
            $data->visibility    = in_array($vis, ['shared','private'], true) ? $vis : 'shared';
            $data->owner_user_id = $me->id;
        }
        $data->save();

        AuditLogger::log('data.created', [
            'guest_id' => (int)$guest->id,
            'data_id' => (int)$data->id,
            'kihu_year' => (int)$data->kihu_year,
            'visibility' => (string)($data->visibility ?? 'shared'),
            'proposal_date' => (string)($data->proposal_date ?? ''),
            'data_created_on' => (string)($data->data_created_on ?? ''),
        ], $data);

        return redirect()
            ->route('data.index', ['guest_id' => $guest->id])
            ->with('success', '新規データを作成しました。');
    }

    /** コピー画面：GET /data/copyForm?data_id=◯◯ */
    public function copyForm(Request $request)
    {
        $dataId = (int)$request->integer('data_id');
        $source = Data::with('guest')->findOrFail($dataId);
        $me = auth()->user();
        // 認可（会社一致 + 所属）
        if ((int)$source->company_id !== (int)$me->company_id) abort(403);
        if (!$this->isOwnerOrRegistrar() && (int)$source->group_id !== (int)($me->group_id ?? 0)) abort(403);

        // コピー先候補（Owner/Registrar=横断、自ロール=自部署のみ）
        $q = Guest::query()
            ->select('id','name','company_id','group_id','user_id','birth_date')
            ->where('company_id', (int)$me->company_id);
        if (!$this->isOwnerOrRegistrar()) {
            $q->where('group_id', (int)($me->group_id ?? 0));
        }
        $guests = $q->orderBy('created_at','asc')->get();
        $defaultBirthDate = $this->formatBirthDateForView($source->guest?->birth_date ?? null);

        return view('data.data_copy', compact('source', 'guests', 'defaultBirthDate'));
    }

    /** コピー実行：POST /data/copy（複数年度対応） */
    public function copy(Request $request)
    {
        // 1) 入力検証
        $rules = [
            'selected_data_id' => ['required','integer','exists:datas,id'],
            'copy_mode'        => ['required','in:same,existing,new'],
            'target_guest_id'  => ['nullable','integer','required_if:copy_mode,existing','exists:guests,id'],
            'target_guest_name'=> ['nullable','string','max:25','required_if:copy_mode,new'],
            'years'            => ['required','array','min:1','max:21'],
            'years.*'          => ['integer','between:2025,2035'],
            'birth_date'       => ['nullable','date_format:Y-m-d'],
            'proposal_date'    => ['nullable','date_format:Y-m-d'],
        ];
        if (config('feature.data_privacy')) {
            $rules['visibility'] = ['nullable','in:shared,private'];
        }
        $messages = [
            'selected_data_id.required' => 'コピー元データが指定されていません。',
            'copy_mode.required'        => 'コピー先の指定を選択してください。',
            'target_guest_id.required_if'   => '登録済から選択する場合は、お客様を選択してください。',
            'target_guest_id.exists'        => '選択したお客様が見つかりません。',
            'target_guest_name.required_if' => '新規のお客様の場合は、お客様名を入力してください。',
            'target_guest_name.max'         => 'お客様名は25文字以内で入力してください。',
            'years.required' => '年度を1つ以上選択してください。',
            'years.array'    => '年度の指定が不正です。',
            'years.*.between'=> '年度の指定が不正です（2025〜2035）。',
            'birth_date.date_format' => '生年月日は YYYY-MM-DD 形式で入力してください。',
            'proposal_date.date_format' => '提案書日は YYYY-MM-DD 形式で入力してください。',
        ];
        $validated = $request->validate($rules, $messages);

        $me = auth()->user();
        $companyId = (int)$me->company_id;
        $userGroupId = (int)($me->group_id ?? 0);
        $birthDate = $validated['birth_date'] ?? null;
        $proposalIn = $validated['proposal_date'] ?? null;

        // 2) コピー元
        $source = Data::with('guest')->findOrFail((int)$validated['selected_data_id']);
        if ((int)$source->company_id !== $companyId) abort(403);
        if (!$this->isOwnerOrRegistrar() && (int)$source->group_id !== $userGroupId) abort(403);

        // 3) コピー先ゲスト決定（スコープ整合）
        $mode = (string)$validated['copy_mode'];
        if ($mode === 'same') {
            $targetGuest = $source->guest;
        } elseif ($mode === 'existing') {
            $targetGuest = Guest::findOrFail((int)$validated['target_guest_id']);
            if ((int)$targetGuest->company_id !== $companyId) abort(403);
            if (!$this->isOwnerOrRegistrar() && (int)$targetGuest->group_id !== $userGroupId) abort(403);
        } else { // new
            $targetGuest = new Guest();
            $targetGuest->name       = (string)$validated['target_guest_name'];
            $targetGuest->company_id = $companyId;
            $targetGuest->group_id   = $userGroupId;
            $targetGuest->user_id    = $me->id;
            $targetGuest->save();
        }

        if ($targetGuest) {
            $this->updateGuestBirthDate($targetGuest, $birthDate);
        }

        // 4) 年度ごとに複製
        $years = collect($validated['years'] ?? [])->map(fn($y)=>(int)$y)->unique()->values()->all();
        $created = [];
        $duplicated = [];

        foreach ($years as $yy) {
            $dup = Data::query()
                ->where('guest_id', $targetGuest->id)
                ->where('kihu_year', $yy)
                ->exists();
            if ($dup) {
                $duplicated[] = $yy;
                continue;
            }
            // deep copy → 保存
            $new = \DB::transaction(function () use ($source, $targetGuest, $yy, $me) {
                if (class_exists(\App\Services\Data\DeepCopyService::class)) {
                    /** @var \App\Services\Data\DeepCopyService $svc */
                    $svc = app(\App\Services\Data\DeepCopyService::class);
                    $cloned = $svc->deepCopy($source, $targetGuest->id);
                } else {
                    $cloned = $source->replicate();
                    $cloned->exists = false;
                    $cloned->save();
                }
                // 上書き
                $cloned->guest_id   = $targetGuest->id;
                $cloned->company_id = (int)$targetGuest->company_id;
                $cloned->group_id   = (int)$targetGuest->group_id;
                $cloned->user_id    = $me->id;
                $cloned->kihu_year  = (int)$yy;

                // コピーで作られる新データの「データ作成日」はコピー実行日（当日）
                $today = now()->toDateString();
                $cloned->setAttribute('data_created_on', $today);
                // 提案書日は入力があれば優先。無ければ当日。
                $cloned->setAttribute('proposal_date', request()->input('proposal_date') ?: $today);

                if (config('feature.data_privacy')) {
                    $vis = strtolower((string)request()->input('visibility','shared'));
                    $cloned->visibility    = in_array($vis, ['shared','private'], true) ? $vis : 'shared';
                    $cloned->owner_user_id = $me->id;
                }
                $cloned->save();
                return $cloned;
            });
            $created[] = $new->id;
        }

        if (count($created) > 0) {
            AuditLogger::log('data.copied', [
                'from_data_id' => (int)$source->id,
                'from_guest_id' => (int)$source->guest_id,
                'to_guest_id' => (int)$targetGuest->id,
                'years' => $years,
                'created_data_ids' => $created,
                'skipped_years' => $duplicated,
                'proposal_date' => (string)($proposalIn ?? ''),
            ], $source);
        }

        // 5) 結果の返却
        if (count($created) === 0 && count($duplicated) > 0) {
            // 全て重複 → copyForm に戻してモーダルで案内
            return back()
                ->withInput()
                ->with('modal_error', ['duplicate_years' => $duplicated]);
        }
        // 一部でも作成されたら一覧へ。重複があれば情報メッセージも付与
        $redir = redirect()->route('data.index', ['guest_id' => $targetGuest->id])
            ->with('success', 'コピーを作成しました。');
        if (count($duplicated) > 0) {
            $redir->with('info', '一部の年度は既に存在するためスキップしました（'.implode('年, ', $duplicated).'年）。');
        }
        return $redir;
    }

    public function updateBirthDate(Request $request): RedirectResponse
    {
        $user = $request->user();
        if (! $user) {
            abort(403);
        }

        $validated = $request->validate([
            'guest_id'   => ['required', 'integer', 'exists:guests,id'],
            'birth_date' => ['nullable', 'date_format:Y-m-d'],
        ], [
            'guest_id.required' => 'お客様を選択してください。',
            'guest_id.exists'   => '選択したお客様が見つかりません。',
            'birth_date.date_format' => '生年月日は YYYY-MM-DD 形式で入力してください。',
        ]);

        $guest = Guest::findOrFail((int) $validated['guest_id']);

        if ((int) $guest->company_id !== (int) $user->company_id) {
            abort(403);
        }

        if (! $this->isOwnerOrRegistrar() && (int) $guest->group_id !== (int) ($user->group_id ?? 0)) {
            abort(403);
        }

        $this->updateGuestBirthDate($guest, $validated['birth_date'] ?? null);

        return back()->with('birth_date_success', '生年月日を保存しました。');
    }

    private function updateGuestBirthDate(?Guest $guest, ?string $birthDate): void
    {
        if (! $guest) {
            return;
        }

        $normalized = $this->formatBirthDateForView($birthDate);
        $current = $this->formatBirthDateForView($guest->birth_date ?? null);

        if ($normalized === $current) {
            return;
        }

        $guest->birth_date = $normalized ?: null;
        $guest->save();
    }

    private function formatBirthDateForView(DateTimeInterface|string|null $value): ?string
    {
        if ($value instanceof DateTimeInterface) {
            return $value->format('Y-m-d');
        }

        if (is_string($value) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return $value;
        }

        return null;
    }

    // editForm は廃止（ルートも削除）。残っているリンクがあれば /data へ寄せる
    public function editForm(Request $r){ return redirect()->route('data.index')->with('info','編集は年度行の「編集」から行ってください。'); }
}