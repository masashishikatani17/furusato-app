{{-- resources/views/data/data_master.blade.php --}}
@extends('layouts.min')

@section('content')
<div class="container-blue">
  <div class="card-header d-flex align-items-start">
      <img src="{{ asset('storage/images/kado_lefttop.jpg') }}" alt="…">
      <h0 class="mb-0 mt-2"> お客様・年度一覧</h0>
      <!-- ヘッダの新規作成ボタンは下部帯に集約 -->
  </div>
  <div class="card-body">
    @php
      // Blade → Alpine 受け渡し用
      $guestsJson = $guests->map(fn($g) => [
        'id' => (int)$g->id,
        'name' => $g->name,
        'user_id' => (int)($g->user_id ?? 0),
      ]);
      $datasJson = $datas->map(fn($d) => [
        'id' => (int)$d->id,
        'guest_id' => (int)$d->guest_id,
        'kihu_year' => (int)$d->kihu_year,
        'owner_user_id' => (int)($d->owner_user_id ?? 0),
        'user_id' => (int)($d->user_id ?? 0),
        // 鍵マーク表示用（feature.data_privacyがfalseでもnullで来るだけなので安全）
        'visibility' => $d->visibility ?? null,
      ]);
    @endphp
  
    <div x-data="masterPane(@js($guestsJson), @js($datasJson), {{ $guestId ?? 'null' }})"
         x-init="init()" x-cloak class="border rounded p-3">
  
      <!-- 上部：検索（お客様名） -->
      <table align="center" class="table-beige ms-3 mb-3" style="width: 230px;">
        <tr>
           <td>
            <div class="d-flex align-items-center gap-2 ms-2 mt-1">
              <label class="mb-1">表示：</label>
              <div class="form-check form-check-inline">
                <input class="form-check-input" type="radio" value="all" x-model="viewMode" id="viewAll">
                <label class="form-check-label" for="viewAll">全表示</label>
              </div>
              <div class="form-check form-check-inline">
                <input class="form-check-input" type="radio" value="search" x-model="viewMode" id="viewSearch">
                <label class="form-check-label" for="viewSearch">検　索</label>
              </div>
              <template x-if="viewMode==='search'">
                <input type="text" class="form-control form-control-sm" style="width: 220px;"
                       placeholder="お客様名で検索" x-model="searchQuery"
                       @keyup.enter="updateFilter" @blur="updateFilter">
              </template>
            </div>
          </td> 
        </tr>   
      </table>
      <!-- 2ペイン -->
      <div class="d-flex gap-3 flex-nowrap justify-content-center" style="overflow-x:auto;">
        <!-- 左：お客様一覧 -->
        <div class="flex-shrink-0" style="width: 300px;">
          <table class="table table-bordered table-sm mb-2">
            <thead class="table-light">
            <tr><th class="text-center" style="width: 300px;height: 25px;background-color:#d0e5f4;">お客様名</th></tr>
            </thead>
          </table>
          <div class="border" style="max-height: 420px; overflow-y: auto;">
            <table class="table table-sm mb-0">
              <tbody>
              <template x-for="g in filteredGuests" :key="g.id">
                <tr :class="g.id===guestId ? 'table-primary' : ''" style="cursor:pointer;">
                  <td class="py-1 px-2" @click="selectGuest(g.id)" x-text="g.name" style="width: 300px;"></td>
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
          <table class="table table-bordered table-sm mb-2 align-middle">
            <thead class="table-light">
            <tr style="height:25px;">
              <th class="text-center" style="width: 150px;background-color:#d0e5f4;">
                年　度
                <button type="button" class="btn btn-sm btn-outline-primary ms-2 py-0 px-1"
                        style="font-size: 11px; height: 18px; line-height: 1;"
                        @click="toggleSort()"
                        :title="sortOrder==='desc' ? '新しい順→古い順に切替' : '古い順→新しい順に切替'">
                  ⇅
                </button>
              </th>
              <th class="text-center" style="width: 150px;background-color:#d0e5f4;">選　択</th>
            </tr>
            </thead>
          </table>
          <div class="border" style="max-height: 300px; overflow-y: auto;">
            <table class="table table-sm mb-0 align-middle">
              <tbody>
              <template x-for="d in filteredDatas" :key="d.id">
                <tr style="height: 25px; cursor:pointer;"
                    :class="selectedDataId===d.id ? 'table-active' : ''"
                    @click="selectedDataId=d.id">
                  <td class="text-end pe-3" style="width:150px;">
                    <template x-if="isPrivate(d)">
                      <span title="非共有（作成者のみ）">🔒</span>
                    </template>
                    <span x-text="formatYear(d.kihu_year)"></span>
                  </td>
                  <td class="text-center" style="width:150px;" nowrap="nowrap">
                    <div class="d-flex flex-column gap-1 align-items-center">
                      <button type="button" class="btn-base-blue"
                              @click.stop="selectedDataId=d.id; openYearModal(d)">選 択</button>
                    </div>
                  </td>
                </tr>
              </template>
              <template x-if="filteredDatas.length===0">
                <tr><td class="text-muted py-2 px-2" colspan="2">（年度データがありません）</td></tr>
              </template>
              </tbody>
            </table>
          </div>
        </div>
      </div>
      <hr>
      <!-- 下部ボタン帯（“ビル”と同じ配置） -->
                <div class="btn-footer">
                  <div class="d-flex justify-content-between">
                    <div class="d-flex flex-wrap gap-2">
                      <a href="{{ route('data.create') }}" class="btn-base-blue">新規データの作成</a>
                      <a :href="selectedDataId ? `/data/copyForm?data_id=${selectedDataId}` : '#'"
                         class="btn-base-blue"
                         :class="{'btn-disabled-link': !selectedDataId}"
                         :title="selectedDataId ? '' : 'コピーするデータを選択してください'">
                        既存データのコピー
                      </a>
                      <!-- ▼ PDF出力（分離課税） -->
                      <a href="#"
                         class="btn-base-blue"
                         @click.prevent="openPdf('bunri')"
                         :class="{'btn-disabled-link': !selectedDataId}"
                         :title="selectedDataId ? '確定申告書（分離課税）PDFを出力' : '年度データを選択してください'">
                        PDF出力（分離課税）
                      </a>
                    </div>
                    <div class="d-flex align-items-center">
                      <a href="{{ route('data.index') }}" class="btn-base-blue">戻 る</a>
                    </div>
                  </div>
                </div>
                 
      <!-- 年度変更モーダル（※ x-data の内側に配置することが重要） -->
      <template x-if="showYearModal">
        <div class="position-fixed top-0 start-0 w-100 h-100"
             style="background: rgba(0,0,0,.35); z-index: 1055;"
             @click.self="showYearModal=false">
          <div class="bg-white rounded shadow p-3"
               style="width:340px; margin:10vh auto;">
            <h6 class="mb-2">○年度の選択</h6>
            <div class="ms-3 mb-3">
              <hs>
              同じ年度を選ぶと既存データへ遷移します。<br>別の年度を選ぶと複製して新しいデータへ遷移します。
              <hs>
            </div>
            <div class="d-flex align-items-center gap-2">
              <label class="ms-3" style="width:50px;">年度</label>
              <select class="form-select form-select-sm" style="width:200px;" x-model.number="yearSelected">
                <template x-for="y in yearOptions" :key="y">
                  <option :value="y" x-text="y + '年'"></option>
                </template>
            </select>
            </div>
            <hr>
            <div class="d-flex justify-content-end gap-2">
            <button type="button" class="btn btn-base-blue" @click="proceedByYear()">決 定</button>
              <button type="button" class="btn btn-base-blue" @click="showYearModal=false">戻 る</button>
            </div>
          </div>
        </div>
      </template>
    </div> <!-- /x-data="masterPane(...)" -->
  </div>
</div>
@endsection

@push('scripts')
<script>
function masterPane(guestsInit, datasInit, guestIdInit) {
  return {
    // ---- state ----
    ready: false,
    guests: guestsInit || [],
    filteredGuests: guestsInit || [],
    datas: datasInit || [],
    filteredDatas: datasInit || [],
    guestId: guestIdInit || null,
    viewMode: 'all',
    searchQuery: '',
    sortOrder: 'desc', // 'desc' = 新しい年度優先 / 'asc' = 古い年度優先
    selectedDataId: null, // ▼ 画面下の「既存データのコピー」で使用する選択ID
    showYearModal: false,
    yearSelected: null,
    yearOptions: [],
    targetRow: null,

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
        this.updateDataFilter();
      }

      // 3) 初期選択IDをセット
      if (this.filteredDatas.length > 0) {
        this.selectedDataId = this.filteredDatas[0].id;
      }
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
      this.updateDataFilter();
    },
    getYearKey(d){ return Number(d?.kihu_year || 0) || 0; },
    sortDatas(list){
      const arr = [...list];
      arr.sort((a,b) => {
        const ak = this.getYearKey(a), bk = this.getYearKey(b);
        if (ak === bk) return this.sortOrder==='desc' ? (b.id - a.id) : (a.id - b.id);
        return this.sortOrder==='desc' ? (bk - ak) : (ak - bk);
      });
      return arr;
    },
    updateDataFilter(){ this.filteredDatas = this.sortDatas(this.datas); },
    toggleSort(){ this.sortOrder = (this.sortOrder==='desc') ? 'asc' : 'desc'; this.updateDataFilter(); },

    // ---- year modal ----
    formatYear(y){ return (Number(y)||0) ? `${y}年` : '—'; },
    isPrivate(row){ return String(row?.visibility || 'shared') === 'private'; },
    buildYearOptions(){
      const now = new Date().getFullYear();
      const ys = [];
      for(let y=now+10; y>=now-10; y--){ ys.push(y); }
      this.yearOptions = ys;
    },
    openYearModal(row){
      this.targetRow = row;
      this.selectedDataId = Number(row?.id) || this.selectedDataId;
      this.yearSelected = Number(row?.kihu_year) || new Date().getFullYear();
      this.showYearModal = true;
    },
    proceedByYear(){
      const cur = Number(this.targetRow?.kihu_year)||0;
      const sel = Number(this.yearSelected)||0;
      if(!this.targetRow?.id || !sel){ alert('年度を選択してください。'); return; }
      if(cur === sel){
        // 既存データの処理メニューへ遷移
        window.location.href = `/furusato/syori?data_id=${this.targetRow.id}`;
        return;
      }
      // 複製 → 年度置換 → 新IDで遷移
      fetch(`/api/data/${this.targetRow.id}/clone-year`, {
        method : 'POST',
        headers: {
          'Content-Type'     : 'application/json',
          'Accept'           : 'application/json',
          'X-Requested-With' : 'XMLHttpRequest',
          'X-CSRF-TOKEN'     : document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
        },
        body: JSON.stringify({ kihu_year: sel })
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

