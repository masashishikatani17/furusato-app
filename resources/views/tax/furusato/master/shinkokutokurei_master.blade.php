<!-- views/tax/furusato/master/shinkokutokurei_master.blade.php -->
@extends('layouts.min')
@section('content')
<div class="container-grey mt-2" style="width: 500px;">
  <div class="card-header d-flex align-items-start">
    <img src="{{ asset('storage/images/kado_lefttop_m.jpg') }}" alt="…">
    <hb class="mb-0 mt-2">申告特例控除マスター</hb>
  </div>
  <div class="card-body">
    <div class="wrapper">
      <table class="table-base table-bordered align-middle">
        <thead>
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
              <td class="text-end b-r-no">{{ $formatAmount($rate->lower) }}</td>
              <td class="text-center b-l-no b-r-no">～</td>
              <td class="text-end b-l-no">{{ $formatAmount($rate->upper) }}</td>
              <td class="text-end">{{ $formatRatio($rate->ratio_a) }}/{{ $formatRatio($rate->ratio_b) }}</td>
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