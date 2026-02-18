<!-- views/tax/furusato/details/bunri_sakimono_details.blade.php -->
@extends('layouts.min')

@section('title', '先物取引 内訳')

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
@endphp
<div class="container-blue mt-2" style="width: 720px;">
  <div class="card-header d-flex align-items-start justify-content-between">
    <div class="d-flex align-items-start">
      @include('components.kado_lefttop_img')
      <h0 class="mb-0 mt-2 ms-2">内訳－先物取引</h0>
    </div>
  </div>
  <div class="card-body m-3">
      <form method="POST" action="{{ route('furusato.details.bunri_sakimono.save') }}">
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
          <div class="fw-bold ms-2 mb-1">{{ $label }}</div>
          @php $off = ($period === 'prev') ? $bunriPrevOff : $bunriCurrOff; @endphp
          <div class="table-responsive mb-2">
            <table class="table-input align-middle text-center">
              <thead>
                <tr>
                  <th style="height:30px;">収入金額</th>
                  <th>必要経費</th>
                  <th>所得金額</th>
                  <th>繰越損失の金額</th>
                  <th>繰越控除後の所得金額</th>
                </tr>
              </thead>
              <tbody>
                <tr>
                  @php($name = 'syunyu_sakimono_' . $period)
                  <td>
                    @if($off)
                      <input type="text" class="form-control suji11 text-center bg-light" readonly value="－">
                      <input type="hidden" name="{{ $name }}" value="0">
                    @else
                      <input type="text" inputmode="numeric" autocomplete="off"
                             data-format="comma-int" data-name="{{ $name }}"
                             class="form-control suji11 text-end"
                             value="{{ old($name, $inputs[$name] ?? null) }}">
                      <input type="hidden" name="{{ $name }}" value="{{ old($name, $inputs[$name] ?? null) }}">
                    @endif
                  </td>
                  @php($name = 'keihi_sakimono_' . $period)
                  <td>
                    @if($off)
                      <input type="text" class="form-control suji11 text-center bg-light" readonly value="－">
                      <input type="hidden" name="{{ $name }}" value="0">
                    @else
                      <input type="text" inputmode="numeric" autocomplete="off"
                             data-format="comma-int" data-name="{{ $name }}"
                             class="form-control suji11 text-end"
                             value="{{ old($name, $inputs[$name] ?? null) }}">
                      <input type="hidden" name="{{ $name }}" value="{{ old($name, $inputs[$name] ?? null) }}">
                     @endif
                  </td>
                  @php($name = 'shotoku_sakimono_' . $period)
                  <td>
                    @if($off)
                      <input type="text" class="form-control suji11 text-center bg-light" readonly value="－">
                      <input type="hidden" name="{{ $name }}" value="0">
                    @else
                     <input type="text" inputmode="numeric" autocomplete="off"
                             data-format="comma-int" data-name="{{ $name }}"
                             class="form-control suji11 text-end bg-light"
                             value="{{ old($name, $inputs[$name] ?? null) }}" readonly>
                      <input type="hidden" name="{{ $name }}" value="{{ old($name, $inputs[$name] ?? null) }}">
                     @endif
                  </td>
                  @php($name = 'kurikoshi_sakimono_' . $period)
                  <td>
                    @if($off)
                      <input type="text" class="form-control suji11 text-center bg-light" readonly value="－">
                      <input type="hidden" name="{{ $name }}" value="0">
                    @else
                     <input type="text" inputmode="numeric" autocomplete="off"
                             data-format="comma-int" data-name="{{ $name }}"
                             class="form-control suji11 text-end"
                             value="{{ old($name, $inputs[$name] ?? null) }}">
                      <input type="hidden" name="{{ $name }}" value="{{ old($name, $inputs[$name] ?? null) }}">
                     @endif
                  </td>
                  @php($name = 'shotoku_sakimono_after_kurikoshi_' . $period)
                  <td>
                    @if($off)
                      <input type="text" class="form-control suji11 text-center bg-light" readonly value="－">
                      <input type="hidden" name="{{ $name }}" value="0">
                    @else
                      <input type="text" inputmode="numeric" autocomplete="off"
                             data-format="comma-int" data-name="{{ $name }}"
                             class="form-control suji11 text-end bg-light"
                             value="{{ old($name, $inputs[$name] ?? null) }}" readonly>
                      <input type="hidden" name="{{ $name }}" value="{{ old($name, $inputs[$name] ?? null) }}">
                     @endif
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        @endforeach
        <hr class="mb-2">
        <div class="d-flex justify-content-between">
            <div>
              <button type="submit"
                      class="btn-base-green"
                      id="btn-recalc"
                      data-disable-on-submit>再計算</button>
            </div>
            <div class="d-flex">
              <button type="submit" class="btn-base-blue" id="btn-back">戻 る</button>
            </div>
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

  // ============================
  // comma-int（表示=3桁カンマ / 送信=生整数）ユーティリティ
  // ============================
  const toRawInt = (value) => {
    if (typeof value !== 'string') {
      value = String(value ?? '');
    }
    const stripped = value.replace(/,/g, '').trim();
    if (stripped === '' || stripped === '-') {
      return '';
    }
    if (!/^(-)?\d+$/.test(stripped)) {
      return '';
    }
    const parsed = parseInt(stripped, 10);
    return Number.isNaN(parsed) ? '' : parsed.toString();
  };

  const formatWithComma = (raw) => {
    if (raw === '') {
      return '';
    }
    const parsed = parseInt(raw, 10);
    return Number.isNaN(parsed) ? '' : parsed.toLocaleString('ja-JP');
  };

  const hiddenCache = new Map();

  const getHidden = (name) => {
    if (hiddenCache.has(name)) {
      return hiddenCache.get(name);
    }
    const hidden = document.querySelector(`input[type="hidden"][name="${name}"]`);
    if (hidden) {
      hiddenCache.set(name, hidden);
    }
    return hidden || null;
  };

  const getDisplay = (name) => document.querySelector(`[data-format="comma-int"][data-name="${name}"]`);

  const ensureHidden = (input) => {
    const name = input.dataset.name;
    if (!name) {
      return null;
    }

    let hidden = getHidden(name);
    if (!hidden) {
      hidden = document.createElement('input');
      hidden.type = 'hidden';
      hidden.name = name;
      hidden.dataset.commaMirror = '1';
      const parent = input.parentElement;
      if (parent) {
        parent.appendChild(hidden);
      } else {
        const form = input.closest('form');
        (form || document.body).appendChild(hidden);
      }
      hiddenCache.set(name, hidden);
    }

    const hiddenRaw = toRawInt(hidden.value ?? '');
    const inputRaw = toRawInt(input.value ?? '');
    const raw = hiddenRaw !== '' ? hiddenRaw : inputRaw;
    hidden.value = raw;
    input.value = raw === '' ? '' : formatWithComma(raw);

    return hidden;
  };

  const getIntValue = (name) => {
    const hidden = getHidden(name);
    if (!hidden) {
      return 0;
    }
    const raw = toRawInt(hidden.value ?? '');
    if (raw === '') {
      return 0;
    }
    const parsed = parseInt(raw, 10);
    return Number.isNaN(parsed) ? 0 : parsed;
  };

  const setValue = (name, value) => {
    const hidden = getHidden(name);
    const display = getDisplay(name);
    if (value === '' || value === null || typeof value === 'undefined' || Number.isNaN(value)) {
      if (hidden) {
        hidden.value = '';
      }
      if (display) {
        display.value = '';
      }
      return;
    }
    const raw = Math.trunc(Number(value)).toString();
    if (hidden) {
      hidden.value = raw;
    }
    if (display) {
      display.value = formatWithComma(raw);
    }
  };

  // ============================
  // 先物取引：表示はカンマ付き / 計算はhidden(生整数)で実施
  // ============================
  const recalc = (period) => {
    const syunyu = Math.trunc(Number(getIntValue(`syunyu_sakimono_${period}`)));
    const keihi  = Math.trunc(Number(getIntValue(`keihi_sakimono_${period}`)));
    const shotoku = Math.trunc(syunyu - keihi);
    setValue(`shotoku_sakimono_${period}`, shotoku);

    const kurikoshi = Math.trunc(Number(getIntValue(`kurikoshi_sakimono_${period}`)));
    const after = Math.trunc(Math.max(0, shotoku - kurikoshi));
    setValue(`shotoku_sakimono_after_kurikoshi_${period}`, after);
  };

  const inputs = document.querySelectorAll('[data-format="comma-int"]');

  inputs.forEach((input) => {
    const name = input.dataset.name;
    if (!name) {
      return;
    }

    ensureHidden(input);

    if (input.readOnly) {
      return;
    }

    input.addEventListener('focus', () => {
      const hidden = getHidden(name);
      input.value = hidden ? hidden.value : toRawInt(input.value ?? '');
      input.select();
    });

    input.addEventListener('blur', () => {
      const raw = toRawInt(input.value ?? '');
      const hidden = getHidden(name);
      if (hidden) {
        hidden.value = raw;
      }
      input.value = raw === '' ? '' : formatWithComma(raw);
      const match = name.match(/_(prev|curr)$/);
      if (match) {
        recalc(match[1]);
      }
    });
  });

  // 初期表示：hidden整合＋計算結果反映
  ['prev','curr'].forEach(recalc);

  // 送信直前ガード：hiddenへ最終同期（カンマ除去）
  const form = document.querySelector('form');
  if (form) {
    form.addEventListener('submit', () => {
      inputs.forEach((input) => {
        const name = input.dataset.name;
        if (!name) {
          return;
        }
        const hidden = getHidden(name) || ensureHidden(input);
        if (!hidden) {
          return;
        }
        const raw = toRawInt(input.value ?? hidden.value ?? '');
        hidden.value = raw;
      });
    });
  }
});
</script>
@endpush 

{{-- Enter移動（ふるさと全画面共通） --}}
@include('tax.furusato.partials.enter_nav')

@endsection