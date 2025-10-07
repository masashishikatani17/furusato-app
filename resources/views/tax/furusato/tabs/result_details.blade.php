@php
  $details = $results['details'] ?? [];
  $prevDetails = $details['prev'] ?? [];
  $currDetails = $details['curr'] ?? [];
  $formatRate = static function (?float $rate): string {
      if ($rate === null) {
          return '';
      }

      return number_format($rate * 100, 3) . '%';
  };
  $warekiPrevLabel = $warekiPrev ?? '前年';
  $warekiCurrLabel = $warekiCurr ?? '当年';
@endphp

<div class="py-3">
  <div class="table-responsive">
    <table class="table table-bordered table-sm align-middle">
      <thead class="table-light">
        <tr>
          <th scope="col" class="w-50">項目</th>
          <th scope="col" class="text-end">{{ $warekiPrevLabel }}</th>
          <th scope="col" class="text-end">{{ $warekiCurrLabel }}</th>
        </tr>
      </thead>
      <tbody>
        <tr>
          <th scope="row">特例控除率（標準）</th>
          <td class="text-end">{{ $formatRate($prevDetails['tokurei_standard'] ?? null) }}</td>
          <td class="text-end">{{ $formatRate($currDetails['tokurei_standard'] ?? null) }}</td>
        </tr>
        <tr>
          <th scope="row">特例控除率（90％）</th>
          <td class="text-end">{{ $formatRate($prevDetails['tokurei_90'] ?? null) }}</td>
          <td class="text-end">{{ $formatRate($currDetails['tokurei_90'] ?? null) }}</td>
        </tr>
        <tr>
          <th scope="row">山林所得（1/5）ベース</th>
          <td class="text-end">{{ $formatRate($prevDetails['sanrin_base'] ?? null) }}</td>
          <td class="text-end">{{ $formatRate($currDetails['sanrin_base'] ?? null) }}</td>
        </tr>
        <tr>
          <th scope="row">退職所得ベース</th>
          <td class="text-end">{{ $formatRate($prevDetails['taishoku_base'] ?? null) }}</td>
          <td class="text-end">{{ $formatRate($currDetails['taishoku_base'] ?? null) }}</td>
        </tr>
        <tr>
          <th scope="row">採用率（小さい方）</th>
          <td class="text-end">{{ $formatRate($prevDetails['adopted_min'] ?? null) }}</td>
          <td class="text-end">{{ $formatRate($currDetails['adopted_min'] ?? null) }}</td>
        </tr>
        <tr>
          <th scope="row">分離課税に基づく率（最小）</th>
          <td class="text-end">{{ $formatRate($prevDetails['bunri_min'] ?? null) }}</td>
          <td class="text-end">{{ $formatRate($currDetails['bunri_min'] ?? null) }}</td>
        </tr>
        <tr class="table-primary">
          <th scope="row" class="fw-bold">特例控除 最終率</th>
          <td class="text-end fw-bold">{{ $formatRate($prevDetails['final_rate'] ?? null) }}</td>
          <td class="text-end fw-bold">{{ $formatRate($currDetails['final_rate'] ?? null) }}</td>
        </tr>
      </tbody>
    </table>
  </div>
</div>