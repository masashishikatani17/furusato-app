@extends('layouts.min')
@section('content')
<div class="container-grey mt-2" style="width: 450px;">
  <div class="card-header d-flex align-items-start">
    <img src="{{ asset('storage/images/kado_lefttop_m.jpg') }}" alt="…">
    <hb class="mb-0 mt-2">所得税率マスター</hb>
  </div>
  <div class="card-body">
    <div class="wrapper">
      <table class="table-base table-bordered align-middle">
        <tbody>
          @php
            $formatAmount = static fn ($value): string => $value === null ? '' : number_format((int) $value);
            $formatPercent = static fn ($value): string => $value === null ? '' : rtrim(rtrim(number_format((float) $value, 3), '0'), '.').'%';
          @endphp
          @foreach ($rates as $rate)
            <tr>
              <td class="text-end b-r-no" style="width:80px;">{{ $formatAmount($rate->lower ?? null) }}</td>
              <td class="text-center b-l-no b-r-no" style="width:20px;">～</td>
              <td class="text-end b-l-no" style="width:80px;">{{ $formatAmount($rate->upper ?? null) }}</td>
              <td class="text-end" style="width:50px;">{{ $formatPercent($rate->rate ?? null) }}</td>
              <td class="text-end" style="width:80px;">{{ number_format((int) ($rate->deduction_amount ?? 0)) }}</td>
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