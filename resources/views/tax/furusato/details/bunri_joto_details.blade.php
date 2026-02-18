<!-- views/tax/furusato/details/bunri_joto_detail.blade.php -->
@extends('layouts.min')

@section('title', '分離課税 譲渡所得（短期/長期）内訳')

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
    // ▼ 0下限＆表示用フォーマッタ（tsusango_* 用の補助）
    //   - $nz: 文字列/数値/カンマ付き → int にして max(0, n)
    //   - $fmt: カンマ付き文字列へ
    $nz = static function($v): int {
        if ($v === null || $v === '') return 0;
        $s = is_string($v) ? str_replace(',', '', $v) : (string) $v;
        return max(0, (int) (is_numeric($s) ? $s : 0));
    };
    $fmt = static fn(int $n): string => number_format($n);
    $groups = [
        [
            'title' => '短期譲渡',
            'key'   => 'tanki',
            'rows'  => [
                ['label' => '一般分', 'key' => 'tanki_ippan'],
                ['label' => '軽減分', 'key' => 'tanki_keigen'],
            ],
        ],
        [
            'title' => '長期譲渡',
            'key'   => 'choki',
            'rows'  => [
                ['label' => '一般分', 'key' => 'choki_ippan'],
                ['label' => '特定分', 'key' => 'choki_tokutei'],
                ['label' => '軽課分', 'key' => 'choki_keika'],
            ],
        ],
    ];

  // 分離譲渡ページ専用 HELP 辞書
  $helpPath = resource_path('views/tax/furusato/helps/help_bunrijoto_modal.php');
  $HELP_TEXTS = file_exists($helpPath) ? require $helpPath : [];
@endphp

<div class="container-blue mt-2" style="width: 1050px;">
  <div class="card-header d-flex align-items-start justify-content-between">
    <div class="d-flex align-items-start">
      @include('components.kado_lefttop_img')
      <h0 class="mt-2 ms-2">内訳－分離課税 譲渡所得（短期/長期）</h0>
    </div>
  </div>
  <div class="card-body m-3">
      <form method="POST" action="{{ route('furusato.details.bunri_joto.save') }}">
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
                  <th colspan="2" style="height:30px;"></th>
                  <th>収入金額</th>
                  <th>必要経費</th>
                  <th>差引金額</th>
                  <th>損益通算後</th>
                  <th>特別控除額</th>
                  <th>譲渡所得金額</th>
                  <th>課税所得金額</th>
                </tr>
              </thead>
              <tbody>
              {{-- groups（短期/長期） × rows（一般/軽減/…）の二重ループ --}}
              @foreach ($groups as $group)
                @php $rowspan = count($group['rows']); @endphp
                @foreach ($group['rows'] as $index => $row)
                  @php
                    $base = $row['key'] . '_' . $period;
                  @endphp
                  <tr>
                    @if ($index === 0)
                      <th scope="rowgroup" rowspan="{{ $rowspan }}" class="text-center align-middle" style="width:38px;">
                        {{ $group['title'] }}
                      </th>
                    @endif
                    <th class="text-center align-middle th-ddd" nowrap="nowrap" style="width:54px;">{{ $row['label'] }}</th>
                    {{-- 収入 --}}
                    @php $name = 'syunyu_' . $base; @endphp
                    <td>
                      @if($off)
                        <input type="text" class="form-control suji11 text-center bg-light" readonly value="－">
                        <input type="hidden" name="{{ $name }}" value="0">
                      @else
                        <input type="text" inputmode="numeric" autocomplete="off"
                               data-format="comma-int" data-name="{{ $name }}"
                               class="form-control suji11 text-end"
                               value="{{ old($name, $inputs[$name] ?? null) }}">
                      @endif
                    </td>
                    {{-- 必要経費 --}}
                    @php $name = 'keihi_' . $base; @endphp
                    <td>
                      @if($off)
                        <input type="text" class="form-control suji11 text-center bg-light" readonly value="－">
                        <input type="hidden" name="{{ $name }}" value="0">
                      @else
                        <input type="text" inputmode="numeric" autocomplete="off"
                               data-format="comma-int" data-name="{{ $name }}"
                               class="form-control suji11 text-end"
                               value="{{ old($name, $inputs[$name] ?? null) }}">
                      @endif
                    </td>
                    {{-- 差引（通算前）※サーバ計算専用：表示のみ --}}
                    @php $name = 'before_tsusan_' . $base; @endphp
                    <td>
                      @if($off)
                        <input type="text" class="form-control suji11 text-center bg-light" readonly value="－">
                      @else
                        <input type="text" inputmode="numeric" autocomplete="off"
                               data-format="comma-int" data-name="{{ $name }}" data-display-only="1"
                               class="form-control suji11 text-end bg-light"
                               value="{{ old($name, $inputs[$name] ?? null) }}" readonly>
                      @endif
                    </td>
                    {{-- 損益通算後（0下限・サーバ計算専用：表示のみ） --}}
                    @php $name = 'tsusango_' . $base; @endphp
                    <td>
                      @if($off)
                        <input type="text" class="form-control suji11 text-center bg-light" value="－" readonly>
                      @else
                        @php
                          $tsusangoSrc = old($name, $inputs[$name] ?? null);
                          $tsusangoVal = $nz($tsusangoSrc);
                        @endphp
                        <input type="text" inputmode="numeric" autocomplete="off"
                               data-format="comma-int" data-name="{{ $name }}" data-display-only="1"
                               class="form-control suji11 text-end bg-light"
                               value="{{ $fmt($tsusangoVal) }}" readonly>
                      @endif
                    </td>
                    {{-- 特別控除額 --}}
                    @php $name = 'tokubetsukojo_' . $base; @endphp
                    <td>
                      @if($off)
                        <input type="text" class="form-control suji8 text-center bg-light" readonly value="－">
                        <input type="hidden" name="{{ $name }}" value="0">
                      @else
                        <input type="text" inputmode="numeric" autocomplete="off"
                               data-format="comma-int" data-name="{{ $name }}"
                               class="form-control suji8 text-end"
                               value="{{ old($name, $inputs[$name] ?? null) }}">
                      @endif
                    </td>
                    {{-- 譲渡所得金額（サーバ計算専用：表示のみ） --}}
                    @php $name = 'joto_shotoku_' . $base; @endphp
                    <td>
                      @if($off)
                        <input type="text" class="form-control suji11 text-center bg-light" readonly value="－">
                      @else
                        <input type="text" inputmode="numeric" autocomplete="off"
                               data-format="comma-int" data-name="{{ $name }}" data-display-only="1"
                               class="form-control suji11 text-end bg-light"
                               value="{{ old($name, $inputs[$name] ?? null) }}" readonly>
                      @endif
                    </td>
                    {{-- 区分合計（行頭で rowspan：サーバ計算専用・表示のみ） --}}
                    @if ($index === 0)
                      @php $gokeiName = sprintf('joto_shotoku_%s_gokei_%s', $group['key'], $period); @endphp
                      <td rowspan="{{ $rowspan }}">
                        @if($off)
                          <input type="text" class="form-control suji11 text-center bg-light" readonly value="－">
                        @else
                          <input type="text" inputmode="numeric" autocomplete="off"
                                 data-format="comma-int" data-name="{{ $gokeiName }}" data-display-only="1"
                                 class="form-control suji11 text-end bg-light"
                                 value="{{ old($gokeiName, $inputs[$gokeiName] ?? null) }}" readonly>
                        @endif
                      </td>
                    @endif
                  </tr>
                @endforeach
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
                          class="btn-base-blue js-help-btn-bunrijoto me-2"
                          data-help-key="bunri_joto_tansho_choki"
                          data-bs-toggle="modal"
                          data-bs-target="#helpModalBunriJoto">HELP</button>
                  <!-- 戻る: 再計算+保存して第一表へ（redirect_to=input を明示） -->
                  <button type="submit"
                          class="btn-base-blue"
                          name="redirect_to"
                          value="input"
                          onclick="this.form.stay_on_details.value='0';">戻 る</button>
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
    if (hidden) {
      const raw = toRawInt(hidden.value ?? '');
      if (raw === '') {
        return 0;
      }
      const parsed = parseInt(raw, 10);
      return Number.isNaN(parsed) ? 0 : parsed;
    }
    // hidden が無い場合は display-only フィールドとみなし、表示値から読む
    const display = getDisplay(name);
    if (!display) {
      return 0;
    }
    const raw = toRawInt(display.value ?? '');
    if (raw === '') {
      return 0;
    }
    const parsed = parseInt(raw, 10);
    return Number.isNaN(parsed) ? 0 : parsed;
  };

  const setIntValue = (name, value) => {
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

  const rows = [
    { key: 'tanki_ippan', group: 'tanki' },
    { key: 'tanki_keigen', group: 'tanki' },
    { key: 'choki_ippan', group: 'choki' },
    { key: 'choki_tokutei', group: 'choki' },
    { key: 'choki_keika', group: 'choki' },
  ];

  const recalc = (period) => {
    const sums = { tanki: 0, choki: 0 };
    rows.forEach((row) => {
      const base = `${row.key}_${period}`;
      const syunyu = getIntValue(`syunyu_${base}`);
      const keihi = getIntValue(`keihi_${base}`);
      const beforeTsusan = syunyu - keihi;
      setIntValue(`before_tsusan_${base}`, beforeTsusan);

      const tsusango = getIntValue(`tsusango_${base}`);
      const tokubetsu = getIntValue(`tokubetsukojo_${base}`);
      const joto = tsusango - tokubetsu;
      setIntValue(`joto_shotoku_${base}`, joto);
      sums[row.group] += joto;
    });

    setIntValue(`joto_shotoku_tanki_gokei_${period}`, sums.tanki);
    setIntValue(`joto_shotoku_choki_gokei_${period}`, sums.choki);
  };

  // 旧HTML属性 oninput="updateCalculation('prev')" 等から呼ばれるラッパー
  // （既存の recalc() をそのまま使う）
  window.updateCalculation = function (period) {
    try {
      recalc(period);
    } catch (e) {
      console.error('updateCalculation error:', e);
    }
  };

  const inputs = document.querySelectorAll('[data-format="comma-int"]');
  const displayOnlyNames = new Set();

  inputs.forEach((input) => {
    const name = input.dataset.name;
    if (!name) {
      return;
    }
    if (input.dataset.displayOnly === '1') {
      displayOnlyNames.add(name);
    }

    // 表示専用フィールド以外についてのみ hidden を作成し、POST 対象とする
    if (!displayOnlyNames.has(name)) {
      ensureHidden(input);
    }

    const applyFormat = () => {
      const hidden = getHidden(name);
      const rawSource = hidden ? hidden.value ?? '' : input.value ?? '';
      const raw = toRawInt(rawSource);
      input.value = raw === '' ? '' : formatWithComma(raw);
    };

    applyFormat();

    // display-only / readOnly なフィールドはここで終了（入力イベントを張らない）
    if (input.readOnly || displayOnlyNames.has(name)) {
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

  ['prev', 'curr'].forEach(recalc);

  const form = document.querySelector('form');
  if (form) {
    form.addEventListener('submit', () => {
      inputs.forEach((input) => {
        const name = input.dataset.name;
        if (!name) {
          return;
        }
        if (displayOnlyNames.has(name)) {
          // 表示専用フィールドはサーバに送らない
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
@endpush
@push('styles')
<style>
  /* 分離譲渡ページのHELPモーダルだけ */
  #helpModalBunriJoto .modal-dialog { max-width: 550px; }
  #helpModalBunriJoto .modal-content { font-family: inherit; font-size: 15px; }
  #helpModalBunriJoto .modal-body { padding-left: 2rem; padding-right: 2rem; }

  /* 「○」行：太字＋色 */
  #helpModalBunriJoto #helpModalBodyBunriJoto strong.help-bullet {
    font-weight: 700;
    color: #192C4B;
  }
  /* 「(1)」など：太字のみ（色はそのまま） */
  #helpModalBunriJoto #helpModalBodyBunriJoto strong.help-num {
    font-weight: 700;
  }
  /* __下線__ 記法 */
  #helpModalBunriJoto #helpModalBodyBunriJoto u { text-underline-offset: 2px; }
</style>
@endpush
<script>
  window.__PAGE_HELP_TEXTS_BUNRIJOTO__ = @json($HELP_TEXTS, JSON_UNESCAPED_UNICODE);
</script>
{{-- Enter移動（ふるさと全画面共通） --}}
{{-- 共通HELPモーダル（分離譲渡ページ専用） --}}
<div class="modal fade" id="helpModalBunriJoto" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <button type="button" class="btn btn-vp me-2">HELP</button><h15 class="modal-title" id="helpModalTitleBunriJoto">HELP</h15>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body text-start">
        <div id="helpModalBodyBunriJoto" style="white-space: normal;"></div>
      </div>
    </div>
  </div>
</div>

@push('scripts')
<script>
  document.addEventListener('click', function (e) {
    const btn = e.target.closest('.js-help-btn-bunrijoto');
    if (!btn) return;

    const key  = btn.getAttribute('data-help-key') || '';
    const dict = window.__PAGE_HELP_TEXTS_BUNRIJOTO__ || {};
    const item = dict[key];

    const title = item?.title ?? 'HELP';
    const body  = item?.body  ?? '（この項目のHELPは未登録です）';

    const titleEl = document.getElementById('helpModalTitleBunriJoto');
    const bodyEl  = document.getElementById('helpModalBodyBunriJoto');
    if (titleEl) titleEl.textContent = title;
    if (!bodyEl) return;

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

        // 行頭「(1)」「(2)」など：太字（色はそのまま）
        const mNum = line.match(/^(\s*\(\d+\)\s*[^　 ]*)(.*)$/);
        if (mNum) {
          const head = underline(escapeHtml(mNum[1]));
          const rest = underline(escapeHtml(mNum[2] ?? ''));
          return `<strong class="help-num">${head}</strong>${rest}`;
        }

        // 行頭「○」：太字＋色
        const m = line.match(/^(\s*○\s*[^・…：:　]+?)(\s*・・・\s*.*)?$/);
        if (m) {
          const head = underline(escapeHtml(m[1]));
          const rest = underline(escapeHtml(m[2] ?? ''));
          return `<strong class="help-bullet">${head}</strong>${rest}`;
        }

        return underline(escapeHtml(line));
      })
      .join('<br>');

    bodyEl.innerHTML = html;
  });
</script>
@endpush
@include('tax.furusato.partials.enter_nav')

@endsection