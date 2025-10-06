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
        <th class="text-center" colspan="3">課税所得金額から人的控除差調整額を控除した金額</th>
        <th class="text-center">申告特例控除の割合</th>
      </tr>
    </thead>
    <tbody>
      @php
        $formatAmount = static fn (?int $value): string => $value === null ? '' : number_format($value);
        $formatRatio = static fn (?float $value): string => $value === null ? '' : rtrim(rtrim(number_format($value, 3), '0'), '.');
      @endphp
      @foreach ($rates as $rate)
        <tr>
          <td class="text-end">{{ $formatAmount($rate->lower) }}</td>
          <td class="text-center">～</td>
          <td class="text-end">{{ $formatAmount($rate->upper) }}</td>
          <td class="text-end">{{ $formatRatio($rate->ratio_a) }}/{{ $formatRatio($rate->ratio_b) }}</td>
        </tr>
      @endforeach
    </tbody>
  </table>
</div>
@endsection