<!-- resources/views/tax/furusato/details/bunri_sanrin_details.blade.php -->
@extends('layouts.min')

@section('title', '山林所得 内訳')

@section('content')
@php
    $inputs = $out['inputs'] ?? [];
    $warekiPrevLabel = $warekiPrev ?? '前年';
    $warekiCurrLabel = $warekiCurr ?? '当年';
    $originTabRaw = request()->input('origin_tab', 'input');
    $originTab = is_string($originTabRaw) && trim($originTabRaw) === 'input' ? 'input' : '';
    $originAnchor = preg_replace('/[^A-Za-z0-9_-]/', '', (string) request()->input('origin_anchor', ''));
@endphp
<div class="container-blue mt-2" style="width: 800px;">
  <div class="card-header d-flex align-items-start justify-content-between">
    <div class="d-flex align-items-start">
      <img src="{{ asset('storage/images/kado_lefttop.jpg') }}" alt="…">
      <h0 class="mb-0 mt-2 ms-2">内訳－山林所得</h0>
    </div>
  </div>
  <div class="card-body">
  　<div class="wrapper">
      <form method="POST" action="{{ route('furusato.details.bunri_sanrin.save') }}">
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
                  <th style="height:30px;">収入金額</th>
                  <th>必要経費</th>
                  <th>差引金額</th>
                  <th>特別控除額</th>
                  <th>山林所得金額</th>
                </tr>
              </thead>
              <tbody>
                <tr>
                  @php($name = 'syunyu_sanrin_' . $period)
                  <td>
                    <input type="text" inputmode="numeric" autocomplete="off"
                           data-format="comma-int" data-name="{{ $name }}"
                           class="form-control suji11 text-end"
                           value="{{ old($name, $inputs[$name] ?? null) }}">
                  </td>
                  @php($name = 'keihi_sanrin_' . $period)
                  <td>
                    <input type="text" inputmode="numeric" autocomplete="off"
                           data-format="comma-int" data-name="{{ $name }}"
                           class="form-control suji11 text-end"
                           value="{{ old($name, $inputs[$name] ?? null) }}">
                  </td>
                  @php($name = 'sashihiki_sanrin_' . $period)
                  <td>
                    <input type="text" inputmode="numeric" autocomplete="off"
                           data-format="comma-int" data-name="{{ $name }}"
                           class="form-control suji11 text-end bg-light"
                           value="{{ old($name, $inputs[$name] ?? null) }}" readonly>
                  </td>
                  @php($name = 'tokubetsukojo_sanrin_' . $period)
                  <td>
                    <input type="text" inputmode="numeric" autocomplete="off"
                           data-format="comma-int" data-name="{{ $name }}"
                           class="form-control suji11 text-end bg-light"
                           value="{{ old($name, $inputs[$name] ?? null) }}" readonly>
                  </td>
                  @php($name = 'shotoku_sanrin_' . $period)
                  <td>
                    <input type="text" inputmode="numeric" autocomplete="off"
                           data-format="comma-int" data-name="{{ $name }}"
                           class="form-control suji11 text-end bg-light"
                           value="{{ old($name, $inputs[$name] ?? null) }}" readonly>
                  </td>
                </tr>
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
  const toRawInt = (value) => {
    if (typeof value !== 'string') {
      return '';
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

  const periods = ['prev', 'curr'];

  const recalc = (period) => {
    // 差引＝収入−経費（小数点以下は切り捨てのみ／千円丸めはここではしない）
    const syunyu = Math.trunc(Number(getIntValue(`syunyu_sanrin_${period}`)));
    const keihi  = Math.trunc(Number(getIntValue(`keihi_sanrin_${period}`)));
    const sashihiki = Math.trunc(syunyu - keihi);
    setValue(`sashihiki_sanrin_${period}`, sashihiki);

    // 特別控除＝min(500,000, max(0, 差引))（小数点以下切捨）
    const tokubetsu = Math.trunc(Math.min(500000, Math.max(0, sashihiki)));
    setValue(`tokubetsukojo_sanrin_${period}`, tokubetsu);

    // 「山林所得金額」はここでは算出しない（= after_3 の最終値をサーバ側/全体再計算で反映させる）
    // setValue(`shotoku_sanrin_${period}`, ...) は行わない
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

  periods.forEach(recalc);

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
@endsection