<!-- resources/views/tax/furusato/details/kyuyo_zatsu_details.blade.php -->
@extends('layouts.min')

@section('title','給与・雑所得 内訳')

@section('content')
@push('styles')
<style>
  /* チェックボックス視認性を上げる（未チェックでも枠が濃い） */
  .form-check-input {
    width: 1rem;
    height: 1rem;
    border: 1.12px solid #555 !important; /* 未チェック時に濃い枠 */
  }
  /* ラベルもクリック可能に＆間隔を少し */
  .form-check-label {
    margin-left: .4rem;
    cursor: pointer;
  }
</style>
@endpush
@php
  $inputs = $out['inputs'] ?? [];
  $prevY = $warekiPrev ?? '前年';
  $currY = $warekiCurr ?? '当年';
  $v = fn($k)=> old($k, $inputs[$k] ?? null);
@endphp
<div class="container-blue mt-2" style="width: 980px;">
  <div class="card-header d-flex align-items-start justify-content-between">
    <div class="d-flex align-items-start">
      <img src="{{ asset('storage/images/kado_lefttop.jpg') }}" alt="…">
      <h0 class="mb-0 mt-2 ms-2">内訳－給与・雑所得</h0>
    </div>
  </div>
  <div class="card-body">
    <form method="POST" action="{{ route('furusato.details.kyuyo_zatsu.save') }}" id="kyuyo-zatsu-form">
      @csrf
      <input type="hidden" name="data_id" value="{{ $dataId }}">
      <input type="hidden" name="origin_tab" value="input">
      <input type="hidden" name="origin_subtab" value="sogo">
      <input type="hidden" name="origin_anchor" value="{{ request('origin_anchor','shotoku_row_kyuyo') }}">
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

      {{-- ▼ 給与所得（2行×3列 + 注意書き） --}}
      <hb class="mb-2 ms-20">給与所得</hb>
      <div class="table-responsive mb-2">
        <table class="table-base table-bordered align-middle text-center">
          <tbody>
            <tr>
              <th style="width:260px;"></th>
              <th style="width:180px">{{ $prevY }}</th>
              <th style="width:180px">{{ $currY }}</th>
            </tr>
            <tr>
              <th class="text-start align-middle ps-2">給与収入金額</th>
              <td>
                <input type="text" inputmode="numeric" class="form-control text-end js-comma"
                       name="kyuyo_syunyu_prev" value="{{ $v('kyuyo_syunyu_prev') }}">
              </td>
              <td>
                <input type="text" inputmode="numeric" class="form-control text-end js-comma"
                       name="kyuyo_syunyu_curr" value="{{ $v('kyuyo_syunyu_curr') }}">
              </td>
            </tr>
            <tr>
              <th class="text-start align-middle ps-2">子育て・介護世帯向け所得金額調整控除</th>
              <td>
                <div class="form-check d-inline-flex align-items-center">
                  <input type="checkbox" class="form-check-input" id="adj-prev"
                         name="kyuyo_chosei_applicable_prev" value="1"
                         @checked((int)old('kyuyo_chosei_applicable_prev', (int)($inputs['kyuyo_chosei_applicable_prev'] ?? 0))===1)>
                  <label for="adj-prev" id="label-adj-prev" class="form-check-label" title="">適用する</label>
                </div>
              </td>
              <td>
                <div class="form-check d-inline-flex align-items-center">
                  <input type="checkbox" class="form-check-input" id="adj-curr"
                         name="kyuyo_chosei_applicable_curr" value="1"
                         @checked((int)old('kyuyo_chosei_applicable_curr', (int)($inputs['kyuyo_chosei_applicable_curr'] ?? 0))===1)>
                  <label for="adj-curr" id="label-adj-curr" class="form-check-label" title="">適用する</label>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
      <div class="small text-muted mb-4 ms-40 me-40">
        給与収入金額（支払金額）が850万円を超え、次のいずれかの要件に該当する場合（給与所得の源泉徴収票の所得金額調整控除額欄に記載がある場合）、「適用」にチェックをつけてください。<br>
        ・本人が特別障害者に該当する<br>
        ・年齢23歳未満の扶養親族がいる<br>
        ・特別障害者である同一生計配偶者または扶養親族がいる
      </div>

      {{-- ▼ 雑所得（6行×4列） --}}
      <hb class="mb-2 ms-20">雑所得（公的年金等・業務・その他）</hb>
      <div class="table-responsive mb-3">
        <table class="table-base table-bordered align-middle text-center">
          <tbody>
            <tr>
              <th colspan="2" style="width:260px;"></th>
              <th style="width:180px">{{ $prevY }}</th>
              <th style="width:180px">{{ $currY }}</th>
            </tr>
            <tr>
              <th colspan="2" class="text-start align-middle ps-2">公的年金等収入金額</th>
              <td><input type="text" inputmode="numeric" class="form-control text-end js-comma" name="zatsu_nenkin_syunyu_prev" value="{{ $v('zatsu_nenkin_syunyu_prev') }}"></td>
              <td><input type="text" inputmode="numeric" class="form-control text-end js-comma" name="zatsu_nenkin_syunyu_curr" value="{{ $v('zatsu_nenkin_syunyu_curr') }}"></td>
            </tr>
            <tr>
              <th rowspan="2" class="text-start align-middle ps-2">業務</th>
              <th class="text-start align-middle ps-2">収入金額</th>
              <td><input type="text" inputmode="numeric" class="form-control text-end js-comma" name="zatsu_gyomu_syunyu_prev" value="{{ $v('zatsu_gyomu_syunyu_prev') }}"></td>
              <td><input type="text" inputmode="numeric" class="form-control text-end js-comma" name="zatsu_gyomu_syunyu_curr" value="{{ $v('zatsu_gyomu_syunyu_curr') }}"></td>
            </tr>
            <tr>
              <th class="text-start align-middle ps-2">支払金額</th>
              <td><input type="text" inputmode="numeric" class="form-control text-end js-comma" name="zatsu_gyomu_shiharai_prev" value="{{ $v('zatsu_gyomu_shiharai_prev') }}"></td>
              <td><input type="text" inputmode="numeric" class="form-control text-end js-comma" name="zatsu_gyomu_shiharai_curr" value="{{ $v('zatsu_gyomu_shiharai_curr') }}"></td>
            </tr>
            <tr>
              <th rowspan="2" class="text-start align-middle ps-2">その他</th>
              <th class="text-start align-middle ps-2">収入金額</th>
              <td><input type="text" inputmode="numeric" class="form-control text-end js-comma" name="zatsu_sonota_syunyu_prev" value="{{ $v('zatsu_sonota_syunyu_prev') }}"></td>
              <td><input type="text" inputmode="numeric" class="form-control text-end js-comma" name="zatsu_sonota_syunyu_curr" value="{{ $v('zatsu_sonota_syunyu_curr') }}"></td>
            </tr>
            <tr>
              <th class="text-start align-middle ps-2">支払金額</th>
              <td><input type="text" inputmode="numeric" class="form-control text-end js-comma" name="zatsu_sonota_shiharai_prev" value="{{ $v('zatsu_sonota_shiharai_prev') }}"></td>
              <td><input type="text" inputmode="numeric" class="form-control text-end js-comma" name="zatsu_sonota_shiharai_curr" value="{{ $v('zatsu_sonota_shiharai_curr') }}"></td>
            </tr>
          </tbody>
        </table>
      </div>

      <div class="text-end">
        <a href="{{ route('furusato.input', ['data_id'=>$dataId]) }}" class="btn-base-blue">戻 る</a>
        <button type="submit" class="btn-base-green">再計算</button>
      </div>
    </form>
  </div>
</div>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
  // 3桁カンマ表示/送信整数化
  const toRaw = s => {
    s = String(s ?? '').replace(/,/g,'').trim();
    if (s === '' || s === '-') return '';
    return (/^-?\d+$/).test(s) ? String(parseInt(s,10)) : '';
  };
  const fmt = n => n==='' ? '' : Number(n).toLocaleString('ja-JP');
  document.querySelectorAll('.js-comma').forEach(el=>{
    // 初期整形
    el.value = fmt(toRaw(el.value));
    el.addEventListener('focus', ()=> { el.value = toRaw(el.value); el.select(); });
    el.addEventListener('blur',  ()=> { el.value = fmt(toRaw(el.value)); enforceCheckboxRules(); });
  });

  // 850万円超でのみチェック可能
  function enforceCheckboxRules(){
    const prevIncome = parseInt(toRaw(document.querySelector('[name="kyuyo_syunyu_prev"]')?.value||''))||0;
    const currIncome = parseInt(toRaw(document.querySelector('[name="kyuyo_syunyu_curr"]')?.value||''))||0;
    const prevCb = document.getElementById('adj-prev');
    const currCb = document.getElementById('adj-curr');
    if (prevCb){
      const ok = prevIncome > 8500000;
      prevCb.disabled = !ok;
      if (!ok) prevCb.checked = false;
    if (currCb){
      const ok = currIncome > 8500000;
      currCb.disabled = !ok;
      if (!ok) currCb.checked = false;
    }
  }
  enforceCheckboxRules();
});
</script>
@endpush
@endsection