@extends('layouts.min')
@section('content')
<div class="container" style="min-width: 960px; max-width: 1080px;">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h5 class="mb-0">ふるさと納税：特例控除マスター</h5>
    <a href="{{ route('furusato.master', ['data_id' => $dataId]) }}" class="btn btn-outline-secondary btn-sm">戻る</a>
  </div>
  <table class="table table-sm table-bordered align-middle">
    <thead class="table-light">
      <tr>
        <th class="text-center" colspan="3">課税所得金額から人的控除差調整額を控除した金額</th>
        <th class="text-center">申告特例控除の割合</th>
      </tr>
    </thead>
    <tbody>
      <tr>
        <td class="text-end">0</td>
        <td class="text-center">～</td>
        <td class="text-end">1,950,000</td>
        <td class="text-end">5.105/84.895</td>
      </tr>
      <tr>
        <td class="text-end">1,951,000</td>
        <td class="text-center">～</td>
        <td class="text-end">3,300,000</td>
        <td class="text-end">10.21/79.79</td>
      </tr>
      <tr>
        <td class="text-end">3,301,000</td>
        <td class="text-center">～</td>
        <td class="text-end">6,950,000</td>
        <td class="text-end">20.42/69.58</td>
      </tr>
      <tr>
        <td class="text-end">6,951,000</td>
        <td class="text-center">～</td>
        <td class="text-end">9,000,000</td>
        <td class="text-end">23.483/66.517</td>
      </tr>
      <tr>
        <td class="text-end">9,001,000</td>
        <td class="text-center">～</td>
        <td></td>
        <td class="text-end">33.693/56.307</td>
      </tr>
    </tbody>
  </table>
</div>
@endsection