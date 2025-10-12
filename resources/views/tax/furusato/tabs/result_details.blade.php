@php
  $details = $results['details'] ?? [];
  $prevDetails = $details['prev'] ?? [];
  $currDetails = $details['curr'] ?? [];
  $jintekiDiff = $jintekiDiff ?? [];
  $inputs = $inputs ?? [];
  $tkFallback = $tokureiFallbackPercent ?? [];
  $valPercent = static function (array $ins, string $key, ?float $fallbackPercent, ?float $aa): string {
      if (array_key_exists($key, $ins) && $ins[$key] !== null && $ins[$key] !== '') {
          return (string) $ins[$key];
      }
      if ($fallbackPercent !== null) {
          return rtrim(rtrim(number_format($fallbackPercent, 3), '0'), '.');
      }
      if ($aa !== null) {
          return rtrim(rtrim(number_format($aa * 100, 3), '0'), '.');
      }

      return '';
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
            @php $v = $valPercent($inputs, 'tokurei_rate_standard_prev', null, $prevDetails['AA50'] ?? null); @endphp
            <div class="input-group input-group-sm">
              <input name="tokurei_rate_standard_prev" type="number" inputmode="decimal" step="0.001" min="0"
                     class="form-control form-control-sm text-end" value="{{ $v }}" readonly>
              <span class="input-group-text">%</span>
            </div>
          </td>
          <td class="text-end">
            @php $v = $valPercent($inputs, 'tokurei_rate_standard_curr', null, $currDetails['AA50'] ?? null); @endphp
            <div class="input-group input-group-sm">
              <input name="tokurei_rate_standard_curr" type="number" inputmode="decimal" step="0.001" min="0"
                     class="form-control form-control-sm text-end" value="{{ $v }}" readonly>
              <span class="input-group-text">%</span>
            </div>
          </td>
        </tr>
        <tr>
          <th scope="row">特例控除率（90％）</th>
          <td class="text-end">
            @php
              $aa = $prevDetails['AA51'] ?? 0.90;
              $v = $valPercent($inputs, 'tokurei_rate_90_prev', null, $aa);
            @endphp
            <div class="input-group input-group-sm">
              <input name="tokurei_rate_90_prev" type="number" inputmode="decimal" step="0.001" min="0"
                     class="form-control form-control-sm text-end" value="{{ $v }}" readonly>
              <span class="input-group-text">%</span>
            </div>
          </td>
          <td class="text-end">
            @php
              $aa = $currDetails['AA51'] ?? 0.90;
              $v = $valPercent($inputs, 'tokurei_rate_90_curr', null, $aa);
            @endphp
            <div class="input-group input-group-sm">
              <input name="tokurei_rate_90_curr" type="number" inputmode="decimal" step="0.001" min="0"
                     class="form-control form-control-sm text-end" value="{{ $v }}" readonly>
              <span class="input-group-text">%</span>
            </div>
          </td>
        </tr>
        <tr>
          <th scope="row">山林所得（1/5）ベース</th>
          <td class="text-end">
            @php
              $fallback = $tkFallback['sanrin_div5_prev'] ?? null;
              $aa = $prevDetails['AA52'] ?? null;
              $v = $valPercent($inputs, 'tokurei_rate_sanrin_div5_prev', $fallback, $aa);
            @endphp
            <div class="input-group input-group-sm">
              <input name="tokurei_rate_sanrin_div5_prev" type="number" inputmode="decimal" step="0.001" min="0"
                     class="form-control form-control-sm text-end" value="{{ $v }}" readonly>
              <span class="input-group-text">%</span>
            </div>
          </td>
          <td class="text-end">
            @php
              $fallback = $tkFallback['sanrin_div5_curr'] ?? null;
              $aa = $currDetails['AA52'] ?? null;
              $v = $valPercent($inputs, 'tokurei_rate_sanrin_div5_curr', $fallback, $aa);
            @endphp
            <div class="input-group input-group-sm">
              <input name="tokurei_rate_sanrin_div5_curr" type="number" inputmode="decimal" step="0.001" min="0"
                     class="form-control form-control-sm text-end" value="{{ $v }}" readonly>
              <span class="input-group-text">%</span>
            </div>
          </td>
        </tr>
        <tr>
          <th scope="row">退職所得ベース</th>
          <td class="text-end">
            @php $v = $valPercent($inputs, 'tokurei_rate_taishoku_prev', null, $prevDetails['AA53'] ?? null); @endphp
            <div class="input-group input-group-sm">
              <input name="tokurei_rate_taishoku_prev" type="number" inputmode="decimal" step="0.001" min="0"
                     class="form-control form-control-sm text-end" value="{{ $v }}" readonly>
              <span class="input-group-text">%</span>
            </div>
          </td>
          <td class="text-end">
            @php $v = $valPercent($inputs, 'tokurei_rate_taishoku_curr', null, $currDetails['AA53'] ?? null); @endphp
            <div class="input-group input-group-sm">
              <input name="tokurei_rate_taishoku_curr" type="number" inputmode="decimal" step="0.001" min="0"
                     class="form-control form-control-sm text-end" value="{{ $v }}" readonly>
              <span class="input-group-text">%</span>
            </div>
          </td>
        </tr>
        <tr>
          <th scope="row">採用率（山林／退職の小さい方）</th>
          <td class="text-end">
            @php $v = $valPercent($inputs, 'tokurei_rate_adopted_prev', null, $prevDetails['AA54'] ?? null); @endphp
            <div class="input-group input-group-sm">
              <input name="tokurei_rate_adopted_prev" type="number" inputmode="decimal" step="0.001" min="0"
                     class="form-control form-control-sm text-end" value="{{ $v }}" readonly>
              <span class="input-group-text">%</span>
            </div>
          </td>
          <td class="text-end">
            @php $v = $valPercent($inputs, 'tokurei_rate_adopted_curr', null, $currDetails['AA54'] ?? null); @endphp
            <div class="input-group input-group-sm">
              <input name="tokurei_rate_adopted_curr" type="number" inputmode="decimal" step="0.001" min="0"
                     class="form-control form-control-sm text-end" value="{{ $v }}" readonly>
              <span class="input-group-text">%</span>
            </div>
          </td>
        </tr>
        <tr>
          <th scope="row">分離課税に基づく率（最小）</th>
          <td class="text-end">
            @php $v = $valPercent($inputs, 'tokurei_rate_bunri_min_prev', null, $prevDetails['AA55'] ?? null); @endphp
            <div class="input-group input-group-sm">
              <input name="tokurei_rate_bunri_min_prev" type="number" inputmode="decimal" step="0.001" min="0"
                     class="form-control form-control-sm text-end" value="{{ $v }}" readonly>
              <span class="input-group-text">%</span>
            </div>
          </td>
          <td class="text-end">
            @php $v = $valPercent($inputs, 'tokurei_rate_bunri_min_curr', null, $currDetails['AA55'] ?? null); @endphp
            <div class="input-group input-group-sm">
              <input name="tokurei_rate_bunri_min_curr" type="number" inputmode="decimal" step="0.001" min="0"
                     class="form-control form-control-sm text-end" value="{{ $v }}" readonly>
              <span class="input-group-text">%</span>
            </div>
          </td>
        </tr>
        <tr class="table-primary">
          <th scope="row" class="fw-bold">特例控除 最終率</th>
          <td class="text-end">
            @php $v = $valPercent($inputs, 'tokurei_rate_final_prev', null, $prevDetails['AA56'] ?? null); @endphp
            <div class="input-group input-group-sm">
              <input name="tokurei_rate_final_prev" type="number" inputmode="decimal" step="0.001" min="0"
                     class="form-control form-control-sm text-end fw-bold" value="{{ $v }}" readonly>
              <span class="input-group-text">%</span>
            </div>
          </td>
          <td class="text-end">
            @php $v = $valPercent($inputs, 'tokurei_rate_final_curr', null, $currDetails['AA56'] ?? null); @endphp
            <div class="input-group input-group-sm">
              <input name="tokurei_rate_final_curr" type="number" inputmode="decimal" step="0.001" min="0"
                     class="form-control form-control-sm text-end fw-bold" value="{{ $v }}" readonly>
              <span class="input-group-text">%</span>
            </div>
          </td>
        </tr>
      </tbody>
    </table>
  </div>
</div>