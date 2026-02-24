<!-- views/tax/furusato/details/kojo_iryo_details.blade.php -->
@extends('layouts.min')

@section('title', '内訳－医療費控除')

@section('content')
@php
    $inputs = $out['inputs'] ?? [];
    $warekiPrevLabel = $warekiPrev ?? '前年';
    $warekiCurrLabel = $warekiCurr ?? '当年';
    $originTab = 'input';
    $originAnchor = preg_replace('/[^A-Za-z0-9_-]/', '', (string) request()->input('origin_anchor', 'kojo_iryo'));
    // サーバ確定値をそのまま表示（JSで再計算しない）
    $nf = static function ($v): string {
      $n = (int) ($v ?? 0);
      return number_format($n);
    };
@endphp
<div class="container-blue mt-2" style="width:600px;">
  <div class="card-header d-flex align-items-start">
    @include('components.kado_lefttop_img')
    <h0 class="mb-0 mt-2">医療費控除の内訳</h0>
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
  <div class="card-body m-3">　
      <form method="POST" action="{{ route('furusato.details.kojo_iryo.save') }}">
        @csrf
        <input type="hidden" name="data_id" value="{{ $dataId }}">
        <input type="hidden" name="origin_tab" value="{{ $originTab }}">
        <input type="hidden" name="origin_anchor" value="{{ $originAnchor ?: 'kojo_iryo' }}">
        <input type="hidden" name="redirect_to" value="input">
        <input type="hidden" name="recalc_all" value="1">
        <input type="hidden" name="stay_on_details" id="stay-on-details-flag" value="0">
    
        <div class="table-responsive">
          <table class="table-input align-middle">
              <tr>
                <th scope="col" class="text-center th-ccc" style="width: 230px;height:30px;">項  目</th>
                <th scope="col" class="th-ccc">{{ $warekiPrevLabel }}</th>
                <th scope="col" class="th-ccc">{{ $warekiCurrLabel }}</th>
                <th scope="col" class="th-ccc" style="width: 40px;"></th>
              </tr>
            <tbody>
              <tr>
                <th scope="row" class="text-start ps-1">支払った医療費</th>
                <td>
                  <input type="text" inputmode="numeric" autocomplete="off"
                         data-format="comma-int" data-name="kojo_iryo_shiharai_prev"
                         maxlength="11"
                         class="form-control suji11 js-iryo text-end"
                         value="{{ old('kojo_iryo_shiharai_prev', $inputs['kojo_iryo_shiharai_prev'] ?? null) }}">
                </td>
                <td>
                  <input type="text" inputmode="numeric" autocomplete="off"
                         data-format="comma-int" data-name="kojo_iryo_shiharai_curr"
                         maxlength="11"
                         class="form-control suji11 js-iryo text-end"
                         value="{{ old('kojo_iryo_shiharai_curr', $inputs['kojo_iryo_shiharai_curr'] ?? null) }}">
                </td>
                <td class="bg-light">Ⓐ</td>
              </tr>
              <tr>
                <th scope="row" class="text-start ps-1">保険金などで補填される金額</th>
                <td>
                  <input type="text" inputmode="numeric" autocomplete="off"
                         data-format="comma-int" data-name="kojo_iryo_hotengaku_prev"
                         maxlength="11"
                         class="form-control suji11 js-iryo text-end"
                         value="{{ old('kojo_iryo_hotengaku_prev', $inputs['kojo_iryo_hotengaku_prev'] ?? null) }}">
                </td>
                <td>
                  <input type="text" inputmode="numeric" autocomplete="off"
                         data-format="comma-int" data-name="kojo_iryo_hotengaku_curr"
                         maxlength="11"
                         class="form-control suji11 js-iryo text-end"
                         value="{{ old('kojo_iryo_hotengaku_curr', $inputs['kojo_iryo_hotengaku_curr'] ?? null) }}">
                </td>
                <td class="bg-light">Ⓑ</td>
              </tr>
              <tr>
                <th scope="row" class="text-start ps-1">差引金額（Ⓐ－Ⓑ）</th>
                <td>
                  <input type="text" inputmode="numeric" autocomplete="off"
                         class="form-control suji11 text-end bg-light"
                         value="{{ $nf($inputs['kojo_iryo_sashihiki_prev'] ?? 0) }}" readonly>
                </td>
                <td>
                  <input type="text" inputmode="numeric" autocomplete="off"
                         class="form-control suji11 text-end bg-light"
                         value="{{ $nf($inputs['kojo_iryo_sashihiki_curr'] ?? 0) }}" readonly>
                </td>
                <td class="bg-light">Ⓒ</td>
              </tr>
              <tr>
                <th scope="row" class="text-start ps-1">所得金額の合計額</th>
                <td>
                  <input type="text" inputmode="numeric" autocomplete="off"
                         class="form-control suji11 text-end bg-light"
                         value="{{ $nf($inputs['kojo_iryo_shotoku_gokei_prev'] ?? 0) }}" readonly>
                </td>
                <td>
                  <input type="text" inputmode="numeric" autocomplete="off"
                         class="form-control suji11 text-end bg-light"
                         value="{{ $nf($inputs['kojo_iryo_shotoku_gokei_curr'] ?? 0) }}" readonly>
                </td>
                <td class="bg-light">Ⓓ</td>
              </tr>
              <tr>
                <th scope="row" class="text-start ps-1">Ⓓ×0.05</th>
                <td>
                  <input type="text" inputmode="numeric" autocomplete="off"
                         class="form-control suji11 text-end bg-light"
                         value="{{ $nf($inputs['kojo_iryo_shotoku_5pct_prev'] ?? 0) }}" readonly>
                </td>
                <td>
                  <input type="text" inputmode="numeric" autocomplete="off"
                         class="form-control suji11 text-end bg-light"
                         value="{{ $nf($inputs['kojo_iryo_shotoku_5pct_curr'] ?? 0) }}" readonly>
                </td>
                <td class="bg-light">Ⓔ</td>
              </tr>
              <tr>
                <th scope="row" class="text-start ps-1">Ⓔと10万円のいずれか少ない方の金額</th>
                <td>
                  <input type="text" inputmode="numeric" autocomplete="off"
                         class="form-control suji11 text-end bg-light"
                         value="{{ $nf($inputs['kojo_iryo_min_threshold_prev'] ?? 0) }}" readonly>
                </td>
                <td>
                  <input type="text" inputmode="numeric" autocomplete="off"
                         class="form-control suji11 text-end bg-light"
                         value="{{ $nf($inputs['kojo_iryo_min_threshold_curr'] ?? 0) }}" readonly>
                </td>
                <td class="bg-light">Ⓕ</td>
              </tr>
              <tr>
                <th scope="row" class="text-center th-cream">医療費控除額（Ⓒ－Ⓕ）</th>
                <td>
                  <input type="text" inputmode="numeric" autocomplete="off"
                         class="form-control suji11 text-end bg-light"
                         value="{{ $nf($inputs['kojo_iryo_kojogaku_prev'] ?? 0) }}" readonly>
                </td>
                <td>
                  <input type="text" inputmode="numeric" autocomplete="off"
                         class="form-control suji11 text-end bg-light"
                         value="{{ $nf($inputs['kojo_iryo_kojogaku_curr'] ?? 0) }}" readonly>
                </td>
                <td class="bg-light">Ⓖ</td>
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
              <button type="submit" class="btn-base-blue" id="btn-back">戻 る</button>
            </div>
          </div>
      </form>
  </div>    
</div>
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
      const getDisplay = (name) => document.querySelector(`[data-format="comma-int"][data-name="${name}"]`);
      const ensureHidden = (displayInput) => {
        const name = displayInput?.dataset?.name;
        if (!name) return null;

        // 既存 hidden を厳密に一意化
        let existing = Array.from(document.querySelectorAll(`input[type="hidden"][name="${name}"]`));
        if (existing.length > 1) {
          console.warn(`[kojo_iryo] duplicated hidden inputs for ${name}. Using the first and ignoring others.`);
          // 2個以上ある場合は最初以外を無効化（送信されないように name を外す等）
          existing.slice(1).forEach(el => el.name = `${name}__dup_ignored`);
        }
        let h = existing[0] || getHidden(name);
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

      // ===== 再計算 =====
      const recalcCol = (period) => {
        const a = V(`kojo_iryo_shiharai_${period}`);
        const b = V(`kojo_iryo_hotengaku_${period}`);
        const d = V(`kojo_iryo_shotoku_gokei_${period}`); // サーバ渡しの読み取り専用
        // 総所得金額等は負にならない前提で5%を計算（負の場合は0として扱う）
        const dPos = Math.max(d, 0);
        // 差引金額（A-B）…表示のⒸは従来どおり生の差引を表示
        const c = a - b;
        // 医療費控除の対象額には200万円の上限（A-B を 0〜2,000,000 に丸め）
        const cCapped = Math.min(Math.max(c, 0), 2000000);
        const e = Math.floor(dPos * 0.05);
        const f = Math.min(e, 100000);
        // 控除額は「上限適用後のC」から足切り額を引いたもの（0未満は0）
        const g = Math.max(cCapped - f, 0);

        S(`kojo_iryo_sashihiki_${period}`, c);
        S(`kojo_iryo_shotoku_5pct_${period}`, e);
        S(`kojo_iryo_min_threshold_${period}`, f);
        S(`kojo_iryo_kojogaku_${period}`, g);
      };

      // 表示input初期化（hidden生成＋初期カンマ整形）＆blurで再計算
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
          if (name.endsWith('_prev')) recalcCol('prev');
          if (name.endsWith('_curr')) recalcCol('curr');
        });
      });

      // 初期計算
      recalcCol('prev');
      recalcCol('curr');

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
@endpush
 
{{-- Enter移動（ふるさと全画面共通） --}}
@include('tax.furusato.partials.enter_nav')
