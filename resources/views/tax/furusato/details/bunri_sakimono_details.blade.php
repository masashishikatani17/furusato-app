<!-- views/tax/furusato/details/bunri_sakimono_details.blade.php -->
@extends('layouts.min')

@section('title', '先物取引 内訳')

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
      <h0 class="mb-0 mt-2 ms-2">内訳－先物取引</h0>
    </div>
  </div>
  <div class="card-body">
  　<div class="wrapper">
      <form method="POST" action="{{ route('furusato.details.bunri_sakimono.save') }}">
        @csrf
        <input type="hidden" name="data_id" value="{{ $dataId }}">
        <input type="hidden" name="origin_tab" value="{{ $originTab }}">
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
          <div class="table-responsive mb-2">
            <table class="table-base table-bordered align-middle text-center">
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
                    <input type="number" min="0" step="1" class="form-control suji11 text-end" name="{{ $name }}" value="{{ old($name, $inputs[$name] ?? null) }}">
                  </td>
                  @php($name = 'keihi_sakimono_' . $period)
                  <td>
                    <input type="number" min="0" step="1" class="form-control suji11 text-end" name="{{ $name }}" value="{{ old($name, $inputs[$name] ?? null) }}">
                  </td>
                  @php($name = 'shotoku_sakimono_' . $period)
                  <td>
                    <input type="number" step="1" class="form-control suji11 text-end bg-light" name="{{ $name }}" value="{{ old($name, $inputs[$name] ?? null) }}" readonly>
                  </td>
                  @php($name = 'kurikoshi_sakimono_' . $period)
                  <td>
                    <input type="number" min="0" step="1" class="form-control suji11 text-end" name="{{ $name }}" value="{{ old($name, $inputs[$name] ?? null) }}">
                  </td>
                  @php($name = 'shotoku_sakimono_after_kurikoshi_' . $period)
                  <td>
                    <input type="number" step="1" class="form-control suji11 text-end bg-light" name="{{ $name }}" value="{{ old($name, $inputs[$name] ?? null) }}" readonly>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        @endforeach
        <hr>
        <div class="text-end me-2 mb-2">
          <button type="submit" class="btn-base-blue" id="btn-back">戻 る</button>
          <button type="submit"
                  class="btn-base-green ms-2"
                  id="btn-recalc"
                  data-disable-on-submit>再計算</button>
        </div>
      </form>
    </div>
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
    const base = `sakimono_${period}`;
    const syunyu = V(`syunyu_${base}`);
    const keihi = V(`keihi_${base}`);
    const shotoku = syunyu - keihi;
    S(`shotoku_${base}`, shotoku);

    const kurikoshi = V(`kurikoshi_${base}`);
    S(`shotoku_sakimono_after_kurikoshi_${period}`, shotoku - kurikoshi);
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