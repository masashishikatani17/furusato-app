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
            'kihu_year' => ['required','integer','between:2010,2100'],
        ],[
            'kihu_year.required' => '年度を選択してください。',
            'kihu_year.between'  => '年度の指定が不正です（2010〜2100）。',
        ]);
        $year = (int)$validated['kihu_year'];

        // 同一ゲスト×同一年の重複を禁止
        $exists = Data::query()
            ->where('guest_id', $data->guest_id)
            ->where('kihu_year', $year)
            ->exists();
        if ($exists) {
            return response()->json([
                'message' => '同じお客様について同一の年度のデータは登録できません。'
            ], 422);
        }

        // DeepCopyService があれば使い、無ければ replicate で最小クローン
        $new = DB::transaction(function () use ($data, $year) {
            if (class_exists(\App\Services\Data\DeepCopyService::class)) {
                /** @var \App\Services\Data\DeepCopyService $svc */
                $svc = app(\App\Services\Data\DeepCopyService::class);
                $cloned = $svc->deepCopy($data, $data->guest_id);
            } else {
                $cloned = $data->replicate(); // Data本体のみ複製
                $cloned->exists = false;
                $cloned->push();
            }
            $cloned->kihu_year = $year;
            $cloned->owner_user_id = Auth::id();
            $cloned->save();
            return $cloned;
        });

        return response()->json([
            'id'       => $new->id,
            'guest_id' => $new->guest_id,
        ], 201);
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

        DB::transaction(function () use ($data) {
            $guest = $data->guest;
            $data->delete();
            if ($guest && $guest->datas()->count() === 0) {
                $guest->delete();
            }
        });
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
            'kihu_year'  => ['required','integer','between:2010,2100'],
            'birth_date' => ['nullable','date_format:Y-m-d'],
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
            'kihu_year.between'   => '年度の指定が不正です（2010〜2100）。',
            'visibility.in'       => '共有設定が不正です。',
            'birth_date.date_format' => '生年月日は YYYY-MM-DD 形式で入力してください。',
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
        if (config('feature.data_privacy')) {
            $vis = strtolower((string)($request->input('visibility','shared')));
            $data->visibility    = in_array($vis, ['shared','private'], true) ? $vis : 'shared';
            $data->owner_user_id = $me->id;
        }
        $data->save();

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
            'years.*'          => ['integer','between:2010,2100'],
            'birth_date'       => ['nullable','date_format:Y-m-d'],
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
            'years.*.between'=> '年度の指定が不正です（2010〜2100）。',
            'birth_date.date_format' => '生年月日は YYYY-MM-DD 形式で入力してください。',
        ];
        $validated = $request->validate($rules, $messages);

        $me = auth()->user();
        $companyId = (int)$me->company_id;
        $userGroupId = (int)($me->group_id ?? 0);
        $birthDate = $validated['birth_date'] ?? null;

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

    /* ===== 以降は未実装リンクの安全なフォールバック ===== */
    public function editForm(Request $r){ return redirect()->route('data.index')->with('info','未実装です'); }
    public function edit(Request $r, $id){ return redirect()->route('data.index')->with('info','未実装です'); }
}