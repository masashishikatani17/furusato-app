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
    /** data_name 禁止文字（仕様） */
    private function assertValidDataNameOrFail(?string $name): void
    {
        $s = (string)($name ?? '');
        // 必須/最大長は validator 側で担保。ここでは禁止文字だけ見る。
        // 改行/タブ/制御文字（0x00-0x1F,0x7F）＋ファイル名危険記号を禁止
        if (preg_match("/[\r\n\t]/u", $s) === 1) {
            throw ValidationException::withMessages(['data_name' => 'データ名に改行やタブは使用できません。']);
        }
        if (preg_match("/[\x00-\x1F\x7F]/u", $s) === 1) {
            throw ValidationException::withMessages(['data_name' => 'データ名に制御文字は使用できません。']);
        }
        if (preg_match('/[\\\\\/\:\*\?\"\<\>\|]/u', $s) === 1) {
            throw ValidationException::withMessages(['data_name' => 'データ名に使用できない記号が含まれています（\\ / : * ? " < > |）。']);
        }
    }

    /**
     * copy/clone 用：同一(guest,year,name)が存在する場合、末尾に数字を付けて空きを作る
     * - 例：XXX_コピー → XXX_コピー2 → XXX_コピー3 ...
     * - 25文字上限を守る（末尾の数字が入る分だけ切り詰める）
     */
    private function resolveAvailableCopyName(int $guestId, int $year, string $baseName): array
    {
        $base = (string)$baseName;
        if ($base === '') {
            $base = 'default_コピー';
        }

        // まず base のまま存在しないならそれを採用
        $exists = Data::query()
            ->where('guest_id', $guestId)
            ->where('kihu_year', $year)
            ->where('data_name', $base)
            ->exists();
        if (!$exists) {
            return [$base, null]; // [final, renamed_from]
        }

        // ★コピー系列（xxx_コピー / xxx_コピーN）の場合は「最小の空き番号」を探す
        if (preg_match('/^(.*)_コピー(\d+)?$/u', $base, $m) === 1) {
            $seriesBase = (string)($m[1] ?? '');
            if ($seriesBase === '') {
                $seriesBase = 'default';
            }

            $used = [];
            $names = Data::query()
                ->where('guest_id', $guestId)
                ->where('kihu_year', $year)
                ->where(function($q) use ($seriesBase) {
                    $q->where('data_name', $seriesBase . '_コピー')
                      ->orWhere('data_name', 'like', $seriesBase . '_コピー%');
                })
                ->pluck('data_name')
                ->all();

            foreach ($names as $nm) {
                $s = (string)$nm;
                if (preg_match('/^' . preg_quote($seriesBase, '/') . '_コピー(\d+)?$/u', $s, $mm) === 1) {
                    $n = isset($mm[1]) && $mm[1] !== '' ? (int)$mm[1] : 1; // _コピー は 1 扱い
                    $used[$n] = true;
                }
            }

            for ($n = 1; $n <= 999; $n++) {
                if (isset($used[$n])) continue;
                $suffix = ($n === 1) ? '_コピー' : ('_コピー' . $n);
                $candidate = mb_substr($seriesBase, 0, max(0, 25 - mb_strlen($suffix))) . $suffix;
                // 念のため DB でも確認（並行作成対策）
                $ok = !Data::query()
                    ->where('guest_id', $guestId)
                    ->where('kihu_year', $year)
                    ->where('data_name', $candidate)
                    ->exists();
                if ($ok) {
                    return [$candidate, $base];
                }
            }
            throw ValidationException::withMessages(['data_name' => 'データ名の自動採番に失敗しました。別の名前を入力してください。']);
        }

        // ★それ以外は従来どおり末尾に数字を付けて空きを探す（最小空きを探す）
        for ($i = 2; $i <= 999; $i++) {
            $suffix = (string)$i;
            $candidate = mb_substr($base, 0, max(0, 25 - mb_strlen($suffix))) . $suffix;
            $ok = !Data::query()
                ->where('guest_id', $guestId)
                ->where('kihu_year', $year)
                ->where('data_name', $candidate)
                ->exists();
            if ($ok) {
                return [$candidate, $base];
            }
        }
        throw ValidationException::withMessages(['data_name' => 'データ名の自動採番に失敗しました。別の名前を入力してください。']);
    }
    
    /**
     * copyForm(GET) 用：DBを見て「次に提案すべき _コピー名」を返す
     * - base が "テストデータ" のとき、既存に
     *   テストデータ_コピー, テストデータ_コピー2 があれば テストデータ_コピー3 を返す
     * - base 自体が "xxx_コピー" や "xxx_コピー2" の場合も、
     *   「xxx」の系列として次番号を返す（UIで _コピー_コピー を作らない）
     */
    private function suggestNextCopyNameForForm(int $guestId, int $year, string $sourceName): string
    {
        $src = (string)$sourceName;
        if ($src === '') {
            $src = 'default';
        }

        // 末尾が _コピー or _コピーN なら base を取り出す
        $base = $src;
        if (preg_match('/^(.*)_コピー(\d+)?$/u', $src, $m) === 1) {
            $base = (string)($m[1] ?? '');
            if ($base === '') {
                $base = 'default';
            }
        }

        // 既存の base_コピー 系を列挙（同一 guest/year のみ）
        $rows = Data::query()
            ->where('guest_id', $guestId)
            ->where('kihu_year', $year)
            ->where(function($q) use ($base) {
                $q->where('data_name', $base . '_コピー')
                  ->orWhere('data_name', 'like', $base . '_コピー%');
            })
            ->pluck('data_name')
            ->all();

        // 使われている番号を収集（_コピー=1, _コピーN=N）
        $used = [];
        foreach ($rows as $name) {
            $s = (string)$name;
            if (preg_match('/^' . preg_quote($base, '/') . '_コピー(\d+)?$/u', $s, $mm) === 1) {
                $n = isset($mm[1]) && $mm[1] !== '' ? (int)$mm[1] : 1;
                $used[$n] = true;
            }
        }
        // ★最小の空き番号を探す
        for ($n = 1; $n <= 999; $n++) {
            if (isset($used[$n])) continue;
            $suffix = ($n === 1) ? '_コピー' : ('_コピー' . $n);
            return mb_substr($base, 0, max(0, 25 - mb_strlen($suffix))) . $suffix;
        }
        // 通常は到達しない
        $suffix = '_コピー999';
        return mb_substr($base, 0, max(0, 25 - mb_strlen($suffix))) . $suffix;
    }

    /** client 判定（顧問先アカウント） */
    private function isClient(): bool
    {
        $u = auth()->user();
        $role = strtolower((string)($u->role ?? ''));
        return $role === 'client';
    }

    /**
     * client → 自分に紐付く guest を1件取得（無ければ403）
     * - guests.client_user_id が SoT
     * - client は必ず部署に所属（users.group_id 必須）
     * - guest.group_id と users.group_id は一致していること（不一致は設定不備として403）
     */
    private function resolveClientGuestOrFail(): Guest
    {
        $me = auth()->user();
        if (!$me) {
            abort(403);
        }
        if (!$this->isClient()) {
            abort(403);
        }
        $companyId = (int)($me->company_id ?? 0);
        $myGroupId = (int)($me->group_id ?? 0);
        if ($companyId <= 0 || $myGroupId <= 0) {
            abort(403, '顧客アカウントが顧客に紐づいていません。担当者に連絡してください。');
        }

        $guest = Guest::query()
            ->where('company_id', $companyId)
            ->where('client_user_id', (int)$me->id)
            ->first();

        if (!$guest) {
            abort(403, '顧客アカウントが顧客に紐づいていません。担当者に連絡してください。');
        }

        if ((int)($guest->group_id ?? 0) !== $myGroupId) {
            abort(403, '顧客アカウントの部署設定に不整合があります。担当者に連絡してください。');
        }

        return $guest;
    }
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

        // client：自分の guest に紐付く data のみ
        if ($this->isClient()) {
            $data->loadMissing('guest');
            $guest = $data->guest;
            if (!$guest || (int)($guest->client_user_id ?? 0) !== (int)$me->id) {
                abort(403);
            }
        } else {
            // owner/registrar 以外は自部署のみ
            if (!$this->isOwnerOrRegistrar() && (int)$data->group_id !== (int)($me->group_id ?? 0)) abort(403);
        }

        // 共有設定（private は作成者のみ）
        if (config('feature.data_privacy')) {
            $vis = (string)($data->visibility ?? 'shared');
            if ($vis === 'private') {
                $creatorId = (int)($data->owner_user_id ?? 0) ?: (int)($data->user_id ?? 0);
                if ((int)$me->id !== $creatorId) {
                    abort(403);
                }
            }
        }
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
        if ($this->isClient()) {
            // client は自分の顧客（1件）に固定
            $clientGuest = $this->resolveClientGuestOrFail();
            $guestQuery->where('id', (int)$clientGuest->id);
        } else {
            if ($ctxGroupId !== null) {
                $guestQuery->where('group_id', $ctxGroupId);
            }
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
            $dq = Data::query()
                ->select('id', 'guest_id', 'kihu_year', 'data_name', 'owner_user_id', 'user_id', 'visibility')
                ->where('guest_id', $guest->id)
                ->whereNotNull('kihu_year')
                ->orderByDesc('kihu_year')->orderBy('data_name')->orderByDesc('id');

            // private は作成者のみ（一覧にも出さない）
            if (config('feature.data_privacy')) {
                $me = auth()->user();
                $dq->where(function($q) use ($me){
                    $q->whereNull('visibility')
                      ->orWhere('visibility', '!=', 'private')
                      ->orWhere(function($qq) use ($me){
                          $qq->where('visibility', 'private')
                             ->where(function($qqq) use ($me){
                                 $qqq->where('owner_user_id', (int)$me->id)
                                     ->orWhere('user_id', (int)$me->id);
                             });
                      });
                });
            }

            $datas = $dq->get();
        }

        return view('data.data_master', [
            'guests'     => $guests,
            'datas'      => $datas,
            'guestId'    => $guestId,
            'companyId'  => $companyId,
            'ctxGroupId' => $ctxGroupId, // null=横断
            'clientGuest' => $this->isClient() ? ($guest ?? null) : null,
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
        if ($this->isClient()) {
            // client は自分の顧客のみ
            if ((int)($guest->client_user_id ?? 0) !== (int)$me->id) {
                abort(403);
            }
        } else {
            if (!$this->isOwnerOrRegistrar() && (int)$guest->group_id !== (int)$me->group_id) {
                abort(403);
            }
        }

        $q = Data::query()
            ->select('id', 'guest_id', 'kihu_year', 'data_name', 'owner_user_id', 'user_id', 'visibility')
            ->where('guest_id', $guest->id)
            ->whereNotNull('kihu_year')
            ->orderByDesc('kihu_year')->orderBy('data_name')->orderByDesc('id')
            ;

        // private は作成者のみ（一覧にも出さない）
        if (config('feature.data_privacy')) {
            $q->where(function($qq) use ($me){
                $qq->whereNull('visibility')
                   ->orWhere('visibility', '!=', 'private')
                   ->orWhere(function($qq2) use ($me){
                       $qq2->where('visibility', 'private')
                           ->where(function($qq3) use ($me){
                               $qq3->where('owner_user_id', (int)$me->id)
                                   ->orWhere('user_id', (int)$me->id);
                           });
                   });
            });
        }

        $list = $q->get();

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
        // 共通のアクセス制御（client/部署/private を含む）
        $this->assertCanAccessData($data);

        $validated = $request->validate([
            'kihu_year'  => ['required','integer','between:2025,2035'],
            'data_name'  => ['required','string','max:25'],
        ],[
            'kihu_year.required' => '年度を選択してください。',
            'kihu_year.between'  => '年度の指定が不正です（2025〜2035）。',
            'data_name.required' => 'データ名を入力してください。',
            'data_name.max'      => 'データ名は25文字以内で入力してください。',
        ]);
        $year = (int)$validated['kihu_year'];
        $baseName = (string)$validated['data_name'];
        $this->assertValidDataNameOrFail($baseName);

        // ★clone-year は自動採番（仕様）：同名があれば _コピー2 などに寄せて複製する
        [$finalName, $renamedFrom] = $this->resolveAvailableCopyName((int)$data->guest_id, $year, $baseName);
        $this->assertValidDataNameOrFail($finalName);

        // 入力だけコピーして、結果は再計算で生成する
        $new = DB::transaction(function () use ($data, $year, $finalName) {
            // 1) Data本体を複製（★ユニーク制約があるため、保存前に key を必ず変更する）
            $cloned = $data->replicate();
            $cloned->exists = false;

            $cloned->kihu_year     = $year;
            $cloned->data_name     = $finalName;
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
            'data_name'=> (string)$new->data_name,
            'renamed_from' => $renamedFrom, // null or string
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
            'data_name'     => ['required','string','max:25'],
        ];
        if (config('feature.data_privacy')) {
            $rules['visibility'] = ['required','in:shared,private'];
        }
        $messages = [
            'proposal_date.required' => '提案書日を入力してください。',
            'proposal_date.date_format' => '提案書日は YYYY-MM-DD 形式で入力してください。',
            'kihu_year.required' => '年度を選択してください。',
            'kihu_year.between'  => '年度の指定が不正です（2025〜2035）。',
            'data_name.required' => 'データ名を入力してください。',
            'data_name.max'      => 'データ名は25文字以内で入力してください。',
            'visibility.required' => '共有設定を選択してください。',
            'visibility.in' => '共有設定が不正です。',
        ];
        $validated = $request->validate($rules, $messages);

        $newYear = (int)$validated['kihu_year'];
        $oldYear = (int)($data->kihu_year ?? 0);
        $newVis  = config('feature.data_privacy') ? (string)$validated['visibility'] : (string)($data->visibility ?? 'shared');
        $newProposal = (string)$validated['proposal_date'];
        $newName = (string)$validated['data_name'];
        $this->assertValidDataNameOrFail($newName);

        $confirmOverwrite = (int)$request->input('confirm_overwrite', 0) === 1;

        // 年度/データ名とも変更なし：メタ更新のみ
        $oldName = (string)($data->data_name ?? 'default');
        if ($newYear === $oldYear && $newName === $oldName) {
            $data->proposal_date = $newProposal;
            if (config('feature.data_privacy')) {
                $data->visibility = $newVis;
            }
            $data->data_name = $newName;
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

        // 変更あり：同一guest内のターゲットを探す（key=年度+データ名）
        $target = Data::query()
            ->where('guest_id', (int)$data->guest_id)
            ->where('kihu_year', $newYear)
            ->where('data_name', $newName)
            ->first();

        // 変更先が存在しない → 移動（data_idはそのまま、結果は削除）
        if (!$target) {
            DB::transaction(function () use ($data, $newYear, $newProposal, $newVis) {
                $data->kihu_year = $newYear;
                $data->data_name = request()->input('data_name');
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
                    'from_name' => $oldName,
                    'to_data_id' => (int)$target->id,
                    'to_year' => $newYear,
                    'to_name' => $newName,
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
            $target->data_name = request()->input('data_name');
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
        if ($this->isClient()) {
            abort(403);
        }
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
        // 共通のアクセス制御（client/部署/private を含む）
        $this->assertCanAccessData($data);

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

        if ($this->isClient()) {
            $clientGuest = $this->resolveClientGuestOrFail();
            $guests = collect([$clientGuest]);
            $defaultBirthDate = $this->formatBirthDateForView($clientGuest->birth_date ?? null);
            return view('data.data_create', [
                'guests' => $guests,
                'defaultBirthDate' => $defaultBirthDate,
                'clientGuest' => $clientGuest,
            ]);
        }

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
        // client：顧客は固定（ゲスト新規作成や他ゲスト選択は不可）
        if ($this->isClient()) {
            $clientGuest = $this->resolveClientGuestOrFail();
            // request値は信用せず固定化
            $request->merge([
                'guest_mode' => 'existing',
                'guest_id' => (int)$clientGuest->id,
            ]);
        }
        // 1) バリデーション（基本）
        $rules = [
            'guest_mode' => ['required','in:new,existing'],
            'guest_id'   => ['required_if:guest_mode,existing','nullable','integer','exists:guests,id'],
            'guest_name' => ['required_if:guest_mode,new','nullable','string','max:25'],
            'kihu_year'  => ['required','integer','between:2025,2035'],
            'data_name'  => ['required','string','max:25'],
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
            'data_name.required'  => 'データ名を入力してください。',
            'data_name.max'       => 'データ名は25文字以内で入力してください。',
            'visibility.in'       => '共有設定が不正です。',
            'birth_date.date_format' => '生年月日は YYYY-MM-DD 形式で入力してください。',
            'proposal_date.date_format' => '提案書日は YYYY-MM-DD 形式で入力してください。',
        ];
        $validated = $request->validate($rules, $messages);
        $this->assertValidDataNameOrFail((string)$validated['data_name']);

        $me = auth()->user();
        $companyId = (int)$me->company_id;
        $groupIdOfUser = (int)($me->group_id ?? 0);

        // 2) お客様の決定（スコープ整合）
        $birthDate = $validated['birth_date'] ?? null;

        if ($validated['guest_mode'] === 'new') {
            // client は new を許可しない（固定）
            if ($this->isClient()) {
                abort(403);
            }
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
            if ($this->isClient()) {
                if ((int)($guest->client_user_id ?? 0) !== (int)$me->id) abort(403);
            } else {
                if (!$this->isOwnerOrRegistrar() && (int)$guest->group_id !== $groupIdOfUser) abort(403);
            }
        }

        $this->updateGuestBirthDate($guest, $birthDate);

        // 3) 同一(年度+データ名)ユニークのサーバ検証（create は自動採番しない）
        $exists = Data::query()
            ->where('guest_id', $guest->id)
            ->where('kihu_year', (int)$validated['kihu_year'])
            ->where('data_name', (string)$validated['data_name'])
            ->exists();
        if ($exists) {
            return back()
                ->withInput()
                ->with('modal_error', [
                    'duplicate_year' => true,
                    'message' => '同じお客様について 同一の年度・同一のデータ名 のデータは登録できません。',
                ]);
        }

        // 4) Data 作成（親から company/group を強制継承）
        $data = new Data();
        $data->guest_id   = $guest->id;
        $data->company_id = (int)$guest->company_id;
        $data->group_id   = (int)$guest->group_id;
        $data->user_id    = $me->id;
        $data->kihu_year  = (int)$validated['kihu_year'];
        $data->data_name  = (string)$validated['data_name'];

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
        // 共通のアクセス制御（client/部署/private を含む）
        $this->assertCanAccessData($source);
        $me = auth()->user();

        // コピー先候補（Owner/Registrar=横断、自ロール=自部署のみ）
        if ($this->isClient()) {
            $clientGuest = $this->resolveClientGuestOrFail();
            $guests = collect([$clientGuest]);
        } else {
            $q = Guest::query()
                ->select('id','name','company_id','group_id','user_id','birth_date')
                ->where('company_id', (int)$me->company_id);
            if (!$this->isOwnerOrRegistrar()) {
                $q->where('group_id', (int)($me->group_id ?? 0));
            }
            $guests = $q->orderBy('created_at','asc')->get();
        }
        $defaultBirthDate = $this->formatBirthDateForView($source->guest?->birth_date ?? null);

        // ★コピー画面のデフォルト data_name は「実際に作られる名前」と一致させる
        // - 初期はコピー元と同じ年度（source->kihu_year）
        // - コピー先の初期は copy_mode=same なので guest は source->guest を前提に算出
        $defaultYear = (int)($source->kihu_year ?? 0);
        if ($defaultYear < 2025) $defaultYear = 2025;
        if ($defaultYear > 2035) $defaultYear = 2035;
        $baseName = (string)($source->data_name ?? 'default');
        $suggestedCopyName = $this->suggestNextCopyNameForForm((int)$source->guest_id, $defaultYear, $baseName);

        return view('data.data_copy', [
            'source' => $source,
            'guests' => $guests,
            'defaultBirthDate' => $defaultBirthDate,
            'clientGuest' => $this->isClient() ? ($this->resolveClientGuestOrFail()) : null,
            'suggestedCopyName' => $suggestedCopyName,
        ]);
    }

    /** コピー実行：POST /data/copy（複数年度対応） */
    public function copy(Request $request)
    {
        // client：コピー先は必ず同じ顧客（same固定）
        if ($this->isClient()) {
            $clientGuest = $this->resolveClientGuestOrFail();
            $request->merge([
                'copy_mode' => 'same',
                'target_guest_id' => (int)$clientGuest->id,
                'target_guest_name' => null,
            ]);
        }
        // 1) 入力検証
        $rules = [
            'selected_data_id' => ['required','integer','exists:datas,id'],
            'copy_mode'        => ['required','in:same,existing,new'],
            'target_guest_id'  => ['nullable','integer','required_if:copy_mode,existing','exists:guests,id'],
            'target_guest_name'=> ['nullable','string','max:25','required_if:copy_mode,new'],
            'kihu_year'        => ['required','integer','between:2025,2035'],
            'data_name'        => ['required','string','max:25'],
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
            'kihu_year.required' => '年度を選択してください。',
            'kihu_year.between'  => '年度の指定が不正です（2025〜2035）。',
            'data_name.required' => 'データ名を入力してください。',
            'data_name.max'      => 'データ名は25文字以内で入力してください。',
            'birth_date.date_format' => '生年月日は YYYY-MM-DD 形式で入力してください。',
            'proposal_date.date_format' => '提案書日は YYYY-MM-DD 形式で入力してください。',
        ];
        $validated = $request->validate($rules, $messages);
        $this->assertValidDataNameOrFail((string)$validated['data_name']);

        $me = auth()->user();
        $companyId = (int)$me->company_id;
        $userGroupId = (int)($me->group_id ?? 0);
        $birthDate = $validated['birth_date'] ?? null;
        $proposalIn = $validated['proposal_date'] ?? null;

        // 2) コピー元
        $source = Data::with('guest')->findOrFail((int)$validated['selected_data_id']);
        $this->assertCanAccessData($source);

        // 3) コピー先ゲスト決定（スコープ整合）
        $mode = (string)$validated['copy_mode'];
        if ($mode === 'same') {
            $targetGuest = $source->guest;
            if ($this->isClient()) {
                // client：source の guest が自分の顧客であることを厳密化
                if ((int)($targetGuest->client_user_id ?? 0) !== (int)$me->id) {
                    abort(403);
                }
            }
        } elseif ($mode === 'existing') {
            if ($this->isClient()) abort(403);
            $targetGuest = Guest::findOrFail((int)$validated['target_guest_id']);
            if ((int)$targetGuest->company_id !== $companyId) abort(403);
            if (!$this->isOwnerOrRegistrar() && (int)$targetGuest->group_id !== $userGroupId) abort(403);
        } else { // new
            if ($this->isClient()) abort(403);
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

        // 4) 単一年度で複製（copy は自動採番あり）
        $yy = (int)$validated['kihu_year'];
        $baseName = (string)$validated['data_name'];
        [$finalName, $renamedFrom] = $this->resolveAvailableCopyName((int)$targetGuest->id, $yy, $baseName);
        $this->assertValidDataNameOrFail($finalName);

        $new = \DB::transaction(function () use ($source, $targetGuest, $yy, $me, $finalName, $proposalIn) {
            // ★ユニーク制約があるため、copy は「新規Data行を先に正しいkeyで作る」方式に統一する
            $cloned = $source->replicate();
            $cloned->exists = false;

            $cloned->guest_id   = (int)$targetGuest->id;
            $cloned->company_id = (int)$targetGuest->company_id;
            $cloned->group_id   = (int)$targetGuest->group_id;
            $cloned->user_id    = (int)$me->id;
            $cloned->kihu_year  = (int)$yy;
            $cloned->data_name  = (string)$finalName;

            // コピーで作られる新データの「データ作成日」はコピー実行日（当日）
            $today = now()->toDateString();
            $cloned->setAttribute('data_created_on', $today);
            // 提案書日は入力があれば優先。無ければ当日。
            $cloned->setAttribute('proposal_date', (string)($proposalIn ?: $today));

            if (config('feature.data_privacy')) {
                $vis = strtolower((string)request()->input('visibility','shared'));
                $cloned->visibility    = in_array($vis, ['shared','private'], true) ? $vis : 'shared';
                $cloned->owner_user_id = (int)$me->id;
            }

            // ★ここで初回保存（keyは衝突しない）
            $cloned->save();

            // ★入力だけコピーして、結果は破棄（再計算で生成）
            $this->copyTableByDataId('furusato_inputs', (int)$source->id, (int)$cloned->id);
            $this->copyTableByDataId('furusato_syori_settings', (int)$source->id, (int)$cloned->id);
            \DB::table('furusato_results')->where('data_id', (int)$cloned->id)->delete();

            return $cloned;
        });

        AuditLogger::log('data.copied', [
            'from_data_id' => (int)$source->id,
            'from_guest_id' => (int)$source->guest_id,
            'to_guest_id' => (int)$targetGuest->id,
            'year' => $yy,
            'requested_name' => $baseName,
            'final_name' => $finalName,
            'renamed_from' => $renamedFrom,
            'created_data_id' => (int)$new->id,
            'proposal_date' => (string)($proposalIn ?? ''),
        ], $source);

        // 5) 返却（リネームが発生したら info に出す）
        $redir = redirect()->route('data.index', ['guest_id' => $targetGuest->id])
            ->with('success', 'コピーを作成しました。');
        if (is_string($renamedFrom) && $renamedFrom !== '') {
            $redir->with('info', '同名が存在したためデータ名を「'.$finalName.'」に変更して作成しました。');
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