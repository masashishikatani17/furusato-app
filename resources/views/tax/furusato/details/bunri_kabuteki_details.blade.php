<!-- views/tax/furusato/details/bunri_kabuteki_details.blade.php -->
@extends('layouts.min')

@section('title', '株式等の譲渡所得等 内訳')

@section('content')
@php
    $inputs = $out['inputs'] ?? [];
    $warekiPrevLabel = $warekiPrev ?? '前年';
    $warekiCurrLabel = $warekiCurr ?? '当年';
    $bunriPrevOff = (int)($syoriSettings['bunri_flag_prev'] ?? $syoriSettings['bunri_flag'] ?? 0) === 0;
    $bunriCurrOff = (int)($syoriSettings['bunri_flag_curr'] ?? $syoriSettings['bunri_flag'] ?? 0) === 0;
    $originTabRaw = request()->input('origin_tab', 'input');
    $originTab = is_string($originTabRaw) && trim($originTabRaw) === 'input' ? 'input' : '';
    $originSubtabRaw = request()->input('origin_subtab', 'bunri');
    $originSubtabCandidate = is_string($originSubtabRaw) ? preg_replace('/[^A-Za-z0-9_-]/', '', trim($originSubtabRaw)) : '';
    $originSubtab = in_array($originSubtabCandidate, ['bunri', 'sogo', 'prev', 'curr'], true) ? $originSubtabCandidate : 'bunri';
    $originAnchor = preg_replace('/[^A-Za-z0-9_-]/', '', (string) request()->input('origin_anchor', ''));
    $rows = [
        ['label' => '一般株式等の譲渡', 'key' => 'ippan_joto', 'has_kurikoshi' => false],
        ['label' => '上場株式等の譲渡', 'key' => 'jojo_joto', 'has_kurikoshi' => true],
        ['label' => '上場株式等の配当等', 'key' => 'jojo_haito', 'has_kurikoshi' => false],
    ];
@endphp
<div class="container-blue mt-2" style="width: 980px;">
  <div class="card-header d-flex align-items-start justify-content-between">
    <div class="d-flex align-items-start">
      <img src="{{ asset('storage/images/kado_lefttop.jpg') }}" alt="…">
      <h0 class="mt-2 ms-2">内訳－株式等の譲渡所得等</h0>
    </div>
  </div>
  <div class="card-body m-3">
      <form method="POST" action="{{ route('furusato.details.bunri_kabuteki.save') }}">
        @csrf
        <input type="hidden" name="data_id" value="{{ $dataId }}">
        <input type="hidden" name="origin_tab" value="{{ $originTab }}">
        <input type="hidden" name="origin_subtab" value="{{ $originSubtab }}">
        <input type="hidden" name="origin_anchor" value="{{ $originAnchor }}">
        <input type="hidden" name="redirect_to" value="input">
        <input type="hidden" name="recalc_all" value="1">
        <input type="hidden" name="stay_on_details" id="stay-on-details-flag" value="0">

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
          @php $off = ($period === 'prev') ? $bunriPrevOff : $bunriCurrOff; @endphp
          <div class="table-responsive mb-2">
            <table class="table-input align-middle text-center">
              <thead>
                <tr>
                  <th style="height:30px;"></th>
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
                      @if($off)
                        <input type="text" class="form-control suji11 text-center bg-light" readonly value="－">
                        <input type="hidden" name="{{ $name }}" value="0">
                      @else
                        <input type="text" inputmode="numeric" autocomplete="off"
                               data-format="comma-int" data-name="{{ $name }}"
                               class="form-control suji11 text-end"
                               value="{{ old($name, $inputs[$name] ?? null) }}">
                      @endif
                    </td>
                    @php($name = 'keihi_' . $base)
                    <td>
                      @if($off)
                        <input type="text" class="form-control suji11 text-center bg-light" readonly value="－">
                        <input type="hidden" name="{{ $name }}" value="0">
                      @else
                        <input type="text" inputmode="numeric" autocomplete="off"
                               data-format="comma-int" data-name="{{ $name }}"
                               class="form-control suji11 text-end"
                               value="{{ old($name, $inputs[$name] ?? null) }}">
                      @endif
                    </td>
                    @php($name = 'shotoku_' . $base)
                    <td>
                      @if($off)
                        <input type="text" class="form-control suji11 text-center bg-light" readonly value="－">
                        <input type="hidden" name="{{ $name }}" value="0">
                      @else
                        <input type="text" inputmode="numeric" autocomplete="off"
                               class="form-control suji11 text-end bg-light"
                               value="{{ (isset($inputs[$name]) && $inputs[$name] !== '' && $inputs[$name] !== null)
                                   ? number_format((int) $inputs[$name])
                                   : '' }}"
                               data-display-key="{{ $name }}"
                               readonly>
                      @endif
                    </td>
                    @php($name = 'tsusango_' . $base)
                    <td>
                      @if($off)
                        <input type="text" class="form-control suji11 text-center bg-light" readonly value="－">
                        <input type="hidden" name="{{ $name }}" value="0">
                      @else
                        <input type="text" inputmode="numeric" autocomplete="off"
                               class="form-control suji11 text-end bg-light"
                               value="{{ (isset($inputs[$name]) && $inputs[$name] !== '' && $inputs[$name] !== null)
                                   ? number_format((int) $inputs[$name])
                                   : '' }}"
                               readonly>
                      @endif
                    </td>
                    <td>
                      @if ($row['has_kurikoshi'])
                        @php($name = 'kurikoshi_' . $base)
                        @if($off)
                          <input type="text" class="form-control suji11 text-center bg-light" readonly value="－">
                          <input type="hidden" name="{{ $name }}" value="0">
                        @else
                          <input type="text" inputmode="numeric" autocomplete="off"
                                 data-format="comma-int" data-name="{{ $name }}"
                                 class="form-control suji11 text-end"
                                 value="{{ old($name, $inputs[$name] ?? null) }}">
                        @endif
                      @else
                        <span class="d-inline-block w-100">－</span>
                      @endif
                    </td>
                    @php($name = 'shotoku_after_kurikoshi_' . $base)
                    <td>
                      @if($off)
                        <input type="text" class="form-control suji11 text-center bg-light" readonly value="－">
                        <input type="hidden" name="{{ $name }}" value="0">
                      @else
                        <input type="text" inputmode="numeric" autocomplete="off"
                               class="form-control suji11 text-end bg-light"
                               value="{{ (isset($inputs[$name]) && $inputs[$name] !== '' && $inputs[$name] !== null)
                                   ? number_format((int) $inputs[$name])
                                   : '' }}"
                               readonly>
                      @endif
                    </td>
                  </tr>
                @endforeach
              </tbody>
            </table>
          </div>
        @endforeach
        <hr class="mb-2">
        <div class="text-end gap-2">
          <button type="submit" class="btn-base-blue" id="btn-back">戻 る</button>
          <button type="submit"
                  class="btn-base-green"
                  data-disable-on-submit>再計算</button>
        </div>
      </form>
  </div>
</div>


@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
  // ===== stay_on_details フラグの確実送信（プログラム submit でも失われないように） =====
  (function ensureStayFlag() {
    const form = document.querySelector('form');
    if (!form) return;
    const stayFlag = form.querySelector('#stay-on-details-flag');
    const btnBack = form.querySelector('#btn-back');
    const btnRecalc = form.querySelector('#btn-recalc');

    if (btnBack) {
      btnBack.addEventListener('click', () => { if (stayFlag) stayFlag.value = '0'; });
    }
    if (btnRecalc) {
      btnRecalc.addEventListener('click', () => { if (stayFlag) stayFlag.value = '1'; });
    }
    // 念のため submit 直前にも最終補正（submitterが失われても安全）
    form.addEventListener('submit', () => {
      if (!stayFlag || (stayFlag.value !== '0' && stayFlag.value !== '1')) {
        stayFlag.value = '0';
      }
    });
  })();
  // ===== カンマ整形 + hidden 連携（入力欄のみ：syunyu/keihi/kurikoshi）=====
  // この画面では、所得金額/損益通算後/繰越控除後はサーバ確定値の「表示専用」。
  // JSで再計算すると古い値の混入源になるため、ここでは「入力欄の整形とPOST用hidden同期」だけ行う。
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
  const getDisplayByKey = (name) => document.querySelector(`[data-display-key="${name}"]`);

  // signed int をカンマ整形して readonly 表示欄へ入れる（所得金額だけ）
  const setDisplayInt = (displayKey, value) => {
    const el = getDisplayByKey(displayKey);
    if (!el) return;
    const n = Math.trunc(Number(value) || 0);
    // 0 は "0" を出す（空欄にしたいならここを '' に変えてOK）
    el.value = n.toLocaleString('ja-JP');
  };

  // hidden から整数を取得（無ければ0）
  const V = (name) => {
    const h = getHidden(name);
    const raw = toRawInt(String(h?.value ?? ''));
    const n = raw === '' ? 0 : parseInt(raw, 10);
    return Number.isNaN(n) ? 0 : n;
  };

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
    // 初回同期（表示値→raw→hidden）
    const raw = toRawInt(displayInput.value ?? '');
    hidden.value = raw;
    displayInput.value = raw === '' ? '' : formatWithComma(raw);
    return hidden;
  };

  // ===== 表示 input（data-name）に hidden を用意し、blurで3桁カンマ化 =====
  const displays = Array.from(document.querySelectorAll('[data-format="comma-int"][data-name]'));
  displays.forEach((input) => {
    const name = input.dataset.name;
    if (!name) return;
    ensureHidden(input);
    // 初期表示（hiddenがあればそれ、なければ表示値から）
    const h = getHidden(name);
    const raw = toRawInt((h?.value ?? '') !== '' ? (h?.value ?? '') : (input.value ?? ''));
    if (h) h.value = raw;
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

      // 所得金額（=収入-経費）だけを即時再計算して表示
      const m = name.match(/^(syunyu|keihi)_(ippan_joto|jojo_joto|jojo_haito)_(prev|curr)$/);
      if (m) {
        const rowKey = m[2];
        const period = m[3];
        const syunyuKey = `syunyu_${rowKey}_${period}`;
        const keihiKey  = `keihi_${rowKey}_${period}`;
        const shotokuKey = `shotoku_${rowKey}_${period}`;
        const syunyu = V(syunyuKey);
        const keihi  = V(keihiKey);
        setDisplayInt(shotokuKey, syunyu - keihi);
      }
    });
  });
  // 初期表示でも「所得金額（収入−経費）」だけは整合させる
  (function initShotokuDisplay() {
    ['prev','curr'].forEach((period) => {
      ['ippan_joto','jojo_joto','jojo_haito'].forEach((rowKey) => {
        const syunyu = V(`syunyu_${rowKey}_${period}`);
        const keihi  = V(`keihi_${rowKey}_${period}`);
        setDisplayInt(`shotoku_${rowKey}_${period}`, syunyu - keihi);
      });
    });
  })();

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
 
{{-- Enter移動（ふるさと全画面共通） --}}
@include('tax.furusato.partials.enter_nav')

@endsection