@extends('layouts.min')

@section('title', '事業・営業等（内訳）')

@section('content')
@php($inputs = $out['inputs'] ?? [])
<div class="container-blue mt-2">
  <div class="card-header d-flex align-items-start">
    <img src="{{ asset('storage/images/kado_lefttop.jpg') }}" alt="…">
    <h0 class="mb-0 mt-2">内訳－事業・営業等</h0>
  </div>
  <div class="card-body">　
  　<div class="wrapper">
        <form method="POST" action="{{ route('furusato.details.jigyo.save') }}">
          @csrf
          <input type="hidden" name="data_id" value="{{ $dataId }}">
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
                  <th colspan="2" class="text-start align-middle">売上(収入)金額</th>
                  <td>
                    <input type="number" min="0" step="1" class="form-control form-control-sm text-end" name="jigyo_eigyo_uriage_prev" value="{{ old('jigyo_eigyo_uriage_prev', $inputs['jigyo_eigyo_uriage_prev'] ?? null) }}">
                  </td>
                  <td>
                    <input type="number" min="0" step="1" class="form-control form-control-sm text-end" name="jigyo_eigyo_uriage_curr" value="{{ old('jigyo_eigyo_uriage_curr', $inputs['jigyo_eigyo_uriage_curr'] ?? null) }}">
                  </td>
                </tr>
                <tr>
                  <th colspan="2" class="text-start align-middle">売上原価</th>
                  <td>
                    <input type="number" min="0" step="1" class="form-control form-control-sm text-end" name="jigyo_eigyo_urigenka_prev" value="{{ old('jigyo_eigyo_urigenka_prev', $inputs['jigyo_eigyo_urigenka_prev'] ?? null) }}">
                  </td>
                  <td>
                    <input type="number" min="0" step="1" class="form-control form-control-sm text-end" name="jigyo_eigyo_urigenka_curr" value="{{ old('jigyo_eigyo_urigenka_curr', $inputs['jigyo_eigyo_urigenka_curr'] ?? null) }}">
                  </td>
                </tr>
                <tr>
                  <th colspan="2" class="text-start align-middle">差引金額</th>
                  <td>
                    @php($name = 'jigyo_eigyo_sashihiki_1_prev')
                    <input type="number" min="0" step="1" class="form-control form-control-sm text-end bg-light" name="{{ $name }}" value="{{ old($name, $inputs[$name] ?? null) }}" readonly>
                  </td>
                  <td>
                    @php($name = 'jigyo_eigyo_sashihiki_1_curr')
                    <input type="number" min="0" step="1" class="form-control form-control-sm text-end bg-light" name="{{ $name }}" value="{{ old($name, $inputs[$name] ?? null) }}" readonly>
                  </td>
                </tr>
                <tr>
                  <th scope="rowgroup" rowspan="9" class="text-center align-middle">経費</th>
                  <th class="u-nowrap th-ccc"></th>
                  <td>
                    <input type="number" min="0" step="1" class="form-control form-control-sm text-end" name="jigyo_eigyo_keihi_1_prev" value="{{ old('jigyo_eigyo_keihi_1_prev', $inputs['jigyo_eigyo_keihi_1_prev'] ?? null) }}">
                  </td>
                  <td>
                    <input type="number" min="0" step="1" class="form-control form-control-sm text-end" name="jigyo_eigyo_keihi_1_curr" value="{{ old('jigyo_eigyo_keihi_1_curr', $inputs['jigyo_eigyo_keihi_1_curr'] ?? null) }}">
                  </td>
                </tr>
                <tr>
                  <th class="u-nowrap th-ccc"></th>
                  <td>
                    <input type="number" min="0" step="1" class="form-control form-control-sm text-end" name="jigyo_eigyo_keihi_2_prev" value="{{ old('jigyo_eigyo_keihi_2_prev', $inputs['jigyo_eigyo_keihi_2_prev'] ?? null) }}">
                  </td>
                  <td>
                    <input type="number" min="0" step="1" class="form-control form-control-sm text-end" name="jigyo_eigyo_keihi_2_curr" value="{{ old('jigyo_eigyo_keihi_2_curr', $inputs['jigyo_eigyo_keihi_2_curr'] ?? null) }}">
                  </td>
                </tr>
                <tr>
                  <th class="u-nowrap th-ccc"></th>
                  <td>
                    <input type="number" min="0" step="1" class="form-control form-control-sm text-end" name="jigyo_eigyo_keihi_3_prev" value="{{ old('jigyo_eigyo_keihi_3_prev', $inputs['jigyo_eigyo_keihi_3_prev'] ?? null) }}">
                  </td>
                  <td>
                    <input type="number" min="0" step="1" class="form-control form-control-sm text-end" name="jigyo_eigyo_keihi_3_curr" value="{{ old('jigyo_eigyo_keihi_3_curr', $inputs['jigyo_eigyo_keihi_3_curr'] ?? null) }}">
                  </td>
                </tr>
                <tr>
                  <th class="u-nowrap th-ccc"></th>
                  <td>
                    <input type="number" min="0" step="1" class="form-control form-control-sm text-end" name="jigyo_eigyo_keihi_4_prev" value="{{ old('jigyo_eigyo_keihi_4_prev', $inputs['jigyo_eigyo_keihi_4_prev'] ?? null) }}">
                  </td>
                  <td>
                    <input type="number" min="0" step="1" class="form-control form-control-sm text-end" name="jigyo_eigyo_keihi_4_curr" value="{{ old('jigyo_eigyo_keihi_4_curr', $inputs['jigyo_eigyo_keihi_4_curr'] ?? null) }}">
                  </td>
                </tr>
                <tr>
                  <th class="u-nowrap th-ccc"></th>
                  <td>
                    <input type="number" min="0" step="1" class="form-control form-control-sm text-end" name="jigyo_eigyo_keihi_5_prev" value="{{ old('jigyo_eigyo_keihi_5_prev', $inputs['jigyo_eigyo_keihi_5_prev'] ?? null) }}">
                  </td>
                  <td>
                    <input type="number" min="0" step="1" class="form-control form-control-sm text-end" name="jigyo_eigyo_keihi_5_curr" value="{{ old('jigyo_eigyo_keihi_5_curr', $inputs['jigyo_eigyo_keihi_5_curr'] ?? null) }}">
                  </td>
                </tr>
                <tr>
                  <th class="u-nowrap th-ccc"></th>
                  <td>
                    <input type="number" min="0" step="1" class="form-control form-control-sm text-end" name="jigyo_eigyo_keihi_6_prev" value="{{ old('jigyo_eigyo_keihi_6_prev', $inputs['jigyo_eigyo_keihi_6_prev'] ?? null) }}">
                  </td>
                  <td>
                    <input type="number" min="0" step="1" class="form-control form-control-sm text-end" name="jigyo_eigyo_keihi_6_curr" value="{{ old('jigyo_eigyo_keihi_6_curr', $inputs['jigyo_eigyo_keihi_6_curr'] ?? null) }}">
                  </td>
                </tr>
                <tr>
                  <th class="u-nowrap th-ccc"></th>
                  <td>
                    <input type="number" min="0" step="1" class="form-control form-control-sm text-end" name="jigyo_eigyo_keihi_7_prev" value="{{ old('jigyo_eigyo_keihi_7_prev', $inputs['jigyo_eigyo_keihi_7_prev'] ?? null) }}">
                  </td>
                  <td>
                    <input type="number" min="0" step="1" class="form-control form-control-sm text-end" name="jigyo_eigyo_keihi_7_curr" value="{{ old('jigyo_eigyo_keihi_7_curr', $inputs['jigyo_eigyo_keihi_7_curr'] ?? null) }}">
                  </td>
                </tr>
                <tr>
                  <th class="u-nowrap th-ccc">その他</th>
                  <td>
                    <input type="number" min="0" step="1" class="form-control form-control-sm text-end" name="jigyo_eigyo_keihi_sonota_prev" value="{{ old('jigyo_eigyo_keihi_sonota_prev', $inputs['jigyo_eigyo_keihi_sonota_prev'] ?? null) }}">
                  </td>
                  <td>
                    <input type="number" min="0" step="1" class="form-control form-control-sm text-end" name="jigyo_eigyo_keihi_sonota_curr" value="{{ old('jigyo_eigyo_keihi_sonota_curr', $inputs['jigyo_eigyo_keihi_sonota_curr'] ?? null) }}">
                  </td>
                </tr>
                <tr>
                  <th class="u-nowrap th-ccc">合計</th>
                  <td>
                    @php($name = 'jigyo_eigyo_keihi_gokei_prev')
                    <input type="number" min="0" step="1" class="form-control form-control-sm text-end bg-light" name="{{ $name }}" value="{{ old($name, $inputs[$name] ?? null) }}" readonly>
                  </td>
                  <td>
                    @php($name = 'jigyo_eigyo_keihi_gokei_curr')
                    <input type="number" min="0" step="1" class="form-control form-control-sm text-end bg-light" name="{{ $name }}" value="{{ old($name, $inputs[$name] ?? null) }}" readonly>
                  </td>
                </tr>
                <tr>
                  <th colspan="2" class="text-start align-middle">差引金額</th>
                  <td>
                    @php($name = 'jigyo_eigyo_sashihiki_2_prev')
                    <input type="number" min="0" step="1" class="form-control form-control-sm text-end bg-light" name="{{ $name }}" value="{{ old($name, $inputs[$name] ?? null) }}" readonly>
                  </td>
                  <td>
                    @php($name = 'jigyo_eigyo_sashihiki_2_curr')
                    <input type="number" min="0" step="1" class="form-control form-control-sm text-end bg-light" name="{{ $name }}" value="{{ old($name, $inputs[$name] ?? null) }}" readonly>
                  </td>
                </tr>
                <tr>
                  <th colspan="2" class="text-start align-middle">専従者給与</th>
                  <td>
                    <input type="number" min="0" step="1" class="form-control form-control-sm text-end" name="jigyo_eigyo_senjuusha_kyuyo_prev" value="{{ old('jigyo_eigyo_senjuusha_kyuyo_prev', $inputs['jigyo_eigyo_senjuusha_kyuyo_prev'] ?? null) }}">
                  </td>
                  <td>
                    <input type="number" min="0" step="1" class="form-control form-control-sm text-end" name="jigyo_eigyo_senjuusha_kyuyo_curr" value="{{ old('jigyo_eigyo_senjuusha_kyuyo_curr', $inputs['jigyo_eigyo_senjuusha_kyuyo_curr'] ?? null) }}">
                  </td>
                </tr>
                <tr>
                  <th colspan="2" class="text-start align-middle">青色申告特別控除前の所得金額</th>
                  <td>
                    @php($name = 'jigyo_eigyo_aoi_tokubetsu_kojo_mae_prev')
                    <input type="number" min="0" step="1" class="form-control form-control-sm text-end bg-light" name="{{ $name }}" value="{{ old($name, $inputs[$name] ?? null) }}" readonly>
                  </td>
                  <td>
                    @php($name = 'jigyo_eigyo_aoi_tokubetsu_kojo_mae_curr')
                    <input type="number" min="0" step="1" class="form-control form-control-sm text-end bg-light" name="{{ $name }}" value="{{ old($name, $inputs[$name] ?? null) }}" readonly>
                  </td>
                </tr>
                <tr>
                  <th colspan="2" class="text-start align-middle">青色申告特別控除額</th>
                  <td>
                    <input type="number" min="0" step="1" class="form-control form-control-sm text-end" name="jigyo_eigyo_aoi_tokubetsu_kojo_gaku_prev" value="{{ old('jigyo_eigyo_aoi_tokubetsu_kojo_gaku_prev', $inputs['jigyo_eigyo_aoi_tokubetsu_kojo_gaku_prev'] ?? null) }}">
                  </td>
                  <td>
                    <input type="number" min="0" step="1" class="form-control form-control-sm text-end" name="jigyo_eigyo_aoi_tokubetsu_kojo_gaku_curr" value="{{ old('jigyo_eigyo_aoi_tokubetsu_kojo_gaku_curr', $inputs['jigyo_eigyo_aoi_tokubetsu_kojo_gaku_curr'] ?? null) }}">
                  </td>
                </tr>
                <tr>
                  <th colspan="2" class="text-start align-middle">所得金額</th>
                  <td>
                    @php($name = 'jigyo_eigyo_shotoku_prev')
                    <input type="number" min="0" step="1" class="form-control form-control-sm text-end bg-light" name="{{ $name }}" value="{{ old($name, $inputs[$name] ?? null) }}" readonly>
                  </td>
                  <td>
                    @php($name = 'jigyo_eigyo_shotoku_curr')
                    <input type="number" min="0" step="1" class="form-control form-control-sm text-end bg-light" name="{{ $name }}" value="{{ old($name, $inputs[$name] ?? null) }}" readonly>
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