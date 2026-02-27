{{-- resources/views/data/data_master.blade.php --}}
@extends('layouts.min')

@section('content')
<style>
      /* 年度一覧：右2列（選択・編集）だけ罫線を消す（左の年度列は残す） */
      table.table-compact-p td.b-none{
        border: 0 !important;
      }
      /* border-collapse の見え方で“左境界線”が残る場合の保険：
         右2列の左側境界（=年度列の右線）を消したいならこれも有効 */
      table.table-compact-p td.b-none{
        border-left: 0 !important;
      }
      /* もし右端の外枠（テーブル右端線）も消したいなら */
      table.table-compact-p td.b-none:last-child{
        border-right: 0 !important;
      }

      /* === 右ペイン：年度列 + データ名列 + 操作列（分割表示） === */
      .year-data-wrap{ display:flex; flex-wrap:nowrap; gap: 14px; }
      .year-col{ width: 110px; flex: 0 0 110px; }
      .data-col{ width: 424px; flex: 0 0 424px; }
      /* 分割表示：それぞれ独立した表として枠線を維持する（境界線を消さない） */
      table.year-table, table.data-table{ border-collapse: collapse; }

      /* === 上部検索バー（全表示は短く、検索は長く） === */
      table.table-beige.search-bar-narrow{ width: 230px !important; }
      table.table-beige.search-bar-wide{ width: 380px !important; }

      /* === 検索入力（高さが変わらない対策：min-height/padding を強制上書き） === */
      #guest-search-input.form-control{
        min-height: 24px !important;
        height: 24px !important;
        padding-top: 0 !important;
        padding-bottom: 0 !important;
        line-height: 1.1 !important;
      }
</style>
<div class="container-blue" style="max-width:920px; width:100%; margin:0 auto;">
  <div class="card-header d-flex justify-content-between gap-2">
    <div class="d-flex align-items-start">
      <img src="{{ asset('storage/images/kado_lefttop.jpg') }}" alt="…">
      <h0 class="ms-3 mt-2"> お客様・年度一覧</h0>
    </div>
    <div class="d-flex align-items-center gap-2 me-5 mt-2">
      {{-- ログアウト（settings/index と同じ） --}}
      <form method="POST" action="{{ route('logout') }}" class="m-0">
        @csrf
        <button type="submit" class="btn btn-base-blue">ログアウト</button>
      </form>

      @php
        $me = $me ?? auth()->user();
        $role = strtolower((string)($me->role ?? ''));
        $isClient = ($role === 'client');
        $canGear = !($isClient);
      @endphp

      @if ($canGear)
        <span class="gear-wrap">
          <x-gear-button position="inline" size="26" />
        </span>
      @endif
    </div>
  </div>
  
    @php
      // Blade → Alpine 受け渡し用
      $guestsJson = $guests->map(fn($g) => [
        'id' => (int)$g->id,
        'name' => $g->name,
        'user_id' => (int)($g->user_id ?? 0),
        'birth_date' => optional($g->birth_date)->format('Y-m-d'),
      ]);
      $datasJson = $datas->map(fn($d) => [
        'id' => (int)$d->id,
        'guest_id' => (int)$d->guest_id,
        'kihu_year' => (int)$d->kihu_year,
        'data_name' => (string)($d->data_name ?? 'default'),
        'owner_user_id' => (int)($d->owner_user_id ?? 0),
        'user_id' => (int)($d->user_id ?? 0),
        // 鍵マーク表示用（feature.data_privacyがfalseでもnullで来るだけなので安全）
        'visibility' => $d->visibility ?? null,
      ]);
    @endphp
    @php
      $companyIdJs = (int)($companyId ?? 0);
      $userIdJs = (int)(auth()->id() ?? 0);
    @endphp
    <div x-data="masterPane(@js($guestsJson), @js($datasJson), {{ $guestId ?? 'null' }}, {{ $companyIdJs }}, {{ $userIdJs }})"
          x-init="init()" x-cloak class="border-0 rounded p-3">
  
      <!-- 上部：検索（お客様名） -->
      <table align="center"
             class="table-beige mt-0 ms-5 mb-3"
             :class="viewMode==='search' ? 'search-bar-wide' : 'search-bar-narrow'">
        <tr>
           <td>
            <div class="d-flex align-items-center gap-2 ms-2 mt-1 flex-nowrap">
              <label class="mb-1">表示：</label>
              <div class="form-check form-check-inline">
                <input class="form-check-input" type="radio" value="all" x-model="viewMode" id="viewAll">
                <label class="form-check-label" for="viewAll">全表示</label>
              </div>
              <div class="form-check form-check-inline">
                <input class="form-check-input" type="radio" value="search" x-model="viewMode" id="viewSearch">
                <label class="form-check-label" for="viewSearch">検　索</label>
              </div>
              <template x-effect="updateFilter()"></template>
              <input type="text"
                     class="form-control form-control-sm"
                     id="guest-search-input"
                     style="width: 150px; height: 24px; padding-top: 0; padding-bottom: 0;"
                     placeholder="お客様名で検索"
                     x-model="searchQuery"
                     x-show="viewMode==='search'"
                     x-cloak
                     @keyup.enter="updateFilter"
                     @blur="updateFilter">
            </div>
          </td> 
        </tr>   
      </table>
      <!-- 2ペイン（高さを固定：検索で行が減っても下のボタン帯が上がらないようにする） -->
      <div class="d-flex gap-3 flex-nowrap justify-content-center"
           style="overflow-x:auto; min-height: 480px;">
        <!-- 左：お客様一覧 -->
        <div class="flex-shrink-0" style="width: 300px;">
          <table class="table table-base mb-2">
            <thead class="table-light">
            <tr><th class="text-center" style="width: 300px;height: 25px;">お客様名</th></tr>
            </thead>
          </table>
          <div class="mt-2" style="max-height: 420px; overflow-y: auto;">
            <table class="table table-base mb-0">
              <tbody>
              <template x-for="g in filteredGuests" :key="g.id">
                <tr :class="g.id===guestId ? 'table-primary' : ''" style="cursor:pointer;">
                  <td class="text-start py-1 px-2" @click="selectGuest(g.id)" x-text="g.name" style="width: 300px;"></td>
                </tr>
              </template>
              <template x-if="filteredGuests.length===0">
                <tr><td class="text-muted py-2 px-2" style="width: 300px;">（お客様がありません）</td></tr>
              </template>
              </tbody>
            </table>
          </div>
        </div>
  
        <!-- 右：年度一覧 -->
        <div class="flex-shrink-0">
          <!-- 右ペイン：ヘッダ（年度列 + データ名列 + 操作列） -->
          <div class="year-data-wrap" style="width: 548px;">
            <div class="year-col">
              <table class="table table-base mb-2 align-middle year-table" style="width: 110px;">
                <thead class="table-light">
                <tr style="height:25px;">
                  <th class="text-center" style="width:110px;">
                    年 度
                    <button type="button" class="btn btn-sm btn-outline-primary ms-1 py-0 px-1"
                            style="font-size: 11px; height: 18px; line-height: 1;"
                            @click="toggleSort()"
                            :title="sortOrder==='desc' ? '新しい順→古い順に切替' : '古い順→新しい順に切替'">
                      ⇅
                    </button>
                  </th>
                </tr>
                </thead>
              </table>
            </div>
            <div class="data-col">
              <table class="table table-base mb-2 align-middle data-table" style="width: 424px;">
                <thead class="table-light">
                <tr style="height:25px;">
                  <th class="text-center" style="width: 300px;">データ名</th>
                  <th class="text-center" style="width: 124px;">操 作</th>
                </tr>
                </thead>
              </table>
            </div>
          </div>

          <!-- 右ペイン：本体（年度一覧 + 選択年度のデータ一覧） -->
          <div class="mt-4 year-data-wrap"
               style="max-height: 420px; overflow-y: auto; width: 548px; min-height: 420px;">
            <!-- ★ロード中は“同じ枠内”で表示（ヘッダの位置は動かさない） -->
            <div class="year-col" x-show="!ready" x-cloak>
              <div class="text-muted py-2 px-2 text-center" style="width:110px;">（読み込み中）</div>
            </div>
            <div class="data-col" x-show="!ready" x-cloak>
              <div class="text-muted py-2 px-2" style="width:424px;">（読み込み中）</div>
            </div>

            <!-- ready後に一覧を表示 -->
            <!-- 年度列（重複なし・自分から見えるデータがある年度のみ） -->
            <div class="year-col" x-show="ready" x-cloak>
              <table class="table table-compact-p mb-0 align-middle year-table" style="width: 110px;">
                <tbody>
                <template x-for="y in yearList" :key="y">
                  <tr style="height: 25px; cursor:pointer;"
                      :class="selectedYear===y ? 'table-primary' : ''"
                      @click="selectYear(y)">
                    <td class="text-center" style="width:110px;">
                      <span x-text="warekiYearLabel(y)"></span>
                    </td>
                  </tr>
                </template>
                <template x-if="yearList.length===0">
                  <tr><td class="text-muted py-2 px-2 text-center">（データがありません）</td></tr>
                </template>
                </tbody>
              </table>
            </div>

            <!-- データ名列（選択年度に属するデータのみ） -->
            <div class="data-col" x-show="ready" x-cloak>
              <table class="table table-compact-p mb-0 align-middle data-table" style="width: 424px;">
                <tbody>
                <template x-for="d in filteredDatas" :key="d.id">
                  <tr style="height: 25px; cursor:pointer;"
                      :class="selectedDataId===d.id ? 'table-active' : ''"
                      @click="selectData(d.id)">
                    <td class="text-start" style="width:300px;">
                      <span class="d-inline-flex align-items-center gap-1">
                        <template x-if="isPrivate(d)">
                          <span title="非共有（作成者のみ）" aria-label="非共有" role="img">🔒</span>
                        </template>
                        <span x-text="d.data_name || 'default'"></span>
                      </span>
                    </td>
                    <td class="text-center bg-cream b-none" style="width:124px;" nowrap="nowrap">
                      <div class="d-inline-flex align-items-center justify-content-center gap-1">
                        <button type="button" class="btn-base-blue"
                                @click.stop="selectData(d.id); openYearModal(d)">選 択</button>
                        <button type="button" class="btn-base-blue"
                                @click.stop="window.location.href = `/data/${d.id}/edit`">編 集</button>
                      </div>
                    </td>
                  </tr>
                </template>
                <template x-if="yearList.length>0 && filteredDatas.length===0">
                  <tr><td class="text-muted py-2 px-2" colspan="2">（データがありません）</td></tr>
                </template>
                <template x-if="yearList.length===0">
                  <tr><td class="text-muted py-2 px-2" colspan="2">（データがありません）</td></tr>
                </template>
                </tbody>
              </table>
            </div>
          </div>
        </div>
      </div>
      <hr class="mb-2">
      <!-- 下部ボタン帯（“ビル”と同じ配置） -->
                <div class="btn-footer">
                  <div class="d-flex justify-content-between">
                    <div class="d-flex flex-wrap gap-2">
                      <a href="{{ route('data.create') }}" class="btn-base-blue">新規データの作成</a>
                      <a :href="selectedDataId ? `/data/copyForm?data_id=${selectedDataId}` : '#'"
                         class="btn-base-blue"
                         :class="{'btn-disabled-link': !selectedDataId}"
                         :title="selectedDataId ? '' : 'コピーするデータを選択して下さい'">
                        既存データのコピー
                      </a>
                      @php
                        $me = auth()->user();
                        $role = strtolower((string)($me->role ?? ''));
                        $isClient = ($role === 'client');
                      @endphp
                      @if (! $isClient)
                        <button type="button"
                                class="btn-base-red"
                                :disabled="!guestId"
                                :class="{'btn-disabled-link': !guestId}"
                                :title="guestId ? '' : '削除するお客様を選択して下さい'"
                                @click="openDeleteGuestModal()">
                          お客様データの削除
                        </button>
                      @endif
                    </div>
                    <div class="d-flex align-items-center">
                      <a href="{{ route('data.index') }}" class="btn-base-blue">戻 る</a>
                    </div>
                  </div>
                </div>
                  
      <!-- 年度変更モーダル（x-if だと select が先頭(2035)に寄ることがあるので x-show でDOMを保持する） -->
      <div x-show="showYearModal" x-cloak
           class="position-fixed top-0 start-0 w-100 h-100"
           style="background: rgba(0,0,0,.35); z-index: 1055;"
           @click.self="showYearModal=false">
        <div class="bg-white rounded shadow p-3"
             style="width:360px; max-width:90vw; margin:10vh auto;">
          <h15>○年度の選択</h15>
          <div class="mt-3 ms-5 mb-3">
            <hs>同一年度・同一データ名は作成できません。</hs>
          </div>
          <div class="mt-1 ms-5 mb-2">
            <hs x-show="noteText" x-text="noteText"></hs>
          </div>
          <div class="text-center">
            <label class="me-3">年度</label>
            <select class="form-select form-select-sm" style="height:30px; width:100px;" x-model.number="yearSelected">
              <template x-for="y in yearOptions" :key="y">
                <option :value="y" x-text="warekiYearLabel(y)"></option>
              </template>
            </select>
          </div>
          <div class="text-center mt-2">
            <input type="text" class="form-control form-control-sm mx-auto text-start"
                   style="height:30px; width:260px;"
                   x-model="nameSelected">
          </div>
          <hr class="mb-2">
          <div class="d-flex justify-content-end gap-2 mt-0">
            <button type="button" class="btn btn-base-blue" @click="proceedByYear()">決 定</button>
            <button type="button" class="btn btn-base-blue" @click="showYearModal=false">戻 る</button>
          </div>
        </div>
      </div>

      <!-- お客様データ削除モーダル -->
      <div x-show="showDeleteGuestModal" x-cloak
           class="position-fixed top-0 start-0 w-100 h-100"
           style="background: rgba(0,0,0,.35); z-index: 1056;"
           @click.self="showDeleteGuestModal=false">
        <div class="bg-white rounded shadow p-3"
             style="width:420px; max-width:92vw; margin:10vh auto;">
          <h15>○お客様データの削除</h15>
          <div class="mt-3 mb-2">
            <div class="mb-2">
              <strong x-text="deleteGuestName ? `${deleteGuestName}` : '（未選択）'"></strong>
              <span> に紐づくデータ（</span><strong x-text="deleteDataCount"></strong><span>件）をすべて削除します。</span>
            </div>
            <div class="text-danger fw-semibold">
              削除後は復元できません。
            </div>
          </div>
          <hr class="mb-2">
          <div class="d-flex justify-content-end gap-2">
            <button type="button" class="btn btn-base-red"
                    :disabled="deleteBusy || !guestId"
                    @click="confirmDeleteGuest()">
              削除する
            </button>
            <button type="button" class="btn btn-base-blue"
                    :disabled="deleteBusy"
                    @click="showDeleteGuestModal=false">
              キャンセル
            </button>
          </div>
        </div>
      </div>
    </div> <!-- /x-data="masterPane(...)" -->
</div>
@endsection

@push('scripts')
<script>
function masterPane(guestsInit, datasInit, guestIdInit, companyIdInit, userIdInit) {
  return {
    // ---- state ----
    ready: false,
    guests: guestsInit || [],
    filteredGuests: guestsInit || [],
    datas: datasInit || [],
    filteredDatas: datasInit || [],
    guestId: guestIdInit || null,
    companyId: Number(companyIdInit || 0) || 0,
    userId: Number(userIdInit || 0) || 0,
    viewMode: 'all',
    searchQuery: '',
    sortOrder: 'desc', // 'desc' = 新しい年度優先 / 'asc' = 古い年度優先
    selectedDataId: null, // ▼ 画面下の「既存データのコピー」で使用する選択ID
    selectedYear: null,   // ▼ 年度列で選択された年度
    yearList: [],         // ▼ 年度列（重複なし：自分から見えるデータがある年のみ）
    showYearModal: false,
    yearSelected: null,
    nameSelected: '',
    yearOptions: [],
    targetRow: null,
    noteText: '',
    origYear: null,
    origName: '',

    // ---- guest delete modal ----
    showDeleteGuestModal: false,
    deleteGuestName: '',
    deleteDataCount: 0,
    deleteBusy: false,

    // ---- init ----
    async init() {
      this.buildYearOptions();
      this.updateFilter();

      // 1) 初期 guestId が無い場合は先頭を採用
      if (!this.guestId && this.guests.length > 0) {
        this.guestId = this.guests[0].id;
      }

      // 2) サーバから渡された静的 datasInit は信用せず、必ずAPIで最新を取得
      if (this.guestId) {
        await this.fetchDatas(this.guestId);
      } else {
        this.rebuildYearsAndRestoreSelection();
      }

      // 3) 初期選択は restoreSelection() 側で確定済み
      this.ready = true;
    },

    // ---- guests ----
    normalize(s){ return (s||'').toString().normalize('NFKC').toLowerCase(); },
    updateFilter() {
      if (this.viewMode==='search' && this.searchQuery.trim()) {
        const q = this.normalize(this.searchQuery.trim());
        this.filteredGuests = this.guests.filter(g => this.normalize(g.name).includes(q));
      } else {
        this.filteredGuests = this.guests;
      }
    },
    selectGuest(id) {
      if (this.guestId === id) return;
      this.guestId = id;
      this.fetchDatas(id);
    },

    // ---- datas ----
    async fetchDatas(id) {
      try {
        const r = await fetch(`/api/guest/${id}/datas`, { headers: { 'Accept': 'application/json' }});
        const list = await r.json();
        this.datas = Array.isArray(list) ? list : [];
      } catch (e) {
        this.datas = [];
      }
      this.rebuildYearsAndRestoreSelection();
    },
    getYearKey(d){ return Number(d?.kihu_year || 0) || 0; },
    sortDatas(list){
      const arr = [...list];
      arr.sort((a,b) => {
        const ak = this.getYearKey(a), bk = this.getYearKey(b);
        if (ak === bk) {
          const an = (a?.data_name || 'default').toString();
          const bn = (b?.data_name || 'default').toString();
          if (an !== bn) return an.localeCompare(bn, 'ja');
          return this.sortOrder==='desc' ? (b.id - a.id) : (a.id - b.id);
        }
        return this.sortOrder==='desc' ? (bk - ak) : (ak - bk);
      });
      return arr;
    },
    updateDataFilter(){
      // 選択年度で絞り込み → 従来ソート（data_name→id等）を維持
      const y = Number(this.selectedYear || 0) || 0;
      const subset = y ? (this.datas || []).filter(d => Number(d?.kihu_year) === y) : [];
      this.filteredDatas = this.sortDatas(subset);
    },
    toggleSort(){
      this.sortOrder = (this.sortOrder==='desc') ? 'asc' : 'desc';
      // 年度の並び替え → データ一覧も再評価
      this.rebuildYearsOnly();
      // 年度が存在しない/消えた可能性に備え、選択年度が list に無ければ先頭へ
      if (this.yearList.length > 0 && !this.yearList.includes(Number(this.selectedYear))) {
        this.selectYear(this.yearList[0], { persist: true, autoPickData: true });
      } else {
        this.updateDataFilter();
      }
    },

    // ---- selection persistence (localStorage) ----
    storageKey(suffix){
      const cid = Number(this.companyId || 0) || 0;
      const uid = Number(this.userId || 0) || 0;
      const gid = Number(this.guestId || 0) || 0;
      return `data_master:${cid}:${uid}:${gid}:${suffix}`;
    },
    saveLastYear(){
      if (!this.guestId) return;
      const y = Number(this.selectedYear || 0) || 0;
      if (!y) return;
      try { localStorage.setItem(this.storageKey('year'), String(y)); } catch (e) {}
    },
    saveLastData(){
      if (!this.guestId) return;
      const id = Number(this.selectedDataId || 0) || 0;
      if (!id) return;
      try { localStorage.setItem(this.storageKey('data_id'), String(id)); } catch (e) {}
    },
    loadLastYear(){
      try {
        const v = localStorage.getItem(this.storageKey('year'));
        const y = Number(v || 0) || 0;
        return y || null;
      } catch (e) { return null; }
    },
    loadLastData(){
      try {
        const v = localStorage.getItem(this.storageKey('data_id'));
        const id = Number(v || 0) || 0;
        return id || null;
      } catch (e) { return null; }
    },

    // ---- year list / restore ----
    rebuildYearsOnly(){
      // datas（権限フィルタ済み）から「自分から見える年度のみ」を抽出（重複なし）
      const ys = new Set();
      for (const d of (this.datas || [])) {
        const y = Number(d?.kihu_year || 0) || 0;
        if (y) ys.add(y);
      }
      const arr = Array.from(ys);
      arr.sort((a,b) => this.sortOrder==='desc' ? (b-a) : (a-b));
      this.yearList = arr;
    },
    rebuildYearsAndRestoreSelection(){
      this.rebuildYearsOnly();

      // 1) 年度の復元（guestごと、company+userもキーに含む）
      let y = this.loadLastYear();
      if (!y || !this.yearList.includes(y)) {
        y = (this.yearList.length > 0) ? this.yearList[0] : null;
      }
      this.selectedYear = y;

      // 2) データ一覧を作成
      this.updateDataFilter();

      // 3) data_id の復元（同一年度の中でのみ有効）
      let id = this.loadLastData();
      if (id && !(this.filteredDatas || []).some(d => Number(d?.id) === id)) {
        id = null;
      }
      if (!id && (this.filteredDatas || []).length > 0) {
        id = Number(this.filteredDatas[0].id) || null;
      }
      this.selectedDataId = id;

      // 4) 黙って先頭フォールバック（メッセージ出さない）
      //    ※ここで保存も更新しておく（次回復元の安定化）
      if (this.selectedYear) this.saveLastYear();
      if (this.selectedDataId) this.saveLastData();
    },

    selectYear(y, opt = {}){
      const yy = Number(y || 0) || 0;
      if (!yy) return;
      if (this.selectedYear === yy) return;
      this.selectedYear = yy;
      if (opt.persist !== false) this.saveLastYear();
      this.updateDataFilter();

      // 年度を変えたら、その年度内の「前回データ」が見つからない場合は先頭を黙って選択
      let id = this.loadLastData();
      if (opt.autoPickData === false) {
        // 何もしない
      } else {
        if (id && !(this.filteredDatas || []).some(d => Number(d?.id) === id)) {
          id = null;
        }
        if (!id && (this.filteredDatas || []).length > 0) {
          id = Number(this.filteredDatas[0].id) || null;
        }
        this.selectedDataId = id;
        if (this.selectedDataId) this.saveLastData();
      }
    },
    selectData(id){
      const did = Number(id || 0) || 0;
      if (!did) return;
      if (this.selectedDataId === did) return;
      this.selectedDataId = did;
      this.saveLastData();
    },

    // ---- guest delete ----
    getSelectedGuest(){
      const gid = Number(this.guestId || 0) || 0;
      if (!gid) return null;
      return (this.guests || []).find(g => Number(g?.id) === gid) || null;
    },
    openDeleteGuestModal(){
      const g = this.getSelectedGuest();
      if (!g || !this.guestId) return;
      this.deleteGuestName = (g?.name || '').toString();
      // 件数は「自分から見える data（API返却）」のみ
      this.deleteDataCount = Array.isArray(this.datas) ? this.datas.length : 0;
      this.deleteBusy = false;
      this.showDeleteGuestModal = true;
    },
    clearLocalStorageForGuest(gid){
      const cid = Number(this.companyId || 0) || 0;
      const uid = Number(this.userId || 0) || 0;
      const keyYear = `data_master:${cid}:${uid}:${gid}:year`;
      const keyData = `data_master:${cid}:${uid}:${gid}:data_id`;
      try { localStorage.removeItem(keyYear); } catch(e) {}
      try { localStorage.removeItem(keyData); } catch(e) {}
    },
    async confirmDeleteGuest(){
      const gid = Number(this.guestId || 0) || 0;
      if (!gid) return;
      if (this.deleteBusy) return;
      this.deleteBusy = true;
      try {
        const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
        const r = await fetch(`/api/guest/${gid}`, {
          method: 'DELETE',
          headers: {
            'Accept': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-TOKEN': token,
          },
        });
        const text = await r.text();
        if (!r.ok) {
          // 403/422 等はそのまま表示
          let msg = '削除に失敗しました。';
          try {
            const j = JSON.parse(text || '{}');
            msg = j.message || msg;
          } catch {
            msg = text || msg;
          }
          throw new Error(msg);
        }

        // localStorage（復元情報）をクリア
        this.clearLocalStorageForGuest(gid);

        // 一覧から削除し、次のguestを選択
        const idx = (this.guests || []).findIndex(x => Number(x?.id) === gid);
        this.guests = (this.guests || []).filter(x => Number(x?.id) !== gid);
        this.updateFilter();

        let nextId = null;
        if (this.filteredGuests && this.filteredGuests.length > 0) {
          // 元の位置の「次」を優先、なければ先頭
          const next = this.filteredGuests[Math.min(idx, this.filteredGuests.length - 1)];
          nextId = next ? Number(next.id) : null;
        }

        // session(selected_guest_id) もクリアしたいので、最終的に画面を遷移して確定させる
        // - 次があれば guest_id 付きで遷移（次を選択）
        // - 無ければ /data へ遷移（空表示）
        this.showDeleteGuestModal = false;
        if (nextId) {
          window.location.href = `/data?guest_id=${nextId}`;
        } else {
          window.location.href = `/data`;
        }
      } catch (e) {
        alert(e?.message || '削除に失敗しました。');
        this.deleteBusy = false;
      }
    },

    // ---- year modal ----
    warekiYearLabel(y){
      const yy = Number(y || 0) || 0;
      if (!yy) return '—';
      // 年だけなので 1/1 基準で元号判定（2019→平成31年）
      if (yy >= 2019) return `令和${yy - 2018}年`;
      if (yy >= 1989) return `平成${yy - 1988}年`;
      if (yy >= 1926) return `昭和${yy - 1925}年`;
      if (yy >= 1912) return `大正${yy - 1911}年`;
      return `${yy}年`;
    },
    formatYear(y){ return this.warekiYearLabel(y); },
    isPrivate(row){ return String(row?.visibility || 'shared') === 'private'; },
    buildYearOptions(){
      // 一旦：2025〜2035 に固定
      const minY = 2025, maxY = 2035;
      const ys = [];
      for(let y=maxY; y>=minY; y--){ ys.push(y); }
      this.yearOptions = ys;
    },
    openYearModal(row){
      this.targetRow = row;
      this.selectData(Number(row?.id) || this.selectedDataId);
      // デフォルトは「選択したデータの年度」
      const yRaw = Number(row?.kihu_year) || new Date().getFullYear();
      // 年度候補が 2025〜2035 なので範囲外データはクランプ（古いデータが残っている場合の保険）
      const y = Math.max(2025, Math.min(2035, yRaw));
      this.yearSelected = y;
      const baseName = (row?.data_name || 'default').toString();
      // ★選択は「まず既存に入る」が第一優先：初期値は元のデータ名
      this.nameSelected = baseName;
      this.noteText = '';  
      this.origYear = y;
      this.origName = baseName;
      this.showYearModal = true;
      // x-show にしたので基本不要だが、念のため確定
      this.$nextTick(() => { this.yearSelected = y; });
    },
    proceedByYear(){
      const cur = Number(this.targetRow?.kihu_year)||0;
      const sel = Number(this.yearSelected)||0;
      if(!this.targetRow?.id || !sel){ alert('年度を選択して下さい。'); return; }
      const nm = (this.nameSelected || '').toString();
      if(!nm){ alert('データ名を入力して下さい。'); return; }
      if(sel < 2025 || sel > 2035){
        alert('年度は 2025〜2035 の範囲で選択して下さい。');
        return;
      }

      // ★1) 年度もデータ名も変更なし → 複製せずそのまま入る
      if (Number(this.origYear) === sel && String(this.origName) === nm) {
        window.location.href = `/furusato/syori?data_id=${this.targetRow.id}`;
        return;
      }

      // ★2) 変更先(年度+データ名)が既に存在するなら、複製せず既存へ入る（選択優先）
      const existing = (this.datas || []).find(d => Number(d?.kihu_year) === sel && String(d?.data_name || 'default') === nm);
      if (existing && existing.id) {
        alert('同一年度・同一データ名のデータが存在するため、そのデータを開きます。');
        window.location.href = `/furusato/syori?data_id=${existing.id}`;
        return;
      }

      // 複製 → 年度/データ名 置換 → 新IDで遷移（同名衝突時はサーバが自動採番）
      fetch(`/api/data/${this.targetRow.id}/clone-year`, {
        method : 'POST',
        headers: {
          'Content-Type'     : 'application/json',
          'Accept'           : 'application/json',
          'X-Requested-With' : 'XMLHttpRequest',
          'X-CSRF-TOKEN'     : document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
        },
        body: JSON.stringify({ kihu_year: sel, data_name: nm })
      })
      .then(async (r) => {
        const text = await r.text();
        if (!r.ok) {
          try {
            const j = JSON.parse(text||'{}');
            throw new Error(j.message || '複製に失敗しました。');
          } catch {
            throw new Error(text || '複製に失敗しました。');
          }
        }
        return JSON.parse(text);
      })
      .then(json => {
        const newId = json?.id;
        if(!newId){ throw new Error('新規IDの取得に失敗しました。'); }
        if (json?.renamed_from) {
          alert(`同名が存在したためデータ名を「${json?.data_name || ''}」に変更して作成しました。`);
        }
        // 複製後の処理メニューへ遷移
        window.location.href = `/furusato/syori?data_id=${newId}`;
      })
      .catch(e => alert(e.message))
      .finally(() => { this.showYearModal=false; });
    },
    // ---- PDF ----
    openPdf(reportKey){
      if (!this.selectedDataId) return;
      const url = `/pdf/${encodeURIComponent(reportKey)}?data_id=${this.selectedDataId}`;
      window.open(url, '_blank', 'noopener');
    },
  }
}
</script>
@endpush

