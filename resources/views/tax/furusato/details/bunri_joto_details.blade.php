<!-- views/tax/furusato/details/bunri_joto_detail.blade.php -->
@extends('layouts.min')

@section('title', '分離課税 譲渡所得（短期/長期）内訳')

@section('content')
@php
    $inputs = $out['inputs'] ?? [];
    $warekiPrevLabel = $warekiPrev ?? '前年';
    $warekiCurrLabel = $warekiCurr ?? '当年';
    $originTabRaw = request()->input('origin_tab', 'input');
    $originTab = is_string($originTabRaw) && trim($originTabRaw) === 'input' ? 'input' : '';
    $originAnchor = preg_replace('/[^A-Za-z0-9_-]/', '', (string) request()->input('origin_anchor', ''));
    $groups = [
        [
            'title' => '短期譲渡',
            'key' => 'tanki',
            'rows' => [
                ['label' => '一般分', 'key' => 'tanki_ippan'],
                ['label' => '軽減分', 'key' => 'tanki_keigen'],
            ],
        ],
        [
            'title' => '長期譲渡',
            'key' => 'choki',
            'rows' => [
                ['label' => '一般分', 'key' => 'choki_ippan'],
                ['label' => '特定分', 'key' => 'choki_tokutei'],
                ['label' => '軽課分', 'key' => 'choki_keika'],
            ],
        ],
    ];
@endphp
<div class="container-blue mt-2" style="width: 1100px;">
  <div class="card-header d-flex align-items-start justify-content-between">
    <div class="d-flex align-items-start">
      <img src="{{ asset('storage/images/kado_lefttop.jpg') }}" alt="…">
      <h0 class="mb-0 mt-2 ms-2">内訳－分離課税 譲渡所得（短期/長期）</h0>
    </div>
  </div>
  <div class="card-body">
  　<div class="wrapper">
      <form method="POST" action="{{ route('furusato.details.bunri_joto.save') }}">
        @csrf
        <input type="hidden" name="data_id" value="{{ $dataId }}">
        <input type="hidden" name="origin_tab" value="{{ $originTab }}">
        <input type="hidden" name="origin_anchor" value="{{ $originAnchor }}">
        <input type="hidden" name="redirect_to" value="">

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
          <div class="table-responsive mb-2">
            <table class="table-base table-bordered align-middle text-center">
              <thead>
                <tr>
                  <th colspan="2" style="height:30px; width:200px;"></th>
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
                @foreach ($groups as $group)
                  @php($rowspan = count($group['rows']))
                  @foreach ($group['rows'] as $index => $row)
                    <tr>
                      @if ($index === 0)
                        <th scope="rowgroup" rowspan="{{ $rowspan }}" class="text-center align-middle" style="width:40px;">{{ $group['title'] }}</th>
                      @endif
                      <th class="text-start align-middle th-ddd ps-1" nowrap="nowrap">{{ $row['label'] }}</th>
                      @php($base = $row['key'] . '_' . $period)
                      @php($name = 'syunyu_' . $base)
                      <td>
                        <input type="text" inputmode="numeric" autocomplete="off"
                               data-format="comma-int" data-name="{{ $name }}"
                               class="form-control suji11 text-end"
                               value="{{ old($name, $inputs[$name] ?? null) }}">
                      </td>
                      @php($name = 'keihi_' . $base)
                      <td>
                        <input type="text" inputmode="numeric" autocomplete="off"
                               data-format="comma-int" data-name="{{ $name }}"
                               class="form-control suji11 text-end"
                               value="{{ old($name, $inputs[$name] ?? null) }}">
                      </td>
                      @php($name = 'before_tsusan_' . $base)
                      <td>
                        <input type="text" inputmode="numeric" autocomplete="off"
                               data-format="comma-int" data-name="{{ $name }}"
                               class="form-control suji11 text-end bg-light"
                               value="{{ old($name, $inputs[$name] ?? null) }}" readonly>
                      </td>
                      @php($name = 'tsusango_' . $base)
                      <td>
                        <input type="text" inputmode="numeric" autocomplete="off"
                               data-format="comma-int" data-name="{{ $name }}"
                               class="form-control suji11 text-end bg-light"
                               value="{{ old($name, $inputs[$name] ?? null) }}" readonly>
                      </td>
                      @php($name = 'tokubetsukojo_' . $base)
                      <td>
                        <input type="text" inputmode="numeric" autocomplete="off"
                               data-format="comma-int" data-name="{{ $name }}"
                               class="form-control suji8 text-end"
                               value="{{ old($name, $inputs[$name] ?? null) }}">
                      </td>
                      @php($name = 'joto_shotoku_' . $base)
                      <td>
                        <input type="text" inputmode="numeric" autocomplete="off"
                               data-format="comma-int" data-name="{{ $name }}"
                               class="form-control suji11 text-end bg-light"
                               value="{{ old($name, $inputs[$name] ?? null) }}" readonly>
                      </td>
                      @if ($index === 0)
                        @php($gokeiName = sprintf('joto_shotoku_%s_gokei_%s', $group['key'], $period))
                        <td rowspan="{{ $rowspan }}">
                          <input type="text" inputmode="numeric" autocomplete="off"
                                 data-format="comma-int" data-name="{{ $gokeiName }}"
                                 class="form-control suji11 text-end bg-light"
                                 value="{{ old($gokeiName, $inputs[$gokeiName] ?? null) }}" readonly>
                        </td>
                      @endif
                    </tr>
                  @endforeach
                @endforeach
              </tbody>
            </table>
          </div>
        @endforeach
        <hr>
        <div class="text-end me-2 mb-3">
          <button type="submit" class="btn btn-base-blue me-2">入力画面へ戻る</button>
          <button type="submit"
                  class="btn btn-base-green"
                  name="recalc_all"
                  value="1"
                  data-disable-on-submit
                  data-redirect-to="bunri_joto">再計算</button>
        </div>
      </form>
    </div>
  </div>
</div>


@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
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

  const inputs = document.querySelectorAll('[data-format="comma-int"]');

  inputs.forEach((input) => {
    const name = input.dataset.name;
    if (!name) {
      return;
    }

    ensureHidden(input);

    const applyFormat = () => {
      const hidden = getHidden(name);
      const raw = hidden ? toRawInt(hidden.value ?? '') : toRawInt(input.value ?? '');
      input.value = raw === '' ? '' : formatWithComma(raw);
    };

    applyFormat();

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

  ['prev', 'curr'].forEach(recalc);

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
@endsection