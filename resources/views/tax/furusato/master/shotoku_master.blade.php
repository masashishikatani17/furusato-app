@extends('layouts.min')
@section('content')
<div class="container" style="min-width: 960px; max-width: 1080px;">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h5 class="mb-0">ふるさと納税：所得税率マスター</h5>
    <a href="{{ route('furusato.master', ['data_id' => $dataId]) }}" class="btn btn-outline-secondary btn-sm">戻る</a>
  </div>

  <table class="table table-sm table-bordered align-middle">
    <tbody>
      @php
        $formatAmount = static fn (?int $value): string => $value === null ? '' : number_format($value);
        $formatPercent = static fn (?float $value): string => $value === null ? '' : rtrim(rtrim(number_format($value, 3), '0'), '.').'%';
      @endphp
      @foreach ($rates as $rate)
        <tr>
          <td class="text-end">{{ $formatAmount($rate->lower) }}</td>
          <td class="text-center">～</td>
          <td class="text-end">{{ $formatAmount($rate->upper) }}</td>
          <td class="text-end">{{ $formatPercent($rate->rate) }}</td>
          <td class="text-end">{{ number_format((int) $rate->deduction_amount) }}</td>
        </tr>
      @endforeach
    </tbody>
  </table>
</div>
@endsection