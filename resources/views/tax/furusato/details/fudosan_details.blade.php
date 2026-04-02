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
    $helpPath = resource_path('views/tax/furusato/helps/help_fudosan_details_modal.php');
    $HELP_TEXTS = file_exists($helpPath) ? require $helpPath : [];
@endphp
<div class="container-blue mt-2" style="width:530px;">
  <div class="card-header d-flex align-items-start">
    @include('components.kado_lefttop_img')
    <h0 class="mb-0 mt-2">内訳－不動産</h0>
  </div>
  <div class="card-body m-3">
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
          <table class="table-input align-middle">
            <tbody>
              <tr>
                <th colspan="2" class="th-ccc" style="height:30px;">項 目</th>
                <th class="th-ccc">{{ $warekiPrev ?? '前年' }}</th>
                <th class="th-ccc">{{ $warekiCurr ?? '当年' }}</th>
              </tr>
              <tr>
                <th class="text-start align-middle ps-1" colspan="2">収入金額</th>
                <td>
                  @php($name = 'fudosan_syunyu_prev')
                  <input type="text" inputmode="numeric" autocomplete="off"
                         data-format="comma-int" data-name="{{ $name }}"
                         maxlength="11"
                         class="form-control suji11 text-end"
                         value="{{ old($name, $inputs[$name] ?? null) }}">
                </td>
                <td>
                  @php($name = 'fudosan_syunyu_curr')
                  <input type="text" inputmode="numeric" autocomplete="off"
                         data-format="comma-int" data-name="{{ $name }}"
                         maxlength="11"
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
                ['label' => '合　計', 'name' => 'fudosan_keihi_gokei', 'readonly' => true],
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
                           maxlength="10"
                           class="form-control kana10"
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
                         maxlength="{{ $readonly ? 12 : 11 }}"
                         class="form-control suji11 text-end{{ $readonly ? ' bg-light' : '' }}"
                         value="{{ old($name, $inputs[$name] ?? null) }}" @if($readonly) readonly @endif>
                </td>
                <td>
                  @php($name = $field['name'] . '_curr')
                  <input type="text" inputmode="numeric" autocomplete="off"
                         data-format="comma-int" data-name="{{ $name }}"
                         maxlength="{{ $readonly ? 12 : 11 }}"
                         class="form-control suji11 text-end{{ $readonly ? ' bg-light' : '' }}"
                         value="{{ old($name, $inputs[$name] ?? null) }}" @if($readonly) readonly @endif>
                </td>
              </tr>
              @foreach ($expenseFields as $field)
                @php($isTotalRow = (($field['name'] ?? '') === 'fudosan_keihi_gokei'))
                <tr class="{{ $isTotalRow ? 'is-total-row' : '' }}">
                  <th class="{{ $isTotalRow ? 'text-center' : 'text-start' }} u-nowrap th-ddd{{ $isTotalRow ? '' : ' ' }}">
              @php($labelName = $field['labelInput'] ?? null)
                    @if($labelName)
                      @php($placeholder = $field['placeholder'] ?? '')
                      <input type="text"
                             name="{{ $labelName }}"
                             value="{{ old($labelName, $storedLabels[$labelName] ?? '') }}"
                             maxlength="10"
                             class="form-control kana10"
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
                           maxlength="{{ $readonly ? 12 : 11 }}"
                           class="form-control suji11 text-end{{ $readonly ? ' bg-light' : '' }}"
                           value="{{ old($name, $inputs[$name] ?? null) }}" @if($readonly) readonly @endif>
                  </td>
                  <td>
                    @php($name = $field['name'] . '_curr')
                    <input type="text" inputmode="numeric" autocomplete="off"
                           data-format="comma-int" data-name="{{ $name }}"
                           maxlength="{{ $readonly ? 12 : 11 }}"
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
                  <th class="text-start align-middle ps-1" colspan="2">{{ $field['label'] }}</th>
                  <td>
                    @php($name = $field['name'] . '_prev')
                    @php($readonly = $field['readonly'] ?? false)
                    <input type="text" inputmode="numeric" autocomplete="off"
                           data-format="comma-int" data-name="{{ $name }}"
                           maxlength="{{ $readonly ? 12 : 11 }}"
                           class="form-control suji11 text-end{{ $readonly ? ' bg-light' : '' }}"
                           value="{{ old($name, $inputs[$name] ?? null) }}" @if($readonly) readonly @endif>
                  </td>
                  <td>
                    @php($name = $field['name'] . '_curr')
                    <input type="text" inputmode="numeric" autocomplete="off"
                           data-format="comma-int" data-name="{{ $name }}"
                           maxlength="{{ $readonly ? 12 : 11 }}"
                           class="form-control suji11 text-end{{ $readonly ? ' bg-light' : '' }}"
                           value="{{ old($name, $inputs[$name] ?? null) }}" @if($readonly) readonly @endif>
                  </td>
                </tr>
              @endforeach
            </tbody>
          </table>
        </div>
        <hr class="mb-2">
          <div class="d-flex justify-content-between">
            <div>
              <button type="submit"
                      class="btn-base-green"
                      id="btn-recalc"
                      data-disable-on-submit>再計算</button>
            </div>
            <div class="d-flex">
              <button type="button"
                      class="btn-base-blue me-2 js-help-btn"
                      data-help-key="fudosan_shotoku"
                      data-bs-toggle="modal"
                      data-bs-target="#helpModalCommon">HELP</button>
              <button type="submit" class="btn-base-blue" id="btn-back">戻 る</button>
            </div>
          </div>
      </form>
  </div>
</div>

{{-- ============================
   HELP出力モーダル（不動産所得画面専用）
   ============================ --}}
<div class="modal fade" id="helpModalCommon" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog" style="max-width: 650px;">
    <div class="modal-content">
      <div class="modal-header mb-0">
        <button type="button" class="btn btn-vp me-2">HELP</button><h15 class="modal-title" id="helpModalTitle">HELP</h15>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body text-start mt-0 ms-2 mb-2">
        <div id="helpModalBody" class="small" style="white-space: pre-wrap;"></div>
      </div>
    </div>
  </div>
</div>

<script>
  window.__PAGE_HELP_TEXTS__ = @json($HELP_TEXTS, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
</script>
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
<script>
document.addEventListener('click', function (e) {
  const btn = e.target.closest('.js-help-btn');
  if (!btn) return;

  const key  = btn.getAttribute('data-help-key') || '';
  const dict = window.__PAGE_HELP_TEXTS__ || {};
  const item = dict[key] || {};

  const title    = item.title || 'HELP';
  const htmlBody = (typeof item.html === 'string') ? item.html : '';
  const body     = (typeof item.body === 'string') ? item.body : '';
  const image    = (typeof item.image === 'string') ? item.image : '';

  const titleEl  = document.getElementById('helpModalTitle');
  const bodyEl   = document.getElementById('helpModalBody');
  const modalEl  = document.getElementById('helpModalCommon');
  const dialogEl = modalEl ? modalEl.querySelector('.modal-dialog') : null;

  if (titleEl) {
    titleEl.textContent = title;
  }
  if (!bodyEl) return;

  if (dialogEl) {
    dialogEl.style.maxWidth = '650px';
  }

  if (htmlBody) {
    bodyEl.innerHTML = htmlBody;
    return;
  }

  const escapeHtml = function (str) {
    return String(str)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#039;');
  };

  let html = '';

  if (image) {
    html += ''
      + '<div style="text-align:center; margin:0 0 12px 0;">'
      +   '<img src="' + image + '"'
      +        ' alt="' + escapeHtml(title) + '"'
      +        ' style="max-width:100%; height:auto; border:1px solid #ccc;">'
      + '</div>';
  }

  html += escapeHtml(body)
    .replace(/^○([^\n\r]+)/gm, '<strong>○$1</strong>')
    .replace(/\n/g, '<br>');

  bodyEl.innerHTML = html;
});
</script>

@endpush
 
{{-- Enter移動（ふるさと全画面共通） --}}
@include('tax.furusato.partials.enter_nav')
    @push('styles')
    <style>
      /* 不動産：必要経費の項目名（8行＋その他）だけ、入力欄の左に余白を付ける */
      input.form-control.kana10[name^="fudosan_keihi_label_"] {
        padding-left: 0.25rem; /* bootstrap の ps-1 相当 */
      }

      #helpModalCommon .modal-content {
        font-family: inherit;
        font-size: 15px;
      }

      #helpModalCommon .modal-body {
        padding-left: 2rem;
        padding-right: 2rem;
      }

      #helpModalCommon #helpModalBody strong {
        font-weight: 700;
        color: #192C4B;
      }

</style>
    @endpush

@endsection