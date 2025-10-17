@extends('layouts.min')

@section('title', '不動産（内訳）')

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
    <h0 class="mb-0 mt-2">内訳－不動産</h0>
  </div>
  <div class="card-body">　
  　<div class="wrapper">
      <form method="POST" action="{{ route('furusato.details.fudosan.save') }}">
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
    
        <div class="table-responsive">
          <table class="table-base table-bordered align-middle">
            <tbody>
              <tr>
                <th colspan="2" class="th-ccc" style="height:30px;">項 目</th>
                <th class="th-ccc">{{ $warekiPrev ?? '前年' }}</th>
                <th class="th-ccc">{{ $warekiCurr ?? '当年' }}</th>
              </tr>
              <tr>
                <th class="text-start align-middle" colspan="2">収入金額</th>
                <td>
                  @php($name = 'fudosan_syunyu_prev')
                  <input type="number" min="0" step="1" class="form-control suji11" value="{{ old($name, $inputs[$name] ?? null) }}" name="{{ $name }}">
                </td>
                <td>
                  @php($name = 'fudosan_syunyu_curr')
                  <input type="number" min="0" step="1" class="form-control suji11" value="{{ old($name, $inputs[$name] ?? null) }}" name="{{ $name }}">
                </td>
              </tr>
              @php($expenseFields = [
                ['labelInput' => 'fudosan_keihi_label_01', 'labelIndex' => 1, 'name' => 'fudosan_keihi_1'],
                ['labelInput' => 'fudosan_keihi_label_02', 'labelIndex' => 2, 'name' => 'fudosan_keihi_2'],
                ['labelInput' => 'fudosan_keihi_label_03', 'labelIndex' => 3, 'name' => 'fudosan_keihi_3'],
                ['labelInput' => 'fudosan_keihi_label_04', 'labelIndex' => 4, 'name' => 'fudosan_keihi_4'],
                ['labelInput' => 'fudosan_keihi_label_05', 'labelIndex' => 5, 'name' => 'fudosan_keihi_5'],
                ['labelInput' => 'fudosan_keihi_label_06', 'labelIndex' => 6, 'name' => 'fudosan_keihi_6'],
                ['labelInput' => 'fudosan_keihi_label_07', 'labelIndex' => 7, 'name' => 'fudosan_keihi_7'],
                ['label' => 'その他', 'name' => 'fudosan_keihi_sonota'],
                ['label' => '合計', 'name' => 'fudosan_keihi_gokei', 'readonly' => true],
              ])
              @php($expenseRowspan = count($expenseFields))
              @php($field = array_shift($expenseFields))
              <tr>
                <th class="text-center align-middle" rowspan="{{ $expenseRowspan }}" style="width: 30px;">必<br>要<br>経<br>費</th>
                <th class="text-start u-nowrap th-ddd">
                  @php($labelName = $field['labelInput'] ?? null)
                  @if($labelName)
                    @php($placeholder = $field['placeholder'] ?? '')
                    <input type="text"
                           name="{{ $labelName }}"
                           value="{{ old($labelName, $storedLabels[$labelName] ?? '') }}"
                           maxlength="64"
                           class="form-control form-control-sm"
                           aria-label="必要経費項目名{{ $field['labelIndex'] ?? '' }}"
                           @if($placeholder !== '') placeholder="{{ $placeholder }}" @endif>
                  @else
                    {{ $field['label'] ?? '' }}
                  @endif
                </th>
                <td>
                  @php($name = $field['name'] . '_prev')
                  @php($readonly = $field['readonly'] ?? false)
                  <input type="number" min="0" step="1" class="form-control suji11{{ $readonly ? ' bg-light' : '' }}" value="{{ old($name, $inputs[$name] ?? null) }}" name="{{ $name }}" @if($readonly) readonly @endif>
                </td>
                <td>
                  @php($name = $field['name'] . '_curr')
                  <input type="number" min="0" step="1" class="form-control suji11{{ $readonly ? ' bg-light' : '' }}" value="{{ old($name, $inputs[$name] ?? null) }}" name="{{ $name }}" @if($readonly) readonly @endif>
                </td>
              </tr>
              @foreach ($expenseFields as $field)
                <tr>
                  <th class="text-center u-nowrap th-ddd">
                    @php($labelName = $field['labelInput'] ?? null)
                    @if($labelName)
                      @php($placeholder = $field['placeholder'] ?? '')
                      <input type="text"
                             name="{{ $labelName }}"
                             value="{{ old($labelName, $storedLabels[$labelName] ?? '') }}"
                             maxlength="64"
                             class="form-control form-control-sm"
                             aria-label="必要経費項目名{{ $field['labelIndex'] ?? '' }}"
                             @if($placeholder !== '') placeholder="{{ $placeholder }}" @endif>
                    @else
                      {{ $field['label'] ?? '' }}
                    @endif
                  </th>
                  <td>
                    @php($name = $field['name'] . '_prev')
                    @php($readonly = $field['readonly'] ?? false)
                    <input type="number" min="0" step="1" class="form-control suji11{{ $readonly ? ' bg-light' : '' }}" value="{{ old($name, $inputs[$name] ?? null) }}" name="{{ $name }}" @if($readonly) readonly @endif>
                  </td>
                  <td>
                    @php($name = $field['name'] . '_curr')
                    <input type="number" min="0" step="1" class="form-control suji11{{ $readonly ? ' bg-light' : '' }}" value="{{ old($name, $inputs[$name] ?? null) }}" name="{{ $name }}" @if($readonly) readonly @endif>
                  </td>
                </tr>
              @endforeach
              @php($footerFields = [
                ['name' => 'fudosan_sashihiki', 'label' => '差引金額', 'readonly' => true],
                ['name' => 'fudosan_senjuusha_kyuyo', 'label' => '専従者給与'],
                ['name' => 'fudosan_aoi_tokubetsu_kojo_mae', 'label' => '青色申告特別控除前の所得金額', 'readonly' => true],
                ['name' => 'fudosan_aoi_tokubetsu_kojo_gaku', 'label' => '青色申告特別控除額'],
                ['name' => 'fudosan_shotoku', 'label' => '所得金額', 'readonly' => true],
                ['name' => 'fudosan_fusairishi', 'label' => '土地等を取得するための負債利子'],
              ])
              @foreach ($footerFields as $field)
                <tr>
                  <th class="text-start align-middle" colspan="2">{{ $field['label'] }}</th>
                  <td>
                    @php($name = $field['name'] . '_prev')
                    @php($readonly = $field['readonly'] ?? false)
                    <input type="number" min="0" step="1" class="form-control suji11{{ $readonly ? ' bg-light' : '' }}" value="{{ old($name, $inputs[$name] ?? null) }}" name="{{ $name }}" @if($readonly) readonly @endif>
                  </td>
                  <td>
                    @php($name = $field['name'] . '_curr')
                    <input type="number" min="0" step="1" class="form-control suji11{{ $readonly ? ' bg-light' : '' }}" value="{{ old($name, $inputs[$name] ?? null) }}" name="{{ $name }}" @if($readonly) readonly @endif>
                  </td>
                </tr>
              @endforeach
            </tbody>
          </table>
        </div>
        <hr>
        <div class="text-end me-2 mb-2">
          <button type="submit" class="btn-base-blue">戻 る</button>
          <button type="submit"
                  class="btn-base-green ms-2"
                  name="recalc_all"
                  value="1"
                  data-disable-on-submit
                  data-redirect-to="fudosan">再計算</button>
        </div>
      </form>
    </div>
  </div>
</div>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
  const Q = (name) => document.querySelector(`[name="${name}"]`);
  const V = (name) => { const el = Q(name); const s=(el?.value??'').trim(); return s===''?0:parseInt(s,10); };
  const S = (name, val) => { const el = Q(name); if (el) el.value = (val ?? 0); };

  const recalc = (suffix) => {
    let g = 0;
    for (let i=1;i<=7;i++) g += V(`fudosan_keihi_${i}_${suffix}`);
    g += V(`fudosan_keihi_sonota_${suffix}`);
    S(`fudosan_keihi_gokei_${suffix}`, g);

    const shunyu = V(`fudosan_syunyu_${suffix}`);
    const sashihiki = shunyu - g;
    S(`fudosan_sashihiki_${suffix}`, sashihiki);

    const senju = V(`fudosan_senjuusha_kyuyo_${suffix}`);
    const mae = sashihiki - senju;
    S(`fudosan_aoi_tokubetsu_kojo_mae_${suffix}`, mae);

    const tokugaku = V(`fudosan_aoi_tokubetsu_kojo_gaku_${suffix}`);
    S(`fudosan_shotoku_${suffix}`, mae - tokugaku);
  };

  const bindBlur = (names) => names.forEach(n=>{ const el=Q(n); if(el) el.addEventListener('blur', ()=>{ recalc('prev'); recalc('curr'); }); });

  bindBlur([
    'fudosan_syunyu_prev','fudosan_senjuusha_kyuyo_prev','fudosan_aoi_tokubetsu_kojo_gaku_prev',
    'fudosan_syunyu_curr','fudosan_senjuusha_kyuyo_curr','fudosan_aoi_tokubetsu_kojo_gaku_curr',
    'fudosan_keihi_1_prev','fudosan_keihi_2_prev','fudosan_keihi_3_prev','fudosan_keihi_4_prev','fudosan_keihi_5_prev','fudosan_keihi_6_prev','fudosan_keihi_7_prev','fudosan_keihi_sonota_prev',
    'fudosan_keihi_1_curr','fudosan_keihi_2_curr','fudosan_keihi_3_curr','fudosan_keihi_4_curr','fudosan_keihi_5_curr','fudosan_keihi_6_curr','fudosan_keihi_7_curr','fudosan_keihi_sonota_curr'
  ]);

  recalc('prev'); recalc('curr');
});
</script>
@endpush
@endsection