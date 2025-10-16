@extends('layouts.min')

@section('title', '株式等の譲渡所得等 内訳')

@section('content')
@php
    $inputs = $out['inputs'] ?? [];
    $warekiPrevLabel = $warekiPrev ?? '前年';
    $warekiCurrLabel = $warekiCurr ?? '当年';
    $originTabRaw = request()->input('origin_tab', 'input');
    $originTab = is_string($originTabRaw) && trim($originTabRaw) === 'input' ? 'input' : '';
    $originAnchor = preg_replace('/[^A-Za-z0-9_-]/', '', (string) request()->input('origin_anchor', ''));
    $rows = [
        ['label' => '一般株式等の譲渡', 'key' => 'ippan_joto', 'has_kurikoshi' => false],
        ['label' => '上場株式等の譲渡', 'key' => 'jojo_joto', 'has_kurikoshi' => true],
        ['label' => '上場株式等の配当等', 'key' => 'jojo_haito', 'has_kurikoshi' => false],
    ];
@endphp
<div class="container-blue mt-2" style="width: 1050px;">
  <div class="card-header d-flex align-items-start justify-content-between">
    <div class="d-flex align-items-start">
      <img src="{{ asset('storage/images/kado_lefttop.jpg') }}" alt="…">
      <h0 class="mb-0 mt-2 ms-2">内訳－株式等の譲渡所得等</h0>
    </div>
  </div>
  <div class="card-body">
  　<div class="wrapper">
      <form method="POST" action="{{ route('furusato.details.bunri_kabuteki.save') }}">
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
          <div class="table-responsive mb-2">
            <table class="table-base table-bordered align-middle text-center">
              <thead>
                <tr>
                  <th style="width: 150px; height:30px;"></th>
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
                      <input type="number" min="0" step="1" class="form-control suji11 text-end" name="{{ $name }}" value="{{ old($name, $inputs[$name] ?? null) }}">
                    </td>
                    @php($name = 'keihi_' . $base)
                    <td>
                      <input type="number" min="0" step="1" class="form-control suji11 text-end" name="{{ $name }}" value="{{ old($name, $inputs[$name] ?? null) }}">
                    </td>
                    @php($name = 'shotoku_' . $base)
                    <td>
                      <input type="number" step="1" class="form-control suji11 text-end bg-light" name="{{ $name }}" value="{{ old($name, $inputs[$name] ?? null) }}" readonly>
                    </td>
                    @php($name = 'tsusango_' . $base)
                    <td>
                      <input type="number" min="0" step="1" class="form-control suji11 text-end" name="{{ $name }}" value="{{ old($name, $inputs[$name] ?? null) }}">
                    </td>
                    <td>
                      @if ($row['has_kurikoshi'])
                        @php($name = 'kurikoshi_' . $base)
                        <input type="number" min="0" step="1" class="form-control suji11 text-end" name="{{ $name }}" value="{{ old($name, $inputs[$name] ?? null) }}">
                      @else
                        <span class="d-inline-block w-100">－</span>
                      @endif
                    </td>
                    @php($name = 'shotoku_after_kurikoshi_' . $base)
                    <td>
                      <input type="number" step="1" class="form-control suji11 text-end bg-light" name="{{ $name }}" value="{{ old($name, $inputs[$name] ?? null) }}" readonly>
                    </td>
                  </tr>
                @endforeach
              </tbody>
            </table>
          </div>
        @endforeach
        <hr>
        <div class="text-end me-2 mb-3">
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
    { key: 'ippan_joto', hasKurikoshi: false },
    { key: 'jojo_joto', hasKurikoshi: true },
    { key: 'jojo_haito', hasKurikoshi: false },
  ];

  const recalc = (period) => {
    rows.forEach((row) => {
      const base = `${row.key}_${period}`;
      const syunyu = V(`syunyu_${base}`);
      const keihi = V(`keihi_${base}`);
      const shotoku = syunyu - keihi;
      S(`shotoku_${base}`, shotoku);

      const tsusango = V(`tsusango_${base}`);
      const kurikoshi = row.hasKurikoshi ? V(`kurikoshi_${base}`) : 0;
      const after = tsusango - kurikoshi;
      S(`shotoku_after_kurikoshi_${base}`, after);
    });
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