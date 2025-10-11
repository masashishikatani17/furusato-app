@php
  $details = $results['details'] ?? [];
  $prevDetails = $details['prev'] ?? [];
  $currDetails = $details['curr'] ?? [];
  $jintekiDiff = $jintekiDiff ?? [];
  $tokureiStandardRate = $tokureiStandardRate ?? [];
  $formatRate = static function (?float $rate): string {
      if ($rate === null) {
          return '';
      }

      return number_format($rate * 100, 3) . '%';
  };
  $formatPercent = static function (?float $v): string {
      return $v === null ? '' : rtrim(rtrim(number_format($v, 3), '0'), '.') . '%';
  };
  $warekiPrevLabel = $warekiPrev ?? '前年';
  $warekiCurrLabel = $warekiCurr ?? '当年';
@endphp

<div class="py-3">
  <div class="table-responsive mb-3">
    <table class="table table-bordered table-sm align-middle text-end">
      <thead class="table-light">
        <tr>
          <th class="text-start">人的控除額の差</th>
          <th class="text-center">{{ $warekiPrevLabel }}</th>
          <th class="text-center">{{ $warekiCurrLabel }}</th>
        </tr>
      </thead>
      <tbody>
        @php
          $rows = [
            ['label' => '寡婦控除', 'key' => 'kafu'],
            ['label' => 'ひとり親控除', 'key' => 'hitorioya'],
            ['label' => '勤労学生控除', 'key' => 'kinrogakusei'],
            ['label' => '障害者控除', 'key' => 'shogaisyo'],
            ['label' => '配偶者控除', 'key' => 'haigusha'],
            ['label' => '配偶者特別控除', 'key' => 'haigusha_tokubetsu'],
            ['label' => '扶養控除', 'key' => 'fuyo'],
            ['label' => '特定親族特別控除', 'key' => 'tokutei_shinzoku'],
            ['label' => '基礎控除', 'key' => 'kiso'],
            ['label' => '人的控除額の差の合計額', 'key' => 'sum'],
          ];
        @endphp
        @foreach ($rows as $row)
          <tr>
            <th class="text-start">{{ $row['label'] }}</th>
            <td>{{ number_format((int) ($jintekiDiff[$row['key']]['prev'] ?? 0)) }}</td>
            <td>{{ number_format((int) ($jintekiDiff[$row['key']]['curr'] ?? 0)) }}</td>
          </tr>
        @endforeach
        <tr>
          <th class="text-start">課税総所得金額-人的控除差調整額</th>
          <td>{{ number_format((int) ($jintekiDiff['adjusted_taxable']['prev'] ?? 0)) }}</td>
          <td>{{ number_format((int) ($jintekiDiff['adjusted_taxable']['curr'] ?? 0)) }}</td>
        </tr>
      </tbody>
    </table>
  </div>
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
          <td class="text-end">
            @php
              $aa50Prev = $prevDetails['AA50'] ?? null;
              $stdPrev = $tokureiStandardRate['prev'] ?? null;
            @endphp
            @if ($aa50Prev !== null)
              {{ $formatRate($aa50Prev) }}
            @else
              {{ $formatPercent($stdPrev) }}
            @endif
          </td>
          <td class="text-end">
            @php
              $aa50Curr = $currDetails['AA50'] ?? null;
              $stdCurr = $tokureiStandardRate['curr'] ?? null;
            @endphp
            @if ($aa50Curr !== null)
              {{ $formatRate($aa50Curr) }}
            @else
              {{ $formatPercent($stdCurr) }}
            @endif
          </td>
        </tr>
        <tr>
          <th scope="row">特例控除率（90％）</th>
          <td class="text-end">{{ $formatRate($prevDetails['AA51'] ?? null) }}</td>
          <td class="text-end">{{ $formatRate($currDetails['AA51'] ?? null) }}</td>
        </tr>
        <tr>
          <th scope="row">山林所得（1/5）ベース</th>
          <td class="text-end">{{ $formatRate($prevDetails['AA52'] ?? null) }}</td>
          <td class="text-end">{{ $formatRate($currDetails['AA52'] ?? null) }}</td>
        </tr>
        <tr>
          <th scope="row">退職所得ベース</th>
          <td class="text-end">{{ $formatRate($prevDetails['AA53'] ?? null) }}</td>
          <td class="text-end">{{ $formatRate($currDetails['AA53'] ?? null) }}</td>
        </tr>
        <tr>
          <th scope="row">採用率（山林／退職の小さい方）</th>
          <td class="text-end">{{ $formatRate($prevDetails['AA54'] ?? null) }}</td>
          <td class="text-end">{{ $formatRate($currDetails['AA54'] ?? null) }}</td>
        </tr>
        <tr>
          <th scope="row">分離課税に基づく率（最小）</th>
          <td class="text-end">{{ $formatRate($prevDetails['AA55'] ?? null) }}</td>
          <td class="text-end">{{ $formatRate($currDetails['AA55'] ?? null) }}</td>
        </tr>
        <tr class="table-primary">
          <th scope="row" class="fw-bold">特例控除 最終率</th>
          <td class="text-end fw-bold">{{ $formatRate($prevDetails['AA56'] ?? null) }}</td>
          <td class="text-end fw-bold">{{ $formatRate($currDetails['AA56'] ?? null) }}</td>
        </tr>
      </tbody>
    </table>
  </div>
</div>