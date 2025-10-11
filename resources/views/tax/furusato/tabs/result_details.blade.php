@php
  $details = $results['details'] ?? [];
  $prevDetails = $details['prev'] ?? [];
  $currDetails = $details['curr'] ?? [];
  $jintekiDiff = $jintekiDiff ?? [];
  $inputs = $inputs ?? [];
  $formatRate = static function (?float $rate): string {
      if ($rate === null) {
          return '';
      }

      return number_format($rate * 100, 3) . '%';
  };
  $val = static function (array $inputs, string $key, ?float $fallback): string {
      if (array_key_exists($key, $inputs) && $inputs[$key] !== null && $inputs[$key] !== '') {
          return (string) $inputs[$key];
      }

      return $fallback !== null ? rtrim(rtrim(number_format($fallback, 6), '0'), '.') : '';
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
          <td class="text-end">{{ $formatRate($prevDetails['AA50'] ?? null) }}</td>
          <td class="text-end">{{ $formatRate($currDetails['AA50'] ?? null) }}</td>
        </tr>
        <tr>
          <th scope="row">特例控除率（90％）</th>
          <td class="text-end">{{ $formatRate($prevDetails['AA51'] ?? null) }}</td>
          <td class="text-end">{{ $formatRate($currDetails['AA51'] ?? null) }}</td>
        </tr>
        <tr>
          <th scope="row">山林所得（1/5）ベース</th>
            <input name="tokurei_rate_sanrin_div5_prev" type="number" inputmode="decimal"
                   min="0" max="1" step="0.000001" class="form-control form-control-sm text-end"
                   value="{{ $val($inputs, 'tokurei_rate_sanrin_div5_prev', $prevDetails['AA52'] ?? null) }}"
                   placeholder="0.000000">
          </td>
          <td class="text-end">
            <input name="tokurei_rate_sanrin_div5_curr" type="number" inputmode="decimal"
                   min="0" max="1" step="0.000001" class="form-control form-control-sm text-end"
                   value="{{ $val($inputs, 'tokurei_rate_sanrin_div5_curr', $currDetails['AA52'] ?? null) }}"
                   placeholder="0.000000">
          </td>
        </tr>
        <tr>
          <th scope="row">退職所得ベース</th>
          <td class="text-end">
            <input name="tokurei_rate_taishoku_prev" type="number" inputmode="decimal"
                   min="0" max="1" step="0.000001" class="form-control form-control-sm text-end"
                   value="{{ $val($inputs, 'tokurei_rate_taishoku_prev', $prevDetails['AA53'] ?? null) }}"
                   placeholder="0.000000">
          </td>
          <td class="text-end">
            <input name="tokurei_rate_taishoku_curr" type="number" inputmode="decimal"
                   min="0" max="1" step="0.000001" class="form-control form-control-sm text-end"
                   value="{{ $val($inputs, 'tokurei_rate_taishoku_curr', $currDetails['AA53'] ?? null) }}"
                   placeholder="0.000000">
          </td>
        </tr>
        <tr>
          <th scope="row">採用率（山林／退職の小さい方）</th>
          <td class="text-end">
            <input name="tokurei_rate_adopted_prev" type="number" inputmode="decimal"
                   min="0" max="1" step="0.000001" class="form-control form-control-sm text-end"
                   value="{{ $val($inputs, 'tokurei_rate_adopted_prev', $prevDetails['AA54'] ?? null) }}"
                   placeholder="0.000000">
          </td>
          <td class="text-end">
            <input name="tokurei_rate_adopted_curr" type="number" inputmode="decimal"
                   min="0" max="1" step="0.000001" class="form-control form-control-sm text-end"
                   value="{{ $val($inputs, 'tokurei_rate_adopted_curr', $currDetails['AA54'] ?? null) }}"
                   placeholder="0.000000">
          </td>
        </tr>
        <tr>
          <th scope="row">分離課税に基づく率（最小）</th>
          <td class="text-end">
            <input name="tokurei_rate_bunri_min_prev" type="number" inputmode="decimal"
                   min="0" max="1" step="0.000001" class="form-control form-control-sm text-end"
                   value="{{ $val($inputs, 'tokurei_rate_bunri_min_prev', $prevDetails['AA55'] ?? null) }}"
                   placeholder="0.000000">
          </td>
          <td class="text-end">
            <input name="tokurei_rate_bunri_min_curr" type="number" inputmode="decimal"
                   min="0" max="1" step="0.000001" class="form-control form-control-sm text-end"
                   value="{{ $val($inputs, 'tokurei_rate_bunri_min_curr', $currDetails['AA55'] ?? null) }}"
                   placeholder="0.000000">
          </td>
        </tr>
        <tr class="table-primary">
          <th scope="row" class="fw-bold">特例控除 最終率</th>
          <td class="text-end">
            <input name="tokurei_rate_final_prev" type="number" inputmode="decimal"
                   min="0" max="1" step="0.000001" class="form-control form-control-sm text-end fw-bold"
                   value="{{ $val($inputs, 'tokurei_rate_final_prev', $prevDetails['AA56'] ?? null) }}"
                   placeholder="0.000000">
          </td>
          <td class="text-end">
            <input name="tokurei_rate_final_curr" type="number" inputmode="decimal"
                   min="0" max="1" step="0.000001" class="form-control form-control-sm text-end fw-bold"
                   value="{{ $val($inputs, 'tokurei_rate_final_curr', $currDetails['AA56'] ?? null) }}"
                   placeholder="0.000000">
          </td>
        </tr>
      </tbody>
    </table>
  </div>
</div>