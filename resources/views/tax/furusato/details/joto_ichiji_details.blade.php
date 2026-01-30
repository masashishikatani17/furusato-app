<!-- resources/views/tax/furusato/details/joto_ichiji_details.blade.php -->
@extends('layouts.min')

@section('title', '内訳ー総合譲渡・一時')

@section('content')
@php
    $inputs = $out['inputs'] ?? [];
    $warekiPrevLabel = $warekiPrev ?? '前年';
    $warekiCurrLabel = $warekiCurr ?? '当年';
    $originTabRaw = request()->input('origin_tab', 'input');
    $originTab = is_string($originTabRaw) && trim($originTabRaw) === 'input' ? 'input' : '';
    $originAnchor = preg_replace('/[^A-Za-z0-9_-]/', '', (string) request()->input('origin_anchor', ''));
@endphp
<div class="container mt-2" style="width: 1200px;">
  <div class="card-header d-flex align-items-start">
    <img src="{{ asset('storage/images/kado_lefttop.jpg') }}" alt="…">
    <h0 class="mb-2 mt-2">総合譲渡・一時の内訳</h0>
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
  <div class="card-body">
    <div class="wrapper">
      <form method="POST" action="{{ route('furusato.details.joto_ichiji.save') }}">
        @csrf
        <input type="hidden" name="data_id" value="{{ $dataId }}">
        <input type="hidden" name="origin_tab" value="{{ $originTab }}">
        <input type="hidden" name="origin_anchor" value="{{ $originAnchor }}">
        <input type="hidden" name="redirect_to" value="input">
        <input type="hidden" name="recalc_all" value="1">
        <input type="hidden" name="stay_on_details" id="stay-on-details-flag" value="0">
        {{-- ▼ Calculator入力用（サーバが読むキー）：短期/長期の「差引金額（総合）」を必ずPOSTする --}}
        <input type="hidden" name="sashihiki_joto_tanki_sogo_prev" id="sashihiki_joto_tanki_sogo_prev">
        <input type="hidden" name="sashihiki_joto_tanki_sogo_curr" id="sashihiki_joto_tanki_sogo_curr">
        <input type="hidden" name="sashihiki_joto_choki_sogo_prev" id="sashihiki_joto_choki_sogo_prev">
        <input type="hidden" name="sashihiki_joto_choki_sogo_curr" id="sashihiki_joto_choki_sogo_curr">

        @php
            $tables = [
                'prev' => $warekiPrevLabel,
                'curr' => $warekiCurrLabel,
            ];
        @endphp

        @foreach ($tables as $period => $label)
          <div class="mb-2">
            <hb class="fw-bold">{{ $label }}</hb>
            <div class="table-responsive">
              <table class="table-input align-middle text-center">
                <thead>
                  <tr>
                    <th colspan="2" style="height:30px;"></th>
                    <th>収入金額</th>
                    <th>必要経費</th>
                    <th>差引金額</th>
                    <th>内部通算後</th>
                    <th>特別控除額</th>
                    <th>譲渡・一時所得<br>の通算後</th>
                    <th>損益通算後</th>
                    <th>1/2</th>
                    <th>所得金額</th>
                  </tr>
                </thead>
                <tbody>
                  <tr>
                    <th nowrap="nowrap" rowspan="2" class="th-cream">総合<br>譲渡　</th>
                    <th nowrap="nowrap" class="th-cream">短期　</th>
                    <td>
                      <input type="text" inputmode="numeric"
                             data-format="comma-int" data-name="syunyu_joto_tanki_{{ $period }}"
                             class="form-control suji10"
                             value="{{ old('syunyu_joto_tanki_' . $period, $inputs['syunyu_joto_tanki_' . $period] ?? null) }}">
                    </td>
                    <td>
                      <input type="text" inputmode="numeric" 
                             data-format="comma-int" data-name="keihi_joto_tanki_{{ $period }}" 
                             class="form-control suji10" 
                             value="{{ old('keihi_joto_tanki_' . $period, $inputs['keihi_joto_tanki_' . $period] ?? null) }}">
                    </td>
                    <td>
                      <input type="text" inputmode="numeric" autocomplete="off"
                             data-format="comma-int" data-name="sashihiki_joto_tanki_{{ $period }}"
                             class="form-control suji10 text-end bg-light"
                             value="{{ $inputs['sashihiki_joto_tanki_' . $period] ?? '' }}" readonly>
                    </td>
                    <td>
                      {{-- 内部通算後（仕様上、tsusango_*_sogo を表示） --}}
                      <input type="text" inputmode="numeric" autocomplete="off"
                             data-format="comma-int" data-name="after_naibutsusan_joto_tanki_sogo_{{ $period }}"
                             class="form-control suji10 text-end bg-light"
                             value="{{ $inputs['tsusango_joto_tanki_sogo_' . $period] ?? '' }}" readonly>
                    </td>
                    <td>
                      <input type="text" inputmode="numeric" autocomplete="off"
                             data-format="comma-int" data-name="tokubetsukojo_joto_tanki_{{ $period }}"
                             class="form-control suji8 text-end bg-light"
                             value="{{ $inputs['tokubetsukojo_joto_tanki_sogo_' . $period] ?? '' }}" readonly>
                    </td>
                    <td>
                      <input type="text" inputmode="numeric" autocomplete="off"
                             data-format="comma-int" data-name="after_joto_ichiji_tousan_joto_tanki_{{ $period }}"
                             class="form-control suji10 text-end bg-light"
                             value="{{ $inputs['after_joto_ichiji_tousan_joto_tanki_sogo_' . $period] ?? '' }}" readonly>
                    </td>
                    <td>
                      {{-- 損益通算後（最終通算） --}}
                      <input type="text" inputmode="numeric" autocomplete="off"
                             data-format="comma-int" data-name="tsusango_joto_tanki_{{ $period }}"
                             class="form-control suji10 text-end bg-light"
                             value="{{ $inputs['after_3jitsusan_joto_tanki_sogo_' . $period] ?? '' }}" readonly>
                    </td>
                    <td class="text-center align-middle">－</td>
                    <td>
                      {{-- 最終 所得金額（サーバ確定） --}}
                      <input type="text" inputmode="numeric" autocomplete="off"
                             data-format="comma-int" data-name="shotoku_joto_tanki_{{ $period }}"
                             class="form-control suji10 text-end bg-light"
                             value="{{ $inputs['shotoku_joto_tanki_sogo_' . $period] ?? '' }}" readonly>
                    </td>
                  </tr>
                  <tr>
                    <th class="th-cream">長期　</th>
                    <td>
                      <input type="text" inputmode="numeric" 
                             data-format="comma-int" data-name="syunyu_joto_choki_{{ $period }}" 
                             class="form-control suji10"
                             value="{{ old('syunyu_joto_choki_' . $period, $inputs['syunyu_joto_choki_' . $period] ?? null) }}">
                    </td>
                    <td>
                      <input type="text" inputmode="numeric" 
                             data-format="comma-int" data-name="keihi_joto_choki_{{ $period }}"
                             class="form-control suji10"
                             value="{{ old('keihi_joto_choki_' . $period, $inputs['keihi_joto_choki_' . $period] ?? null) }}">
                    </td>
                    <td>
                      <input type="text" inputmode="numeric" autocomplete="off"
                             data-format="comma-int" data-name="sashihiki_joto_choki_{{ $period }}"
                             class="form-control suji10 text-end bg-light"
                             value="{{ $inputs['sashihiki_joto_choki_' . $period] ?? '' }}" readonly>
                    </td>
                    <td>
                      {{-- 内部通算後（仕様上、tsusango_*_sogo を表示） --}}
                      <input type="text" inputmode="numeric" autocomplete="off"
                             data-format="comma-int" data-name="after_naibutsusan_joto_choki_sogo_{{ $period }}"
                             class="form-control suji10 text-end bg-light"
                             value="{{ $inputs['tsusango_joto_choki_sogo_' . $period] ?? '' }}" readonly>
                    </td>
                    <td>
                      <input type="text" inputmode="numeric" autocomplete="off"
                             data-format="comma-int" data-name="tokubetsukojo_joto_choki_{{ $period }}"
                             class="form-control suji8 text-end bg-light"
                             value="{{ $inputs['tokubetsukojo_joto_choki_sogo_' . $period] ?? '' }}" readonly>
                    </td>
                    <td>
                      <input type="text" inputmode="numeric" autocomplete="off"
                             data-format="comma-int" data-name="after_joto_ichiji_tousan_joto_choki_{{ $period }}"
                             class="form-control suji10 text-end bg-light"
                             value="{{ $inputs['after_joto_ichiji_tousan_joto_choki_sogo_' . $period] ?? '' }}" readonly>
                    </td>
                    <td>
                      {{-- 損益通算後（最終通算） --}}
                      <input type="text" inputmode="numeric" autocomplete="off"
                             data-format="comma-int" data-name="tsusango_joto_choki_{{ $period }}"
                             class="form-control suji10 text-end bg-light"
                             value="{{ $inputs['after_3jitsusan_joto_choki_sogo_' . $period] ?? '' }}" readonly>
                    </td>
                    <td>
                      @php
                        $tsusangoChokiDisplay = old(
                          'tsusango_joto_choki_' . $period,
                          $inputs['tsusango_joto_choki_' . $period] ?? ($inputs['after_3jitsusan_joto_choki_sogo_' . $period] ?? 0)
                        );
                        $tsusangoChokiInt = (int) preg_replace('/[^\d-]/', '', (string) $tsusangoChokiDisplay);
                        // 1/2 は「控除額の表示」なので切り上げ。負は 0 扱い。
                        $halfJotoChoki    = (int) ceil(max(0, $tsusangoChokiInt) / 2);
                      @endphp
                      <input type="text" inputmode="numeric" autocomplete="off"
                             data-format="comma-int" data-name="half_joto_choki_{{ $period }}"
                             class="form-control suji8 text-end bg-light"
                             value="{{ $halfJotoChoki }}" readonly>
                    </td>
                    <td>
                      {{-- 最終 所得金額（サーバ確定） --}}
                      <input type="text" inputmode="numeric" autocomplete="off"
                             data-format="comma-int" data-name="shotoku_joto_choki_{{ $period }}"
                             class="form-control suji10 text-end bg-light"
                             value="{{ $inputs['shotoku_joto_choki_sogo_' . $period] ?? '' }}" readonly>
                    </td>
                  </tr>
                  <tr>
                    <th colspan="2" class="th-cream">一時</th>
                    <td>
                      <input type="text" inputmode="numeric"
                             data-format="comma-int" data-name="syunyu_ichiji_{{ $period }}"
                             class="form-control suji10"
                             value="{{ old('syunyu_ichiji_' . $period, $inputs['syunyu_ichiji_' . $period] ?? null) }}">
                    </td>
                    <td>
                      <input type="text" inputmode="numeric" 
                             data-format="comma-int" data-name="keihi_ichiji_{{ $period }}" 
                             class="form-control suji10" 
                             value="{{ old('keihi_ichiji_' . $period, $inputs['keihi_ichiji_' . $period] ?? null) }}">
                    </td>
                    <td>
                      <input type="text" inputmode="numeric" autocomplete="off"
                             data-format="comma-int" data-name="sashihiki_ichiji_{{ $period }}"
                             class="form-control suji10 text-end bg-light"
                             value="{{ max(0, (int)($inputs['syunyu_ichiji_' . $period] ?? 0) - (int)($inputs['keihi_ichiji_' . $period] ?? 0)) }}"
                             readonly
                             onblur="calculateSashihikiIchiji('{{ $period }}')">
                    </td>
                    <td>
                      <input type="text" inputmode="numeric" autocomplete="off"
                             data-format="comma-int" data-name="after_naibutsusan_ichiji_{{ $period }}"
                             class="form-control suji10 text-end bg-light"
                             value="{{ max(
                                 0,
                                 (int)($inputs['syunyu_ichiji_' . $period] ?? 0)
                                   - (int)($inputs['keihi_ichiji_' . $period] ?? 0)
                             ) }}"
                             readonly>
                    </td>
                    <td>
                      <input type="text" inputmode="numeric" autocomplete="off"
                             data-format="comma-int" data-name="tokubetsukojo_ichiji_{{ $period }}"
                             class="form-control suji8 text-end bg-light"
                             value="{{ $inputs['tokubetsukojo_ichiji_' . $period] ?? '' }}" readonly>
                    </td>
                    <td>
                      <input type="text" inputmode="numeric" autocomplete="off"
                             data-format="comma-int" data-name="after_joto_ichiji_tousan_ichiji_{{ $period }}"
                             class="form-control suji10 text-end bg-light"
                             value="{{ $inputs['after_joto_ichiji_tousan_ichiji_' . $period] ?? '' }}" readonly>
                    </td>
                    <td>
                      {{-- 損益通算後（最終通算） --}}
                      <input type="text" inputmode="numeric" autocomplete="off"
                             data-format="comma-int" data-name="tsusango_ichiji_{{ $period }}"
                             class="form-control suji10 text-end bg-light"
                             value="{{ $inputs['after_3jitsusan_ichiji_' . $period] ?? '' }}" readonly>
                    </td>
                    <td>
                      @php
                        $tsusangoIchijiDisplay = old(
                          'tsusango_ichiji_' . $period,
                          $inputs['after_3jitsusan_ichiji_' . $period] ?? 0
                        );
                        $tsusangoIchijiInt = (int) preg_replace('/[^\d-]/', '', (string) $tsusangoIchijiDisplay);
                        // 1/2 は「控除額の表示」なので切り上げ。負は 0 扱い。
                        $halfIchiji        = (int) ceil(max(0, $tsusangoIchijiInt) / 2);
                      @endphp
                      <input type="text" inputmode="numeric" autocomplete="off"
                             data-format="comma-int" data-name="half_ichiji_{{ $period }}"
                             class="form-control suji8 text-end bg-light"
                             value="{{ $halfIchiji }}" readonly>
                    </td>
                    <td>
                      <input type="text" inputmode="numeric" autocomplete="off"
                             data-format="comma-int" data-name="shotoku_ichiji_{{ $period }}"
                             class="form-control suji10 text-end bg-light"
                             value="{{ $inputs['shotoku_ichiji_' . $period] ?? '' }}" readonly>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>
        @endforeach
        
        <script>
          function calculateSashihikiIchiji(period) {
            const syunyu = parseFloat(document.querySelector('[data-name="syunyu_ichiji_' + period + '"]').value.replace(/,/g, '') || 0);
            const keihi = parseFloat(document.querySelector('[data-name="keihi_ichiji_' + period + '"]').value.replace(/,/g, '') || 0);
            const result = Math.max(0, syunyu - keihi);
            // 表示更新（カンマ付き）
            const out = document.querySelector('[data-name="sashihiki_ichiji_' + period + '"]');
            if (out) out.value = result.toLocaleString('ja-JP');
            // hidden(name="sashihiki_ichiji_*") にも raw を同期（これがPOSTに載る）
            const hidden = document.querySelector('input[type="hidden"][name="sashihiki_ichiji_' + period + '"]');
            if (hidden) hidden.value = String(result);
          }
          // ▼ 短期/長期の差引（sashihiki_joto_*）を計算し、Calculatorが読む *_sogo_* hidden にも同期
          function calculateSashihikiJoto(kind, period) {
            // kind = 'tanki' | 'choki'
            const syunyuEl = document.querySelector('[data-name="syunyu_joto_' + kind + '_' + period + '"]');
            const keihiEl  = document.querySelector('[data-name="keihi_joto_'  + kind + '_' + period + '"]');
            const outEl    = document.querySelector('[data-name="sashihiki_joto_' + kind + '_' + period + '"]');
            if (!syunyuEl || !keihiEl || !outEl) return;
            const toRaw = (v) => {
              const s = String(v ?? '').replace(/,/g, '').trim();
              if (s === '' || s === '-') return 0;
              const n = Number(s); return Number.isFinite(n) ? Math.trunc(n) : 0;
            };
            const syunyu = toRaw(syunyuEl.value);
            const keihi  = toRaw(keihiEl.value);
            const diff   = syunyu - keihi; // 譲渡の差引は負も許容（内部通算の入力になり得る）
            // 表示（カンマ付き）
            outEl.value  = diff.toLocaleString('ja-JP');
            // hidden（Calculator入力用）：*_sogo_* キーへ raw を同期
            const hiddenId = 'sashihiki_joto_' + kind + '_sogo_' + period;
            const hiddenEl = document.getElementById(hiddenId);
            if (hiddenEl) hiddenEl.value = String(diff);
          }
        </script>
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

@endsection

@push('scripts')
<script>
  document.addEventListener('DOMContentLoaded', () => {
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

    const ensureProbablyCreateHidden = (input) => {
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

      // 表示値を正として raw に落として同期
      const raw = toRawInt(input.value ?? '');
      hidden.value = raw;
      input.value = raw === '' ? '' : formatWithComma(raw);

      return hidden;
    };

    // 1) 全ての data-format="comma-int" に対して、readonly であっても必ず hidden(name=...) を用意する
    const allFmtInputs = Array.from(document.querySelectorAll('[data-format="comma-int"]'));
    allFmtInputs.forEach((input) => {
      const name = input.dataset.name;
      if (!name) return;
      ensureProbablyCreateHidden(input);
    });

    // 1.5) 一時の入力 → 差引 の再計算フック（syunyu/keihi の blur で必ず再計算＆hidden 同期）
    ['prev','curr'].forEach((p) => {
      const s = document.querySelector('[data-name="syunyu_ichiji_' + p + '"]');
      const k = document.querySelector('[data-name="keihi_ichiji_' + p + '"]');
      if (s) s.addEventListener('blur', () => calculateSashihikiIchiji(p));
      if (k) k.addEventListener('blur', () => calculateSashihikiIchiji(p));
    });
    // 1.6) 譲渡（短期/長期）も同様に blur で差引を再計算し、*_sogo_* hidden を更新
    ['prev','curr'].forEach((p) => {
      // 短期
      const sTanki = document.querySelector('[data-name="syunyu_joto_tanki_' + p + '"]');
      const kTanki = document.querySelector('[data-name="keihi_joto_tanki_' + p + '"]');
      if (sTanki) sTanki.addEventListener('blur', () => calculateSashihikiJoto('tanki', p));
      if (kTanki) kTanki.addEventListener('blur', () => calculateSashihikiJoto('tanki', p));
      // 長期
      const sChoki = document.querySelector('[data-name="syunyu_joto_choki_' + p + '"]');
      const kChoki = document.querySelector('[data-name="keihi_joto_choki_' + p + '"]');
      if (sChoki) sChoki.addEventListener('blur', () => calculateSashihikiJoto('choki', p));
      if (kChoki) kChoki.addEventListener('blur', () => calculateSashihikiJoto('choki', p));
      // 初期計算（ロード時にも一発計算）
      calculateSashihikiJoto('tanki', p);
      calculateSashihikiJoto('choki', p);
    });

    // 2) 入力可能な項目にのみフォーカス/ブラーでのミラーリングを設定
    allFmtInputs
      .filter(el => !el.hasAttribute('readonly') && el.dataset.noMirror !== '1')
      .forEach((input) => {
        const name = input.dataset.name;
        if (!name) return;
        input.addEventListener('focus', () => {
          const hidden = getHidden(name);
          input.value = hidden ? hidden.value : toRawInt(input.value ?? '');
          input.select();
        });
        input.addEventListener('blur', () => {
          const raw = toRawInt(input.value ?? '');
          const hidden = getHidden(name);
          if (hidden) hidden.value = raw;
          input.value = raw === '' ? '' : formatWithComma(raw);
        });
      });

    const form = document.querySelector('form');
    if (form) {
      form.addEventListener('submit', () => {
        // 送信直前は readonly を含む全てを最終同期（hidden に raw を格納）
        allFmtInputs.forEach((input) => {
          const name = input.dataset.name;
          if (!name) return;
          const hidden = getHidden(name) || ensureProbablyCreateHidden(input);
          if (!hidden) return;
          const raw = toRawInt(input.value ?? hidden.value ?? '');
          hidden.value = raw;
        });
        // 念のため：短期/長期の *_sogo_* hidden を最終補正
        ['prev','curr'].forEach((p) => {
          calculateSashihikiJoto('tanki', p);
          calculateSashihikiJoto('choki', p);
        });
      });
    }
  });
</script>
@endpush
 
{{-- Enter移動（ふるさと全画面共通） --}}
@include('tax.furusato.partials.enter_nav')
