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
      @php
        $formatPercent = static fn (?float $value): string => $value === null ? '' : rtrim(rtrim(number_format($value, 3), '0'), '.').'%';
        $categoryCounts = $rates->groupBy('category')->map->count();
        $subcategoryCounts = $rates->groupBy(fn ($rate) => $rate->category.'|'.($rate->sub_category ?? ''))->map->count();
        $renderedCategories = [];
        $renderedSubCategories = [];
      @endphp
      @foreach ($rates as $rate)
        <tr>
          @php
            $categoryKey = $rate->category;
            $subKey = $rate->category.'|'.($rate->sub_category ?? '');
          @endphp
          @if(($categoryCounts[$categoryKey] ?? 0) > 1)
            @if(empty($renderedCategories[$categoryKey]))
              <th class="text-center" rowspan="{{ $categoryCounts[$categoryKey] }}">{{ $rate->category }}</th>
              @php $renderedCategories[$categoryKey] = true; @endphp
            @endif
            @if($rate->sub_category !== null && $rate->sub_category !== '')
              @if(($subcategoryCounts[$subKey] ?? 0) > 1)
                @if(empty($renderedSubCategories[$subKey]))
                  <th class="text-center" rowspan="{{ $subcategoryCounts[$subKey] }}">{{ $rate->sub_category }}</th>
                  @php $renderedSubCategories[$subKey] = true; @endphp
                @endif
              @else
                <th class="text-center">{{ $rate->sub_category }}</th>
              @endif
            @else
              @if(($subcategoryCounts[$subKey] ?? 0) > 1)
                @if(empty($renderedSubCategories[$subKey]))
                  <th class="text-center" rowspan="{{ $subcategoryCounts[$subKey] }}"></th>
                  @php $renderedSubCategories[$subKey] = true; @endphp
                @endif
              @else
                <th class="text-center"></th>
              @endif
            @endif
          @else
            <th class="text-center" colspan="2">{{ $rate->category }}</th>
          @endif
          <td class="text-end">{{ $formatPercent($rate->city_specified) }}</td>
          <td class="text-end">{{ $formatPercent($rate->pref_specified) }}</td>
          <td class="text-end">{{ $formatPercent($rate->city_non_specified) }}</td>
          <td class="text-end">{{ $formatPercent($rate->pref_non_specified) }}</td>
          <td>{{ $rate->remark }}</td>
        </tr>
      @endforeach
    </tbody>
  </table>
</div>
@endsection