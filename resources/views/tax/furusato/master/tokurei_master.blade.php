@extends('layouts.min')
@section('content')
<div class="container-grey mt-2" style="width: 1000px;">
  <div class="card-header d-flex align-items-start">
    <img src="{{ asset('storage/images/kado_lefttop_m.jpg') }}" alt="…">
    <hb class="mb-0 mt-2">特例控除マスター</hb>
  </div>
  <div class="card-body">
    <div class="wrapper">
      <table class="table-base table-bordered align-middle">
        <thead>
          <tr>
            <th class="text-center nowrap" colspan="3">課税総所得金額から<br>人的控除差調整額を控除した金額</th>
            <th class="text-center">所得税率</th>
            <th class="text-center">90%-所得税率</th>
            <th class="text-center">復興特別所得税率を<br>加味した所得税率</th>
            <th class="text-center">特例控除の控除率</th>
            <th class="text-center">摘  要</th>
          </tr>
        </thead>
        <tbody>
          @php
            $formatAmount = static fn (?int $value): string => $value === null ? '' : number_format($value);
            $formatPercent = static fn (?float $value): string => $value === null ? '' : rtrim(rtrim(number_format($value, 3), '0'), '.').'%';
            $splitNote = static fn (?string $note): array => $note ? array_pad(explode('||', $note, 2), 2, '') : ['', ''];
          @endphp
          @foreach ($rates as $rate)
            @php [$noteLabel, $noteText] = $splitNote($rate->note); @endphp
            @if($rate->lower !== null)
              <tr>
                <td class="text-end b-r-no">{{ $formatAmount($rate->lower) }}</td>
                <td class="text-center b-l-no b-r-no">～</td>
                <td class="text-end b-l-no">{{ $formatAmount($rate->upper) }}</td>
                <td class="text-end">{{ $formatPercent($rate->income_rate) }}</td>
                <td class="text-end">{{ $formatPercent($rate->ninety_minus_rate) }}</td>
                <td class="text-end">{{ $formatPercent($rate->income_rate_with_recon) }}</td>
                <td class="text-end">{{ $formatPercent($rate->tokurei_deduction_rate) }}</td>
                <td>{{ $noteText }}</td>
              </tr>
            @else
              <tr>
                <th class="text-start" colspan="3">{{ $noteLabel }}</th>
                <td class="text-end">{{ $formatPercent($rate->income_rate) }}</td>
                <td class="text-end">{{ $formatPercent($rate->ninety_minus_rate) }}</td>
                <td class="text-end">{{ $formatPercent($rate->income_rate_with_recon) }}</td>
                <td class="text-end">{{ $formatPercent($rate->tokurei_deduction_rate) }}</td>
                <td>{{ $noteText }}</td>
              </tr>
            @endif
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