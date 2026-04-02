<!-- views/tax/furusato/details/kojo_seimei_jishin_details.blade.php -->
@extends('layouts.min')

@section('title', '内訳ー生命保険料・地震保険料')

@section('content')
@php
    $inputs = $out['inputs'] ?? [];
    $warekiPrevLabel = $warekiPrev ?? '前年';
    $warekiCurrLabel = $warekiCurr ?? '当年';
    $originTab = 'input';
    $originAnchor = preg_replace('/[^A-Za-z0-9_-]/', '', (string) request()->input('origin_anchor', 'kojo_seimei_jishin'));
    $helpPath = resource_path('views/tax/furusato/helps/help_kojo_seimei_jishin_details_modal.php');
    $HELP_TEXTS = file_exists($helpPath) ? require $helpPath : [];
@endphp
<div class="container-blue mt-2" style="width:480px;">
  <div class="card-header d-flex align-items-start">
    @include('components.kado_lefttop_img')
    <h0 class="mt-2">生命保険料・地震保険料の内訳</h0>
  </div>
  @if ($errors->any())
    <div class="alert alert-danger">
      <ul class="mb-0">
        @foreach ($errors->all() as $error)
          <li>{{ $error }}</li>
        @endforeach
      </ul>
    </div>
  @endif
　 <div class="card-body mx-3 mb-2 mt-2">
      <form method="POST" action="{{ route('furusato.details.kojo_seimei_jishin.save') }}">
        @csrf
        <input type="hidden" name="data_id" value="{{ $dataId }}">
        <input type="hidden" name="origin_tab" value="{{ $originTab }}">
        <input type="hidden" name="origin_anchor" value="{{ $originAnchor ?: 'kojo_seimei_jishin' }}">
        <input type="hidden" name="redirect_to" value="input">
        <input type="hidden" name="recalc_all" value="1">
        <input type="hidden" name="stay_on_details" id="stay-on-details-flag" value="0">
    
        <div class="table-responsive">
          <table class="table-input align-middle mb-4">
              <tr style="height:30px;">
                <th class="text-center th-ccc" style="width:120px;">項&nbsp;&nbsp;&nbsp;&nbsp;目</th>
                <th class="th-ccc">{{ $warekiPrevLabel }}</th>
                <th class="th-ccc">{{ $warekiCurrLabel }}</th>
              </tr>
            <tbody>
              <tr>
                <th scope="row" class="text-start ps-1">新生命保険料</th>
                <td>
                  <input type="text" inputmode="numeric" autocomplete="off"
                         data-format="comma-int" data-name="kojo_seimei_shin_prev"
                         maxlength="11"
                         class="form-control suji11 js-seimei text-end"
                         value="{{ old('kojo_seimei_shin_prev', $inputs['kojo_seimei_shin_prev'] ?? null) }}">
                </td>
                <td>
                  <input type="text" inputmode="numeric" autocomplete="off"
                         data-format="comma-int" data-name="kojo_seimei_shin_curr"
                         maxlength="11"
                         class="form-control suji11 js-seimei text-end"
                         value="{{ old('kojo_seimei_shin_curr', $inputs['kojo_seimei_shin_curr'] ?? null) }}">
                </td>
              </tr>
              <tr>
                <th scope="row" class="text-start ps-1">旧生命保険料</th>
                <td>
                  <input type="text" inputmode="numeric" autocomplete="off"
                         data-format="comma-int" data-name="kojo_seimei_kyu_prev"
                         maxlength="11"
                         class="form-control suji11 js-seimei text-end"
                         value="{{ old('kojo_seimei_kyu_prev', $inputs['kojo_seimei_kyu_prev'] ?? null) }}">
                </td>
                <td>
                  <input type="text" inputmode="numeric" autocomplete="off"
                         data-format="comma-int" data-name="kojo_seimei_kyu_curr"
                         maxlength="11"
                         class="form-control suji11 js-seimei text-end"
                         value="{{ old('kojo_seimei_kyu_curr', $inputs['kojo_seimei_kyu_curr'] ?? null) }}">
                </td>
              </tr>
              <tr>
                <th scope="row" class="text-start ps-1">新個人年金保険料</th>
                <td>
                  <input type="text" inputmode="numeric" autocomplete="off"
                         data-format="comma-int" data-name="kojo_seimei_nenkin_shin_prev"
                         maxlength="11"
                         class="form-control suji11 js-seimei text-end"
                         value="{{ old('kojo_seimei_nenkin_shin_prev', $inputs['kojo_seimei_nenkin_shin_prev'] ?? null) }}">
                </td>
                <td>
                  <input type="text" inputmode="numeric" autocomplete="off"
                         data-format="comma-int" data-name="kojo_seimei_nenkin_shin_curr"
                         maxlength="11"
                         class="form-control suji11 js-seimei text-end"
                         value="{{ old('kojo_seimei_nenkin_shin_curr', $inputs['kojo_seimei_nenkin_shin_curr'] ?? null) }}">
                </td>
              </tr>
              <tr>
                <th scope="row" class="text-start ps-1">旧個人年金保険料</th>
                <td>
                  <input type="text" inputmode="numeric" autocomplete="off"
                         data-format="comma-int" data-name="kojo_seimei_nenkin_kyu_prev"
                         maxlength="11"
                         class="form-control suji11 js-seimei text-end"
                         value="{{ old('kojo_seimei_nenkin_kyu_prev', $inputs['kojo_seimei_nenkin_kyu_prev'] ?? null) }}">
                </td>
                <td>
                  <input type="text" inputmode="numeric" autocomplete="off"
                         data-format="comma-int" data-name="kojo_seimei_nenkin_kyu_curr"
                         maxlength="11"
                         class="form-control suji11 js-seimei text-end"
                         value="{{ old('kojo_seimei_nenkin_kyu_curr', $inputs['kojo_seimei_nenkin_kyu_curr'] ?? null) }}">
                </td>
              </tr>
              <tr>
                <th scope="row" class="text-start ps-1">介護医療保険料</th>
                <td>
                  <input type="text" inputmode="numeric" autocomplete="off"
                         data-format="comma-int" data-name="kojo_seimei_kaigo_iryo_prev"
                         maxlength="11"
                         class="form-control suji11 js-seimei text-end"
                         value="{{ old('kojo_seimei_kaigo_iryo_prev', $inputs['kojo_seimei_kaigo_iryo_prev'] ?? null) }}">
                </td>
                <td>
                  <input type="text" inputmode="numeric" autocomplete="off"
                         data-format="comma-int" data-name="kojo_seimei_kaigo_iryo_curr"
                         maxlength="11"
                         class="form-control suji11 js-seimei text-end"
                         value="{{ old('kojo_seimei_kaigo_iryo_curr', $inputs['kojo_seimei_kaigo_iryo_curr'] ?? null) }}">
                </td>
              </tr>
              <tr>
                <th scope="row" class="text-center th-cream">合&nbsp;&nbsp;&nbsp;&nbsp;計</th>
                <td>
                  <input type="text" inputmode="numeric" autocomplete="off"
                         data-format="comma-int" data-name="kojo_seimei_gokei_prev"
                         maxlength="11"
                         class="form-control suji11 text-end bg-light"
                         value="{{ old('kojo_seimei_gokei_prev', $inputs['kojo_seimei_gokei_prev'] ?? null) }}" readonly>
                </td>
                <td>
                  <input type="text" inputmode="numeric" autocomplete="off"
                         data-format="comma-int" data-name="kojo_seimei_gokei_curr"
                         maxlength="11"
                         class="form-control suji11 text-end bg-light"
                         value="{{ old('kojo_seimei_gokei_curr', $inputs['kojo_seimei_gokei_curr'] ?? null) }}" readonly>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
        <div class="table-responsive">
          <table class="table-input align-middle mb-0">
              <tr style="height:30px;">
                <th class="text-center th-ccc" style="width:120px;">項&nbsp;&nbsp;&nbsp;&nbsp;目</th>
                <th class="th-ccc">{{ $warekiPrevLabel }}</th>
                <th class="th-ccc">{{ $warekiCurrLabel }}</th>
              </tr>
            <tbody>
              <tr>
                <th scope="row" class="text-start ps-1">地震保険料</th>
                <td>
                  <input type="text" inputmode="numeric" autocomplete="off"
                         data-format="comma-int" data-name="kojo_jishin_prev"
                         maxlength="11"
                         class="form-control suji11 js-jishin text-end"
                         value="{{ old('kojo_jishin_prev', $inputs['kojo_jishin_prev'] ?? null) }}">
                </td>
                <td>
                  <input type="text" inputmode="numeric" autocomplete="off"
                         data-format="comma-int" data-name="kojo_jishin_curr"
                         maxlength="11"
                         class="form-control suji11 js-jishin text-end"
                         value="{{ old('kojo_jishin_curr', $inputs['kojo_jishin_curr'] ?? null) }}">
                </td>
              </tr>
              <tr>
                <th scope="row" class="text-start ps-1">旧長期損害保険料</th>
                <td>
                  <input type="text" inputmode="numeric" autocomplete="off"
                         data-format="comma-int" data-name="kojo_kyuchoki_songai_prev"
                         maxlength="11"
                         class="form-control suji11 js-jishin text-end"
                         value="{{ old('kojo_kyuchoki_songai_prev', $inputs['kojo_kyuchoki_songai_prev'] ?? null) }}">
                </td>
                <td>
                  <input type="text" inputmode="numeric" autocomplete="off"
                         data-format="comma-int" data-name="kojo_kyuchoki_songai_curr"
                         maxlength="11"
                         class="form-control suji11 js-jishin text-end"
                         value="{{ old('kojo_kyuchoki_songai_curr', $inputs['kojo_kyuchoki_songai_curr'] ?? null) }}">
                </td>
              </tr>
              <tr>
                <th scope="row" class="text-center th-cream">合&nbsp;&nbsp;&nbsp;&nbsp;計</th>
                <td>
                  <input type="text" inputmode="numeric" autocomplete="off"
                         data-format="comma-int" data-name="kojo_jishin_gokei_prev"
                         maxlength="11"
                         class="form-control suji11 text-end bg-light"
                         value="{{ old('kojo_jishin_gokei_prev', $inputs['kojo_jishin_gokei_prev'] ?? null) }}" readonly>
                </td>
                <td>
                  <input type="text" inputmode="numeric" autocomplete="off"
                         data-format="comma-int" data-name="kojo_jishin_gokei_curr"
                         maxlength="11"
                         class="form-control suji11 text-end bg-light"
                         value="{{ old('kojo_jishin_gokei_curr', $inputs['kojo_jishin_gokei_curr'] ?? null) }}" readonly>
                </td>
              </tr>
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
                      data-help-key="seimei_jishin_hokenryo_kojo"
                      data-bs-toggle="modal"
                      data-bs-target="#helpModalCommon">HELP</button>
              <button type="submit" class="btn-base-blue" id="btn-back">戻 る</button>
            </div>
          </div>
      </form>
  </div>    
</div>

{{-- ============================
   HELP出力モーダル（生命保険料・地震保険料画面専用）
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
@endsection

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
        const d = document.querySelector(`[data-format="comma-int"][data-name="${name}"]`);
        if (val === '' || val === null || typeof val === 'undefined' || Number.isNaN(val)) {
          if (h) h.value = '';
          if (d) d.value = '';
          return;
        }
        const raw = String(Math.trunc(Number(val) || 0));
        if (h) h.value = raw;
        if (d) d.value = fmt(raw);
      };

      const seimeiKeysPrev = [
        'kojo_seimei_shin_prev',
        'kojo_seimei_kyu_prev',
        'kojo_seimei_nenkin_shin_prev',
        'kojo_seimei_nenkin_kyu_prev',
        'kojo_seimei_kaigo_iryo_prev',
      ];
      const seimeiKeysCurr = [
        'kojo_seimei_shin_curr',
        'kojo_seimei_kyu_curr',
        'kojo_seimei_nenkin_shin_curr',
        'kojo_seimei_nenkin_kyu_curr',
        'kojo_seimei_kaigo_iryo_curr',
      ];
      const jishinKeysPrev = [
        'kojo_jishin_prev',
        'kojo_kyuchoki_songai_prev',
      ];
      const jishinKeysCurr = [
        'kojo_jishin_curr',
        'kojo_kyuchoki_songai_curr',
      ];

      const sumByKeys = (keys) => keys.reduce((acc, k) => acc + V(k), 0);

      const updateSeimeiTotals = () => {
        const prevTotal = sumByKeys(seimeiKeysPrev);
        const currTotal = sumByKeys(seimeiKeysCurr);
        S('kojo_seimei_gokei_prev', prevTotal);
        S('kojo_seimei_gokei_curr', currTotal);
      };

      const updateJishinTotals = () => {
        const prevTotal = sumByKeys(jishinKeysPrev);
        const currTotal = sumByKeys(jishinKeysCurr);
        S('kojo_jishin_gokei_prev', prevTotal);
        S('kojo_jishin_gokei_curr', currTotal);
      };

      // 表示input初期化（hidden生成＋初期カンマ整形）＆ blurで合計再計算
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
          if (name.startsWith('kojo_seimei_')) updateSeimeiTotals();
          if (name.startsWith('kojo_jishin') || name.startsWith('kojo_kyuchoki_songai')) updateJishinTotals();
        });
      });

      updateSeimeiTotals();
      updateJishinTotals();

      // 送信直前：hiddenへ数値を確実に格納（表示側はname無しでチラつき無し）
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
