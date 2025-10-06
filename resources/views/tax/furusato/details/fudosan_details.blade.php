@extends('layouts.min')

@section('title', '不動産（内訳）')

@section('content')
@php($inputs = $out['inputs'] ?? [])
<div class="container" style="min-width: 720px; max-width: 960px;">
  <form method="POST" action="{{ route('furusato.details.fudosan.save') }}">
    @csrf
    <input type="hidden" name="data_id" value="{{ $dataId }}">

    <div class="d-flex justify-content-between align-items-center mb-3">
      <h5 class="mb-0">不動産（内訳）</h5>
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
      <table class="table table-bordered table-sm align-middle text-center mb-0">
        <tbody>
          <tr class="table-light">
            <th class="align-middle" colspan="2">項目</th>
            <th class="align-middle">{{ $warekiPrev ?? '前年' }}</th>
            <th class="align-middle">{{ $warekiCurr ?? '当年' }}</th>
          </tr>
          <tr>
            <th class="align-middle" colspan="2">収入金額</th>
            <td>
              @php($name = 'fudosan_shunyu_prev')
              <input type="number" min="0" step="1" class="form-control form-control-sm text-end" value="{{ old($name, $inputs[$name] ?? null) }}" name="{{ $name }}">
            </td>
            <td>
              @php($name = 'fudosan_shunyu_curr')
              <input type="number" min="0" step="1" class="form-control form-control-sm text-end" value="{{ old($name, $inputs[$name] ?? null) }}" name="{{ $name }}">
            </td>
          </tr>
          @php($expenseFields = [
            ['label' => '', 'name' => 'fudosan_keihi_1'],
            ['label' => '', 'name' => 'fudosan_keihi_2'],
            ['label' => '', 'name' => 'fudosan_keihi_3'],
            ['label' => '', 'name' => 'fudosan_keihi_4'],
            ['label' => '', 'name' => 'fudosan_keihi_5'],
            ['label' => '', 'name' => 'fudosan_keihi_6'],
            ['label' => '', 'name' => 'fudosan_keihi_7'],
            ['label' => 'その他', 'name' => 'fudosan_keihi_sonota'],
            ['label' => '合計', 'name' => 'fudosan_keihi_gokei'],
          ])
          <tr>
            <th class="align-middle" rowspan="9">必要経費</th>
            @php($field = array_shift($expenseFields))
            <td class="align-middle">{{ $field['label'] }}</td>
            <td>
              @php($name = $field['name'] . '_prev')
              <input type="number" min="0" step="1" class="form-control form-control-sm text-end" value="{{ old($name, $inputs[$name] ?? null) }}" name="{{ $name }}">
            </td>
            <td>
              @php($name = $field['name'] . '_curr')
              <input type="number" min="0" step="1" class="form-control form-control-sm text-end" value="{{ old($name, $inputs[$name] ?? null) }}" name="{{ $name }}">
            </td>
          </tr>
          @foreach ($expenseFields as $field)
            <tr>
              <td class="align-middle">{{ $field['label'] }}</td>
              <td>
                @php($name = $field['name'] . '_prev')
                <input type="number" min="0" step="1" class="form-control form-control-sm text-end" value="{{ old($name, $inputs[$name] ?? null) }}" name="{{ $name }}">
              </td>
              <td>
                @php($name = $field['name'] . '_curr')
                <input type="number" min="0" step="1" class="form-control form-control-sm text-end" value="{{ old($name, $inputs[$name] ?? null) }}" name="{{ $name }}">
              </td>
            </tr>
          @endforeach
          @php($footerFields = [
            'fudosan_sashihiki' => '差引金額',
            'fudosan_senjuusha_kyuyo' => '専従者給与',
            'fudosan_aoi_tokubetsu_kojo_mae' => '青色申告特別控除前の所得金額',
            'fudosan_aoi_tokubetsu_kojo_gaku' => '青色申告特別控除額',
            'fudosan_shotoku' => '所得金額',
            'fudosan_fusairishi' => '土地等を取得するための負債利子',
          ])
          @foreach ($footerFields as $namePrefix => $label)
            <tr>
              <th class="align-middle" colspan="2">{{ $label }}</th>
              <td>
                @php($name = $namePrefix . '_prev')
                <input type="number" min="0" step="1" class="form-control form-control-sm text-end" value="{{ old($name, $inputs[$name] ?? null) }}" name="{{ $name }}">
              </td>
              <td>
                @php($name = $namePrefix . '_curr')
                <input type="number" min="0" step="1" class="form-control form-control-sm text-end" value="{{ old($name, $inputs[$name] ?? null) }}" name="{{ $name }}">
              </td>
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>
  </form>
</div>
@endsection