@extends('layouts.min')

@section('title', '住宅借入金等特別控除の内訳')

@section('content')
@php
  $inputs = $out['inputs'] ?? [];
  $warekiPrevLabel = $warekiPrev ?? '前年';
  $warekiCurrLabel = $warekiCurr ?? '当年';
  // 画面表示値はサーバ側 inputs のみを参照（old() は使用しない）
  $sv = function (string $key, $default = '') use ($inputs) {
      $v = $inputs[$key] ?? null;
      return ($v === null || $v === '') ? $default : (string)$v;
  };
  // 控除率（％）は未入力時「0.7」、表示は小数1位
  $ratePrev = number_format((float)($inputs['itax_credit_rate_percent_prev'] ?? 0.7), 1, '.', '');
  $rateCurr = number_format((float)($inputs['itax_credit_rate_percent_curr'] ?? 0.7), 1, '.', '');
@endphp
<div class="container-blue mt-2" style="width: 520px;">
  <div class="card-header d-flex align-items-start">
    <img src="{{ asset('storage/images/kado_lefttop.jpg') }}" alt="…">
    <h0 class="mb-0 mt-2 mb-3">住宅借入金等特別控除の内訳</h0>
  </div>

  @if (session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
  @endif
  @if ($errors->any())
    <div class="alert alert-danger">
      <ul class="mb-0">
        @foreach ($errors->all() as $error)
          <li>{{ $error }}</li>
        @endforeach
      </ul>
    </div>
  @endif

  <div class="card-body">
    <div class="wrapper">
      <form method="POST" action="{{ route('furusato.details.kojo_tokubetsu_jutaku_loan.save') }}">
        @csrf
        <input type="hidden" name="data_id" value="{{ $dataId }}">
        <input type="hidden" name="origin_tab" value="input">
        <input type="hidden" name="origin_anchor" value="tax_jutaku">
        <input type="hidden" name="recalc_all" value="1">
        <input type="hidden" id="stay-on-details" name="stay_on_details" value="0">
  
        <hb class="mb-2 ms-3">所得税の控除限度額</hb>
        <div class="table-responsive mb-4">
          <table class="table-base table-bordered align-middle text-start">
            <tr>
              <th class="th-ccc" style="width:130px;height:30px;">項　目　名</th>
              <th class="th-ccc" style="width:140px;">{{ $warekiPrevLabel }}</th>
              <th class="th-ccc" style="width:140px;">{{ $warekiCurrLabel }}</th>
            </tr>
            <tr>
              <th class="text-start ps-1">借入金限度額</th>
              <td><input type="text" inputmode="numeric" class="form-control suji11 text-end js-comma" name="itax_borrow_cap_prev" value="{{ $sv('itax_borrow_cap_prev') }}"></td>
              <td><input type="text" inputmode="numeric" class="form-control suji11 text-end js-comma" name="itax_borrow_cap_curr" value="{{ $sv('itax_borrow_cap_curr') }}"></td>
            </tr>
            <tr>
              <th class="text-start ps-1">借入金の年末残高</th>
              <td><input type="text" inputmode="numeric" class="form-control suji11 text-end js-comma" name="itax_year_end_balance_prev" value="{{ $sv('itax_year_end_balance_prev') }}"></td>
              <td><input type="text" inputmode="numeric" class="form-control suji11 text-end js-comma" name="itax_year_end_balance_curr" value="{{ $sv('itax_year_end_balance_curr') }}"></td>
            </tr>
            <tr>
              <th class="text-start ps-1">控除率</th>
              <td>
                <input type="text"
                       inputmode="decimal"
                       step="0.1"
                       pattern="^\d{1,2}(\.\d)?$"
                       class="form-control suji3 text-end"
                       name="itax_credit_rate_percent_prev"
                       value="{{ $ratePrev }}">
              </td>
              <td>
                <input type="text"
                       inputmode="decimal"
                       step="0.1"
                       pattern="^\d{1,2}(\.\d)?$"
                       class="form-control suji3 text-end"
                       name="itax_credit_rate_percent_curr"
                       value="{{ $rateCurr }}">
              </td>
            </tr>
          </table>
        </div>
  
       　<hb class="mb-2 ms-3">住民税の控除限度額</hb>
        <div class="table-responsive mb-4">
          <table class="table-base table-bordered align-middle text-start">
            <tr>
              <th class="th-ccc" style="width:130px;height:30px;">項　目　名</th>
              <th class="th-ccc" style="width:140px;">{{ $warekiPrevLabel }}</th>
              <th class="th-ccc" style="width:140px;">{{ $warekiCurrLabel }}</th>
            </tr>
            <tr>
              <th class="text-start ps-1">課税総所得金額等×</th>
              <td>
                <select name="rtax_income_rate_percent_prev" class="form-select sel4 text-end" id="rate-prev">
                  @php $rp = (int)($inputs['rtax_income_rate_percent_prev'] ?? 5); @endphp
                  <option value="5" {{ $rp===5 ? 'selected' : '' }}>5%</option>
                  <option value="7" {{ $rp===7 ? 'selected' : '' }}>7%</option>
                </select>
              </td>
              <td>
                <select name="rtax_income_rate_percent_curr" class="form-select sel4 text-end" id="rate-curr">
                  @php $rc = (int)($inputs['rtax_income_rate_percent_curr'] ?? 5); @endphp
                  <option value="5" {{ $rc===5 ? 'selected' : '' }}>5%</option>
                  <option value="7" {{ $rc===7 ? 'selected' : '' }}>7%</option>
                </select>
              </td>
            </tr>
            <tr>
              <th class="text-start ps-1">控除限度額</th>
              <td><input type="text" class="form-control suji11 text-end bg-light js-comma" name="rtax_carry_cap_prev" id="cap-prev" value="{{ $sv('rtax_carry_cap_prev') }}" readonly></td>
              <td><input type="text" class="form-control suji11 text-end bg-light js-comma" name="rtax_carry_cap_curr" id="cap-curr" value="{{ $sv('rtax_carry_cap_curr') }}" readonly></td>
            </tr>
          </table>
        </div>
  
        {{-- （参考）課税総所得金額等：画面入力させない。hidden で返すだけ（サーバで毎回再計算） --}}
        <input type="hidden" id="taxable-prev" name="rtax_taxable_total_prev" value="{{ (int)($inputs['rtax_taxable_total_prev'] ?? 0) }}">
        <input type="hidden" id="taxable-curr" name="rtax_taxable_total_curr" value="{{ (int)($inputs['rtax_taxable_total_curr'] ?? 0) }}">
  
        <div class="text-end me-2 mb-2">
          {{-- 戻る：input に遷移（第一表 #tax_jutaku） --}}
          <button type="submit"
                  class="btn-base-blue"
                  name="redirect_to"
                  value="input"
                  onclick="document.getElementById('stay-on-details').value='0'">戻 る</button>
          {{-- 再計算：この内訳に留まる --}}
          <button type="submit"
                  class="btn-base-green ms-2"
                  name="redirect_to"
                  value="kojo_tokubetsu_jutaku_loan"
                  onclick="document.getElementById('stay-on-details').value='1'">再計算</button>
        </div>
      </form>
    </div>
  </div>
</div>
@endsection

@push('scripts')
<script>
  // 3桁カンマ整形
  document.addEventListener('DOMContentLoaded', () => {
    const fmt = n => new Intl.NumberFormat('ja-JP').format((() => {
      const s = String(n ?? '').replace(/,/g, '').trim();
      if (!s || s === '-') return 0;
      const v = Number(s); return Number.isFinite(v) ? Math.trunc(v) : 0;
    })());
    const commaTargets = [
      'itax_borrow_cap_prev','itax_borrow_cap_curr',
      'itax_year_end_balance_prev','itax_year_end_balance_curr',
      'rtax_carry_cap_prev','rtax_carry_cap_curr'
    ];
    commaTargets.forEach(name => {
      const el = document.querySelector(`input[name="${name}"]`);
      if (!el) return;
      // 初期表示
      if (String(el.value).trim() !== '') el.value = fmt(el.value);
      // blur時
      el.addEventListener('blur', () => { if (el.value !== '－') el.value = fmt(el.value); });
    });

    // ▼ 率(5/7)の選択に応じて、画面上の控除限度額を即時反映（SoTはサーバ。これはUX向けプレビュー）
    const recompute = (which) => {
      const rateSel = document.getElementById(`rate-${which}`);
      const capBox  = document.getElementById(`cap-${which}`);
      const taxable = Number(document.getElementById(`taxable-${which}`)?.value || 0);
      if (!rateSel || !capBox) return;
      const rate = Number(rateSel.value) === 7 ? 7 : 5;
      const hardCap = rate === 7 ? 136500 : 97500;
      const byIncome = Math.floor(taxable * (rate / 100));
      const cap = Math.min(byIncome, hardCap);
      capBox.value = fmt(cap);
    };
    ['prev','curr'].forEach(w => {
      const sel = document.getElementById(`rate-${w}`);
      if (sel) {
        sel.addEventListener('change', () => recompute(w));
        // 初期表示の整合
        recompute(w);
      }
    });
  });
</script>
@endpush