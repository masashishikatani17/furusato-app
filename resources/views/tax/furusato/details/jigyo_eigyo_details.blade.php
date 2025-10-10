@extends('layouts.min')

@section('title', '事業・営業等（内訳）')

@section('content')
@php
    $inputs = $out['inputs'] ?? [];
    $storedLabels = $storedLabels ?? [];
    $originTabRaw = request()->input('origin_tab', 'input');
    $originTab = is_string($originTabRaw) && trim($originTabRaw) === 'input' ? 'input' : '';
    $originAnchor = preg_replace('/[^A-Za-z0-9_-]/', '', (string) request()->input('origin_anchor', ''));
@endphp
<div class="container-blue mt-2" style="width:600px;">
  <div class="card-header d-flex align-items-start">
    <img src="{{ asset('storage/images/kado_lefttop.jpg') }}" alt="…">
    <h0 class="mb-0 mt-2">内訳－事業・営業等</h0>
  </div>
  <div class="card-body">　
  　<div class="wrapper">
        <form method="POST" action="{{ route('furusato.details.jigyo.save') }}">
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
      
          <div class="table-responsive">
            <table class="table-base table-bordered align-middle">
              <thead>
                <tr>
                  <th colspan="2" style="height:30px;">項　目</th>
                  <th>{{ $warekiPrev ?? '前年' }}</th>
                  <th>{{ $warekiCurr ?? '当年' }}</th>
                </tr>
              </thead>
              <tbody>
                <tr>
                  <th colspan="2" class="text-start align-middle ps-1">売上(収入)金額</th>
                  <td>
                    <input type="number" min="0" step="1" class="form-control suji11" name="jigyo_eigyo_uriage_prev" value="{{ old('jigyo_eigyo_uriage_prev', $inputs['jigyo_eigyo_uriage_prev'] ?? null) }}">
                  </td>
                  <td>
                    <input type="number" min="0" step="1" class="form-control suji11" name="jigyo_eigyo_uriage_curr" value="{{ old('jigyo_eigyo_uriage_curr', $inputs['jigyo_eigyo_uriage_curr'] ?? null) }}">
                  </td>
                </tr>
                <tr>
                  <th colspan="2" class="text-start align-middle ps-1">売上原価</th>
                  <td>
                    <input type="number" min="0" step="1" class="form-control suji11" name="jigyo_eigyo_urigenka_prev" value="{{ old('jigyo_eigyo_urigenka_prev', $inputs['jigyo_eigyo_urigenka_prev'] ?? null) }}">
                  </td>
                  <td>
                    <input type="number" min="0" step="1" class="form-control suji11" name="jigyo_eigyo_urigenka_curr" value="{{ old('jigyo_eigyo_urigenka_curr', $inputs['jigyo_eigyo_urigenka_curr'] ?? null) }}">
                  </td>
                </tr>
                <tr>
                  <th colspan="2" class="text-start align-middle ps-1">差引金額</th>
                  <td>
                    @php($name = 'jigyo_eigyo_sashihiki_1_prev')
                    <input type="number" min="0" step="1" class="form-control suji11" name="{{ $name }}" value="{{ old($name, $inputs[$name] ?? null) }}" readonly>
                  </td>
                  <td>
                    @php($name = 'jigyo_eigyo_sashihiki_1_curr')
                    <input type="number" min="0" step="1" class="form-control suji11" name="{{ $name }}" value="{{ old($name, $inputs[$name] ?? null) }}" readonly>
                  </td>
                </tr>
                @php($expenseFields = [
                  ['labelInput' => 'jigyo_eigyo_keihi_label_01', 'labelIndex' => 1, 'name' => 'jigyo_eigyo_keihi_1'],
                  ['labelInput' => 'jigyo_eigyo_keihi_label_02', 'labelIndex' => 2, 'name' => 'jigyo_eigyo_keihi_2'],
                  ['labelInput' => 'jigyo_eigyo_keihi_label_03', 'labelIndex' => 3, 'name' => 'jigyo_eigyo_keihi_3'],
                  ['labelInput' => 'jigyo_eigyo_keihi_label_04', 'labelIndex' => 4, 'name' => 'jigyo_eigyo_keihi_4'],
                  ['labelInput' => 'jigyo_eigyo_keihi_label_05', 'labelIndex' => 5, 'name' => 'jigyo_eigyo_keihi_5'],
                  ['labelInput' => 'jigyo_eigyo_keihi_label_06', 'labelIndex' => 6, 'name' => 'jigyo_eigyo_keihi_6'],
                  ['labelInput' => 'jigyo_eigyo_keihi_label_07', 'labelIndex' => 7, 'name' => 'jigyo_eigyo_keihi_7'],
                  ['label' => 'その他', 'name' => 'jigyo_eigyo_keihi_sonota', 'headerClass' => 'text-start u-nowrap th-cream'],
                  ['label' => '合 計', 'name' => 'jigyo_eigyo_keihi_gokei', 'readonly' => true, 'headerClass' => 'u-nowrap th-cream'],
                ])
                @php($expenseRowspan = count($expenseFields))
                @php($field = array_shift($expenseFields))
                <tr>
                  <th scope="rowgroup" rowspan="{{ $expenseRowspan }}" class="text-start align-middle ps-1">経 費</th>
                  <th class="{{ $field['headerClass'] ?? 'text-start u-nowrap th-cream' }}">
                    @php($labelName = $field['labelInput'] ?? null)
                    @if($labelName)
                      @php($placeholder = $field['placeholder'] ?? '')
                      <input type="text"
                             name="{{ $labelName }}"
                             value="{{ old($labelName, $storedLabels[$labelName] ?? '') }}"
                             maxlength="64"
                             class="form-control form-control-sm"
                             aria-label="経費項目名{{ $field['labelIndex'] ?? '' }}"
                             @if($placeholder !== '') placeholder="{{ $placeholder }}" @endif>
                    @else
                      {{ $field['label'] ?? '' }}
                    @endif
                  </th>
                  <td>
                    @php($name = $field['name'] . '_prev')
                    @php($readonly = $field['readonly'] ?? false)
                    <input type="number" min="0" step="1" class="form-control suji11{{ $readonly ? ' bg-light' : '' }}" name="{{ $name }}" value="{{ old($name, $inputs[$name] ?? null) }}" @if($readonly) readonly @endif>
                  </td>
                  <td>
                    @php($name = $field['name'] . '_curr')
                    <input type="number" min="0" step="1" class="form-control suji11{{ $readonly ? ' bg-light' : '' }}" name="{{ $name }}" value="{{ old($name, $inputs[$name] ?? null) }}" @if($readonly) readonly @endif>
                  </td>
                </tr>
                @foreach ($expenseFields as $field)
                  <tr>
                    <th class="{{ $field['headerClass'] ?? 'text-start u-nowrap th-cream' }}">
                      @php($labelName = $field['labelInput'] ?? null)
                      @if($labelName)
                        @php($placeholder = $field['placeholder'] ?? '')
                        <input type="text"
                               name="{{ $labelName }}"
                               value="{{ old($labelName, $storedLabels[$labelName] ?? '') }}"
                               maxlength="64"
                               class="form-control form-control-sm"
                               aria-label="経費項目名{{ $field['labelIndex'] ?? '' }}"
                               @if($placeholder !== '') placeholder="{{ $placeholder }}" @endif>
                      @else
                        {{ $field['label'] ?? '' }}
                      @endif
                    </th>
                    <td>
                      @php($name = $field['name'] . '_prev')
                      @php($readonly = $field['readonly'] ?? false)
                      <input type="number" min="0" step="1" class="form-control suji11{{ $readonly ? ' bg-light' : '' }}" name="{{ $name }}" value="{{ old($name, $inputs[$name] ?? null) }}" @if($readonly) readonly @endif>
                    </td>
                    <td>
                      @php($name = $field['name'] . '_curr')
                      <input type="number" min="0" step="1" class="form-control suji11{{ $readonly ? ' bg-light' : '' }}" name="{{ $name }}" value="{{ old($name, $inputs[$name] ?? null) }}" @if($readonly) readonly @endif>
                    </td>
                  </tr>
                @endforeach
                <tr>
                  <th colspan="2" class="text-start align-middle ps-1">差引金額</th>
                  <td>
                    @php($name = 'jigyo_eigyo_sashihiki_2_prev')
                    <input type="number" min="0" step="1" class="form-control suji11" name="{{ $name }}" value="{{ old($name, $inputs[$name] ?? null) }}" readonly>
                  </td>
                  <td>
                    @php($name = 'jigyo_eigyo_sashihiki_2_curr')
                    <input type="number" min="0" step="1" class="form-control suji11" name="{{ $name }}" value="{{ old($name, $inputs[$name] ?? null) }}" readonly>
                  </td>
                </tr>
                <tr>
                  <th colspan="2" class="text-start align-middle ps-1">専従者給与</th>
                  <td>
                    <input type="number" min="0" step="1" class="form-control suji11" name="jigyo_eigyo_senjuusha_kyuyo_prev" value="{{ old('jigyo_eigyo_senjuusha_kyuyo_prev', $inputs['jigyo_eigyo_senjuusha_kyuyo_prev'] ?? null) }}">
                  </td>
                  <td>
                    <input type="number" min="0" step="1" class="form-control suji11" name="jigyo_eigyo_senjuusha_kyuyo_curr" value="{{ old('jigyo_eigyo_senjuusha_kyuyo_curr', $inputs['jigyo_eigyo_senjuusha_kyuyo_curr'] ?? null) }}">
                  </td>
                </tr>
                <tr>
                  <th colspan="2" class="text-start align-middle ps-1 pe-1">青色申告特別控除前の所得金額</th>
                  <td>
                    @php($name = 'jigyo_eigyo_aoi_tokubetsu_kojo_mae_prev')
                    <input type="number" min="0" step="1" class="form-control suji11" name="{{ $name }}" value="{{ old($name, $inputs[$name] ?? null) }}" readonly>
                  </td>
                  <td>
                    @php($name = 'jigyo_eigyo_aoi_tokubetsu_kojo_mae_curr')
                    <input type="number" min="0" step="1" class="form-control suji11" name="{{ $name }}" value="{{ old($name, $inputs[$name] ?? null) }}" readonly>
                  </td>
                </tr>
                <tr>
                  <th colspan="2" class="text-start align-middle ps-1">青色申告特別控除額</th>
                  <td>
                    <input type="number" min="0" step="1" class="form-control suji11" name="jigyo_eigyo_aoi_tokubetsu_kojo_gaku_prev" value="{{ old('jigyo_eigyo_aoi_tokubetsu_kojo_gaku_prev', $inputs['jigyo_eigyo_aoi_tokubetsu_kojo_gaku_prev'] ?? null) }}">
                  </td>
                  <td>
                    <input type="number" min="0" step="1" class="form-control suji11" name="jigyo_eigyo_aoi_tokubetsu_kojo_gaku_curr" value="{{ old('jigyo_eigyo_aoi_tokubetsu_kojo_gaku_curr', $inputs['jigyo_eigyo_aoi_tokubetsu_kojo_gaku_curr'] ?? null) }}">
                  </td>
                </tr>
                <tr>
                  <th colspan="2" class="text-start align-middle ps-1">所得金額</th>
                  <td>
                    @php($name = 'jigyo_eigyo_shotoku_prev')
                    <input type="number" min="0" step="1" class="form-control suji11" name="{{ $name }}" value="{{ old($name, $inputs[$name] ?? null) }}" readonly>
                  </td>
                  <td>
                    @php($name = 'jigyo_eigyo_shotoku_curr')
                    <input type="number" min="0" step="1" class="form-control suji11" name="{{ $name }}" value="{{ old($name, $inputs[$name] ?? null) }}" readonly>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
          <hr>
            <div class="text-end me-2 mb-2">
              <button type="submit" class="btn-base-blue">戻 る</button>
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
    const el = Q(name); if (!el) return 0;
    const v = (el.value ?? '').toString().trim();
    return v === '' ? 0 : parseInt(v, 10);
  };
  const S = (name, val) => { const el = Q(name); if (el) el.value = (val ?? 0); };

  const recalc = (suffix) => {
    const uriage   = V(`jigyo_eigyo_uriage_${suffix}`);
    const urigenka = V(`jigyo_eigyo_urigenka_${suffix}`);
    const sashihiki1 = uriage - urigenka;
    S(`jigyo_eigyo_sashihiki_1_${suffix}`, sashihiki1);

    let keihiGokei = 0;
    for (let i=1;i<=7;i++) keihiGokei += V(`jigyo_eigyo_keihi_${i}_${suffix}`);
    keihiGokei += V(`jigyo_eigyo_keihi_sonota_${suffix}`);
    S(`jigyo_eigyo_keihi_gokei_${suffix}`, keihiGokei);

    const sashihiki2 = sashihiki1 - keihiGokei;
    S(`jigyo_eigyo_sashihiki_2_${suffix}`, sashihiki2);

    const senju = V(`jigyo_eigyo_senjuusha_kyuyo_${suffix}`);
    const mae   = sashihiki2 - senju;
    S(`jigyo_eigyo_aoi_tokubetsu_kojo_mae_${suffix}`, mae);

    const tokugaku = V(`jigyo_eigyo_aoi_tokubetsu_kojo_gaku_${suffix}`);
    S(`jigyo_eigyo_shotoku_${suffix}`, mae - tokugaku);
  };

  const bindBlur = (names) => names.forEach(n => { const el = Q(n); if (el) el.addEventListener('blur', () => { recalc('prev'); recalc('curr'); }); });

  bindBlur([
    'jigyo_eigyo_uriage_prev','jigyo_eigyo_urigenka_prev',
    'jigyo_eigyo_senjuusha_kyuyo_prev','jigyo_eigyo_aoi_tokubetsu_kojo_gaku_prev',
    'jigyo_eigyo_uriage_curr','jigyo_eigyo_urigenka_curr',
    'jigyo_eigyo_senjuusha_kyuyo_curr','jigyo_eigyo_aoi_tokubetsu_kojo_gaku_curr',
    'jigyo_eigyo_keihi_1_prev','jigyo_eigyo_keihi_2_prev','jigyo_eigyo_keihi_3_prev','jigyo_eigyo_keihi_4_prev','jigyo_eigyo_keihi_5_prev','jigyo_eigyo_keihi_6_prev','jigyo_eigyo_keihi_7_prev','jigyo_eigyo_keihi_sonota_prev',
    'jigyo_eigyo_keihi_1_curr','jigyo_eigyo_keihi_2_curr','jigyo_eigyo_keihi_3_curr','jigyo_eigyo_keihi_4_curr','jigyo_eigyo_keihi_5_curr','jigyo_eigyo_keihi_6_curr','jigyo_eigyo_keihi_7_curr','jigyo_eigyo_keihi_sonota_curr'
  ]);

  recalc('prev'); recalc('curr');
});
</script>
@endpush
@endsection