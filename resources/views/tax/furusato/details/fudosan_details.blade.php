<!-- views/tax/furusato/details/fudosan_details.blade.php -->
@extends('layouts.min')

@section('title', '内訳ー不動産')

@section('content')
@php
    $inputs = $out['inputs'] ?? [];
    $storedLabels = $storedLabels ?? [];
    $originTabRaw = request()->input('origin_tab', 'input');
    $originTab = is_string($originTabRaw) && trim($originTabRaw) === 'input' ? 'input' : '';
    $originAnchor = preg_replace('/[^A-Za-z0-9_-]/', '', (string) request()->input('origin_anchor', ''));
@endphp
<div class="container-blue mt-2" style="width:600px;">
  <div class="card-header d-flex align-items-start">
    <img src="{{ asset('storage/images/kado_lefttop.jpg') }}" alt="…">
    <h0 class="mb-0 mt-2">内訳－不動産</h0>
  </div>
  <div class="card-body">　
  　<div class="wrapper">
      <form method="POST" action="{{ route('furusato.details.fudosan.save') }}">
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
          <table class="table-base table-bordered align-middle">
            <tbody>
              <tr>
                <th colspan="2" class="th-ccc" style="height:30px;">項 目</th>
                <th class="th-ccc">{{ $warekiPrev ?? '前年' }}</th>
                <th class="th-ccc">{{ $warekiCurr ?? '当年' }}</th>
              </tr>
              <tr>
                <th class="text-start align-middle" colspan="2">収入金額</th>
                <td>
                  @php($name = 'fudosan_syunyu_prev')
                  <input type="text" inputmode="numeric" autocomplete="off"
                         data-format="comma-int" data-name="{{ $name }}"
                         class="form-control suji11 text-end"
                         value="{{ old($name, $inputs[$name] ?? null) }}">
                </td>
                <td>
                  @php($name = 'fudosan_syunyu_curr')
                  <input type="text" inputmode="numeric" autocomplete="off"
                         data-format="comma-int" data-name="{{ $name }}"
                         class="form-control suji11 text-end"
                         value="{{ old($name, $inputs[$name] ?? null) }}">
                </td>
              </tr>
              @php($expenseFields = [
                ['labelInput' => 'fudosan_keihi_label_01', 'labelIndex' => 1, 'name' => 'fudosan_keihi_1'],
                ['labelInput' => 'fudosan_keihi_label_02', 'labelIndex' => 2, 'name' => 'fudosan_keihi_2'],
                ['labelInput' => 'fudosan_keihi_label_03', 'labelIndex' => 3, 'name' => 'fudosan_keihi_3'],
                ['labelInput' => 'fudosan_keihi_label_04', 'labelIndex' => 4, 'name' => 'fudosan_keihi_4'],
                ['labelInput' => 'fudosan_keihi_label_05', 'labelIndex' => 5, 'name' => 'fudosan_keihi_5'],
                ['labelInput' => 'fudosan_keihi_label_06', 'labelIndex' => 6, 'name' => 'fudosan_keihi_6'],
                ['labelInput' => 'fudosan_keihi_label_07', 'labelIndex' => 7, 'name' => 'fudosan_keihi_7'],
                ['label' => 'その他', 'name' => 'fudosan_keihi_sonota'],
                ['label' => '合計', 'name' => 'fudosan_keihi_gokei', 'readonly' => true],
              ])
              @php($expenseRowspan = count($expenseFields))
              @php($field = array_shift($expenseFields))
              <tr>
                <th class="text-center align-middle" rowspan="{{ $expenseRowspan }}" style="width: 30px;">必<br>要<br>経<br>費</th>
                <th class="text-start u-nowrap th-ddd">
                  @php($labelName = $field['labelInput'] ?? null)
                  @if($labelName)
                    @php($placeholder = $field['placeholder'] ?? '')
                    <input type="text"
                           name="{{ $labelName }}"
                           value="{{ old($labelName, $storedLabels[$labelName] ?? '') }}"
                           maxlength="64"
                           class="form-control form-control-sm"
                           aria-label="必要経費項目名{{ $field['labelIndex'] ?? '' }}"
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
                  <th class="text-center u-nowrap th-ddd">
                    @php($labelName = $field['labelInput'] ?? null)
                    @if($labelName)
                      @php($placeholder = $field['placeholder'] ?? '')
                      <input type="text"
                             name="{{ $labelName }}"
                             value="{{ old($labelName, $storedLabels[$labelName] ?? '') }}"
                             maxlength="64"
                             class="form-control form-control-sm"
                             aria-label="必要経費項目名{{ $field['labelIndex'] ?? '' }}"
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
              @php($footerFields = [
                ['name' => 'fudosan_sashihiki', 'label' => '差引金額', 'readonly' => true],
                ['name' => 'fudosan_senjuusha_kyuyo', 'label' => '専従者給与'],
                ['name' => 'fudosan_aoi_tokubetsu_kojo_mae', 'label' => '青色申告特別控除前の所得金額', 'readonly' => true],
                ['name' => 'fudosan_aoi_tokubetsu_kojo_gaku', 'label' => '青色申告特別控除額'],
                ['name' => 'fudosan_shotoku', 'label' => '所得金額', 'readonly' => true],
                ['name' => 'fudosan_fusairishi', 'label' => '土地等を取得するための負債利子'],
              ])
              @foreach ($footerFields as $field)
                <tr>
                  <th class="text-start align-middle" colspan="2">{{ $field['label'] }}</th>
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
            </tbody>
          </table>
        </div>
        <hr>
          <div class="text-end me-2 mb-2">
            <button type="submit" class="btn-base-blue" id="btn-back">戻 る</button>
            <button type="submit"
                    class="btn-base-green ms-2"
                    id="btn-recalc"
                    data-disable-on-submit>再計算</button>
          </div>
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
    form.addEventListener('submit', (e) => {
      // 既に btnRecalc クリックで 1 にされていればそのまま、未設定なら 0 を維持
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
    let g = 0;
    for (let i=1;i<=7;i++) g += V(`fudosan_keihi_${i}_${suffix}`);
    g += V(`fudosan_keihi_sonota_${suffix}`);
    S(`fudosan_keihi_gokei_${suffix}`, g);

    const shunyu = V(`fudosan_syunyu_${suffix}`);
    const sashihiki = shunyu - g;
    S(`fudosan_sashihiki_${suffix}`, sashihiki);

    const senju = V(`fudosan_senjuusha_kyuyo_${suffix}`);
    const mae = sashihiki - senju;
    S(`fudosan_aoi_tokubetsu_kojo_mae_${suffix}`, mae);

    const tokugaku = V(`fudosan_aoi_tokubetsu_kojo_gaku_${suffix}`);
    S(`fudosan_shotoku_${suffix}`, mae - tokugaku);
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
      // prev/curr を見分けて両期再計算（依存があるため両方安全）
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
@endsection