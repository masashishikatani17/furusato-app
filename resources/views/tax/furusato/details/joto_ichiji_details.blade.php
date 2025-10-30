<!-- resources/views/tax/furusato/details/joto_ichiji_details.blade.php -->
@extends('layouts.min')

@section('title', '総合譲渡・一時（内訳）')

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
        <input type="hidden" name="redirect_to" value="">

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
              <table class="table-base table-bordered align-middle text-center">
                <thead>
                  <tr>
                    <th colspan="2" style="width: 140px;height:30px;"></th>
                    <th>収入金額</th>
                    <th>必要経費</th>
                    <th>差引金額</th>
                    <th>内部通算後</th>
                    <th>特別控除額</th>
                    <th>譲渡・一時所得の通算後</th>
                    <th>損益通算後</th>
                    <th>1/2</th>
                    <th>所得金額</th>
                  </tr>
                </thead>
                <tbody>
                  <tr>
                    <th rowspan="2" class="th-cream">総合譲渡</th>
                    <th class="th-cream" nowrap="nowrap">短期</th>
                    <td>
                      <input type="text" inputmode="numeric" data-format="comma-int" data-name="syunyu_joto_tanki_{{ $period }}" class="form-control suji11" value="{{ old('syunyu_joto_tanki_' . $period, $inputs['syunyu_joto_tanki_' . $period] ?? null) }}">
                    </td>
                    <td>
                      <input type="text" inputmode="numeric" data-format="comma-int" data-name="keihi_joto_tanki_{{ $period }}" class="form-control suji11" value="{{ old('keihi_joto_tanki_' . $period, $inputs['keihi_joto_tanki_' . $period] ?? null) }}">
                    </td>
                    <td>
                      <input type="text" inputmode="numeric" autocomplete="off"
                             data-format="comma-int" data-name="sashihiki_joto_tanki_{{ $period }}"
                             class="form-control suji11 text-end bg-light"
                             value="{{ old('sashihiki_joto_tanki_' . $period, $inputs['sashihiki_joto_tanki_' . $period] ?? null) }}" readonly>
                    </td>
                    <td>
                      <input type="text" inputmode="numeric" autocomplete="off"
                             data-format="comma-int" data-name="after_naibutsusan_joto_tanki_sogo_{{ $period }}"
                             class="form-control suji11 text-end bg-light"
                             value="{{ old('after_naibutsusan_joto_tanki_sogo_' . $period, $inputs['tsusango_joto_tanki_sogo_' . $period] ?? null) }}" readonly>
                    </td>
                    <td>
                      <input type="text" inputmode="numeric" autocomplete="off"
                             data-format="comma-int" data-name="tokubetsukojo_joto_tanki_{{ $period }}"
                             class="form-control suji8 text-end bg-light"
                             value="{{ old('tokubetsukojo_joto_tanki_' . $period, $inputs['tokubetsukojo_joto_tanki_sogo_' . $period] ?? null) }}" readonly>
                    </td>
                    <td>
                      <input type="text" inputmode="numeric" autocomplete="off"
                             data-format="comma-int" data-name="after_joto_ichiji_tousan_joto_tanki_{{ $period }}"
                             class="form-control suji11 text-end bg-light"
                             value="{{ old('after_joto_ichiji_tousan_joto_tanki_' . $period, $inputs['after_joto_ichiji_tousan_joto_tanki_sogo_' . $period] ?? null) }}" readonly>
                    </td>
                    <td>
                      <input type="text" inputmode="numeric" autocomplete="off"
                             data-format="comma-int" data-name="tsusango_joto_tanki_{{ $period }}"
                             class="form-control suji11 text-end bg-light"
                             value="{{ old('tsusango_joto_tanki_' . $period, $inputs['after_3jitsusan_joto_tanki_sogo_' . $period] ?? null) }}" readonly>
                    </td>
                    <td class="text-center align-middle">－</td>
                    <td>
                      <input type="text" inputmode="numeric" autocomplete="off"
                             data-format="comma-int" data-name="shotoku_joto_tanki_{{ $period }}"
                             class="form-control suji11 text-end bg-light"
                             value="{{ old('shotoku_joto_tanki_' . $period, $inputs['shotoku_joto_tanki_sogo_' . $period] ?? null) }}" readonly>
                    </td>
                  </tr>
                  <tr>
                    <th class="th-cream">長期</th>
                    <td>
                      <input type="text" inputmode="numeric" data-format="comma-int" data-name="syunyu_joto_choki_{{ $period }}" class="form-control suji11" value="{{ old('syunyu_joto_choki_' . $period, $inputs['syunyu_joto_choki_' . $period] ?? null) }}">
                    </td>
                    <td>
                      <input type="text" inputmode="numeric" data-format="comma-int" data-name="keihi_joto_choki_{{ $period }}" class="form-control suji11" value="{{ old('keihi_joto_choki_' . $period, $inputs['keihi_joto_choki_' . $period] ?? null) }}">
                    </td>
                    <td>
                      <input type="text" inputmode="numeric" autocomplete="off"
                             data-format="comma-int" data-name="sashihiki_joto_choki_{{ $period }}"
                             class="form-control suji11 text-end bg-light"
                             value="{{ old('sashihiki_joto_choki_' . $period, $inputs['sashihiki_joto_choki_' . $period] ?? null) }}" readonly>
                    </td>
                    <td>
                      <input type="text" inputmode="numeric" autocomplete="off"
                             data-format="comma-int" data-name="after_naibutsusan_joto_choki_sogo_{{ $period }}"
                             class="form-control suji11 text-end bg-light"
                             value="{{ old('after_naibutsusan_joto_choki_sogo_' . $period, $inputs['tsusango_joto_choki_sogo_' . $period] ?? null) }}" readonly>
                    </td>
                    <td>
                      <input type="text" inputmode="numeric" autocomplete="off"
                             data-format="comma-int" data-name="tokubetsukojo_joto_choki_{{ $period }}"
                             class="form-control suji8 text-end bg-light"
                             value="{{ old('tokubetsukojo_joto_choki_' . $period, $inputs['tokubetsukojo_joto_choki_sogo_' . $period] ?? null) }}" readonly>
                    </td>
                    <td>
                      <input type="text" inputmode="numeric" autocomplete="off"
                             data-format="comma-int" data-name="after_joto_ichiji_tousan_joto_choki_{{ $period }}"
                             class="form-control suji11 text-end bg-light"
                             value="{{ old('after_joto_ichiji_tousan_joto_choki_' . $period, $inputs['after_joto_ichiji_tousan_joto_choki_sogo_' . $period] ?? null) }}" readonly>
                    </td>
                    <td>
                      <input type="text" inputmode="numeric" autocomplete="off"
                             data-format="comma-int" data-name="tsusango_joto_choki_{{ $period }}"
                             class="form-control suji11 text-end bg-light"
                             value="{{ old('tsusango_joto_choki_' . $period, $inputs['after_3jitsusan_joto_choki_sogo_' . $period] ?? null) }}" readonly>
                    </td>
                    <td>
                      <input type="text" inputmode="numeric" autocomplete="off"
                             data-format="comma-int" data-name="half_joto_choki_{{ $period }}"
                             class="form-control suji8 text-end bg-light"
                             value="{{ old('half_joto_choki_' . $period, (($inputs['tsusango_joto_choki_' . $period] ?? 0) - ($inputs['shotoku_joto_choki_' . $period] ?? 0))) }}" readonly>
                    </td>
                    <td>
                      <input type="text" inputmode="numeric" autocomplete="off"
                             data-format="comma-int" data-name="shotoku_joto_choki_{{ $period }}"
                             class="form-control suji11 text-end bg-light"
                             value="{{ old('shotoku_joto_choki_' . $period, $inputs['shotoku_joto_choki_sogo_' . $period] ?? null) }}" readonly>
                    </td>
                  </tr>
                  <tr>
                    <th colspan="2" class="th-cream">一時</th>
                    <td>
                      <input type="text" inputmode="numeric" data-format="comma-int" data-name="syunyu_ichiji_{{ $period }}" class="form-control suji11" value="{{ old('syunyu_ichiji_' . $period, $inputs['syunyu_ichiji_' . $period] ?? null) }}">
                    </td>
                    <td>
                      <input type="text" inputmode="numeric" data-format="comma-int" data-name="keihi_ichiji_{{ $period }}" class="form-control suji11" value="{{ old('keihi_ichiji_' . $period, $inputs['keihi_ichiji_' . $period] ?? null) }}">
                    </td>
                    <td>
                      <input type="text" inputmode="numeric" autocomplete="off"
                             data-format="comma-int" data-name="sashihiki_ichiji_{{ $period }}"
                             class="form-control suji11 text-end bg-light"
                             value="{{ old('sashihiki_ichiji_' . $period, max(0, ($inputs['syunyu_ichiji_' . $period] ?? 0) - ($inputs['keihi_ichiji_' . $period] ?? 0))) }}" readonly
                             onblur="calculateSashihikiIchiji('{{ $period }}')">
                    </td>
                    <td>
                      <input type="text" inputmode="numeric" autocomplete="off"
                             data-format="comma-int" data-name="after_naibutsusan_ichiji_{{ $period }}"
                             class="form-control suji11 text-end bg-light"
                             value="{{ old('after_naibutsusan_ichiji_' . $period, $inputs['tsusango_ichiji_' . $period] ?? null) }}" readonly>
                    </td>
                    <td>
                      <input type="text" inputmode="numeric" autocomplete="off"
                             data-format="comma-int" data-name="tokubetsukojo_ichiji_{{ $period }}"
                             class="form-control suji8 text-end bg-light"
                             value="{{ old('tokubetsukojo_ichiji_' . $period, $inputs['tokubetsukojo_ichiji_' . $period] ?? null) }}" readonly>
                    </td>
                    <td>
                      <input type="text" inputmode="numeric" autocomplete="off"
                             data-format="comma-int" data-name="after_joto_ichiji_tousan_ichiji_{{ $period }}"
                             class="form-control suji11 text-end bg-light"
                             value="{{ old('after_joto_ichiji_tousan_ichiji_' . $period, $inputs['after_joto_ichiji_tousan_ichiji_' . $period] ?? null) }}" readonly>
                    </td>
                    <td>
                      <input type="text" inputmode="numeric" autocomplete="off"
                             data-format="comma-int" data-name="tsusango_ichiji_{{ $period }}"
                             class="form-control suji11 text-end bg-light"
                             value="{{ old('tsusango_ichiji_' . $period, $inputs['after_3jitsusan_ichiji_' . $period] ?? null) }}" readonly>
                    </td>
                    <td>
                      <input type="text" inputmode="numeric" autocomplete="off"
                             data-format="comma-int" data-name="half_ichiji_{{ $period }}"
                             class="form-control suji8 text-end bg-light"
                             value="{{ old('half_ichiji_' . $period, (($inputs['tsusango_ichiji_' . $period] ?? 0) - ($inputs['shotoku_ichiji_' . $period] ?? 0))) }}" readonly>
                    </td>
                    <td>
                      <input type="text" inputmode="numeric" autocomplete="off"
                             data-format="comma-int" data-name="shotoku_ichiji_{{ $period }}"
                             class="form-control suji11 text-end bg-light"
                             value="{{ old('shotoku_ichiji_' . $period, $inputs['shotoku_ichiji_' . $period] ?? null) }}" readonly>
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
            document.querySelector('[data-name="sashihiki_ichiji_' + period + '"]').value = result.toLocaleString('ja-JP');
          }
        </script>
        <hr>
        <div class="text-end me-2 mb-2">
          <button type="submit" class="btn btn-base-blue me-2">戻 る</button>
          <button type="submit"
                  class="btn btn-base-green"
                  name="recalc_all"
                  value="1"
                  data-disable-on-submit
                  data-redirect-to="joto_ichiji">再計算</button>
        </div>
      </form>
    </div>
  </div> 
</div>

@endsection

@push('scripts')
<script>
  document.addEventListener('DOMContentLoaded', () => {
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

    const inputs = document.querySelectorAll('[data-format="comma-int"]');

    inputs.forEach((input) => {
      const name = input.dataset.name;
      if (!name) {
        return;
      }

      ensureHidden(input);

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
      });
    });

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
@endpush