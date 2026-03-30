<!-- resources/views/tax/furusato/details/bunri_sanrin_details.blade.php -->
@extends('layouts.min')

@section('title', '山林所得 内訳')

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
    $helpPath = resource_path('views/tax/furusato/helps/help_bunri_sanrin_details_modal.php');
    $HELP_TEXTS = file_exists($helpPath) ? require $helpPath : [];
@endphp
<div class="container-blue mt-2" style="width: 720px;">
  <div class="card-header d-flex align-items-start justify-content-between">
    <div class="d-flex align-items-start">
      @include('components.kado_lefttop_img')
      <h0 class="mb-0 mt-2 ms-2">内訳－山林所得</h0>
    </div>
  </div>
  <div class="card-body m-3">
      <form method="POST" action="{{ route('furusato.details.bunri_sanrin.save') }}">
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
          <div class="fw-bold mb-1">{{ $label }}</div>
          @php $off = ($period === 'prev') ? $bunriPrevOff : $bunriCurrOff; @endphp
          <div class="table-responsive mb-2">
            <table class="table-input align-middle text-center">
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
                    @if($off)
                      <input type="text" class="form-control suji11 text-center bg-light" readonly value="－">
                      <input type="hidden" name="{{ $name }}" value="0">
                    @else
                      <input type="text" inputmode="numeric" autocomplete="off"
                             data-format="comma-int" data-name="{{ $name }}"
                             maxlength="11"
                             class="form-control suji11 text-end"
                             value="{{ old($name, $inputs[$name] ?? null) }}"
                             oninput="updateCalculation('{{ $period }}')">
                    @endif
                  </td>
                  @php($name = 'keihi_sanrin_' . $period)
                  <td>
                    @if($off)
                      <input type="text" class="form-control suji11 text-center bg-light" readonly value="－">
                      <input type="hidden" name="{{ $name }}" value="0">
                    @else
                      <input type="text" inputmode="numeric" autocomplete="off"
                             data-format="comma-int" data-name="{{ $name }}"
                             maxlength="11"
                             class="form-control suji11 text-end"
                             value="{{ old($name, $inputs[$name] ?? null) }}"
                             oninput="updateCalculation('{{ $period }}')">
                    @endif
                  </td>
                  @php($name = 'sashihiki_sanrin_' . $period)
                  <td>
                    @if($off)
                      <input type="text" class="form-control suji11 text-center bg-light" readonly value="－">
                      <input type="hidden" name="{{ $name }}" value="0">
                    @else
                      <input type="text" inputmode="numeric" autocomplete="off"
                             data-format="comma-int" data-name="{{ $name }}"
                             maxlength="12"
                             class="form-control suji11 text-end bg-light"
                             value="{{ old($name, $inputs[$name] ?? null) }}" readonly
                             oninput="updateCalculation('{{ $period }}')">
                    @endif
                  </td>
                  @php($name = 'tokubetsukojo_sanrin_' . $period)
                  <td>
                    @if($off)
                      <input type="text" class="form-control suji11 text-center bg-light" readonly value="－">
                      <input type="hidden" name="{{ $name }}" value="0">
                    @else
                      <input type="text" inputmode="numeric" autocomplete="off"
                             data-format="comma-int" data-name="{{ $name }}"
                             maxlength="11"
                             class="form-control suji11 text-end bg-light"
                             value="{{ old($name, $inputs[$name] ?? null) }}" readonly
                             oninput="updateCalculation('{{ $period }}')">
                    @endif
                  </td>
                  @php($name = 'shotoku_sanrin_' . $period)
                  <td>
                    @if($off)
                      <input type="text" class="form-control suji11 text-center bg-light" readonly value="－">
                      <input type="hidden" name="{{ $name }}" value="0">
                    @else
                      <input type="text" inputmode="numeric" autocomplete="off"
                             data-format="comma-int" data-name="{{ $name }}"
                             maxlength="12"
                             class="form-control suji11 text-end bg-light"
                             value="{{ old($name, $inputs[$name] ?? null) }}" readonly
                             oninput="updateCalculation('{{ $period }}')">
                    @endif
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        @endforeach
        <hr class="mb-3">
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
                      data-help-key="sanrin_shotoku"
                      data-bs-toggle="modal"
                      data-bs-target="#helpModalCommon">HELP</button>
              <button type="submit" class="btn-base-blue" id="btn-back">戻 る</button>
            </div>
          </div>
      </form>
  </div>
</div>

{{-- ============================
   HELP出力モーダル（山林所得画面専用）
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
    form.addEventListener('submit', () => {
      if (!stayFlag || (stayFlag.value !== '0' && stayFlag.value !== '1')) {
        stayFlag.value = '0';
      }
    });
  })();
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

  #helpModalCommon #helpModalBody .help-text {
    margin: 0 0 12px 0;
    line-height: 1.8;
  }
</style>
@endpush
@endsection