<!-- resources/views/tax/furusato/details/kyuyo_zatsu_details.blade.php -->
@extends('layouts.min')

@section('title', '給与・雑所得 内訳')

@section('content')
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
  $value = static fn(string $key) => old($key, $inputs[$key] ?? null);
  $isChecked = static fn(string $key): bool => (int) old($key, $inputs[$key] ?? 0) === 1;
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
  $prevChecked = $prevAllow && $isChecked('kyuyo_chosei_applicable_prev');
  $currChecked = $currAllow && $isChecked('kyuyo_chosei_applicable_curr');
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
<div class="container-blue mt-2" style="width: 980px;">
  <div class="card-header d-flex align-items-start justify-content-between">
    <div class="d-flex align-items-start">
      <img src="{{ asset('storage/images/kado_lefttop.jpg') }}" alt="…">
      <h0 class="mb-0 mt-2 ms-2">内訳－給与・雑所得</h0>
    </div>
  </div>
  <div class="card-body">
    <form method="POST" action="{{ route('furusato.details.kyuyo_zatsu.save') }}" id="kyuyo-zatsu-form">
      @csrf
      <input type="hidden" name="data_id" value="{{ $dataId }}">
      <input type="hidden" name="origin_tab" value="input">
      <input type="hidden" name="origin_subtab" value="{{ $originSubtab }}">
      <input type="hidden" name="origin_anchor" value="{{ $originAnchor }}">
      <input type="hidden" name="recalc_all" value="1">
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

      <hb class="mb-2 ms-20">給与所得</hb>
      <div class="table-responsive mb-2">
        <table class="table-base table-bordered align-middle text-center">
          <tbody>
            <tr>
              <th style="width:260px;"></th>
              <th style="width:180px">{{ $warekiPrev }}</th>
              <th style="width:180px">{{ $warekiCurr }}</th>
            </tr>
            <tr>
              <th class="text-start align-middle ps-2">給与収入金額</th>
              <td>
                <input type="text" inputmode="numeric" autocomplete="off"
                       class="form-control text-end"
                       data-format="comma-int" data-name="kyuyo_syunyu_prev"
                       value="{{ $value('kyuyo_syunyu_prev') }}">
              </td>
              <td>
                <input type="text" inputmode="numeric" autocomplete="off"
                       class="form-control text-end"
                       data-format="comma-int" data-name="kyuyo_syunyu_curr"
                       value="{{ $value('kyuyo_syunyu_curr') }}">
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
      <div class="small text-muted mb-4 ms-40 me-40">
        給与収入金額（支払金額）が850万円を超え、次のいずれかの要件に該当する場合（給与所得の源泉徴収票の所得金額調整控除額欄に記載がある場合）、「適用」にチェックをつけてください。<br>
        ・本人が特別障害者に該当する<br>
        ・年齢23歳未満の扶養親族がいる<br>
        ・特別障害者である同一生計配偶者または扶養親族がいる
      </div>

      <hb class="mb-2 ms-20">雑所得（公的年金等・業務・その他）</hb>
      <div class="table-responsive mb-3">
        <table class="table-base table-bordered align-middle text-center">
          <tbody>
            <tr>
              <th colspan="2" style="width:260px;"></th>
              <th style="width:180px">{{ $warekiPrev }}</th>
              <th style="width:180px">{{ $warekiCurr }}</th>
            </tr>
            <tr>
              <th colspan="2" class="text-start align-middle ps-2">公的年金等収入金額</th>
              <td>
                <input type="text" inputmode="numeric" autocomplete="off"
                       class="form-control text-end"
                       data-format="comma-int" data-name="zatsu_nenkin_syunyu_prev"
                       value="{{ $value('zatsu_nenkin_syunyu_prev') }}">
              </td>
              <td>
                <input type="text" inputmode="numeric" autocomplete="off"
                       class="form-control text-end"
                       data-format="comma-int" data-name="zatsu_nenkin_syunyu_curr"
                       value="{{ $value('zatsu_nenkin_syunyu_curr') }}">
              </td>
            </tr>
            <tr>
              <th rowspan="2" class="text-start align-middle ps-2">業務</th>
              <th class="text-start align-middle ps-2">収入金額</th>
              <td>
                <input type="text" inputmode="numeric" autocomplete="off"
                       class="form-control text-end"
                       data-format="comma-int" data-name="zatsu_gyomu_syunyu_prev"
                       value="{{ $value('zatsu_gyomu_syunyu_prev') }}">
              </td>
              <td>
                <input type="text" inputmode="numeric" autocomplete="off"
                       class="form-control text-end"
                       data-format="comma-int" data-name="zatsu_gyomu_syunyu_curr"
                       value="{{ $value('zatsu_gyomu_syunyu_curr') }}">
              </td>
            </tr>
            <tr>
              <th class="text-start align-middle ps-2">支払金額</th>
              <td>
                <input type="text" inputmode="numeric" autocomplete="off"
                       class="form-control text-end"
                       data-format="comma-int" data-name="zatsu_gyomu_shiharai_prev"
                       value="{{ $value('zatsu_gyomu_shiharai_prev') }}">
              </td>
              <td>
                <input type="text" inputmode="numeric" autocomplete="off"
                       class="form-control text-end"
                       data-format="comma-int" data-name="zatsu_gyomu_shiharai_curr"
                       value="{{ $value('zatsu_gyomu_shiharai_curr') }}">
              </td>
            </tr>
            <tr>
              <th rowspan="2" class="text-start align-middle ps-2">その他</th>
              <th class="text-start align-middle ps-2">収入金額</th>
              <td>
                <input type="text" inputmode="numeric" autocomplete="off"
                       class="form-control text-end"
                       data-format="comma-int" data-name="zatsu_sonota_syunyu_prev"
                       value="{{ $value('zatsu_sonota_syunyu_prev') }}">
              </td>
              <td>
                <input type="text" inputmode="numeric" autocomplete="off"
                       class="form-control text-end"
                       data-format="comma-int" data-name="zatsu_sonota_syunyu_curr"
                       value="{{ $value('zatsu_sonota_syunyu_curr') }}">
              </td>
            </tr>
            <tr>
              <th class="text-start align-middle ps-2">支払金額</th>
              <td>
                <input type="text" inputmode="numeric" autocomplete="off"
                       class="form-control text-end"
                       data-format="comma-int" data-name="zatsu_sonota_shiharai_prev"
                       value="{{ $value('zatsu_sonota_shiharai_prev') }}">
              </td>
              <td>
                <input type="text" inputmode="numeric" autocomplete="off"
                       class="form-control text-end"
                       data-format="comma-int" data-name="zatsu_sonota_shiharai_curr"
                       value="{{ $value('zatsu_sonota_shiharai_curr') }}">
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <div class="text-end">
        <a href="{{ route('furusato.input', ['data_id' => $dataId, 'tab' => 'input', 'subtab' => 'sogo']) }}#shotoku_row_kyuyo"
           class="btn-base-blue">戻 る</a>
        <button type="submit" class="btn-base-green">再計算</button>
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
@endsection