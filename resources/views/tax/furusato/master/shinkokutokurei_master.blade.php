@extends('layouts.min')
@section('content')
<div class="container" style="min-width: 960px; max-width: 1080px;">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h5 class="mb-0">ふるさと納税：申告特例控除マスター</h5>
    <a href="{{ route('furusato.master', ['data_id' => $dataId]) }}" class="btn btn-outline-secondary btn-sm">戻る</a>
  </div>
  <table class="table table-sm table-bordered align-middle">
    <thead class="table-light">
      <tr>
        <th class="text-center" colspan="3">課税総所得金額から人的控除差調整額を控除した金額</th>
        <th class="text-center">所得税率</th>
        <th class="text-center">90%-所得税率</th>
        <th class="text-center">復興特別所得税率を加味した所得税率</th>
        <th class="text-center">特例控除の控除率</th>
        <th class="text-center">摘要</th>
      </tr>
    </thead>
    <tbody>
      <tr>
        <td class="text-end">0</td>
        <td class="text-center">～</td>
        <td class="text-end">1,950,000</td>
        <td class="text-end">5%</td>
        <td class="text-end">85%</td>
        <td class="text-end">5.105%</td>
        <td class="text-end">84.895%</td>
        <td></td>
      </tr>
      <tr>
        <td class="text-end">1,951,000</td>
        <td class="text-center">～</td>
        <td class="text-end">3,300,000</td>
        <td class="text-end">10%</td>
        <td class="text-end">80%</td>
        <td class="text-end">10.21%</td>
        <td class="text-end">79.79%</td>
        <td></td>
      </tr>
      <tr>
        <td class="text-end">3,301,000</td>
        <td class="text-center">～</td>
        <td class="text-end">6,950,000</td>
        <td class="text-end">20%</td>
        <td class="text-end">70%</td>
        <td class="text-end">20.42%</td>
        <td class="text-end">69.58%</td>
        <td></td>
      </tr>
      <tr>
        <td class="text-end">6,951,000</td>
        <td class="text-center">～</td>
        <td class="text-end">9,000,000</td>
        <td class="text-end">23%</td>
        <td class="text-end">67%</td>
        <td class="text-end">23.483%</td>
        <td class="text-end">66.517%</td>
        <td></td>
      </tr>
      <tr>
        <td class="text-end">9,001,000</td>
        <td class="text-center">～</td>
        <td class="text-end">18,000,000</td>
        <td class="text-end">33%</td>
        <td class="text-end">57%</td>
        <td class="text-end">33.693%</td>
        <td class="text-end">56.307%</td>
        <td></td>
      </tr>
      <tr>
        <td class="text-end">18,001,000</td>
        <td class="text-center">～</td>
        <td class="text-end">40,000,000</td>
        <td class="text-end">40%</td>
        <td class="text-end">50%</td>
        <td class="text-end">40.84%</td>
        <td class="text-end">49.16%</td>
        <td></td>
      </tr>
      <tr>
        <td class="text-end">40,001,000</td>
        <td class="text-center">～</td>
        <td></td>
        <td class="text-end">45%</td>
        <td class="text-end">45%</td>
        <td class="text-end">45.945%</td>
        <td class="text-end">44.055%</td>
        <td></td>
      </tr>
      <tr>
        <th class="text-center" colspan="3">課税総所得金額-人的控除差調整額が0未満かつ山林所得及び退職所得が0</th>
        <td class="text-end">0%</td>
        <td class="text-end">90%</td>
        <td class="text-end">0%</td>
        <td class="text-end">90%</td>
        <td>所得税額0</td>
      </tr>
      <tr>
        <th class="text-center" colspan="3">短期譲渡所得を有する</th>
        <td class="text-end">30%</td>
        <td class="text-end">60%</td>
        <td class="text-end">30.63%</td>
        <td class="text-end">59.37%</td>
        <td></td>
      </tr>
      <tr>
        <th class="text-center" colspan="3">長期譲渡所得、株式配当等、株式譲渡等、先物取引を有する</th>
        <td class="text-end">15%</td>
        <td class="text-end">75%</td>
        <td class="text-end">15.315%</td>
        <td class="text-end">74.685%</td>
        <td></td>
      </tr>
    </tbody>
  </table>
</div>
@endsection