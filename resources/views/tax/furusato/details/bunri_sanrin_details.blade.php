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
        <input type="hidden" name="redirect_to" value="">

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
                  <th>損益通算後</th>
                  <th>特別控除額</th>
                  <th>山林所得金額</th>
                </tr>
              </thead>
              <tbody>
                <tr>
                  @php($name = 'syunyu_sanrin_' . $period)
                  <td>
                    <input type="text" inputmode="numeric" data-format="comma-int" class="form-control suji11 text-end" name="{{ $name }}" value="{{ old($name, $inputs[$name] ?? null) }}">
                  </td>
                  @php($name = 'keihi_sanrin_' . $period)
                  <td>
                    <input type="text" inputmode="numeric" data-format="comma-int" class="form-control suji11 text-end" name="{{ $name }}" value="{{ old($name, $inputs[$name] ?? null) }}">
                  </td>
                  @php($name = 'sashihiki_sanrin_' . $period)
                  <td>
                    <input type="text" inputmode="numeric" data-format="comma-int" class="form-control suji11 text-end bg-light" name="{{ $name }}" value="{{ old($name, $inputs[$name] ?? null) }}" readonly>
                  </td>
                  @php($name = 'after_3jitsusan_sanrin_' . $period)
                  <td>
                    <input type="text" inputmode="numeric" data-format="comma-int" class="form-control suji11 text-end bg-light" name="{{ $name }}" value="{{ old($name, $inputs[$name] ?? null) }}" readonly>
                  </td>
                  @php($name = 'tokubetsukojo_sanrin_' . $period)
                  <td>
                    <input type="text" inputmode="numeric" data-format="comma-int" class="form-control suji11 text-end bg-light" name="{{ $name }}" value="{{ old($name, $inputs[$name] ?? null) }}" readonly>
                  </td>
                  @php($name = 'shotoku_sanrin_' . $period)
                  <td>
                    <input type="text" inputmode="numeric" data-format="comma-int" class="form-control suji11 text-end bg-light" name="{{ $name }}" value="{{ old($name, $inputs[$name] ?? null) }}" readonly>
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
                  name="recalc_all"
                  value="1"
                  data-disable-on-submit
                  data-redirect-to="bunri_sanrin">再計算</button>
        </div>
      </form>
    </div>
  </div>
</div>


@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
  const Q = (name) => document.querySelector(`[name="${name}"]`);

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

  const getIntValue = (name) => {
    const el = Q(name);
    if (!el) {
      return 0;
    }
    const raw = toRawInt(el.value ?? '');
    if (raw === '') {
      return 0;
    }
    const parsed = parseInt(raw, 10);
    return Number.isNaN(parsed) ? 0 : parsed;
  };

  const setIntValue = (name, value) => {
    const el = Q(name);
    if (!el) {
      return;
    }
    if (value === '' || value === null || typeof value === 'undefined' || Number.isNaN(value)) {
      el.value = '';
      return;
    }
    const raw = Math.trunc(Number(value)).toString();
    el.value = formatWithComma(raw);
  };

  const recalc = (period) => {
    const syunyu = getIntValue(`syunyu_sanrin_${period}`);
    const keihi = getIntValue(`keihi_sanrin_${period}`);
    setIntValue(`sashihiki_sanrin_${period}`, syunyu - keihi);

    const tsusanAfter = getIntValue(`after_3jitsusan_sanrin_${period}`);
    const tokubetsu = getIntValue(`tokubetsukojo_sanrin_${period}`);
    setIntValue(`shotoku_sanrin_${period}`, tsusanAfter - tokubetsu);
  };

  const inputs = document.querySelectorAll('[data-format="comma-int"]');

  inputs.forEach((input) => {
    const applyFormat = () => {
      const raw = toRawInt(input.value ?? '');
      input.value = raw === '' ? '' : formatWithComma(raw);
    };

    applyFormat();

    if (input.readOnly) {
      return;
    }

    input.addEventListener('focus', () => {
      input.value = toRawInt(input.value ?? '');
      input.select();
    });

    input.addEventListener('blur', () => {
      const raw = toRawInt(input.value ?? '');
      input.value = raw === '' ? '' : formatWithComma(raw);
      const match = input.name.match(/_(prev|curr)$/);
      if (match) {
        recalc(match[1]);
      }
    });
  });

  ['prev', 'curr'].forEach(recalc);

  const form = document.querySelector('form');
  if (form) {
    form.addEventListener('submit', () => {
      inputs.forEach((input) => {
        input.value = toRawInt(input.value ?? '');
      });
    });
  }
});
</script>
@endpush
@endsection