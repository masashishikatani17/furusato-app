@extends('layouts.min')
@section('content')
<div class="container" style="min-width: 960px; max-width: 1080px;">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h5 class="mb-0">ふるさと納税：所得税率マスター</h5>
    <a href="{{ route('furusato.master', ['data_id' => $dataId]) }}" class="btn btn-outline-secondary btn-sm">戻る</a>
  </div>

  <table class="table table-sm table-bordered align-middle">
    <tbody>
      <tr>
        <th colspan="3">課税される所得金額</th>
        <th class="text-end">税率</th>
        <th class="text-end">控除額</th>
      </tr>
      <tr>
        <td class="text-end">0</td>
        <td class="text-center">～</td>
        <td class="text-end">1,949,000</td>
        <td class="text-end">5%</td>
        <td class="text-end">0</td>
      </tr>
      <tr>
        <td class="text-end">1,950,000</td>
        <td class="text-center">～</td>
        <td class="text-end">3,299,000</td>
        <td class="text-end">10%</td>
        <td class="text-end">97,500</td>
      </tr>
      <tr>
        <td class="text-end">3,300,000</td>
        <td class="text-center">～</td>
        <td class="text-end">6,949,000</td>
        <td class="text-end">20%</td>
        <td class="text-end">427,500</td>
      </tr>
      <tr>
        <td class="text-end">6,950,000</td>
        <td class="text-center">～</td>
        <td class="text-end">8,999,000</td>
        <td class="text-end">23%</td>
        <td class="text-end">636,000</td>
      </tr>
      <tr>
        <td class="text-end">9,000,000</td>
        <td class="text-center">～</td>
        <td class="text-end">17,999,000</td>
        <td class="text-end">33%</td>
        <td class="text-end">1,536,000</td>
      </tr>
      <tr>
        <td class="text-end">18,000,000</td>
        <td class="text-center">～</td>
        <td class="text-end">39,999,000</td>
        <td class="text-end">40%</td>
        <td class="text-end">2,796,000</td>
      </tr>
      <tr>
        <td class="text-end">40,000,000</td>
        <td class="text-center">～</td>
        <td></td>
        <td class="text-end">45%</td>
        <td class="text-end">4,796,000</td>
      </tr>
    </tbody>
  </table>
</div>
@endsection