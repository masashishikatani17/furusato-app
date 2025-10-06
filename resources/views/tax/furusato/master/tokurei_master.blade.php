@extends('layouts.min')
@section('content')
<div class="container" style="min-width: 960px; max-width: 1080px;">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h5 class="mb-0">ふるさと納税：特例控除マスター</h5>
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
      @php
        $formatAmount = static fn (?int $value): string => $value === null ? '' : number_format($value);
        $formatPercent = static fn (?float $value): string => $value === null ? '' : rtrim(rtrim(number_format($value, 3), '0'), '.').'%';
        $splitNote = static fn (?string $note): array => $note ? array_pad(explode('||', $note, 2), 2, '') : ['', ''];
      @endphp
      @foreach ($rates as $rate)
        @php [$noteLabel, $noteText] = $splitNote($rate->note); @endphp
        @if($rate->lower !== null)
          <tr>
            <td class="text-end">{{ $formatAmount($rate->lower) }}</td>
            <td class="text-center">～</td>
            <td class="text-end">{{ $formatAmount($rate->upper) }}</td>
            <td class="text-end">{{ $formatPercent($rate->income_rate) }}</td>
            <td class="text-end">{{ $formatPercent($rate->ninety_minus_rate) }}</td>
            <td class="text-end">{{ $formatPercent($rate->income_rate_with_recon) }}</td>
            <td class="text-end">{{ $formatPercent($rate->tokurei_deduction_rate) }}</td>
            <td>{{ $noteText }}</td>
          </tr>
        @else
          <tr>
            <th class="text-center" colspan="3">{{ $noteLabel }}</th>
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
</div>
@endsection