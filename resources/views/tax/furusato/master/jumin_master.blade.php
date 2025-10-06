@extends('layouts.min')
@section('content')
<div class="container" style="min-width: 960px; max-width: 1080px;">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h5 class="mb-0">ふるさと納税：住民税率マスター</h5>
    <a href="{{ route('furusato.master', ['data_id' => $dataId]) }}" class="btn btn-outline-secondary btn-sm">戻る</a>
  </div>
  <table class="table table-sm table-bordered align-middle">
    <thead class="table-light">
      <tr>
        <th class="text-center" colspan="2" rowspan="2">区分</th>
        <th class="text-center" colspan="2">指定都市</th>
        <th class="text-center" colspan="2">指定都市以外</th>
        <th class="text-center" rowspan="2">備考</th>
      </tr>
      <tr>
        <th class="text-center">市</th>
        <th class="text-center">県</th>
        <th class="text-center">市</th>
        <th class="text-center">県</th>
      </tr>
    </thead>
    <tbody>
      <tr>
        <th class="text-center" colspan="2">総合</th>
        <td class="text-end">8%</td>
        <td class="text-end">2%</td>
        <td class="text-end">6%</td>
        <td class="text-end">4%</td>
        <td></td>
      </tr>
      <tr>
        <th class="text-center" rowspan="2">短期譲渡</th>
        <th class="text-center">一般</th>
        <td class="text-end">7.2%</td>
        <td class="text-end">1.8%</td>
        <td class="text-end">5.4%</td>
        <td class="text-end">3.6%</td>
        <td></td>
      </tr>
      <tr>
        <th class="text-center">軽減</th>
        <td class="text-end">4%</td>
        <td class="text-end">1%</td>
        <td class="text-end">3%</td>
        <td class="text-end">2%</td>
        <td></td>
      </tr>
      <tr>
        <th class="text-center" rowspan="5">長期譲渡</th>
        <th class="text-center">一般</th>
        <td class="text-end">4%</td>
        <td class="text-end">1%</td>
        <td class="text-end">3%</td>
        <td class="text-end">2%</td>
        <td></td>
      </tr>
      <tr>
        <th class="text-center" rowspan="2">特定</th>
        <td class="text-end">3.2%</td>
        <td class="text-end">0.8%</td>
        <td class="text-end">2.4%</td>
        <td class="text-end">1.6%</td>
        <td>2,000万円以下の部分</td>
      </tr>
      <tr>
        <td class="text-end">4%</td>
        <td class="text-end">1%</td>
        <td class="text-end">3%</td>
        <td class="text-end">2%</td>
        <td>2,000万円超の部分</td>
      </tr>
      <tr>
        <th class="text-center" rowspan="2">軽課</th>
        <td class="text-end">3.2%</td>
        <td class="text-end">0.8%</td>
        <td class="text-end">2.4%</td>
        <td class="text-end">1.6%</td>
        <td>6,000万円以下の部分</td>
      </tr>
      <tr>
        <td class="text-end">4%</td>
        <td class="text-end">1%</td>
        <td class="text-end">3%</td>
        <td class="text-end">2%</td>
        <td>6,000万円超の部分</td>
      </tr>
      <tr>
        <th class="text-center" colspan="2">一般株式等の譲渡</th>
        <td class="text-end">4%</td>
        <td class="text-end">1%</td>
        <td class="text-end">3%</td>
        <td class="text-end">2%</td>
        <td></td>
      </tr>
      <tr>
        <th class="text-center" colspan="2">上場株式等の譲渡</th>
        <td class="text-end">4%</td>
        <td class="text-end">1%</td>
        <td class="text-end">3%</td>
        <td class="text-end">2%</td>
        <td></td>
      </tr>
      <tr>
        <th class="text-center" colspan="2">上場株式等の配当等</th>
        <td class="text-end">4%</td>
        <td class="text-end">1%</td>
        <td class="text-end">3%</td>
        <td class="text-end">2%</td>
        <td></td>
      </tr>
      <tr>
        <th class="text-center" colspan="2">先物取引</th>
        <td class="text-end">4%</td>
        <td class="text-end">1%</td>
        <td class="text-end">3%</td>
        <td class="text-end">2%</td>
        <td></td>
      </tr>
      <tr>
        <th class="text-center" colspan="2">山林</th>
        <td class="text-end">8%</td>
        <td class="text-end">2%</td>
        <td class="text-end">6%</td>
        <td class="text-end">4%</td>
        <td></td>
      </tr>
      <tr>
        <th class="text-center" colspan="2">退職</th>
        <td class="text-end">8%</td>
        <td class="text-end">2%</td>
        <td class="text-end">6%</td>
        <td class="text-end">4%</td>
        <td></td>
      </tr>
      <tr>
        <th class="text-center" colspan="2">調整控除</th>
        <td class="text-end">4%</td>
        <td class="text-end">1%</td>
        <td class="text-end">3%</td>
        <td class="text-end">2%</td>
        <td></td>
      </tr>
      <tr>
        <th class="text-center" colspan="2">基本控除</th>
        <td class="text-end">8%</td>
        <td class="text-end">2%</td>
        <td class="text-end">6%</td>
        <td class="text-end">4%</td>
        <td></td>
      </tr>
      <tr>
        <th class="text-center" colspan="2">特例控除</th>
        <td class="text-end">0.8%</td>
        <td class="text-end">0.2%</td>
        <td class="text-end">0.6%</td>
        <td class="text-end">0.4%</td>
        <td></td>
      </tr>
    </tbody>
  </table>
</div>
@endsection