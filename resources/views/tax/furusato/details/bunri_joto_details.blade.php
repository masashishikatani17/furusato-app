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
<div class="container-blue mt-2" style="max-width: 980px;">
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
          <div class="table-responsive mb-4">
            <table class="table-base table-bordered align-middle text-center">
              <thead>
                <tr>
                  <th class="th-ccc" colspan="2" style="height:30px; width:200px;"></th>
                  <th class="th-ccc">収入金額</th>
                  <th class="th-ccc">必要経費</th>
                  <th class="th-ccc">差引金額</th>
                  <th class="th-ccc">損益通算後</th>
                  <th class="th-ccc">特別控除額</th>
                  <th class="th-ccc">譲渡所得金額</th>
                  <th class="th-ccc">課税所得金額</th>
                </tr>
              </thead>
              <tbody>
                @foreach ($groups as $group)
                  @php($rowspan = count($group['rows']))
                  @foreach ($group['rows'] as $index => $row)
                    <tr>
                      @if ($index === 0)
                        <th scope="rowgroup" rowspan="{{ $rowspan }}" class="text-center align-middle th-ddd">{{ $group['title'] }}</th>
                      @endif
                      <th class="text-start align-middle th-ddd ps-1">{{ $row['label'] }}</th>
                      @php($base = $row['key'] . '_' . $period)
                      @php($name = 'syunyu_' . $base)
                      <td>
                        <input type="number" min="0" step="1" class="form-control suji11 text-end" name="{{ $name }}" value="{{ old($name, $inputs[$name] ?? null) }}">
                      </td>
                      @php($name = 'keihi_' . $base)
                      <td>
                        <input type="number" min="0" step="1" class="form-control suji11 text-end" name="{{ $name }}" value="{{ old($name, $inputs[$name] ?? null) }}">
                      </td>
                      @php($name = 'sashihiki_' . $base)
                      <td>
                        <input type="number" step="1" class="form-control suji11 text-end bg-light" name="{{ $name }}" value="{{ old($name, $inputs[$name] ?? null) }}" readonly>
                      </td>
                      @php($name = 'tsusango_' . $base)
                      <td>
                        <input type="number" min="0" step="1" class="form-control suji11 text-end" name="{{ $name }}" value="{{ old($name, $inputs[$name] ?? null) }}">
                      </td>
                      @php($name = 'tokubetsukojo_' . $base)
                      <td>
                        <input type="number" min="0" step="1" class="form-control suji11 text-end" name="{{ $name }}" value="{{ old($name, $inputs[$name] ?? null) }}">
                      </td>
                      @php($name = 'joto_shotoku_' . $base)
                      <td>
                        <input type="number" step="1" class="form-control suji11 text-end bg-light" name="{{ $name }}" value="{{ old($name, $inputs[$name] ?? null) }}" readonly>
                      </td>
                      @if ($index === 0)
                        @php($gokeiName = sprintf('joto_shotoku_%s_gokei_%s', $group['key'], $period))
                        <td rowspan="{{ $rowspan }}">
                          <input type="number" step="1" class="form-control suji11 text-end bg-light" name="{{ $gokeiName }}" value="{{ old($gokeiName, $inputs[$gokeiName] ?? null) }}" readonly>
                        </td>
                      @endif
                    </tr>
                  @endforeach
                @endforeach
              </tbody>
            </table>
          </div>
        @endforeach

        <div class="text-end me-2">
          <button type="submit" class="btn btn-base-blue">入力画面へ戻る</button>
        </div>
      </form>
    </div>
  </div>
</div>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
  const Q = (name) => document.querySelector(`[name="${name}"]`);
  const V = (name) => {
    const el = Q(name);
    if (!el) { return 0; }
    const raw = (el.value ?? '').toString().trim();
    if (raw === '') { return 0; }
    const num = Number(raw.replace(/[^\-0-9.]/g, ''));
    return Number.isFinite(num) ? Math.trunc(num) : 0;
  };
  const S = (name, value) => {
    const el = Q(name);
    if (el) {
      el.value = value ?? 0;
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
      const syunyu = V(`syunyu_${base}`);
      const keihi = V(`keihi_${base}`);
      const sashihiki = syunyu - keihi;
      S(`sashihiki_${base}`, sashihiki);

      const tsusango = V(`tsusango_${base}`);
      const tokubetsu = V(`tokubetsukojo_${base}`);
      const joto = tsusango - tokubetsu;
      S(`joto_shotoku_${base}`, joto);
      sums[row.group] += joto;
    });

    S(`joto_shotoku_tanki_gokei_${period}`, sums.tanki);
    S(`joto_shotoku_choki_gokei_${period}`, sums.choki);
  };

  const bindBlur = () => {
    document.querySelectorAll('input[type="number"]').forEach((el) => {
      if (el.readOnly) {
        return;
      }
      const match = el.name.match(/_(prev|curr)$/);
      if (!match) {
        return;
      }
      el.addEventListener('blur', () => recalc(match[1]));
    });
  };

  bindBlur();
  recalc('prev');
  recalc('curr');
});
</script>
@endpush
@endsection