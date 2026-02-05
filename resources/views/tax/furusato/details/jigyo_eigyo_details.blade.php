<!-- views/tax/furusato/details/jigyo_eigyo_details.blade.php -->
@extends('layouts.min')

@section('title', '内訳ー事業・営業等')

@section('content')
@php
    $inputs = $out['inputs'] ?? [];
    $storedLabels = $storedLabels ?? [];
    $originTabRaw = request()->input('origin_tab', 'input');
    $originTab = is_string($originTabRaw) && trim($originTabRaw) === 'input' ? 'input' : '';
    $originAnchor = preg_replace('/[^A-Za-z0-9_-]/', '', (string) request()->input('origin_anchor', ''));
@endphp
<div class="container-blue mt-2" style="width:540px;">
  <div class="card-header d-flex align-items-start">
    <img src="{{ asset('storage/images/kado_lefttop.jpg') }}" alt="…">
    <h0 class="mb-0 mt-2">内訳－事業・営業等</h0>
  </div>
  <div class="card-body m-3">
        <form method="POST" action="{{ route('furusato.details.jigyo.save') }}">
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
          <div class="table-responsive">
            <table class="table-input align-middle">
              <thead>
                <tr>
                  <th class="th-ccc" colspan="2" style="height:30px;">項　目</th>
                  <th class="th-ccc">{{ $warekiPrev ?? '前年' }}</th>
                  <th class="th-ccc">{{ $warekiCurr ?? '当年' }}</th>
                </tr>
              </thead>
              <tbody>
                <tr>
                  <th colspan="2" class="text-start align-middle ps-1">売上(収入)金額</th>
                  <td>
                    <input type="text" inputmode="numeric" autocomplete="off"
                           data-format="comma-int" data-name="jigyo_eigyo_uriage_prev"
                           class="form-control suji11 text-end"
                           value="{{ old('jigyo_eigyo_uriage_prev', $inputs['jigyo_eigyo_uriage_prev'] ?? null) }}">
                  </td>
                  <td>
                    <input type="text" inputmode="numeric" autocomplete="off"
                           data-format="comma-int" data-name="jigyo_eigyo_uriage_curr"
                           class="form-control suji11 text-end"
                           value="{{ old('jigyo_eigyo_uriage_curr', $inputs['jigyo_eigyo_uriage_curr'] ?? null) }}">
                  </td>
                </tr>
                <tr>
                  <th colspan="2" class="text-start align-middle ps-1">売上原価</th>
                  <td>
                    <input type="text" inputmode="numeric" autocomplete="off"
                           data-format="comma-int" data-name="jigyo_eigyo_urigenka_prev"
                           class="form-control suji11 text-end"
                           value="{{ old('jigyo_eigyo_urigenka_prev', $inputs['jigyo_eigyo_urigenka_prev'] ?? null) }}">
                  </td>
                  <td>
                    <input type="text" inputmode="numeric" autocomplete="off"
                           data-format="comma-int" data-name="jigyo_eigyo_urigenka_curr"
                           class="form-control suji11 text-end"
                           value="{{ old('jigyo_eigyo_urigenka_curr', $inputs['jigyo_eigyo_urigenka_curr'] ?? null) }}">
                  </td>
                </tr>
                <tr>
                  <th colspan="2" class="text-start align-middle ps-1">差引金額</th>
                  <td>
                    @php($name = 'jigyo_eigyo_sashihiki_1_prev')
                    <input type="text" inputmode="numeric" autocomplete="off"
                           data-format="comma-int" data-name="{{ $name }}"
                           class="form-control suji11 text-end bg-light"
                           value="{{ old($name, $inputs[$name] ?? null) }}" readonly>
                  </td>
                  <td>
                    @php($name = 'jigyo_eigyo_sashihiki_1_curr')
                    <input type="text" inputmode="numeric" autocomplete="off"
                           data-format="comma-int" data-name="{{ $name }}"
                           class="form-control suji11 text-end bg-light"
                           value="{{ old($name, $inputs[$name] ?? null) }}" readonly>
                  </td>
                </tr>
                @php($expenseFields = [
                  ['labelInput' => 'jigyo_eigyo_keihi_label_01', 'labelIndex' => 1, 'name' => 'jigyo_eigyo_keihi_1'],
                  ['labelInput' => 'jigyo_eigyo_keihi_label_02', 'labelIndex' => 2, 'name' => 'jigyo_eigyo_keihi_2'],
                  ['labelInput' => 'jigyo_eigyo_keihi_label_03', 'labelIndex' => 3, 'name' => 'jigyo_eigyo_keihi_3'],
                  ['labelInput' => 'jigyo_eigyo_keihi_label_04', 'labelIndex' => 4, 'name' => 'jigyo_eigyo_keihi_4'],
                  ['labelInput' => 'jigyo_eigyo_keihi_label_05', 'labelIndex' => 5, 'name' => 'jigyo_eigyo_keihi_5'],
                  ['labelInput' => 'jigyo_eigyo_keihi_label_06', 'labelIndex' => 6, 'name' => 'jigyo_eigyo_keihi_6'],
                  ['labelInput' => 'jigyo_eigyo_keihi_label_07', 'labelIndex' => 7, 'name' => 'jigyo_eigyo_keihi_7'],
                  ['label' => 'その他', 'name' => 'jigyo_eigyo_keihi_sonota', 'headerClass' => 'text-start u-nowrap th-ddd'],
                  ['label' => '合 計', 'name' => 'jigyo_eigyo_keihi_gokei', 'readonly' => true, 'headerClass' => 'u-nowrap th-ddd'],
                ])
                @php($expenseRowspan = count($expenseFields))
                @php($field = array_shift($expenseFields))
                <tr>
                  <th scope="rowgroup" rowspan="{{ $expenseRowspan }}" class="align-middle text-center" style="width:40px;">経 費</th>
                  <th class="{{ $field['headerClass'] ?? 'text-start u-nowrap th-ddd' }}">
                    @php($labelName = $field['labelInput'] ?? null)
                    @if($labelName)
                      @php($placeholder = $field['placeholder'] ?? '')
                      <input type="text"
                             name="{{ $labelName }}"
                             value="{{ old($labelName, $storedLabels[$labelName] ?? '') }}"
                             maxlength="64"
                             class="form-control kana10"
                             aria-label="経費項目名{{ $field['labelIndex'] ?? '' }}"
                             @if($placeholder !== '') placeholder="{{ $placeholder }}" @endif>
                    @else
                      {{ $field['label'] ?? '' }}
                    @endif
                  </th>
                  <td>
                    @php($name = $field['name'] . '_prev')
                    @php($readonly = $field['readonly'] ?? false)
                    <input type="text" inputmode="numeric" autocomplete="off"
                           data-format="comma-int" data-name="{{ $name }}"
                           class="form-control suji11 text-end{{ $readonly ? ' bg-light' : '' }}"
                           value="{{ old($name, $inputs[$name] ?? null) }}" @if($readonly) readonly @endif>
                  </td>
                  <td>
                    @php($name = $field['name'] . '_curr')
                    <input type="text" inputmode="numeric" autocomplete="off"
                           data-format="comma-int" data-name="{{ $name }}"
                           class="form-control suji11 text-end{{ $readonly ? ' bg-light' : '' }}"
                           value="{{ old($name, $inputs[$name] ?? null) }}" @if($readonly) readonly @endif>
                  </td>
                </tr>
                @foreach ($expenseFields as $field)
                  <tr>
                    <th class="{{ $field['headerClass'] ?? 'text-start u-nowrap th-ddd' }}">
                      @php($labelName = $field['labelInput'] ?? null)
                      @if($labelName)
                        @php($placeholder = $field['placeholder'] ?? '')
                        <input type="text"
                               name="{{ $labelName }}"
                               value="{{ old($labelName, $storedLabels[$labelName] ?? '') }}"
                               maxlength="64"
                               class="form-control kana10"
                               aria-label="経費項目名{{ $field['labelIndex'] ?? '' }}"
                               @if($placeholder !== '') placeholder="{{ $placeholder }}" @endif>
                      @else
                        {{ $field['label'] ?? '' }}
                      @endif
                    </th>
                    <td>
                      @php($name = $field['name'] . '_prev')
                      @php($readonly = $field['readonly'] ?? false)
                      <input type="text" inputmode="numeric" autocomplete="off"
                             data-format="comma-int" data-name="{{ $name }}"
                             class="form-control suji11 text-end{{ $readonly ? ' bg-light' : '' }}"
                             value="{{ old($name, $inputs[$name] ?? null) }}" @if($readonly) readonly @endif>
                    </td>
                    <td>
                      @php($name = $field['name'] . '_curr')
                      <input type="text" inputmode="numeric" autocomplete="off"
                             data-format="comma-int" data-name="{{ $name }}"
                             class="form-control suji11 text-end{{ $readonly ? ' bg-light' : '' }}"
                             value="{{ old($name, $inputs[$name] ?? null) }}" @if($readonly) readonly @endif>
                    </td>
                  </tr>
                @endforeach
                <tr>
                  <th colspan="2" class="text-start align-middle ps-1">差引金額</th>
                  <td>
                    @php($name = 'jigyo_eigyo_sashihiki_2_prev')
                    <input type="text" inputmode="numeric" autocomplete="off"
                           data-format="comma-int" data-name="{{ $name }}"
                           class="form-control suji11 text-end bg-light"
                           value="{{ old($name, $inputs[$name] ?? null) }}" readonly>
                  </td>
                  <td>
                    @php($name = 'jigyo_eigyo_sashihiki_2_curr')
                    <input type="text" inputmode="numeric" autocomplete="off"
                           data-format="comma-int" data-name="{{ $name }}"
                           class="form-control suji11 text-end bg-light"
                           value="{{ old($name, $inputs[$name] ?? null) }}" readonly>
                  </td>
                </tr>
                <tr>
                  <th colspan="2" class="text-start align-middle ps-1">専従者給与</th>
                  <td>
                    <input type="text" inputmode="numeric" autocomplete="off"
                           data-format="comma-int" data-name="jigyo_eigyo_senjuusha_kyuyo_prev"
                           class="form-control suji11 text-end"
                           value="{{ old('jigyo_eigyo_senjuusha_kyuyo_prev', $inputs['jigyo_eigyo_senjuusha_kyuyo_prev'] ?? null) }}">
                  </td>
                  <td>
                    <input type="text" inputmode="numeric" autocomplete="off"
                           data-format="comma-int" data-name="jigyo_eigyo_senjuusha_kyuyo_curr"
                           class="form-control suji11 text-end"
                           value="{{ old('jigyo_eigyo_senjuusha_kyuyo_curr', $inputs['jigyo_eigyo_senjuusha_kyuyo_curr'] ?? null) }}">
                  </td>
                </tr>
                <tr>
                  <th colspan="2" class="text-start align-middle ps-1 pe-1">青色申告特別控除前の所得金額</th>
                  <td>
                    @php($name = 'jigyo_eigyo_aoi_tokubetsu_kojo_mae_prev')
                    <input type="text" inputmode="numeric" autocomplete="off"
                           data-format="comma-int" data-name="{{ $name }}"
                           class="form-control suji11 text-end bg-light"
                           value="{{ old($name, $inputs[$name] ?? null) }}" readonly>
                  </td>
                  <td>
                    @php($name = 'jigyo_eigyo_aoi_tokubetsu_kojo_mae_curr')
                    <input type="text" inputmode="numeric" autocomplete="off"
                           data-format="comma-int" data-name="{{ $name }}"
                           class="form-control suji11 text-end bg-light"
                           value="{{ old($name, $inputs[$name] ?? null) }}" readonly>
                  </td>
                </tr>
                <tr>
                  <th colspan="2" class="text-start align-middle ps-1">青色申告特別控除額</th>
                  <td>
                    <input type="text" inputmode="numeric" autocomplete="off"
                           data-format="comma-int" data-name="jigyo_eigyo_aoi_tokubetsu_kojo_gaku_prev"
                           class="form-control suji11 text-end"
                           value="{{ old('jigyo_eigyo_aoi_tokubetsu_kojo_gaku_prev', $inputs['jigyo_eigyo_aoi_tokubetsu_kojo_gaku_prev'] ?? null) }}">
                  </td>
                  <td>
                    <input type="text" inputmode="numeric" autocomplete="off"
                           data-format="comma-int" data-name="jigyo_eigyo_aoi_tokubetsu_kojo_gaku_curr"
                           class="form-control suji11 text-end"
                           value="{{ old('jigyo_eigyo_aoi_tokubetsu_kojo_gaku_curr', $inputs['jigyo_eigyo_aoi_tokubetsu_kojo_gaku_curr'] ?? null) }}">
                  </td>
                </tr>
                <tr>
                  <th colspan="2" class="text-center align-middle ps-1 th-cream">所得金額</th>
                  <td>
                    @php($name = 'jigyo_eigyo_shotoku_prev')
                    <input type="text" inputmode="numeric" autocomplete="off"
                           data-format="comma-int" data-name="{{ $name }}"
                           class="form-control suji11 text-end bg-light"
                           value="{{ old($name, $inputs[$name] ?? null) }}" readonly>
                  </td>
                  <td>
                    @php($name = 'jigyo_eigyo_shotoku_curr')
                    <input type="text" inputmode="numeric" autocomplete="off"
                           data-format="comma-int" data-name="{{ $name }}"
                           class="form-control suji11 text-end bg-light"
                           value="{{ old($name, $inputs[$name] ?? null) }}" readonly>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
          <hr class="mb-2">
          <div class="text-end gap-2">
            <button type="submit" class="btn-base-blue" id="btn-back">戻 る</button>
            <button type="submit"
                    class="btn-base-green"
                    id="btn-recalc"
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
  // ===== 3桁カンマ表示 + hidden数値POST 共通ユーティリティ =====
  const toRawInt = (value) => {
    if (typeof value !== 'string') return '';
    const stripped = value.replace(/,/g, '').trim();
    if (stripped === '' || stripped === '-') return '';
    if (!/^(-)?\d+$/.test(stripped)) return '';
    const n = parseInt(stripped, 10);
    return Number.isNaN(n) ? '' : String(n);
  };
  const fmt = (raw) => {
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
    let h = getHidden(name);
    if (!h) {
      h = document.createElement('input');
      h.type = 'hidden';
      h.name = name;
      h.dataset.commaMirror = '1';
      (displayInput.parentElement || displayInput.closest('form') || document.body).appendChild(h);
      hiddenCache.set(name, h);
    }
    const hiddenRaw = toRawInt(h.value ?? '');
    const inputRaw  = toRawInt(displayInput.value ?? '');
    const raw = hiddenRaw !== '' ? hiddenRaw : inputRaw;
    h.value = raw;
    displayInput.value = raw === '' ? '' : fmt(raw);
    return h;
  };
  const V = (name) => {
    const h = getHidden(name);
    if (!h) return 0;
    const raw = toRawInt(h.value ?? '');
    if (raw === '') return 0;
    const n = parseInt(raw, 10);
    return Number.isNaN(n) ? 0 : n;
  };
  const S = (name, val) => {
    const h = getHidden(name);
    const d = getDisplay(name);
    if (val === '' || val === null || typeof val === 'undefined' || Number.isNaN(val)) {
      if (h) h.value = '';
      if (d) d.value = '';
      return;
    }
    const raw = String(Math.trunc(Number(val) || 0));
    if (h) h.value = raw;
    if (d) d.value = fmt(raw);
  };

  const recalc = (suffix) => {
    const uriage   = V(`jigyo_eigyo_uriage_${suffix}`);
    const urigenka = V(`jigyo_eigyo_urigenka_${suffix}`);
    const sashihiki1 = uriage - urigenka;
    S(`jigyo_eigyo_sashihiki_1_${suffix}`, sashihiki1);

    let keihiGokei = 0;
    for (let i=1;i<=7;i++) keihiGokei += V(`jigyo_eigyo_keihi_${i}_${suffix}`);
    keihiGokei += V(`jigyo_eigyo_keihi_sonota_${suffix}`);
    S(`jigyo_eigyo_keihi_gokei_${suffix}`, keihiGokei);

    const sashihiki2 = sashihiki1 - keihiGokei;
    S(`jigyo_eigyo_sashihiki_2_${suffix}`, sashihiki2);

    const senju = V(`jigyo_eigyo_senjuusha_kyuyo_${suffix}`);
    const mae   = sashihiki2 - senju;
    S(`jigyo_eigyo_aoi_tokubetsu_kojo_mae_${suffix}`, mae);

    const tokugaku = V(`jigyo_eigyo_aoi_tokubetsu_kojo_gaku_${suffix}`);
    S(`jigyo_eigyo_shotoku_${suffix}`, mae - tokugaku);
  };

  // 表示 input の初期化（hidden生成＋初期カンマ整形）＆ blur で再計算
  const displays = Array.from(document.querySelectorAll('[data-format="comma-int"][data-name]'));
  displays.forEach((input) => {
    const name = input.dataset.name;
    if (!name) return;
    ensureHidden(input);
    const h = getHidden(name);
    const raw = toRawInt(h?.value ?? input.value ?? '');
    input.value = raw === '' ? '' : fmt(raw);
    if (input.readOnly) return;
    input.addEventListener('focus', () => {
      const hidden = getHidden(name);
      input.value = hidden ? hidden.value : toRawInt(input.value ?? '');
      input.select();
    });
    input.addEventListener('blur', () => {
      const hidden = getHidden(name) || ensureHidden(input);
      const raw2 = toRawInt(input.value ?? hidden?.value ?? '');
      if (hidden) hidden.value = raw2;
      input.value = raw2 === '' ? '' : fmt(raw2);
      // 依存があるため両期再計算（安全側）
      recalc('prev'); recalc('curr');
    });
  });

  recalc('prev'); recalc('curr');

  // 送信直前：hiddenへ数値を確実に反映（表示nameは無いのでチラつき無し）
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