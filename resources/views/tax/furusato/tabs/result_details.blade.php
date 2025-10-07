@php
  $d = $results['details'] ?? [];
  $fmt = static function ($value) {
      return is_null($value) ? '' : number_format($value * 100, 3) . '%';
  };
@endphp

<div class="card my-3">
  <div class="card-body">
    <h6 class="card-title mb-3">特例控除率の計算詳細</h6>
    <div class="table-responsive">
      <table class="table table-bordered table-sm align-middle mb-0">
        <tbody>
          <tr>
            <th scope="row" class="bg-light" style="width: 50%;">(ア) 特例控除率（標準）</th>
            <td class="text-end">{{ $fmt($d['tokurei_standard'] ?? null) }}</td>
          </tr>
          <tr>
            <th scope="row" class="bg-light">(イ) 特例控除率（90%）</th>
            <td class="text-end">{{ $fmt($d['tokurei_90'] ?? null) }}</td>
          </tr>
          <tr>
            <th scope="row" class="bg-light">(ウ) 山林（1/5）ベースの率</th>
            <td class="text-end">{{ $fmt($d['sanrin_base'] ?? null) }}</td>
          </tr>
          <tr>
            <th scope="row" class="bg-light">(ウ) 退職ベースの率</th>
            <td class="text-end">{{ $fmt($d['taishoku_base'] ?? null) }}</td>
          </tr>
          <tr>
            <th scope="row" class="bg-light">(ウ) 採用率（小さい方）</th>
            <td class="text-end">{{ $fmt($d['adopted_min'] ?? null) }}</td>
          </tr>
          <tr>
            <th scope="row" class="bg-light">(エ) 分離課税に基づく率（最小）</th>
            <td class="text-end">{{ $fmt($d['bunri_min'] ?? null) }}</td>
          </tr>
          <tr>
            <th scope="row" class="bg-light">特例控除の最終率</th>
            <td class="text-end fw-bold">{{ $fmt($d['final_rate'] ?? null) }}</td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</div>