<!-- views/tax/furusato/details/bunri_kabuteki_details.blade.php -->
@extends('layouts.min')

@section('title', '株式等の譲渡所得等 内訳')

@section('content')
@php
    $inputs = $out['inputs'] ?? [];
    $warekiPrevLabel = $warekiPrev ?? '前年';
    $warekiCurrLabel = $warekiCurr ?? '当年';
    $originTabRaw = request()->input('origin_tab', 'input');
    $originTab = is_string($originTabRaw) && trim($originTabRaw) === 'input' ? 'input' : '';
    $originAnchor = preg_replace('/[^A-Za-z0-9_-]/', '', (string) request()->input('origin_anchor', ''));
    $rows = [
        ['label' => '一般株式等の譲渡', 'key' => 'ippan_joto', 'has_kurikoshi' => false],
        ['label' => '上場株式等の譲渡', 'key' => 'jojo_joto', 'has_kurikoshi' => true],
        ['label' => '上場株式等の配当等', 'key' => 'jojo_haito', 'has_kurikoshi' => false],
    ];
@endphp
<div class="container-blue mt-2" style="width: 1050px;">
  <div class="card-header d-flex align-items-start justify-content-between">
    <div class="d-flex align-items-start">
      <img src="{{ asset('storage/images/kado_lefttop.jpg') }}" alt="…">
      <h0 class="mb-0 mt-2 ms-2">内訳－株式等の譲渡所得等</h0>
    </div>
  </div>
  <div class="card-body">
  　<div class="wrapper">
      <form method="POST" action="{{ route('furusato.details.bunri_kabuteki.save') }}">
        @csrf
        <input type="hidden" name="data_id" value="{{ $dataId }}">
        <input type="hidden" name="origin_tab" value="{{ $originTab }}">
        <input type="hidden" name="origin_anchor" value="{{ $originAnchor }}">
        <input type="hidden" name="redirect_to" value="input">
        <input type="hidden" name="recalc_all" value="1">

        @if ($errors->any())
          <div class="alert alert-danger">
            <ul class="mb-0">
              @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
              @endforeach
            </ul>
          </div>
        @endif

        @foreach (['prev' => $warekiPrevLabel, 'curr' => $warekiCurrLabel] as $period => $label)
          <div class="fw-bold mb-1">{{ $label }}</div>
          <div class="table-responsive mb-2">
            <table class="table-base table-bordered align-middle text-center">
              <thead>
                <tr>
                  <th style="width: 150px; height:30px;"></th>
                  <th>収入金額</th>
                  <th>必要経費</th>
                  <th>所得金額</th>
                  <th>損益通算後</th>
                  <th>繰越損失の金額</th>
                  <th>繰越控除後の所得金額</th>
                </tr>
              </thead>
              <tbody>
                @foreach ($rows as $row)
                  @php($base = $row['key'] . '_' . $period)
                  <tr>
                    <th class="text-start align-middle th-ddd ps-1">{{ $row['label'] }}</th>
                    @php($name = 'syunyu_' . $base)
                    <td>
                      <input type="text" inputmode="numeric" autocomplete="off"
                             data-format="comma-int" data-name="{{ $name }}"
                             class="form-control suji11 text-end"
                             value="{{ old($name, $inputs[$name] ?? null) }}">
                    </td>
                    @php($name = 'keihi_' . $base)
                    <td>
                      <input type="text" inputmode="numeric" autocomplete="off"
                             data-format="comma-int" data-name="{{ $name }}"
                             class="form-control suji11 text-end"
                             value="{{ old($name, $inputs[$name] ?? null) }}">
                    </td>
                    @php($name = 'shotoku_' . $base)
                    <td>
                      <input type="text" inputmode="numeric" autocomplete="off"
                             data-format="comma-int" data-name="{{ $name }}"
                             class="form-control suji11 text-end bg-light"
                             value="{{ old($name, $inputs[$name] ?? null) }}" readonly>
                    </td>
                    @php($name = 'tsusango_' . $base)
                    <td>
                      <input type="text" inputmode="numeric" autocomplete="off"
                             data-format="comma-int" data-name="{{ $name }}"
                             class="form-control suji11 text-end bg-light"
                             value="{{ old($name, $inputs[$name] ?? null) }}" readonly>
                    </td>
                    <td>
                      @if ($row['has_kurikoshi'])
                        @php($name = 'kurikoshi_' . $base)
                        <input type="text" inputmode="numeric" autocomplete="off"
                               data-format="comma-int" data-name="{{ $name }}"
                               class="form-control suji11 text-end"
                               value="{{ old($name, $inputs[$name] ?? null) }}">
                      @else
                        <span class="d-inline-block w-100">－</span>
                      @endif
                    </td>
                    @php($name = 'shotoku_after_kurikoshi_' . $base)
                    <td>
                      <input type="text" inputmode="numeric" autocomplete="off"
                             data-format="comma-int" data-name="{{ $name }}"
                             class="form-control suji11 text-end bg-light"
                             value="{{ old($name, $inputs[$name] ?? null) }}" readonly>
                    </td>
                  </tr>
                @endforeach
              </tbody>
            </table>
          </div>
        @endforeach
        <hr>
        <div class="text-end me-2 mb-3">
          <button type="submit" class="btn btn-base-blue me-2">入力画面へ戻る</button>
          <button type="submit"
                  class="btn btn-base-green"
                  name="stay_on_details"
                  value="1"
                  data-disable-on-submit>再計算</button>
        </div>
      </form>
    </div>
  </div>
</div>


@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
  // ===== カンマ整形 + hidden 連携（表示は常に3桁カンマ、POSTは数値のみ）=====
  const toRawInt = (value) => {
    if (typeof value !== 'string') return '';
    const stripped = value.replace(/,/g, '').trim();
    if (stripped === '' || stripped === '-') return '';
    if (!/^(-)?\d+$/.test(stripped)) return '';
    const n = parseInt(stripped, 10);
    return Number.isNaN(n) ? '' : String(n);
  };
  const formatWithComma = (raw) => {
    if (raw === '') return '';
    const n = parseInt(raw, 10);
    return Number.isNaN(n) ? '' : n.toLocaleString('ja-JP');
  };
  const hiddenCache = new Map();
  const getHidden = (name) => {
    if (hiddenCache.has(name)) return hiddenCache.get(name);
    const h = document.querySelector(`input[type="hidden"][name="${name}"]`);
    if (h) hiddenCache.set(name, h);
    return h || null;
  };
  const getDisplay = (name) => document.querySelector(`[data-format="comma-int"][data-name="${name}"]`);
  const ensureHidden = (displayInput) => {
    const name = displayInput?.dataset?.name;
    if (!name) return null;
    let hidden = getHidden(name);
    if (!hidden) {
      hidden = document.createElement('input');
      hidden.type = 'hidden';
      hidden.name = name;
      hidden.dataset.commaMirror = '1';
      (displayInput.parentElement || displayInput.closest('form') || document.body).appendChild(hidden);
      hiddenCache.set(name, hidden);
    }
    // 初回同期
    const hiddenRaw = toRawInt(hidden.value ?? '');
    const inputRaw  = toRawInt(displayInput.value ?? '');
    const raw = hiddenRaw !== '' ? hiddenRaw : inputRaw;
    hidden.value = raw;
    displayInput.value = raw === '' ? '' : formatWithComma(raw);
    return hidden;
  };
  const V = (name) => { // get int
    const h = getHidden(name);
    if (!h) return 0;
    const raw = toRawInt(h.value ?? '');
    if (raw === '') return 0;
    const n = parseInt(raw, 10);
    return Number.isNaN(n) ? 0 : n;
  };
  const S = (name, value) => { // set int (sync hidden + display)
    const h = getHidden(name);
    const d = getDisplay(name);
    if (value === '' || value === null || typeof value === 'undefined' || Number.isNaN(value)) {
      if (h) h.value = '';
      if (d) d.value = '';
      return;
    }
    const raw = String(Math.trunc(Number(value) || 0));
    if (h) h.value = raw;
    if (d) d.value = formatWithComma(raw);
  };

  const rows = [
    { key: 'ippan_joto', hasKurikoshi: false },
    { key: 'jojo_joto', hasKurikoshi: true },
    { key: 'jojo_haito', hasKurikoshi: false },
  ];

  const syncIppanTsusango = (period) => {
    const base = `ippan_joto_${period}`;
    const shotoku = V(`shotoku_${base}`);
    S(`tsusango_${base}`, shotoku);
  };

  const recalc = (period) => {
    rows.forEach((row) => {
      const base = `${row.key}_${period}`;
      const syunyu  = V(`syunyu_${base}`);
      const keihi   = V(`keihi_${base}`);
      const shotoku = syunyu - keihi;
      S(`shotoku_${base}`, shotoku);

      let tsusango;
      if (row.key === 'ippan_joto') {
        tsusango = shotoku;
        S(`tsusango_${base}`, tsusango);
      } else {
        tsusango = V(`tsusango_${base}`);
      }
      const kurikoshi = row.hasKurikoshi ? V(`kurikoshi_${base}`) : 0;
      const deduction = row.hasKurikoshi ? Math.min(Math.max(tsusango, 0), Math.max(kurikoshi, 0)) : 0;
      const after = row.hasKurikoshi ? tsusango - deduction : tsusango;
      S(`shotoku_after_kurikoshi_${base}`, after);
    });
  };

  // ===== 表示 input（data-name）に hidden を用意し、blurで3桁カンマ化 =====
  const displays = Array.from(document.querySelectorAll('[data-format="comma-int"][data-name]'));
  displays.forEach((input) => {
    const name = input.dataset.name;
    if (!name) return;
    ensureHidden(input);
    // 初期表示は常にカンマ整形
    const h = getHidden(name);
    const raw = toRawInt(h?.value ?? input.value ?? '');
    input.value = raw === '' ? '' : formatWithComma(raw);
    if (input.readOnly) return;
    input.addEventListener('focus', () => {
      const hidden = getHidden(name);
      input.value = hidden ? hidden.value : toRawInt(input.value ?? '');
      input.select();
    });
    input.addEventListener('blur', () => {
      const hidden = getHidden(name) || ensureHidden(input);
      const raw = toRawInt(input.value ?? hidden?.value ?? '');
      if (hidden) hidden.value = raw;
      input.value = raw === '' ? '' : formatWithComma(raw);
      const m = name.match(/_(prev|curr)$/);
      if (m) recalc(m[1]);
    });
  });

  recalc('prev');
  recalc('curr');

  // 送信直前：hiddenへ数値を確実に格納（表示のnameは元から無いのでチラつき無し）
  const form = document.querySelector('form');
  if (form) {
    form.addEventListener('submit', () => {
      displays.forEach((input) => {
        const name = input.dataset.name;
        if (!name) return;
        const hidden = getHidden(name) || ensureHidden(input);
        const raw = toRawInt(input.value ?? hidden?.value ?? '');
        if (hidden) hidden.value = raw;
      });
    });
  }
});
</script>
@endpush
@endsection