<!-- views/tax/furusato/details/bunri_kabuteki_details.blade.php -->
@extends('layouts.min')

@section('title', '株式等の譲渡所得等 内訳')

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

    $rows = [
        ['label' => '一般株式等の譲渡', 'key' => 'ippan_joto', 'has_kurikoshi' => false],
        ['label' => '上場株式等の譲渡', 'key' => 'jojo_joto', 'has_kurikoshi' => true],
        ['label' => '上場株式等の配当等', 'key' => 'jojo_haito', 'has_kurikoshi' => false],
    ];

    // 株式等の譲渡所得等ページ専用 HELP 辞書
    $helpPath = resource_path('views/tax/furusato/helps/help_bunri_kabuteki_modal.php');
    $HELP_TEXTS = file_exists($helpPath) ? require $helpPath : [];
@endphp

<div class="container-blue mt-2" style="width: 1000px;">
  <div class="card-header d-flex align-items-start justify-content-between">
    <div class="d-flex align-items-start">
      @include('components.kado_lefttop_img')
      <h0 class="mt-2 ms-2">内訳－株式等の譲渡所得等</h0>
    </div>
  </div>

  <div class="card-body m-3">
    <form method="POST" action="{{ route('furusato.details.bunri_kabuteki.save') }}">
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
                <th style="height:30px; width:127px;"></th>
                <th>収入金額</th>
                <th>必要経費</th>
                <th>所得金額</th>
                <th>損益通算後</th>
                <th>繰越損失の金額</th>
                <th>繰越控除後の所得金額</th>
              </tr>
            </thead>
            <tbody>
              @foreach ($rows as $row)
                @php($base = $row['key'] . '_' . $period)
                <tr>
                  <th class="text-start align-middle th-ddd ps-1">{{ $row['label'] }}</th>

                  @php($name = 'syunyu_' . $base)
                  <td>
                    @if($off)
                      <input type="text" class="form-control suji11 text-center bg-light" readonly value="－">
                      <input type="hidden" name="{{ $name }}" value="0">
                    @else
                      <input type="text" inputmode="numeric" autocomplete="off"
                             data-format="comma-int" data-name="{{ $name }}"
                             maxlength="11"
                             class="form-control suji11 text-end"
                             value="{{ old($name, $inputs[$name] ?? null) }}">
                    @endif
                  </td>

                  @php($name = 'keihi_' . $base)
                  <td>
                    @if($off)
                      <input type="text" class="form-control suji11 text-center bg-light" readonly value="－">
                      <input type="hidden" name="{{ $name }}" value="0">
                    @else
                      <input type="text" inputmode="numeric" autocomplete="off"
                             data-format="comma-int" data-name="{{ $name }}"
                             maxlength="11"
                             class="form-control suji11 text-end"
                             value="{{ old($name, $inputs[$name] ?? null) }}">
                    @endif
                  </td>

                  @php($name = 'shotoku_' . $base)
                  <td>
                    @if($off)
                      <input type="text" class="form-control suji11 text-center bg-light" readonly value="－">
                      <input type="hidden" name="{{ $name }}" value="0">
                    @else
                      <input type="text" inputmode="numeric" autocomplete="off"
                             class="form-control suji11 text-end bg-light"
                             value="{{ (isset($inputs[$name]) && $inputs[$name] !== '' && $inputs[$name] !== null)
                                 ? number_format((int) $inputs[$name])
                                 : '' }}"
                             data-display-key="{{ $name }}"
                             maxlength="12"
                             readonly>
                      {{-- ▼ derived（readonly）もPOSTして残留を防ぐ --}}
                      <input type="hidden" name="{{ $name }}" value="{{ old($name, $inputs[$name] ?? 0) }}">
                    @endif
                  </td>

                  @php($name = 'tsusango_' . $base)
                  <td>
                    @if($off)
                      <input type="text" class="form-control suji11 text-center bg-light" readonly value="－">
                      <input type="hidden" name="{{ $name }}" value="0">
                    @else
                      <input type="text" inputmode="numeric" autocomplete="off"
                             class="form-control suji11 text-end bg-light"
                             value="{{ (isset($inputs[$name]) && $inputs[$name] !== '' && $inputs[$name] !== null)
                                 ? number_format((int) $inputs[$name])
                                 : '' }}"
                             maxlength="12"
                             readonly>
                      {{-- ▼ derived（readonly）もPOSTして残留を防ぐ --}}
                      <input type="hidden" name="{{ $name }}" value="{{ old($name, $inputs[$name] ?? 0) }}">
                    @endif
                  </td>

                  <td>
                    @if ($row['has_kurikoshi'])
                      @php($name = 'kurikoshi_' . $base)
                      @if($off)
                        <input type="text" class="form-control suji11 text-center bg-light" readonly value="－">
                        <input type="hidden" name="{{ $name }}" value="0">
                      @else
                        <input type="text" inputmode="numeric" autocomplete="off"
                               data-format="comma-int" data-name="{{ $name }}"
                               maxlength="11"
                               class="form-control suji11 text-end"
                               value="{{ old($name, $inputs[$name] ?? null) }}">
                      @endif
                    @else
                      <span class="d-inline-block w-100">－</span>
                    @endif
                  </td>

                  @php($name = 'shotoku_after_kurikoshi_' . $base)
                  <td>
                    @if($off)
                      <input type="text" class="form-control suji11 text-center bg-light" readonly value="－">
                      <input type="hidden" name="{{ $name }}" value="0">
                    @else
                      <input type="text" inputmode="numeric" autocomplete="off"
                             class="form-control suji11 text-end bg-light"
                             value="{{ (isset($inputs[$name]) && $inputs[$name] !== '' && $inputs[$name] !== null)
                                 ? number_format((int) $inputs[$name])
                                 : '' }}"
                             maxlength="12"
                             readonly>
                      {{-- ▼ derived（readonly）もPOSTして残留を防ぐ --}}
                      <input type="hidden" name="{{ $name }}" value="{{ old($name, $inputs[$name] ?? 0) }}">
                    @endif
                  </td>
                </tr>
              @endforeach
            </tbody>
          </table>
        </div>
      @endforeach

      <hr class="mb-2">

      <div class="d-flex justify-content-between">
        <div>
          <button type="submit"
                  class="btn-base-green"
                  id="btn-recalc"
                  data-disable-on-submit>再計算</button>
        </div>
        <div class="d-flex">
          <!-- HELP（戻るの左） -->
          <button type="button"
                  class="btn-base-blue js-help-btn-bunrikabuteki me-2"
                  data-help-key="bunri_kabuteki"
                  data-bs-toggle="modal"
                  data-bs-target="#helpModalBunriKabuteki">HELP</button>
          <button type="submit" class="btn-base-blue" id="btn-back">戻 る</button>
        </div>
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

    form.addEventListener('submit', () => {
      if (!stayFlag || (stayFlag.value !== '0' && stayFlag.value !== '1')) {
        stayFlag.value = '0';
      }
    });
  })();

  // ===== カンマ整形 + hidden 連携（入力欄のみ：syunyu/keihi/kurikoshi）=====
  // この画面では、所得金額/損益通算後/繰越控除後はサーバ確定値の「表示専用」。
  // JSで再計算すると古い値の混入源になるため、ここでは「入力欄の整形とPOST用hidden同期」だけ行う。
  const toRawInt = (value) => {
    if (typeof value !== 'string') return '';
    const stripped = value.replace(/,/g, '').trim();
    if (stripped === '' || stripped === '-') return '';
    if (!/^(-)?\d+$/.test(stripped)) return '';
    const n = parseInt(stripped, 10);
    return Number.isNaN(n) ? '' : String(n);
  };

  const formatWithComma = (raw) => {
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

  const getDisplayByKey = (name) => document.querySelector(`[data-display-key="${name}"]`);

  // signed int をカンマ整形して readonly 表示欄へ入れる（所得金額だけ）
  const setDisplayInt = (displayKey, value) => {
    const el = getDisplayByKey(displayKey);
    if (!el) return;
    const n = Math.trunc(Number(value) || 0);
    el.value = n.toLocaleString('ja-JP');
  };

  // hidden から整数を取得（無ければ0）
  const V = (name) => {
    const h = getHidden(name);
    const raw = toRawInt(String(h?.value ?? ''));
    const n = raw === '' ? 0 : parseInt(raw, 10);
    return Number.isNaN(n) ? 0 : n;
  };

  const ensureHidden = (displayInput) => {
    const name = displayInput?.dataset?.name;
    if (!name) return null;

    let hidden = getHidden(name);
    if (!hidden) {
      hidden = document.createElement('input');
      hidden.type = 'hidden';
      hidden.name = name;
      hidden.dataset.commaMirror = '1';
      (displayInput.parentElement || displayInput.closest('form') || document.body).appendChild(hidden);
      hiddenCache.set(name, hidden);
    }

    const raw = toRawInt(displayInput.value ?? '');
    hidden.value = raw;
    displayInput.value = raw === '' ? '' : formatWithComma(raw);

    return hidden;
  };

  // ===== 表示 input（data-name）に hidden を用意し、blurで3桁カンマ化 =====
  const displays = Array.from(document.querySelectorAll('[data-format="comma-int"][data-name]'));

  displays.forEach((input) => {
    const name = input.dataset.name;
    if (!name) return;

    ensureHidden(input);

    const h = getHidden(name);
    const raw = toRawInt((h?.value ?? '') !== '' ? (h?.value ?? '') : (input.value ?? ''));
    if (h) h.value = raw;
    input.value = raw === '' ? '' : formatWithComma(raw);

    if (input.readOnly) return;

    input.addEventListener('focus', () => {
      const hidden = getHidden(name);
      input.value = hidden ? hidden.value : toRawInt(input.value ?? '');
      input.select();
    });

    input.addEventListener('blur', () => {
      const hidden = getHidden(name) || ensureHidden(input);
      const raw = toRawInt(input.value ?? hidden?.value ?? '');
      if (hidden) hidden.value = raw;
      input.value = raw === '' ? '' : formatWithComma(raw);

      // 所得金額（=収入-経費）だけを即時再計算して表示
      const m = name.match(/^(syunyu|keihi)_(ippan_joto|jojo_joto|jojo_haito)_(prev|curr)$/);
      if (m) {
        const rowKey = m[2];
        const period = m[3];
        const syunyuKey = `syunyu_${rowKey}_${period}`;
        const keihiKey  = `keihi_${rowKey}_${period}`;
        const shotokuKey = `shotoku_${rowKey}_${period}`;
        const syunyu = V(syunyuKey);
        const keihi  = V(keihiKey);
        setDisplayInt(shotokuKey, syunyu - keihi);
      }
    });
  });

  // 初期表示でも「所得金額（収入−経費）」だけは整合させる
  (function initShotokuDisplay() {
    ['prev', 'curr'].forEach((period) => {
      ['ippan_joto', 'jojo_joto', 'jojo_haito'].forEach((rowKey) => {
        const syunyu = V(`syunyu_${rowKey}_${period}`);
        const keihi  = V(`keihi_${rowKey}_${period}`);
        setDisplayInt(`shotoku_${rowKey}_${period}`, syunyu - keihi);
      });
    });
  })();

  // 送信直前：hiddenへ数値を確実に格納
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

@push('styles')
<style>
  /* 株式等の譲渡所得等ページのHELPモーダルだけ */
  #helpModalBunriKabuteki .modal-dialog { max-width: 750px; }
  #helpModalBunriKabuteki .modal-content { font-family: inherit; font-size: 15px; }
  #helpModalBunriKabuteki .modal-body { padding-left: 2rem; padding-right: 2rem; }

  /* 「○」行：太字＋色 */
  #helpModalBunriKabuteki #helpModalBodyBunriKabuteki strong.help-bullet {
    font-weight: 700;
    color: #192C4B;
  }

  /* 「■」行：太字＋濃い色＋1Q程度大きく */
  #helpModalBunriKabuteki #helpModalBodyBunriKabuteki strong.help-square {
    font-weight: 700;
    color: #192C4B;
    font-size: calc(1em + 1px);
    line-height: 1.5;
  }

  /* 「(1)」など：太字のみ */
  #helpModalBunriKabuteki #helpModalBodyBunriKabuteki strong.help-num {
    font-weight: 700;
  }

  /* __下線__ 記法 */
  #helpModalBunriKabuteki #helpModalBodyBunriKabuteki u {
    text-underline-offset: 2px;
  }
  /* html形式のHELP本文用 */
  #helpModalBunriKabuteki .furu-help-head {
    line-height: 1.2;
    font-weight: 700;
    color: #192C4B;
  }

  #helpModalBunriKabuteki .help-text.furu-help-line {
    line-height: 1.2;
  }

  #helpModalBunriKabuteki .help-text.furu-help-body {
    padding-left: 2.5em;
    line-height: 1.2;
  }

  #helpModalBunriKabuteki .help-text.furu-help-item {
    padding-left: 4em;
    text-indent: -1em;
    line-height: 1.2;
  }

  #helpModalBunriKabuteki .help-text.furu-help-item-last {
    padding-left: 4em;
    text-indent: -1em;
    line-height: 1.2;
  }

  #helpModalBunriKabuteki .help-text.furu-help-note-hanging {
    padding-left: 2.5em;
    text-indent: -1em;
    line-height: 1.2;
  }

  #helpModalBunriKabuteki .help-text.furu-help-star {
    padding-left: 1.5em;
    line-height: 1.2;
  }

  #helpModalBunriKabuteki .furu-help-star-label {
    color: #701616;
    font-weight: 700;
  }
</style>
@endpush

<script>
  window.__PAGE_HELP_TEXTS_BUNRIKABUTEKI__ = @json($HELP_TEXTS, JSON_UNESCAPED_UNICODE);
</script>

{{-- 共通HELPモーダル（株式等の譲渡所得等ページ専用） --}}
<div class="modal fade" id="helpModalBunriKabuteki" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <button type="button" class="btn btn-vp me-2">HELP</button>
        <h15 class="modal-title" id="helpModalTitleBunriKabuteki">HELP</h15>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body text-start mt-0 ms-2 mb-2">
        <div id="helpModalBodyBunriKabuteki" class="small" style="white-space: normal;"></div>
      </div>
    </div>
  </div>
</div>

@push('scripts')
<script>
document.addEventListener('click', function (e) {
  const btn = e.target.closest('.js-help-btn-bunrikabuteki');
  if (!btn) return;

  const key  = btn.getAttribute('data-help-key') || '';
  const dict = window.__PAGE_HELP_TEXTS_BUNRIKABUTEKI__ || {};
  
  const item = dict[key] || {};

  const title    = item.title || 'HELP';
  const htmlBody = (typeof item.html === 'string') ? item.html : '';
  const body     = (typeof item.body === 'string') ? item.body : '（この項目のHELPは未登録です）';
 
   const titleEl = document.getElementById('helpModalTitleBunriKabuteki');
   const bodyEl  = document.getElementById('helpModalBodyBunriKabuteki');
 
   if (titleEl) titleEl.textContent = title;
   if (!bodyEl) return;

  if (htmlBody) {
    bodyEl.innerHTML = htmlBody;
    return;
  }

  const escapeHtml = (s) => String(s)
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#039;');

  const underline = (safeText) => safeText.replace(/__([^_]+)__/g, '<u>$1</u>');

  const html = String(body)
    .split('\n')
    .map((line) => {
      if (line === '') return '';

      // 行頭「(1)」「(2)」など：太字
      const mNum = line.match(/^(\s*\(\d+\)\s*[^　 ]*)(.*)$/);
      if (mNum) {
        const head = underline(escapeHtml(mNum[1]));
        const rest = underline(escapeHtml(mNum[2] ?? ''));
        return `<strong class="help-num">${head}</strong>${rest}`;
      }

      // 行頭「■」：太字＋濃い色＋少し大きく
      const mSquare = line.match(/^(\s*■.*)$/);
      if (mSquare) {
        const text = underline(escapeHtml(mSquare[1]));
        return `<strong class="help-square">${text}</strong>`;
      }

      // 行頭「○」：太字＋色
      const mCircle = line.match(/^(\s*○.*)$/);
      if (mCircle) {
        const text = underline(escapeHtml(mCircle[1]));
        return `<strong class="help-bullet">${text}</strong>`;
      }

      return underline(escapeHtml(line));
    })
    .join('<br>');

  bodyEl.innerHTML = html;
});
</script>
@endpush

{{-- Enter移動（ふるさと全画面共通） --}}
@include('tax.furusato.partials.enter_nav')

@endsection