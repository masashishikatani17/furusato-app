@php
  $details = $results['details'] ?? [];
  $formatRate = static function (?float $rate): string {
      if ($rate === null) {
          return '';
      }

      return number_format($rate * 100, 3) . '%';
  };
@endphp

<div class="py-3">
  <div class="table-responsive">
    <table class="table table-bordered table-sm align-middle">
      <thead class="table-light">
        <tr>
          <th scope="col" class="w-75">項目</th>
          <th scope="col" class="text-end">割合</th>
        </tr>
      </thead>
      <tbody>
        <tr>
          <th scope="row">特例控除率（標準）</th>
          <td class="text-end">{{ $formatRate($details['tokurei_standard'] ?? null) }}</td>
        </tr>
        <tr>
          <th scope="row">特例控除率（90％）</th>
          <td class="text-end">{{ $formatRate($details['tokurei_90'] ?? null) }}</td>
        </tr>
        <tr>
          <th scope="row">山林所得（1/5）ベース</th>
          <td class="text-end">{{ $formatRate($details['sanrin_base'] ?? null) }}</td>
        </tr>
        <tr>
          <th scope="row">退職所得ベース</th>
          <td class="text-end">{{ $formatRate($details['taishoku_base'] ?? null) }}</td>
        </tr>
        <tr>
          <th scope="row">採用率</th>
          <td class="text-end">{{ $formatRate($details['adopted_min'] ?? null) }}</td>
        </tr>
        <tr>
          <th scope="row">分離課税の最小率</th>
          <td class="text-end">{{ $formatRate($details['bunri_min'] ?? null) }}</td>
        </tr>
        <tr class="table-primary">
          <th scope="row" class="fw-bold">特例控除 最終率</th>
          <td class="text-end fw-bold">{{ $formatRate($details['final_rate'] ?? null) }}</td>
        </tr>
      </tbody>
    </table>
  </div>
</div>