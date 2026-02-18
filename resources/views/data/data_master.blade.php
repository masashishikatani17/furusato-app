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
</style>
<div class="container-blue" style="width:800px;">
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
    <div x-data="masterPane(@js($guestsJson), @js($datasJson), {{ $guestId ?? 'null' }})"
         x-init="init()" x-cloak class="border-0 rounded p-3">
  
      <!-- 上部：検索（お客様名） -->
      <table align="center" class="table-beige mt-0 ms-3 mb-3" style="width: 230px;">
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
          <table class="table table-base mb-2 align-middle">
            <thead class="table-light">
            <tr style="height:25px;">
              <th class="text-center" style="width: 80px;">
                年 度
                <button type="button" class="btn btn-sm btn-outline-primary ms-1 py-0 px-1"
                        style="font-size: 11px; height: 18px; line-height: 1;"
                        @click="toggleSort()"
                        :title="sortOrder==='desc' ? '新しい順→古い順に切替' : '古い順→新しい順に切替'">
                  ⇅
                </button>
              </th>
              <th class="text-center" style="width: 220px;">データ名</th>
              <th class="text-center" style="width: 60px;">選 択</th>
              <th class="text-center" style="width: 60px;">編 集</th>
            </tr>
            </thead>
          </table>
          <div class="mt-4" style="max-height: 300px; overflow-y: auto;">
            <table class="table table-compact-p mb-0 align-middle">
              <tbody>
              <template x-for="d in filteredDatas" :key="d.id">
                <tr style="height: 25px; cursor:pointer;"
                    :class="selectedDataId===d.id ? 'table-active' : ''"
                    @click="selectedDataId=d.id">
                  <td class="text-end" style="width:80px;">
                    <div class="d-inline-flex align-items-center justify-content-end pe-1" style="width:100%;">
                      <template x-if="isPrivate(d)">
                        <span title="非共有（作成者のみ）">🔒</span>
                      </template>
                      <span x-text="formatYear(d.kihu_year)"></span>
                    </div>
                  </td>
                  <td class="text-start" style="width:220px;">
                    <span x-text="d.data_name || 'default'"></span>
                  </td>
                  <td class="text-center bg-cream b-none" style="width:60px;" nowrap="nowrap">
                    
                      <button type="button" class="btn-base-blue" style="width:60px;"
                              @click.stop="selectedDataId=d.id; openYearModal(d)">選 択</button>
                  </td>
                  <td class="text-center bg-cream b-none" style="width:60px;" nowrap="nowrap">
                      <button type="button" class="btn-base-blue" style="width:60px;"
                              @click.stop="window.location.href = `/data/${d.id}/edit`">編 集</button>
                  </td>
                </tr>
              </template>
              <template x-if="filteredDatas.length===0">
                <tr><td class="text-muted py-2 px-2" colspan="4">（年度データがありません）</td></tr>
              </template>
              </tbody>
            </table>
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
                         :title="selectedDataId ? '' : 'コピーするデータを選択してください'">
                        既存データのコピー
                      </a>
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
    </div> <!-- /x-data="masterPane(...)" -->
  
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
    nameSelected: '',
    yearOptions: [],
    targetRow: null,
    noteText: '',
    origYear: null,
    origName: '',

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
    updateDataFilter(){ this.filteredDatas = this.sortDatas(this.datas); },
    toggleSort(){ this.sortOrder = (this.sortOrder==='desc') ? 'asc' : 'desc'; this.updateDataFilter(); },

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
      this.selectedDataId = Number(row?.id) || this.selectedDataId;
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
      if(!this.targetRow?.id || !sel){ alert('年度を選択してください。'); return; }
      const nm = (this.nameSelected || '').toString();
      if(!nm){ alert('データ名を入力してください。'); return; }
      if(sel < 2025 || sel > 2035){
        alert('年度は 2025〜2035 の範囲で選択してください。');
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

