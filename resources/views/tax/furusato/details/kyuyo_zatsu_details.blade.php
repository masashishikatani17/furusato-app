<!-- resources/views/tax/furusato/details/kyuyo_zatsu_details.blade.php -->
@extends('layouts.min')

@section('title', '内訳ー給与・雑所得')

@section('content')
@php
  // 雑所得ページ専用 HELP 辞書
  $helpPath = resource_path('views/tax/furusato/helps/help_zatsu_modal.php');
  $HELP_TEXTS = file_exists($helpPath) ? require $helpPath : [];
@endphp

@push('styles')
<style>
  /* このページのHELPモーダルだけ：横幅・フォント・左右余白 */
  #helpModalZatsu .modal-dialog { max-width: 550px; }
  #helpModalZatsu .modal-content { font-family: inherit; font-size: 15px; }
  #helpModalZatsu .modal-body { padding-left: 2rem; padding-right: 2rem; }

  /* 「○」行の見出し強調（色はお好みで調整OK） */
  #helpModalZatsu #helpModalBodyZatsu strong.help-bullet {
    font-weight: 700;
    color: #192C4B;
  }
  /* 「(1)」など：太字のみ（色は変更しない＝継承） */
    #helpModalZatsu #helpModalBodyZatsu strong.help-num {
      font-weight: 700;
  }
  
  /* __下線__ 記法 */
  #helpModalZatsu #helpModalBodyZatsu u { text-underline-offset: 2px; }
  
  /* このページのHELPボタンだけ：文字を縦中央に寄せる
  +<button type="button" id="helpmodal" class="btn-base-blue js-help-btn-zatsu" ...*/
  #helpmodal.btn-base-low-blue {
    line-height: 1;
    padding-top: 0.2rem;
    padding-bottom: 0.6rem;
  }
</style>
@endpush
@push('styles')
<style>
  .form-check-input {
    width: 1rem;
    height: 1rem;
    border: 1.12px solid #555 !important;
  }
  .form-check-label {
    margin-left: .4rem;
    cursor: pointer;
  }
</style>
@endpush
@php
  $inputs = $out['inputs'] ?? [];
  $value = static fn(string $key, $fallback=null) => $inputs[$key] ?? $fallback;
  $isCheckedByInputs = static fn(string $key): bool => (int)($inputs[$key] ?? 0) === 1;
  $normalizeInt = static function ($raw): ?int {
      if ($raw === null || $raw === '') {
          return null;
      }
      if (is_int($raw)) {
          return $raw;
      }
      if (is_float($raw)) {
          return (int) $raw;
      }
      $filtered = preg_replace('/[^0-9\-]/u', '', (string) $raw);
      if ($filtered === null || $filtered === '' || $filtered === '-') {
          return null;
      }
      return (int) $filtered;
  };
  $prevIncome = $normalizeInt($value('kyuyo_syunyu_prev')) ?? 0;
  $currIncome = $normalizeInt($value('kyuyo_syunyu_curr')) ?? 0;
  $prevAllow = $prevIncome > 8_500_000;
  $currAllow = $currIncome > 8_500_000;
  $prevChecked = $prevAllow && $isCheckedByInputs('kyuyo_chosei_applicable_prev');
  $currChecked = $currAllow && $isCheckedByInputs('kyuyo_chosei_applicable_curr');
  $originSubtabRaw = request()->input('origin_subtab', 'sogo');
  $originSubtab = is_string($originSubtabRaw) ? preg_replace('/[^A-Za-z0-9_-]/', '', $originSubtabRaw) : '';
  if ($originSubtab === '') {
      $originSubtab = 'sogo';
  }
  $originAnchorRaw = request()->input('origin_anchor', 'shotoku_row_kyuyo');
  $originAnchor = is_string($originAnchorRaw) ? preg_replace('/[^A-Za-z0-9_-]/', '', $originAnchorRaw) : '';
  if ($originAnchor === '') {
      $originAnchor = 'shotoku_row_kyuyo';
  }
  $warekiPrev = $warekiPrev ?? '前年';
  $warekiCurr = $warekiCurr ?? '当年';
@endphp
<div class="container-blue mt-2" style="width: 610px;">
  <div class="card-header d-flex align-items-start justify-content-between">
    <div class="d-flex align-items-start">
      @include('components.kado_lefttop_img')
      <h0 class="mb-0 mt-2 ms-2">内訳－給与・雑所得</h0>
    </div>
  </div>
  <div class="card-body m-3">
    <form method="POST" action="{{ route('furusato.details.kyuyo_zatsu.save') }}" id="kyuyo-zatsu-form">
      @csrf
      <input type="hidden" name="data_id" value="{{ $dataId }}">
      <input type="hidden" name="origin_tab" value="input">
      <input type="hidden" name="origin_subtab" value="{{ $originSubtab }}">
      <input type="hidden" name="origin_anchor" value="{{ $originAnchor }}">
      <input type="hidden" name="recalc_all" value="1">
      <!-- 再計算の遷移先制御: 0=第一表へ、1=この内訳に留まる -->
      <input type="hidden" name="stay_on_details" value="0">
      <input type="hidden" name="kyuyo_chosei_applicable_prev" value="{{ $prevChecked ? 1 : 0 }}">
      <input type="hidden" name="kyuyo_chosei_applicable_curr" value="{{ $currChecked ? 1 : 0 }}">

      @if ($errors->any())
        <div class="alert alert-danger">
          <ul class="mb-0">
            @foreach ($errors->all() as $error)
              <li>{{ $error }}</li>
            @endforeach
          </ul>
        </div>
      @endif
        <hb class="mb-1 ms-5">■ 給与所得</hb>
        <div class="table-responsive mb-2">
          <table class="table-input align-middle">
            <tbody>
              <tr>
                <th style="width:245px;height:30px;"></th>
                <th>{{ $warekiPrev }}</th>
                <th>{{ $warekiCurr }}</th>
              </tr>
              <tr>
                <th class="text-start align-middle ps-2">給与収入金額</th>
                <td>
                  <input type="text" inputmode="numeric" autocomplete="off"
                         class="form-control suji11 text-end"
                         data-format="comma-int" data-name="kyuyo_syunyu_prev"
                         maxlength="11"
                         value="{{ $value('kyuyo_syunyu_prev','') }}">
                </td>
                <td>
                  <input type="text" inputmode="numeric" autocomplete="off"
                         class="form-control suji11 text-end"
                         data-format="comma-int" data-name="kyuyo_syunyu_curr"
                         maxlength="11"
                         value="{{ $value('kyuyo_syunyu_curr','') }}">
                </td>
              </tr>
              <tr>
                <th class="text-start align-middle ps-2">子育て・介護世帯向け所得金額調整控除</th>
                <td>
                  <div class="form-check d-inline-flex align-items-center">
                    <input type="checkbox" class="form-check-input" id="adj-prev"
                           data-hidden-target="kyuyo_chosei_applicable_prev"
                           @checked($prevChecked) @disabled(! $prevAllow)>
                    <label for="adj-prev" class="form-check-label">適用する</label>
                  </div>
                </td>
                <td>
                  <div class="form-check d-inline-flex align-items-center">
                    <input type="checkbox" class="form-check-input" id="adj-curr"
                           data-hidden-target="kyuyo_chosei_applicable_curr"
                           @checked($currChecked) @disabled(! $currAllow)>
                    <label for="adj-curr" class="form-check-label">適用する</label>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
        <table class="g-table--none mb-4 ms-12 me-10">
          <tr>
            <td><h12>
          給与収入金額（支払金額）が850万円を超え、次のいずれかの要件に該当する場合（給与所得の源泉徴収票の所得金額調整控除額欄に記載がある場合）、「適用」にチェックをつけて下さい。<br>
          <div class="indent-1">・本人が特別障害者に該当する</div>
          <div class="indent-1">・年齢23歳未満の扶養親族がいる</div>
          <div class="indent-1">・特別障害者である同一生計配偶者または扶養親族がいる</div>
          </h12>
           </td>
          </tr>
        </table>
  
        <hb class="mb-1 ms-5">■ 雑所得（公的年金等・業務・その他）</hb>
        <div class="table-responsive mb-3">
          <table class="table-input align-middle">
            <tbody>
              <tr>
                <th colspan="2"></th>
                <th>{{ $warekiPrev }}</th>
                <th>{{ $warekiCurr }}</th>
              </tr>
              <tr>
                <th colspan="2" class="text-start align-middle ps-2">公的年金等収入金額</th>
                <td>
                  <input type="text" inputmode="numeric" autocomplete="off"
                         class="form-control suji11 text-end"
                         data-format="comma-int" data-name="zatsu_nenkin_syunyu_prev"
                         maxlength="11"
                         value="{{ $value('zatsu_nenkin_syunyu_prev','') }}">
                </td>
                <td>
                  <input type="text" inputmode="numeric" autocomplete="off"
                         class="form-control suji11 text-end"
                         data-format="comma-int" data-name="zatsu_nenkin_syunyu_curr"
                         maxlength="11"
                         value="{{ $value('zatsu_nenkin_syunyu_curr','') }}">
                </td>
              </tr>
              <tr>
                <th rowspan="2" class="text-start align-middle ps-2" style="width:60px;">業務</th>
                <th class="text-start align-middle ps-2 th-ddd" style="width:77px;">収入金額</th>
                <td>
                  <input type="text" inputmode="numeric" autocomplete="off"
                         class="form-control suji11 text-end"
                         data-format="comma-int" data-name="zatsu_gyomu_syunyu_prev"
                         maxlength="11"
                         value="{{ $value('zatsu_gyomu_syunyu_prev','') }}">
                </td>
                <td>
                  <input type="text" inputmode="numeric" autocomplete="off"
                         class="form-control suji11 text-end"
                         data-format="comma-int" data-name="zatsu_gyomu_syunyu_curr"
                         maxlength="11"
                         value="{{ $value('zatsu_gyomu_syunyu_curr','') }}">
                </td>
              </tr>
              <tr>
                <th class="text-start align-middle ps-2 th-ddd">支払金額</th>
                <td>
                  <input type="text" inputmode="numeric" autocomplete="off"
                         class="form-control suji11 text-end"
                         data-format="comma-int" data-name="zatsu_gyomu_shiharai_prev"
                         maxlength="11"
                         value="{{ $value('zatsu_gyomu_shiharai_prev','') }}">
                </td>
                <td>
                  <input type="text" inputmode="numeric" autocomplete="off"
                         class="form-control suji11 text-end"
                         data-format="comma-int" data-name="zatsu_gyomu_shiharai_curr"
                         maxlength="11"
                         value="{{ $value('zatsu_gyomu_shiharai_curr','') }}">
                </td>
              </tr>
              <tr>
                <th rowspan="2" class="text-start align-middle ps-2">その他</th>
                <th class="text-start align-middle ps-2 th-ddd">収入金額</th>
                <td>
                  <input type="text" inputmode="numeric" autocomplete="off"
                         class="form-control suji11 text-end"
                         data-format="comma-int" data-name="zatsu_sonota_syunyu_prev"
                         maxlength="11"
                         value="{{ $value('zatsu_sonota_syunyu_prev','') }}">
                </td>
                <td>
                  <input type="text" inputmode="numeric" autocomplete="off"
                         class="form-control suji11 text-end"
                         data-format="comma-int" data-name="zatsu_sonota_syunyu_curr"
                         maxlength="11"
                         value="{{ $value('zatsu_sonota_syunyu_curr','') }}">
                </td>
              </tr>
              <tr>
                <th class="text-start align-middle ps-2 th-ddd">支払金額</th>
                <td>
                  <input type="text" inputmode="numeric" autocomplete="off"
                         class="form-control suji11 text-end"
                         data-format="comma-int" data-name="zatsu_sonota_shiharai_prev"
                         maxlength="11"
                         value="{{ $value('zatsu_sonota_shiharai_prev','') }}">
                </td>
                <td>
                  <input type="text" inputmode="numeric" autocomplete="off"
                         class="form-control suji11 text-end"
                         data-format="comma-int" data-name="zatsu_sonota_shiharai_curr"
                         maxlength="11"
                         value="{{ $value('zatsu_sonota_shiharai_curr','') }}">
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      <hr class="mb-2">
        <div class="d-flex justify-content-between">
          <div> 
          <!-- 再計算: 再計算+保存して内訳に留まる -->
            <button type="submit"
                    class="btn-base-green"
                    onclick="this.form.stay_on_details.value='1';">再計算</button>
           </div>
           <div class="d-flex gap-2">
          <!-- 戻る: 再計算+保存して第一表へ（redirect_to=input を明示） -->
            <button type="button"
                    id="helpmodal"
                    class="btn-base-blue js-help-btn-zatsu"
                    data-help-key="zatsusyotoku"
                    data-bs-toggle="modal"
                    data-bs-target="#helpModalZatsu">HELP</button>
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
document.addEventListener('DOMContentLoaded', () => {
  const toRawInt = (value) => {
    const replaced = String(value ?? '')
      .replace(/,/g, '')
      .replace(/\s+/g, '')
      .replace(/－/g, '-')
      .trim();
    if (replaced === '' || replaced === '-') return '';
    if (!/^[-]?\d+$/.test(replaced)) return '';
    const parsed = parseInt(replaced, 10);
    return Number.isNaN(parsed) ? '' : String(parsed);
  };

  const formatComma = (raw) => {
    if (raw === '') return '';
    const num = Number(raw);
    if (!Number.isFinite(num)) return '';
    return Math.trunc(num).toLocaleString('ja-JP');
  };

  const hiddenCache = new Map();
  const getHidden = (name) => {
    if (hiddenCache.has(name)) {
      return hiddenCache.get(name);
    }
    const found = document.querySelector(`input[type="hidden"][name="${name}"]`);
    if (found) {
      hiddenCache.set(name, found);
      return found;
    }
    return null;
  };

  const ensureHidden = (input) => {
    const name = input?.dataset?.name;
    if (!name) return null;
    let hidden = getHidden(name);
    if (!hidden) {
      hidden = document.createElement('input');
      hidden.type = 'hidden';
      hidden.name = name;
      hidden.dataset.commaMirror = '1';
      const mountPoint = input.parentElement || input.closest('td') || input.closest('form') || document.body;
      mountPoint.appendChild(hidden);
      hiddenCache.set(name, hidden);
    }
    return hidden;
  };

  const syncDisplayAndHidden = (input, { skipFormat } = { skipFormat: false }) => {
    if (!(input instanceof HTMLInputElement)) return;
    const hidden = ensureHidden(input);
    const raw = toRawInt(input.value ?? hidden?.value ?? '');
    if (hidden) {
      hidden.value = raw;
    }
    if (!skipFormat) {
      input.value = raw === '' ? '' : formatComma(raw);
    }
  };

  const displayInputs = Array.from(document.querySelectorAll('[data-format="comma-int"][data-name]'));
  displayInputs.forEach((input) => {
    if (!(input instanceof HTMLInputElement)) return;
    ensureHidden(input);
    syncDisplayAndHidden(input);
    if (input.readOnly) return;
    input.addEventListener('focus', () => {
      const hidden = ensureHidden(input);
      const raw = toRawInt(hidden?.value ?? input.value ?? '');
      input.value = raw;
      input.select();
    });
    const handle = () => {
      syncDisplayAndHidden(input);
      enforceCheckboxRules();
    };
    input.addEventListener('blur', handle);
    input.addEventListener('change', handle);
  });

  const checkboxTargets = Array.from(document.querySelectorAll('input[type="checkbox"][data-hidden-target]'));
  const syncCheckboxHidden = (checkbox) => {
    if (!(checkbox instanceof HTMLInputElement)) return;
    const target = checkbox.dataset.hiddenTarget;
    if (!target) return;
    let hidden = getHidden(target);
    if (!hidden) {
      hidden = document.createElement('input');
      hidden.type = 'hidden';
      hidden.name = target;
      hidden.value = '0';
      const mountPoint = checkbox.parentElement || checkbox.closest('td') || checkbox.closest('form') || document.body;
      mountPoint.appendChild(hidden);
      hiddenCache.set(target, hidden);
    }
    hidden.value = checkbox.checked ? '1' : '0';
  };

  checkboxTargets.forEach((checkbox) => {
    syncCheckboxHidden(checkbox);
    checkbox.addEventListener('change', () => {
      syncCheckboxHidden(checkbox);
    });
  });

  const rawValue = (name) => {
    const hidden = getHidden(name);
    if (!hidden) return 0;
    const raw = toRawInt(hidden.value ?? '');
    if (raw === '') return 0;
    const parsed = parseInt(raw, 10);
    return Number.isNaN(parsed) ? 0 : parsed;
  };

  const applyCheckboxState = (checkbox, allow) => {
    if (!(checkbox instanceof HTMLInputElement)) return;
    if (!allow) {
      checkbox.checked = false;
    }
    checkbox.disabled = !allow;
    syncCheckboxHidden(checkbox);
  };

  const enforceCheckboxRules = () => {
    const prevAllow = rawValue('kyuyo_syunyu_prev') > 8_500_000;
    const currAllow = rawValue('kyuyo_syunyu_curr') > 8_500_000;
    applyCheckboxState(document.getElementById('adj-prev'), prevAllow);
    applyCheckboxState(document.getElementById('adj-curr'), currAllow);
  };

  enforceCheckboxRules();

  const form = document.getElementById('kyuyo-zatsu-form');
  if (form instanceof HTMLFormElement) {
    form.addEventListener('submit', () => {
      displayInputs.forEach((input) => {
        syncDisplayAndHidden(input, { skipFormat: true });
      });
      checkboxTargets.forEach((checkbox) => {
        syncCheckboxHidden(checkbox);
      });
      enforceCheckboxRules();
    });
  }
});
</script>
@endpush

{{-- HELP辞書をJSへ渡す（このページ専用） --}}
<script>
  window.__PAGE_HELP_TEXTS_ZATSU__ = @json($HELP_TEXTS, JSON_UNESCAPED_UNICODE);
</script>

{{-- 共通HELPモーダル（雑所得ページ専用） --}}
<div class="modal fade" id="helpModalZatsu" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog" style="max-width: 550px;">
    <div class="modal-content">
      <div class="modal-header">
        <button type="button" class="btn btn-vp me-2">HELP</button><h15 class="modal-title" id="helpModalTitleZatsu">HELP</h15>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body text-start">
        <div id="helpModalBodyZatsu" style="white-space: normal;"></div>
      </div>
    </div>
  </div>
</div>

@push('scripts')
<script>
  
  // 雑所得ページ：HELPボタン(.js-help-btn-zatsu)クリックで本文を差し替え
  document.addEventListener('click', function (e) {
    const btn = e.target.closest('.js-help-btn-zatsu');
    if (!btn) return;

    const key  = btn.getAttribute('data-help-key') || '';
    const dict = window.__PAGE_HELP_TEXTS_ZATSU__ || {};
    const item = dict[key];

    const title = item?.title ?? 'HELP';
    const body  = item?.body  ?? '（この項目のHELPは未登録です）';

    const titleEl = document.getElementById('helpModalTitleZatsu');
    const bodyEl  = document.getElementById('helpModalBodyZatsu');
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
        
        // 行頭「(1)」「(2)」など：先頭ラベルだけ太字（色は変えない）
        // 例：(1) 公的年金等の雑所得
        const mNum = line.match(/^(\s*\(\d+\)\s*[^　 ]*)(.*)$/);
        if (mNum) {
          const head = underline(escapeHtml(mNum[1]));
          const rest = underline(escapeHtml(mNum[2] ?? ''));
          return `<strong class="help-num">${head}</strong>${rest}`;
        }

        // 行頭「○」を見出しとして太字＋色
        // 例：○具体例・・・xxxx
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
{{-- Enter移動（ふるさと全画面共通） --}}
@include('tax.furusato.partials.enter_nav')

@endsection
 