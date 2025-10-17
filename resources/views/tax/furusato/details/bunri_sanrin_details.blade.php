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
                  <th>特別控除額</th>
                  <th>山林所得金額</th>
                </tr>
              </thead>
              <tbody>
                <tr>
                  @php($name = 'syunyu_sanrin_' . $period)
                  <td>
                    <input type="number" min="0" step="1" class="form-control suji11 text-end" name="{{ $name }}" value="{{ old($name, $inputs[$name] ?? null) }}">
                  </td>
                  @php($name = 'keihi_sanrin_' . $period)
                  <td>
                    <input type="number" min="0" step="1" class="form-control suji11 text-end" name="{{ $name }}" value="{{ old($name, $inputs[$name] ?? null) }}">
                  </td>
                  @php($name = 'sashihiki_sanrin_' . $period)
                  <td>
                    <input type="number" step="1" class="form-control suji11 text-end bg-light" name="{{ $name }}" value="{{ old($name, $inputs[$name] ?? null) }}" readonly>
                  </td>
                  @php($name = 'tokubetsukojo_sanrin_' . $period)
                  <td>
                    <input type="number" step="1" class="form-control suji11 text-end bg-light" name="{{ $name }}" value="{{ old($name, $inputs[$name] ?? null) }}" readonly>
                  </td>
                  @php($name = 'shotoku_sanrin_' . $period)
                  <td>
                    <input type="number" step="1" class="form-control suji11 text-end bg-light" name="{{ $name }}" value="{{ old($name, $inputs[$name] ?? null) }}" readonly>
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
  const V = (name) => {
    const el = Q(name);
    if (!el) { return 0; }
    const raw = (el.value ?? '').toString().trim();
    if (raw === '') { return 0; }
    const num = Number(raw.replace(/[^\-0-9.]/g, ''));
    return Number.isFinite(num) ? Math.trunc(num) : 0;
  };
  const S = (name, value) => {
    const el = Q(name);
    if (el) {
      el.value = value ?? 0;
    }
  };

  const recalc = (period) => {
    const syunyu = V(`syunyu_sanrin_${period}`);
    const keihi = V(`keihi_sanrin_${period}`);
    const sashihiki = syunyu - keihi;
    S(`sashihiki_sanrin_${period}`, sashihiki);

    const tokubetsu = V(`tokubetsukojo_sanrin_${period}`);
    S(`shotoku_sanrin_${period}`, sashihiki - tokubetsu);
  };

  const bindBlur = () => {
    document.querySelectorAll('input[type="number"]').forEach((el) => {
      if (el.readOnly) {
        return;
      }
      const match = el.name.match(/_(prev|curr)$/);
      if (!match) {
        return;
      }
      el.addEventListener('blur', () => recalc(match[1]));
    });
  };

  bindBlur();
  recalc('prev');
  recalc('curr');
});
</script>
@endpush
@endsection