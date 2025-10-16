<!-- resources/views/tax/furusato/tabs/result_details.blade.php -->
@php
  $details = $results['details'] ?? [];
  $prevDetails = $details['prev'] ?? [];
  $currDetails = $details['curr'] ?? [];
  $jintekiDiff = $jintekiDiff ?? [];
  $inputs = $inputs ?? [];
  $tokureiStandardRate = $tokureiStandardRate ?? [];
  $tkComputed = $tokureiComputedPercent ?? [];
  $tkEnabled = $tokureiEnabled ?? [];

  // hiddenの値：payload 優先、なければ計算結果をそのままraw整数（カンマなし）
  $rawInt = static function (array $ins, string $key, ?int $fallback): string {
      if (array_key_exists($key, $ins) && $ins[$key] !== null && $ins[$key] !== '') {
          return (string) (int) $ins[$key];
      }

      return $fallback !== null ? (string) (int) $fallback : '';
  };

  // 表示（素テキスト）用：rawをカンマ区切り、nullなら空
  $dispInt = static function (?int $v): string {
      return $v === null ? '' : number_format((int) $v);
  };

  $formatRawPercent = static function (float $num): string {
      $str = number_format($num, 6, '.', '');

      if (strpos($str, '.') !== false) {
          $str = rtrim(rtrim($str, '0'), '.');
      }

      return $str === '' ? '0' : $str;
  };
  $formatDisplayPercent = static function (float $num): string {
      return rtrim(rtrim(number_format($num, 3), '0'), '.') . '%';
  };

  // 百分率hidden値：payload優先→computed fallback→AA*100
  $valPercent = static function (array $ins, string $key, ?float $fallbackPercent, ?float $aa) use ($formatRawPercent, $formatDisplayPercent): array {
      if (array_key_exists($key, $ins) && $ins[$key] !== null && $ins[$key] !== '') {
          $num = (float) $ins[$key];

          return [$formatRawPercent($num), $formatDisplayPercent($num)];
      }
      if ($fallbackPercent !== null) {
          $num = (float) $fallbackPercent;

          return [$formatRawPercent($num), $formatDisplayPercent($num)];
      }
      if ($aa !== null) {
          $num = (float) ($aa * 100.0);

          return [$formatRawPercent($num), $formatDisplayPercent($num)];
      }

      return ['', ''];
  };
  // 百分率hidden値：有効フラグが false の場合は空欄を返す
  $valPercentEnabled = static function (array $ins, string $key, bool $enabled, ?float $computedPercent) use ($formatRawPercent, $formatDisplayPercent): array {
      if (! $enabled) {
          return ['', ''];
      }

      if (array_key_exists($key, $ins) && $ins[$key] !== null && $ins[$key] !== '') {
          $num = (float) $ins[$key];

          return [$formatRawPercent($num), $formatDisplayPercent($num)];
      }

      if ($computedPercent !== null) {
          $num = (float) $computedPercent;

          return [$formatRawPercent($num), $formatDisplayPercent($num)];
      }

      return ['', ''];
  };
  $toAssocValue = static function (array $pair): array {
      return ['raw' => $pair[0] ?? '', 'disp' => $pair[1] ?? ''];
  };
  $readonlyValue = static function (string $key, $fallback = null) use ($inputs): string {
      $value = old($key, $inputs[$key] ?? $fallback);

      if ($value === null || $value === '') {
          return '';
      }

      $stringValue = (string) $value;
      $normalized = str_replace(',', '', $stringValue);

      if (! is_numeric($normalized)) {
          return $stringValue;
      }

      if (strpos($normalized, '.') !== false) {
          $number = (float) $normalized;
          $formatted = number_format($number, 3, '.', ',');

          return rtrim(rtrim($formatted, '0'), '.');
      }

      $number = (int) $normalized;

      return number_format($number);
  };
  $warekiPrevLabel = $warekiPrev ?? '前年';
  $warekiCurrLabel = $warekiCurr ?? '当年';
  $periodFilter = isset($periodFilter) && in_array($periodFilter, ['prev', 'curr'], true) ? $periodFilter : null;
  $periodsAll = ['prev' => $warekiPrevLabel, 'curr' => $warekiCurrLabel];
  $displayPeriods = $periodFilter === null ? array_keys($periodsAll) : [$periodFilter];
  $displayPeriodLabels = array_intersect_key($periodsAll, array_flip($displayPeriods));
  $shouldRenderHiddenPeriods = $periodFilter === null;
  $hiddenPeriods = $shouldRenderHiddenPeriods
    ? array_values(array_diff(array_keys($periodsAll), $displayPeriods))
    : [];
@endphp

@php
  // テスト互換用：上表の「調整後課税」を素テキストで 1 行出す（視覚的に非表示）
  // 優先：jintekiDiff → payload（hidden入力）→ 空
  $adjPrevRaw = $jintekiDiff['adjusted_taxable']['prev'] ?? ($inputs['human_adjusted_taxable_prev'] ?? null);
  $adjCurrRaw = $jintekiDiff['adjusted_taxable']['curr'] ?? ($inputs['human_adjusted_taxable_curr'] ?? null);
  $adjPrevText = $adjPrevRaw !== null ? number_format((int) $adjPrevRaw) : '';
  $adjCurrText = $adjCurrRaw !== null ? number_format((int) $adjCurrRaw) : '';
@endphp

@php
  $adjTexts = [];
  if (in_array('prev', $displayPeriods, true) && $adjPrevText !== '') {
      $adjTexts[] = $warekiPrevLabel . '：' . $adjPrevText;
  }
  if (in_array('curr', $displayPeriods, true) && $adjCurrText !== '') {
      $adjTexts[] = $warekiCurrLabel . '：' . $adjCurrText;
  }
@endphp
@if(! empty($adjTexts))
  <div class="visually-hidden" aria-hidden="true">
    課税総所得金額-人的控除差調整額 {{ implode(' ', $adjTexts) }}
  </div>
@endif

<div class="wrapper pt-2">
  <div class="table-responsive">
    <table class="table table-base align-middle" style="width:580px">
        <tr>
          @php
            $humanDiffHeader = count($displayPeriods) === 1
              ? '人的控除額の差（' . ($displayPeriodLabels[$displayPeriods[0]] ?? '') . '）'
              : '人的控除額の差';
          @endphp
          <th class="text-center th-ccc" style="height:30px;">{{ $humanDiffHeader }}</th>
          @foreach ($displayPeriods as $period)
            <th class="text-center th-ccc">{{ $displayPeriodLabels[$period] ?? '' }}</th>
          @endforeach
        </tr>
      <tbody>
        @php
          $rows = [
            ['label' => '寡婦控除', 'key' => 'kafu', 'input' => 'human_diff_kafu'],
            ['label' => 'ひとり親控除', 'key' => 'hitorioya', 'input' => 'human_diff_hitorioya'],
            ['label' => '勤労学生控除', 'key' => 'kinrogakusei', 'input' => 'human_diff_kinrogakusei'],
            ['label' => '障害者控除', 'key' => 'shogaisyo', 'input' => 'human_diff_shogaisyo'],
            ['label' => '配偶者控除', 'key' => 'haigusha', 'input' => 'human_diff_haigusha'],
            ['label' => '配偶者特別控除', 'key' => 'haigusha_tokubetsu', 'input' => 'human_diff_haigusha_tokubetsu'],
            ['label' => '扶養控除', 'key' => 'fuyo', 'input' => 'human_diff_fuyo'],
            ['label' => '特定親族特別控除', 'key' => 'tokutei_shinzoku', 'input' => 'human_diff_tokutei_shinzoku'],
            ['label' => '基礎控除', 'key' => 'kiso', 'input' => 'human_diff_kiso'],
            ['label' => '人的控除額の差の合計額', 'key' => 'sum', 'input' => 'human_diff_sum'],
          ];
        @endphp
        @foreach ($rows as $row)
          <tr>
            <th class="text-start ps-1">{{ $row['label'] }}</th>
            @foreach ($displayPeriods as $period)
              @php
                $inputName = $row['input'] . '_' . $period;
                $fallback = $jintekiDiff[$row['key']][$period] ?? null;
                $raw = $rawInt($inputs, $inputName, $fallback);
                $displayValue = $fallback !== null ? (int) $fallback : (is_numeric($raw) ? (int) $raw : null);
              @endphp
              <td class="text-end">
                <input type="hidden" name="{{ $inputName }}" value="{{ $raw }}">{{ $dispInt($displayValue) }}
              </td>
            @endforeach
          </tr>
        @endforeach
        <tr>
          <th class="text-start ps-1 th-cream">課税総所得金額-人的控除差調整額</th>
          @foreach ($displayPeriods as $period)
            @php
              $fallback = $jintekiDiff['adjusted_taxable'][$period] ?? null;
              $inputName = 'human_adjusted_taxable_' . $period;
              $raw = $rawInt($inputs, $inputName, $fallback);
              $displayValue = $fallback !== null ? (int) $fallback : (is_numeric($raw) ? (int) $raw : null);
            @endphp
            <td class="text-end">
              <input type="hidden" name="{{ $inputName }}" value="{{ $raw }}">{{ $dispInt($displayValue) }}</td>
          @endforeach
        </tr>
      </tbody>
    </table>
    @if($shouldRenderHiddenPeriods && ! empty($hiddenPeriods))
      <div class="visually-hidden" aria-hidden="true">
        @foreach ($rows as $row)
          @foreach ($hiddenPeriods as $period)
            @php
              $inputName = $row['input'] . '_' . $period;
              $fallback = $jintekiDiff[$row['key']][$period] ?? null;
              $raw = $rawInt($inputs, $inputName, $fallback);
            @endphp
            <input type="hidden" name="{{ $inputName }}" value="{{ $raw }}">
          @endforeach
        @endforeach
        @foreach ($hiddenPeriods as $period)
          @php
            $inputName = 'human_adjusted_taxable_' . $period;
            $fallback = $jintekiDiff['adjusted_taxable'][$period] ?? null;
            $raw = $rawInt($inputs, $inputName, $fallback);
          @endphp
          <input type="hidden" name="{{ $inputName }}" value="{{ $raw }}">
        @endforeach
      </div>
    @endif
  </div>
  @php
    $stdPrev = $tokureiStandardRate['prev'] ?? (isset($prevDetails['AA50']) ? $prevDetails['AA50'] * 100 : null);
    $stdCurr = $tokureiStandardRate['curr'] ?? (isset($currDetails['AA50']) ? $currDetails['AA50'] * 100 : null);
    $fmt = static fn($v) => $v === null ? '' : rtrim(rtrim(number_format($v, 3), '0'), '.') . '%';
  @endphp
  <div class="table-responsive">
    @php
      [$stdPrevRaw, $stdPrevDisp] = $valPercent($inputs, 'tokurei_rate_standard_prev', $tkComputed['standard_prev'] ?? null, $prevDetails['AA50'] ?? null);
      [$stdCurrRaw, $stdCurrDisp] = $valPercent($inputs, 'tokurei_rate_standard_curr', $tkComputed['standard_curr'] ?? null, $currDetails['AA50'] ?? null);
      $tokureiRateRows = [
        [
          'name' => 'tokurei_rate_standard',
          'label' => '特例控除率（標準）',
          'values' => [
            'prev' => ['raw' => $stdPrevRaw, 'disp' => $stdPrevDisp],
            'curr' => ['raw' => $stdCurrRaw, 'disp' => $stdCurrDisp],
          ],
        ],
        [
          'name' => 'tokurei_rate_90',
          'label' => '特例控除率（90％）',
          'values' => [
            'prev' => $toAssocValue($valPercent($inputs, 'tokurei_rate_90_prev', $tkComputed['ninety_prev'] ?? 90.000, $prevDetails['AA51'] ?? 0.90)),
            'curr' => $toAssocValue($valPercent($inputs, 'tokurei_rate_90_curr', $tkComputed['ninety_curr'] ?? 90.000, $currDetails['AA51'] ?? 0.90)),
          ],
        ],
        [
          'name' => 'tokurei_rate_sanrin_div5',
          'label' => '山林所得（1/5）ベース',
          'values' => [
            'prev' => $toAssocValue($valPercentEnabled($inputs, 'tokurei_rate_sanrin_div5_prev', $tkEnabled['sanrin_prev'] ?? false, $tkComputed['sanrin_prev'] ?? null)),
            'curr' => $toAssocValue($valPercentEnabled($inputs, 'tokurei_rate_sanrin_div5_curr', $tkEnabled['sanrin_curr'] ?? false, $tkComputed['sanrin_curr'] ?? null)),
          ],
        ],
        [
          'name' => 'tokurei_rate_taishoku',
          'label' => '退職所得ベース',
          'values' => [
            'prev' => $toAssocValue($valPercentEnabled($inputs, 'tokurei_rate_taishoku_prev', $tkEnabled['taishoku_prev'] ?? false, $tkComputed['taishoku_prev'] ?? null)),
            'curr' => $toAssocValue($valPercentEnabled($inputs, 'tokurei_rate_taishoku_curr', $tkEnabled['taishoku_curr'] ?? false, $tkComputed['taishoku_curr'] ?? null)),
          ],
        ],
        [
          'name' => 'tokurei_rate_adopted',
          'label' => '採用率（山林／退職の小さい方）',
          'values' => [
            'prev' => $toAssocValue($valPercentEnabled($inputs, 'tokurei_rate_adopted_prev', ($tkEnabled['sanrin_prev'] ?? false) || ($tkEnabled['taishoku_prev'] ?? false), $tkComputed['adopted_prev'] ?? null)),
            'curr' => $toAssocValue($valPercentEnabled($inputs, 'tokurei_rate_adopted_curr', ($tkEnabled['sanrin_curr'] ?? false) || ($tkEnabled['taishoku_curr'] ?? false), $tkComputed['adopted_curr'] ?? null)),
          ],
        ],
        [
          'name' => 'tokurei_rate_bunri_min',
          'label' => '分離課税に基づく率（最小）',
          'values' => [
            'prev' => $toAssocValue($valPercentEnabled($inputs, 'tokurei_rate_bunri_min_prev', $tkEnabled['bunri_prev'] ?? false, $tkComputed['bunri_min_prev'] ?? null)),
            'curr' => $toAssocValue($valPercentEnabled($inputs, 'tokurei_rate_bunri_min_curr', $tkEnabled['bunri_curr'] ?? false, $tkComputed['bunri_min_curr'] ?? null)),
          ],
        ],
        [
          'name' => 'tokurei_rate_final',
          'label' => '特例控除 最終率',
          'rowClass' => 'table-primary',
          'headerClass' => 'text-center th-cream',
          'valueClass' => 'fw-bold',
          'values' => [
            'prev' => $toAssocValue($valPercent($inputs, 'tokurei_rate_final_prev', $tkComputed['final_prev'] ?? null, $prevDetails['AA56'] ?? null)),
            'curr' => $toAssocValue($valPercent($inputs, 'tokurei_rate_final_curr', $tkComputed['final_curr'] ?? null, $currDetails['AA56'] ?? null)),
          ],
        ],
      ];
    @endphp
    <table class="table table-base align-middle" style="width:580px">
        <tr>
          <th scope="col" class="w-50 th-ccc" style="height:30px;">項  目</th>
          @foreach ($displayPeriods as $period)
            <th scope="col" class="text-center th-ccc">{{ $displayPeriodLabels[$period] ?? '' }}</th>
          @endforeach
        </tr>
      <tbody>
        @foreach ($tokureiRateRows as $row)
          <tr class="{{ $row['rowClass'] ?? '' }}">
            <th scope="row" class="{{ $row['headerClass'] ?? 'text-start ps-1' }}">{{ $row['label'] }}</th>
            @foreach ($displayPeriods as $period)
              @php
                $value = $row['values'][$period] ?? ['raw' => '', 'disp' => ''];
                $name = $row['name'] . '_' . $period;
                $valueClass = $row['valueClass'] ?? '';
                $raw = $value['raw'] ?? '';
                $disp = $value['disp'] ?? '';
              @endphp
              <td class="text-end">
                <input type="hidden" name="{{ $name }}" value="{{ $raw }}">
                @if($valueClass !== '' && $disp !== '')
                  <span class="{{ $valueClass }}">{{ $disp }}</span>
                @else
                  {{ $disp }}
                @endif
              </td>
            @endforeach
          </tr>
        @endforeach
      </tbody>
    </table>
    @if($shouldRenderHiddenPeriods && ! empty($hiddenPeriods))
      <div class="visually-hidden" aria-hidden="true">
        @foreach ($tokureiRateRows as $row)
          @foreach ($hiddenPeriods as $period)
            @php
              $value = $row['values'][$period] ?? ['raw' => '', 'disp' => ''];
              $name = $row['name'] . '_' . $period;
            @endphp
            <input type="hidden" name="{{ $name }}" value="{{ $value['raw'] ?? '' }}">
          @endforeach
        @endforeach
      </div>
    @endif
    @php
      $stdTextParts = [];
      if (in_array('prev', $displayPeriods, true) && $stdPrev !== null) {
          $stdTextParts[] = $warekiPrevLabel . '：' . $fmt($stdPrev);
      }
      if (in_array('curr', $displayPeriods, true) && $stdCurr !== null) {
          $stdTextParts[] = $warekiCurrLabel . '：' . $fmt($stdCurr);
      }
    @endphp
    @if(! empty($stdTextParts))
      <div class="visually-hidden" aria-hidden="true">
        特例控除率（標準） {{ implode(' ', $stdTextParts) }}
      </div>
    @endif
  </div>

  @php
    $warekiTables = [];
    foreach ($displayPeriods as $periodKey) {
        $warekiTables[$periodKey] = $displayPeriodLabels[$periodKey] ?? ($periodsAll[$periodKey] ?? '');
    }
  @endphp
  <div class="mt-4">
    @foreach ($warekiTables as $suffix => $label)
      <div class="mt-4">
        <div class="table-responsive">
          <table class="table table-base align-middle" style="width:700px">
            <tbody>
              <tr>
                <th colspan="3" class="th-ccc" style="height:30px;">所得の種類</th>
                <th class="th-ccc">差引金額</th>
                <th colspan="2" class="th-ccc">通算後</th>
                <th class="th-ccc">特別控除額</th>
                <th colspan="2" class="th-ccc" nowrap="nowrap">譲渡・一時所得の通算後</th>
              </tr>
              <tr>
                <th rowspan="3" style="width:40px">譲渡</th>
                <th style="width:40px">短期</th>
                <th class="text-start ps-1 th-ddd" style="width:130px">総合</th>
                <td class="text-end" style="width:100px"><div class="readonly-span text-end">{{ $readonlyValue('sashihiki_joto_tanki_sogo_' . $suffix) }}</div></td>
                <th rowspan="3" class="vtext" style="width:35px">通算</th>
                <td class="text-end" style="width:100px"><div class="readonly-span text-end">{{ $readonlyValue('tsusango_joto_tanki_' . $suffix) }}</div></td>
                <td class="text-end" style="width:100px"><div class="readonly-span text-end">{{ $readonlyValue('tokubetsukojo_joto_tanki_' . $suffix) }}</div></td>
                <th rowspan="4" class="vtext" style="width:35px">譲渡・一時所得の通算</th>
                <td class="text-end" style="width:100px"><div class="readonly-span text-end">{{ $readonlyValue('after_joto_ichiji_tousan_joto_tanki_' . $suffix) }}</div></td>
              </tr>
              <tr>
                <th rowspan="2">長期</th>
                <th class="text-start ps-1 th-ddd">分離（特定損失額）</th>
                <td class="text-end"><div class="readonly-span text-end">{{ $readonlyValue('sashihiki_joto_choki_bunri_' . $suffix) }}</div></td>
                <td class="text-end"><div class="readonly-span text-end">{{ $readonlyValue('tsusango_joto_choki_bunri_' . $suffix) }}</div></td>
                <td><div class="readonly-span text-center">－</div></td>
                <td class="text-end"><div class="readonly-span text-end">{{ $readonlyValue('after_joto_ichiji_tousan_joto_choki_bunri_' . $suffix) }}</div></td>
              </tr>
              <tr>
                <th class="text-start ps-1 th-ddd">総合</th>
                <td class="text-end"><div class="readonly-span text-end">{{ $readonlyValue('sashihiki_joto_choki_sogo_' . $suffix) }}</div></td>
                <td class="text-end"><div class="readonly-span text-end">{{ $readonlyValue('tsusango_joto_choki_sogo_' . $suffix) }}</div></td>
                <td class="text-end"><div class="readonly-span text-end">{{ $readonlyValue('tokubetsukojo_joto_choki_' . $suffix) }}</div></td>
                <td class="text-end"><div class="readonly-span text-end">{{ $readonlyValue('after_joto_ichiji_tousan_joto_choki_sogo_' . $suffix) }}</div></td>
              </tr>
              <tr>
                <th class="text-start ps-1" colspan="3">一時</th>
                <td colspan="2"><div class="readonly-span text-center">⇒</div></td>
                <td class="text-end"><div class="readonly-span text-end">{{ $readonlyValue('tsusango_ichiji_' . $suffix) }}</div></td>
                <td class="text-end"><div class="readonly-span text-end">{{ $readonlyValue('tokubetsukojo_ichiji_' . $suffix) }}</div></td>
                <td class="text-end"><div class="readonly-span text-end">{{ $readonlyValue('after_joto_ichiji_tousan_ichiji_' . $suffix) }}</div></td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    @endforeach
  </div>
  <div class="mt-4">
    @foreach ($warekiTables as $suffix => $label)
      <div class="mt-4">
        <div class="table-responsive">
          <table class="table table-base align-middle" style="width: 780px;">
            <tbody>
              <tr>
                <th colspan="3" class="th-ccc" style="height:30px;">所得の種類</th>
                <th class="th-ccc">通算前</th>
                <th colspan="2" class="th-ccc">第1次通算後</th>
                <th colspan="2" class="th-ccc">第2次通算後</th>
                <th colspan="2" class="th-ccc">第3次通算後</th>
                <th class="th-ccc">所得金額</th>
              </tr>
              <tr>
                <th class="text-start ps-1" colspan="3">経常所得</th>
                <td class="text-end"><div class="readonly-span text-end">{{ $readonlyValue('tsusanmae_keijo_' . $suffix) }}</div></td>
                <th rowspan="5" style="width:35px">第<br>1<br>次<br>通<br>算</th>
                <td class="text-end"><div class="readonly-span text-end">{{ $readonlyValue('after_1jitsusan_keijo_' . $suffix) }}</div></td>
                <th rowspan="6" style="width:35px">第<br>2<br>次<br>通<br>算</th>
                <td class="text-end"><div class="readonly-span text-end">{{ $readonlyValue('after_2jitsusan_keijo_' . $suffix) }}</div></td>
                <th rowspan="7" style="width:35px">第<br>3<br>次<br>通<br>算</th>
                <td class="text-end"><div class="readonly-span text-end">{{ $readonlyValue('after_3jitsusan_keijo_' . $suffix) }}</div></td>
                <td class="text-end"><div class="readonly-span text-end">{{ $readonlyValue('shotoku_keijo_' . $suffix) }}</div></td>
              </tr>
              <tr>
                <th class="text-start ps-1" rowspan="3" style="width:40px">譲渡</th>
                <th class="text-start ps-1" style="width:40px">短期</th>
                <th class="text-start ps-1 th-ddd" style="width:130px">総合</th>
                <td class="text-end"><div class="readonly-span text-end">{{ $readonlyValue('tsusanmae_joto_tanki_sogo_' . $suffix) }}</div></td>
                <td class="text-end"><div class="readonly-span text-end">{{ $readonlyValue('after_1jitsusan_joto_tanki_' . $suffix) }}</div></td>
                <td class="text-end"><div class="readonly-span text-end">{{ $readonlyValue('after_2jitsusan_joto_tanki_' . $suffix) }}</div></td>
                <td class="text-end"><div class="readonly-span text-end">{{ $readonlyValue('after_3jitsusan_joto_tanki_' . $suffix) }}</div></td>
                <td class="text-end"><div class="readonly-span text-end">{{ $readonlyValue('shotoku_joto_tanki_' . $suffix) }}</div></td>
              </tr>
              <tr>
                <th class="text-start ps-1" rowspan="2">長期</th>
                <th class="text-start ps-1 th-ddd">分離（特定損失額）</th>
                <td class="text-end"><div class="readonly-span text-end">{{ $readonlyValue('tsusanmae_joto_choki_bunri_' . $suffix) }}</div></td>
                <td class="text-end"><div class="readonly-span text-end">{{ $readonlyValue('after_1jitsusan_joto_choki_bunri_' . $suffix) }}</div></td>
                <td class="text-end"><div class="readonly-span text-end">{{ $readonlyValue('after_2jitsusan_joto_choki_bunri_' . $suffix) }}</div></td>
                <td class="text-end"><div class="readonly-span text-end">{{ $readonlyValue('after_3jitsusan_joto_choki_bunri_' . $suffix) }}</div></td>
                <td class="text-end"><div class="readonly-span text-end">{{ $readonlyValue('shotoku_joto_choki_bunri_' . $suffix) }}</div></td>
              </tr>
              <tr>
                <th class="text-start ps-1 th-ddd">総合</th>
                <td class="text-end"><div class="readonly-span text-end">{{ $readonlyValue('tsusanmae_joto_choki_sogo_' . $suffix) }}</div></td>
                <td class="text-end"><div class="readonly-span text-end">{{ $readonlyValue('after_1jitsusan_joto_choki_sogo_' . $suffix) }}</div></td>
                <td class="text-end"><div class="readonly-span text-end">{{ $readonlyValue('after_2jitsusan_joto_choki_sogo_' . $suffix) }}</div></td>
                <td class="text-end"><div class="readonly-span text-end">{{ $readonlyValue('after_3jitsusan_joto_choki_sogo_' . $suffix) }}</div></td>
                <td class="text-end"><div class="readonly-span text-end">{{ $readonlyValue('shotoku_joto_choki_sogo_' . $suffix) }}</div></td>
              </tr>
              <tr>
                <th colspan="3" class="text-start ps-1">一時</th>
                <td class="text-end"><div class="readonly-span text-end">{{ $readonlyValue('tsusanmae_ichiji_' . $suffix) }}</div></td>
                <td class="text-end"><div class="readonly-span text-end">{{ $readonlyValue('after_1jitsusan_ichiji_' . $suffix) }}</div></td>
                <td class="text-end"><div class="readonly-span text-end">{{ $readonlyValue('after_2jitsusan_ichiji_' . $suffix) }}</div></td>
                <td class="text-end"><div class="readonly-span text-end">{{ $readonlyValue('after_3jitsusan_ichiji_' . $suffix) }}</div></td>
                <td class="text-end"><div class="readonly-span text-end">{{ $readonlyValue('shotoku_ichiji_' . $suffix) }}</div></td>
              </tr>
              <tr>
                <th colspan="3" class="text-start ps-1">山林</th>
                <td colspan="2">
                  <div class="readonly-stack">
                    <div class="readonly-span text-center">⇒</div>
                  </div>
                </td>
                <td class="text-end"><div class="readonly-span text-end">{{ $readonlyValue('after_1jitsusan_sanrin_' . $suffix) }}</div></td>
                <td class="text-end"><div class="readonly-span text-end">{{ $readonlyValue('after_2jitsusan_sanrin_' . $suffix) }}</div></td>
                <td class="text-end"><div class="readonly-span text-end">{{ $readonlyValue('after_3jitsusan_sanrin_' . $suffix) }}</div></td>
                <td class="text-end"><div class="readonly-span text-end">{{ $readonlyValue('shotoku_sanrin_' . $suffix) }}</div></td>
              </tr>
              <tr>
                <th colspan="3" class="text-start ps-1">退職</th>
                <td colspan="4">
                  <div class="readonly-stack">
                    <div class="readonly-span text-center">⇒</div>
                  </div>
                </td>
                <td class="text-end"><div class="readonly-span text-end">{{ $readonlyValue('after_2jitsusan_taishoku_' . $suffix) }}</div></td>
                <td class="text-end"><div class="readonly-span text-end">{{ $readonlyValue('after_3jitsusan_taishoku_' . $suffix) }}</div></td>
                <td class="text-end"><div class="readonly-span text-end">{{ $readonlyValue('shotoku_taishoku_' . $suffix) }}</div></td>
              </tr>
              <tr>
                <th colspan="10" class="th-cream">所得金額の合計額</th>
                <td class="text-end"><div class="readonly-span text-end">{{ $readonlyValue('shotoku_gokei_' . $suffix) }}</div></td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    @endforeach
  </div>
</div>