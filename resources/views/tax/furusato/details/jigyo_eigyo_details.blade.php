@extends('layouts.min')

@section('title', '事業・営業等（内訳）')

@section('content')
@php($inputs = $out['inputs'] ?? [])
<div class="container" style="min-width: 720px; max-width: 960px;">
  <form method="POST" action="{{ route('furusato.details.jigyo.save') }}">
    @csrf
    <input type="hidden" name="data_id" value="{{ $dataId }}">

    <div class="d-flex justify-content-between align-items-center mb-3">
      <h5 class="mb-0">事業・営業等（内訳）</h5>
      <button type="submit" class="btn btn-outline-secondary btn-sm">戻る</button>
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

    <div class="table-responsive">
      <table class="table table-bordered table-sm align-middle mb-0">
        <thead class="table-light text-center align-middle">
          <tr>
            <th colspan="2">項目</th>
            <th>{{ $warekiPrev ?? '前年' }}</th>
            <th>{{ $warekiCurr ?? '当年' }}</th>
          </tr>
        </thead>
        <tbody>
          <tr>
            <th colspan="2" class="align-middle">売上(収入)金額</th>
            <td>
              <input type="number" min="0" step="1" class="form-control form-control-sm text-end" name="jigyo_eigyo_uriage_prev" value="{{ old('jigyo_eigyo_uriage_prev', $inputs['jigyo_eigyo_uriage_prev'] ?? null) }}">
            </td>
            <td>
              <input type="number" min="0" step="1" class="form-control form-control-sm text-end" name="jigyo_eigyo_uriage_curr" value="{{ old('jigyo_eigyo_uriage_curr', $inputs['jigyo_eigyo_uriage_curr'] ?? null) }}">
            </td>
          </tr>
          <tr>
            <th colspan="2" class="align-middle">売上原価</th>
            <td>
              <input type="number" min="0" step="1" class="form-control form-control-sm text-end" name="jigyo_eigyo_urigenka_prev" value="{{ old('jigyo_eigyo_urigenka_prev', $inputs['jigyo_eigyo_urigenka_prev'] ?? null) }}">
            </td>
            <td>
              <input type="number" min="0" step="1" class="form-control form-control-sm text-end" name="jigyo_eigyo_urigenka_curr" value="{{ old('jigyo_eigyo_urigenka_curr', $inputs['jigyo_eigyo_urigenka_curr'] ?? null) }}">
            </td>
          </tr>
          <tr>
            <th colspan="2" class="align-middle">差引金額</th>
            <td>
              <input type="number" min="0" step="1" class="form-control form-control-sm text-end" name="jigyo_eigyo_sashihiki_1_prev" value="{{ old('jigyo_eigyo_sashihiki_1_prev', $inputs['jigyo_eigyo_sashihiki_1_prev'] ?? null) }}">
            </td>
            <td>
              <input type="number" min="0" step="1" class="form-control form-control-sm text-end" name="jigyo_eigyo_sashihiki_1_curr" value="{{ old('jigyo_eigyo_sashihiki_1_curr', $inputs['jigyo_eigyo_sashihiki_1_curr'] ?? null) }}">
            </td>
          </tr>
          <tr>
            <th scope="rowgroup" rowspan="9" class="text-center align-middle bg-light">経費</th>
            <td></td>
            <td>
              <input type="number" min="0" step="1" class="form-control form-control-sm text-end" name="jigyo_eigyo_keihi_1_prev" value="{{ old('jigyo_eigyo_keihi_1_prev', $inputs['jigyo_eigyo_keihi_1_prev'] ?? null) }}">
            </td>
            <td>
              <input type="number" min="0" step="1" class="form-control form-control-sm text-end" name="jigyo_eigyo_keihi_1_curr" value="{{ old('jigyo_eigyo_keihi_1_curr', $inputs['jigyo_eigyo_keihi_1_curr'] ?? null) }}">
            </td>
          </tr>
          <tr>
            <td></td>
            <td>
              <input type="number" min="0" step="1" class="form-control form-control-sm text-end" name="jigyo_eigyo_keihi_2_prev" value="{{ old('jigyo_eigyo_keihi_2_prev', $inputs['jigyo_eigyo_keihi_2_prev'] ?? null) }}">
            </td>
            <td>
              <input type="number" min="0" step="1" class="form-control form-control-sm text-end" name="jigyo_eigyo_keihi_2_curr" value="{{ old('jigyo_eigyo_keihi_2_curr', $inputs['jigyo_eigyo_keihi_2_curr'] ?? null) }}">
            </td>
          </tr>
          <tr>
            <td></td>
            <td>
              <input type="number" min="0" step="1" class="form-control form-control-sm text-end" name="jigyo_eigyo_keihi_3_prev" value="{{ old('jigyo_eigyo_keihi_3_prev', $inputs['jigyo_eigyo_keihi_3_prev'] ?? null) }}">
            </td>
            <td>
              <input type="number" min="0" step="1" class="form-control form-control-sm text-end" name="jigyo_eigyo_keihi_3_curr" value="{{ old('jigyo_eigyo_keihi_3_curr', $inputs['jigyo_eigyo_keihi_3_curr'] ?? null) }}">
            </td>
          </tr>
          <tr>
            <td></td>
            <td>
              <input type="number" min="0" step="1" class="form-control form-control-sm text-end" name="jigyo_eigyo_keihi_4_prev" value="{{ old('jigyo_eigyo_keihi_4_prev', $inputs['jigyo_eigyo_keihi_4_prev'] ?? null) }}">
            </td>
            <td>
              <input type="number" min="0" step="1" class="form-control form-control-sm text-end" name="jigyo_eigyo_keihi_4_curr" value="{{ old('jigyo_eigyo_keihi_4_curr', $inputs['jigyo_eigyo_keihi_4_curr'] ?? null) }}">
            </td>
          </tr>
          <tr>
            <td></td>
            <td>
              <input type="number" min="0" step="1" class="form-control form-control-sm text-end" name="jigyo_eigyo_keihi_5_prev" value="{{ old('jigyo_eigyo_keihi_5_prev', $inputs['jigyo_eigyo_keihi_5_prev'] ?? null) }}">
            </td>
            <td>
              <input type="number" min="0" step="1" class="form-control form-control-sm text-end" name="jigyo_eigyo_keihi_5_curr" value="{{ old('jigyo_eigyo_keihi_5_curr', $inputs['jigyo_eigyo_keihi_5_curr'] ?? null) }}">
            </td>
          </tr>
          <tr>
            <td></td>
            <td>
              <input type="number" min="0" step="1" class="form-control form-control-sm text-end" name="jigyo_eigyo_keihi_6_prev" value="{{ old('jigyo_eigyo_keihi_6_prev', $inputs['jigyo_eigyo_keihi_6_prev'] ?? null) }}">
            </td>
            <td>
              <input type="number" min="0" step="1" class="form-control form-control-sm text-end" name="jigyo_eigyo_keihi_6_curr" value="{{ old('jigyo_eigyo_keihi_6_curr', $inputs['jigyo_eigyo_keihi_6_curr'] ?? null) }}">
            </td>
          </tr>
          <tr>
            <td></td>
            <td>
              <input type="number" min="0" step="1" class="form-control form-control-sm text-end" name="jigyo_eigyo_keihi_7_prev" value="{{ old('jigyo_eigyo_keihi_7_prev', $inputs['jigyo_eigyo_keihi_7_prev'] ?? null) }}">
            </td>
            <td>
              <input type="number" min="0" step="1" class="form-control form-control-sm text-end" name="jigyo_eigyo_keihi_7_curr" value="{{ old('jigyo_eigyo_keihi_7_curr', $inputs['jigyo_eigyo_keihi_7_curr'] ?? null) }}">
            </td>
          </tr>
          <tr>
            <td class="align-middle">その他</td>
            <td>
              <input type="number" min="0" step="1" class="form-control form-control-sm text-end" name="jigyo_eigyo_keihi_sonota_prev" value="{{ old('jigyo_eigyo_keihi_sonota_prev', $inputs['jigyo_eigyo_keihi_sonota_prev'] ?? null) }}">
            </td>
            <td>
              <input type="number" min="0" step="1" class="form-control form-control-sm text-end" name="jigyo_eigyo_keihi_sonota_curr" value="{{ old('jigyo_eigyo_keihi_sonota_curr', $inputs['jigyo_eigyo_keihi_sonota_curr'] ?? null) }}">
            </td>
          </tr>
          <tr>
            <td class="align-middle">合計</td>
            <td>
              <input type="number" min="0" step="1" class="form-control form-control-sm text-end" name="jigyo_eigyo_keihi_gokei_prev" value="{{ old('jigyo_eigyo_keihi_gokei_prev', $inputs['jigyo_eigyo_keihi_gokei_prev'] ?? null) }}">
            </td>
            <td>
              <input type="number" min="0" step="1" class="form-control form-control-sm text-end" name="jigyo_eigyo_keihi_gokei_curr" value="{{ old('jigyo_eigyo_keihi_gokei_curr', $inputs['jigyo_eigyo_keihi_gokei_curr'] ?? null) }}">
            </td>
          </tr>
          <tr>
            <th colspan="2" class="align-middle">差引金額</th>
            <td>
              <input type="number" min="0" step="1" class="form-control form-control-sm text-end" name="jigyo_eigyo_sashihiki_2_prev" value="{{ old('jigyo_eigyo_sashihiki_2_prev', $inputs['jigyo_eigyo_sashihiki_2_prev'] ?? null) }}">
            </td>
            <td>
              <input type="number" min="0" step="1" class="form-control form-control-sm text-end" name="jigyo_eigyo_sashihiki_2_curr" value="{{ old('jigyo_eigyo_sashihiki_2_curr', $inputs['jigyo_eigyo_sashihiki_2_curr'] ?? null) }}">
            </td>
          </tr>
          <tr>
            <th colspan="2" class="align-middle">専従者給与</th>
            <td>
              <input type="number" min="0" step="1" class="form-control form-control-sm text-end" name="jigyo_eigyo_senjuusha_kyuyo_prev" value="{{ old('jigyo_eigyo_senjuusha_kyuyo_prev', $inputs['jigyo_eigyo_senjuusha_kyuyo_prev'] ?? null) }}">
            </td>
            <td>
              <input type="number" min="0" step="1" class="form-control form-control-sm text-end" name="jigyo_eigyo_senjuusha_kyuyo_curr" value="{{ old('jigyo_eigyo_senjuusha_kyuyo_curr', $inputs['jigyo_eigyo_senjuusha_kyuyo_curr'] ?? null) }}">
            </td>
          </tr>
          <tr>
            <th colspan="2" class="align-middle">青色申告特別控除前の所得金額</th>
            <td>
              <input type="number" min="0" step="1" class="form-control form-control-sm text-end" name="jigyo_eigyo_aoi_tokubetsu_kojo_mae_prev" value="{{ old('jigyo_eigyo_aoi_tokubetsu_kojo_mae_prev', $inputs['jigyo_eigyo_aoi_tokubetsu_kojo_mae_prev'] ?? null) }}">
            </td>
            <td>
              <input type="number" min="0" step="1" class="form-control form-control-sm text-end" name="jigyo_eigyo_aoi_tokubetsu_kojo_mae_curr" value="{{ old('jigyo_eigyo_aoi_tokubetsu_kojo_mae_curr', $inputs['jigyo_eigyo_aoi_tokubetsu_kojo_mae_curr'] ?? null) }}">
            </td>
          </tr>
          <tr>
            <th colspan="2" class="align-middle">青色申告特別控除額</th>
            <td>
              <input type="number" min="0" step="1" class="form-control form-control-sm text-end" name="jigyo_eigyo_aoi_tokubetsu_kojo_gaku_prev" value="{{ old('jigyo_eigyo_aoi_tokubetsu_kojo_gaku_prev', $inputs['jigyo_eigyo_aoi_tokubetsu_kojo_gaku_prev'] ?? null) }}">
            </td>
            <td>
              <input type="number" min="0" step="1" class="form-control form-control-sm text-end" name="jigyo_eigyo_aoi_tokubetsu_kojo_gaku_curr" value="{{ old('jigyo_eigyo_aoi_tokubetsu_kojo_gaku_curr', $inputs['jigyo_eigyo_aoi_tokubetsu_kojo_gaku_curr'] ?? null) }}">
            </td>
          </tr>
          <tr>
            <th colspan="2" class="align-middle">所得金額</th>
            <td>
              <input type="number" min="0" step="1" class="form-control form-control-sm text-end" name="jigyo_eigyo_shotoku_prev" value="{{ old('jigyo_eigyo_shotoku_prev', $inputs['jigyo_eigyo_shotoku_prev'] ?? null) }}">
            </td>
            <td>
              <input type="number" min="0" step="1" class="form-control form-control-sm text-end" name="jigyo_eigyo_shotoku_curr" value="{{ old('jigyo_eigyo_shotoku_curr', $inputs['jigyo_eigyo_shotoku_curr'] ?? null) }}">
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </form>
</div>
@endsection