@extends('layouts.min')
@section('content')
<div class="container-grey mt-2" style="width: 550px;">
  <div class="card-header d-flex align-items-start">
    <img src="{{ asset('storage/images/kado_lefttop_m.jpg') }}" alt="…">
    <hb class="mb-0 mt-2">住民税率マスター</hb>
  </div>
  <div class="card-body">
    <div class="wrapper">
      <table class="table-base table-bordered align-middle">
        <thead>
          <tr>
            <th class="text-center" colspan="2" rowspan="2">区 分</th>
            <th class="text-center" colspan="2">指定都市</th>
            <th class="text-center" colspan="2">指定都市以外</th>
            <th class="text-center" rowspan="2">備 考</th>
          </tr>
          <tr>
            <th class="text-center th-ddd">市</th>
            <th class="text-center th-ddd">県</th>
            <th class="text-center th-ddd">市</th>
            <th class="text-center th-ddd">県</th>
          </tr>
        </thead>
        <tbody>
          @php
            $formatRatio = static fn (?float $v) => $v === null ? '' : rtrim(rtrim(number_format($v, 3), '0'), '.');
            $categoryCounts     = $rates->groupBy('category')->map->count();
            $subcategoryCounts  = $rates->groupBy(fn ($r) => $r->category.'|'.($r->sub_category ?? ''))->map->count();
            $doneCat = []; $doneSub = [];
          @endphp
          @foreach ($rates as $r)
            <tr>
              @php $cat=$r->category; $sub=$r->sub_category; $subKey=$cat.'|'.($sub??''); @endphp
              @if(($categoryCounts[$cat] ?? 0) > 1)
                @if(empty($doneCat[$cat]))
                  <th class="text-center" rowspan="{{ $categoryCounts[$cat] }}">{{ $cat }}</th>
                  @php $doneCat[$cat]=true; @endphp
                @endif
                @if($sub)
                  @if(($subcategoryCounts[$subKey] ?? 0) > 1)
                    @if(empty($doneSub[$subKey]))
                      <th class="text-center th-ddd" rowspan="{{ $subcategoryCounts[$subKey] }}">{{ $sub }}</th>
                      @php $doneSub[$subKey]=true; @endphp
                    @endif
                  @else
                    <th class="text-center th-ddd">{{ $sub }}</th>
                  @endif
                @else
                  @if(($subcategoryCounts[$subKey] ?? 0) > 1)
                    @if(empty($doneSub[$subKey]))
                      <th class="text-start" rowspan="{{ $subcategoryCounts[$subKey] }}"></th>
                      @php $doneSub[$subKey]=true; @endphp
                    @endif
                  @else
                    <th class="text-start"></th>
                  @endif
                @endif
              @else
                <th class="text-start" colspan="2">{{ $cat }}</th>
              @endif
              <td class="text-end">{{ $formatRatio($r->city_specified) }}</td>
              <td class="text-end">{{ $formatRatio($r->pref_specified) }}</td>
              <td class="text-end">{{ $formatRatio($r->city_non_specified) }}</td>
              <td class="text-end">{{ $formatRatio($r->pref_non_specified) }}</td>
              <td class="text-start">{{ $r->remark }}</td>
            </tr>
          @endforeach
        </tbody>
      </table>
      <hr>
      <div class="text-end me-2 mb-2">
        <a href="{{ route('furusato.master', ['data_id' => $dataId]) }}" class="btn-base-blue">戻 る</a>
      </div>
    </div>
  </div>
</div>
@endsection