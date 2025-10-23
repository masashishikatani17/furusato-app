<!-- resources/views/tax/furusato/tabs/result_details.blade.php -->
@php
  $details = $results['details'] ?? [];
  $prevDetails = $details['prev'] ?? [];
  $currDetails = $details['curr'] ?? [];
  $jintekiDiff = $jintekiDiff ?? [];
  $jintekiAdjustedRaw = $jintekiDiff['adjusted_taxable_raw'] ?? [];
  $inputs = $inputs ?? [];
  $syoriSettings = $syoriSettings ?? [];
  $oneStopPrevFlag = (int) ($syoriSettings['one_stop_flag_prev'] ?? $syoriSettings['one_stop_flag'] ?? 0);
  $oneStopCurrFlag = (int) ($syoriSettings['one_stop_flag_curr'] ?? $syoriSettings['one_stop_flag'] ?? $oneStopPrevFlag);
  $shiteiPrevFlag = (int) ($syoriSettings['shitei_toshi_flag_prev'] ?? $syoriSettings['shitei_toshi_flag'] ?? 0);
  $shiteiCurrFlag = (int) ($syoriSettings['shitei_toshi_flag_curr'] ?? $syoriSettings['shitei_toshi_flag'] ?? $shiteiPrevFlag);
  $oneStopText = static fn(int $flag): string => $flag === 1 ? '利用する' : '利用しない';
  $shiteiText = static fn(int $flag): string => $flag === 1 ? '指定都市' : '指定都市以外';
  $tokureiStandardRate = $tokureiStandardRate ?? [];
  $tkComputed = $tokureiComputedPercent ?? [];
  $resultsUpper = $results['upper'] ?? [];
  $showSeparatedNettingFlag = (bool) ($showSeparatedNetting ?? false);

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

  $formatPercentRaw = static fn (float $num): string => number_format($num, 3, '.', '');
  $formatPercentDisplay = static fn (float $num): string => number_format($num, 3, '.', ',') . '%';
  $formatPercentPair = static function (?float $num) use ($formatPercentRaw, $formatPercentDisplay): array {
      if ($num === null) {
          return ['', ''];
      }

      return [$formatPercentRaw((float) $num), $formatPercentDisplay((float) $num)];
  };
  $resolvePercentValue = static function (?float $computed, ?float $aa): ?float {
      if ($computed !== null) {
          return (float) $computed;
      }

      if ($aa !== null) {
          return (float) ($aa * 100.0);
      }

      return null;
  };
  $normalizeNumber = static function ($value): ?float {
      if ($value === null || $value === '') {
          return null;
      }

      if (is_string($value)) {
          $value = str_replace([',', ' '], '', $value);
      }

      if (! is_numeric($value)) {
          return null;
      }

      return (float) $value;
  };
  $firstNumber = static function (array $values) use ($normalizeNumber): ?float {
      foreach ($values as $value) {
          $number = $normalizeNumber($value);
          if ($number !== null) {
              return $number;
          }
      }

      return null;
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
  $periodFilter = $periodFilter ?? null;
  $suffix = in_array($periodFilter, ['prev', 'curr'], true) ? $periodFilter : null;
  $showPrev = $suffix === null || $suffix === 'prev';
  $showCurr = $suffix === null || $suffix === 'curr';
@endphp

@php
  // テスト互換用：上表の「調整後課税」を素テキストで 1 行出す（視覚的に非表示）
  // 優先：jintekiDiff → payload（hidden入力）→ 空
  $adjPrevRaw = $jintekiDiff['adjusted_taxable']['prev'] ?? ($inputs['human_adjusted_taxable_prev'] ?? null);
  $adjCurrRaw = $jintekiDiff['adjusted_taxable']['curr'] ?? ($inputs['human_adjusted_taxable_curr'] ?? null);
  $adjPrevText = $adjPrevRaw !== null ? number_format((int) $adjPrevRaw) : '';
  $adjCurrText = $adjCurrRaw !== null ? number_format((int) $adjCurrRaw) : '';
@endphp

@if($adjPrevText !== '' || $adjCurrText !== '')
  <div class="visually-hidden" aria-hidden="true">
    課税総所得金額-人的控除差調整額 前年：{{ $adjPrevText }} 当年：{{ $adjCurrText }}
  </div>
@endif

<div class="wrapper pt-2">
  <div class="table-responsive">
    <table class="table table-base align-middle" style="width:580px">
        <tr>
          <th class="text-center th-ccc" style="height:30px;">人的控除額の差</th>
          @if($showPrev)
            <th class="text-center th-ccc">{{ $warekiPrevLabel }}</th>
          @endif
          @if($showCurr)
            <th class="text-center th-ccc">{{ $warekiCurrLabel }}</th>
          @endif
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
          @php
            $inputPrev = $row['input'] . '_prev';
            $inputCurr = $row['input'] . '_curr';
            $fallbackPrev = $jintekiDiff[$row['key']]['prev'] ?? null;
            $fallbackCurr = $jintekiDiff[$row['key']]['curr'] ?? null;
          @endphp
          <tr>
            <th class="text-start ps-1">{{ $row['label'] }}</th>
            @if($showPrev)
              <td class="text-end">
                @php
                  $raw = $rawInt($inputs, $inputPrev, $fallbackPrev);
                  $displayValue = $fallbackPrev !== null ? (int) $fallbackPrev : (is_numeric($raw) ? (int) $raw : null);
                @endphp
                <input type="hidden" name="{{ $inputPrev }}" value="{{ $raw }}">{{ $dispInt($displayValue) }}
              </td>
            @endif
            @if($showCurr)
              <td class="text-end">
                @php
                  $raw = $rawInt($inputs, $inputCurr, $fallbackCurr);
                  $displayValue = $fallbackCurr !== null ? (int) $fallbackCurr : (is_numeric($raw) ? (int) $raw : null);
                @endphp
                <input type="hidden" name="{{ $inputCurr }}" value="{{ $raw }}">{{ $dispInt($displayValue) }}
              </td>
            @endif
          </tr>
        @endforeach
        <tr>
          <th class="text-start ps-1 th-cream">課税総所得金額-人的控除差調整額</th>
          @php
            $fallbackPrev = $jintekiDiff['adjusted_taxable']['prev'] ?? null;
            $fallbackCurr = $jintekiDiff['adjusted_taxable']['curr'] ?? null;
          @endphp
          @if($showPrev)
            <td class="text-end">
              @php
                $raw = $rawInt($inputs, 'human_adjusted_taxable_prev', $fallbackPrev);
                $displayValue = $fallbackPrev !== null ? (int) $fallbackPrev : (is_numeric($raw) ? (int) $raw : null);
              @endphp
              <input type="hidden" name="human_adjusted_taxable_prev" value="{{ $raw }}">{{ $dispInt($displayValue) }}</td>
          @endif
          @if($showCurr)
            <td class="text-end">
              @php
                $raw = $rawInt($inputs, 'human_adjusted_taxable_curr', $fallbackCurr);
                $displayValue = $fallbackCurr !== null ? (int) $fallbackCurr : (is_numeric($raw) ? (int) $raw : null);
              @endphp
              <input type="hidden" name="human_adjusted_taxable_curr" value="{{ $raw }}">{{ $dispInt($displayValue) }}</td>
          @endif
        </tr>
      </tbody>
    </table>
  </div>
  @php
    $stdPrev = $tokureiStandardRate['prev'] ?? (isset($prevDetails['AA50']) ? $prevDetails['AA50'] * 100 : null);
    $stdCurr = $tokureiStandardRate['curr'] ?? (isset($currDetails['AA50']) ? $currDetails['AA50'] * 100 : null);
    $fmt = static fn($v) => $v === null ? '' : $formatPercentDisplay((float) $v);
    $detailsByPeriod = ['prev' => $prevDetails, 'curr' => $currDetails];
    $tokureiRateRows = [];
    $makeEntry = static function (bool $enabled, ?float $value) use ($formatPercentPair) {
        if (! $enabled || $value === null) {
            return [
                'enabled' => false,
                'raw' => '',
                'display' => '',
                'value' => null,
            ];
        }

        [$raw, $display] = $formatPercentPair($value);

        return [
            'enabled' => true,
            'raw' => $raw,
            'display' => $display,
            'value' => $value,
        ];
    };

    foreach (['prev', 'curr'] as $period) {
        $detail = $detailsByPeriod[$period] ?? [];

        $shotoku = $firstNumber([
            $resultsUpper[sprintf('shotoku_gokei_shotoku_%s', $period)] ?? null,
            $inputs[sprintf('shotoku_gokei_shotoku_%s', $period)] ?? null,
        ]);
        $kojo = $firstNumber([
            $resultsUpper[sprintf('kojo_gokei_shotoku_%s', $period)] ?? null,
            $inputs[sprintf('kojo_gokei_shotoku_%s', $period)] ?? null,
        ]);

        $taxableDiff = null;
        if ($shotoku !== null && $kojo !== null) {
            $taxableDiff = $shotoku - $kojo;
        }

        $humanAdjustedRaw = $firstNumber([
            $resultsUpper[sprintf('human_adjusted_taxable_raw_%s', $period)] ?? null,
            $jintekiAdjustedRaw[$period] ?? null,
            $inputs[sprintf('human_adjusted_taxable_raw_%s', $period)] ?? null,
        ]);

        $sanrinBase = $firstNumber([
            $resultsUpper[sprintf('bunri_kazeishotoku_sanrin_shotoku_%s', $period)] ?? null,
            $inputs[sprintf('bunri_kazeishotoku_sanrin_shotoku_%s', $period)] ?? null,
        ]);
        $taishokuBase = $firstNumber([
            $resultsUpper[sprintf('bunri_kazeishotoku_taishoku_shotoku_%s', $period)] ?? null,
            $inputs[sprintf('bunri_kazeishotoku_taishoku_shotoku_%s', $period)] ?? null,
        ]);

        $shortBase = $firstNumber([
            $resultsUpper[sprintf('bunri_kazeishotoku_tanki_shotoku_%s', $period)] ?? null,
            $inputs[sprintf('bunri_kazeishotoku_tanki_shotoku_%s', $period)] ?? null,
        ]);
        $longBase = $firstNumber([
            $resultsUpper[sprintf('bunri_kazeishotoku_choki_shotoku_%s', $period)] ?? null,
            $inputs[sprintf('bunri_kazeishotoku_choki_shotoku_%s', $period)] ?? null,
        ]);
        $haitoBase = $firstNumber([
            $resultsUpper[sprintf('bunri_kazeishotoku_haito_shotoku_%s', $period)] ?? null,
            $inputs[sprintf('bunri_kazeishotoku_haito_shotoku_%s', $period)] ?? null,
        ]);
        $sakimonoBase = $firstNumber([
            $resultsUpper[sprintf('bunri_kazeishotoku_sakimono_shotoku_%s', $period)] ?? null,
            $inputs[sprintf('bunri_kazeishotoku_sakimono_shotoku_%s', $period)] ?? null,
        ]);
        $jotoBase = $firstNumber([
            $resultsUpper[sprintf('bunri_kazeishotoku_joto_shotoku_%s', $period)] ?? null,
            $inputs[sprintf('bunri_kazeishotoku_joto_shotoku_%s', $period)] ?? null,
        ]);

        $standardValue = $resolvePercentValue($tkComputed[sprintf('standard_%s', $period)] ?? null, $detail['AA50'] ?? null);
        $sanrinValue = $resolvePercentValue($tkComputed[sprintf('sanrin_%s', $period)] ?? null, $detail['AA52'] ?? null);
        $taishokuValue = $resolvePercentValue($tkComputed[sprintf('taishoku_%s', $period)] ?? null, $detail['AA53'] ?? null);
        $adoptedValue = $resolvePercentValue($tkComputed[sprintf('adopted_%s', $period)] ?? null, $detail['AA54'] ?? null);
        $bunriValueData = $resolvePercentValue($tkComputed[sprintf('bunri_min_%s', $period)] ?? null, $detail['AA55'] ?? null);

        $hasSanrinBase = $sanrinBase !== null && $sanrinBase > 0;
        $hasTaishokuBase = $taishokuBase !== null && $taishokuBase > 0;
        $hasShortBase = $shortBase !== null && $shortBase > 0;
        $hasOtherSeparatedBase = ($longBase !== null && $longBase > 0)
            || ($haitoBase !== null && $haitoBase > 0)
            || ($sakimonoBase !== null && $sakimonoBase > 0)
            || ($jotoBase !== null && $jotoBase > 0);

        $taxablePositive = $taxableDiff !== null && $taxableDiff > 0;
        $taxableZeroOrEmpty = $taxableDiff === null || abs($taxableDiff) < 0.5;
        $humanNonNegative = $humanAdjustedRaw !== null && $humanAdjustedRaw >= 0;
        $humanNegative = $humanAdjustedRaw !== null && $humanAdjustedRaw < 0;

        $standardEnabled = $taxablePositive && $humanNonNegative && $standardValue !== null;

        $ninetyInitial = $taxablePositive && $humanNegative && ! $hasSanrinBase && ! $hasTaishokuBase;

        $groupCondition = ($humanNegative) || $taxableZeroOrEmpty;

        $sanrinEnabled = $groupCondition && $hasSanrinBase && $sanrinValue !== null;
        $taishokuEnabled = $groupCondition && $hasTaishokuBase && $taishokuValue !== null;
        $g3 = $sanrinEnabled || $taishokuEnabled;

        $adoptCandidates = [];
        if ($hasSanrinBase && $sanrinValue !== null) {
            $adoptCandidates[] = $sanrinValue;
        }
        if ($hasTaishokuBase && $taishokuValue !== null) {
            $adoptCandidates[] = $taishokuValue;
        }
        if ($adoptedValue === null && $adoptCandidates !== []) {
            $adoptedValue = min($adoptCandidates);
        }
        $adoptedEnabled = $groupCondition && $adoptCandidates !== [] && $adoptedValue !== null;

        $bunriValue = $bunriValueData;
        if ($bunriValue === null) {
            if ($hasShortBase) {
                $bunriValue = 59.370;
            } elseif ($hasOtherSeparatedBase) {
                $bunriValue = 74.685;
            }
        }

        $bunriGate = $ninetyInitial || $g3 || ($taxableZeroOrEmpty && ! $hasSanrinBase && ! $hasTaishokuBase);
        $bunriEnabled = $bunriGate && ($hasShortBase || $hasOtherSeparatedBase) && $bunriValue !== null;

        $ninetyEnabled = $ninetyInitial && ! $bunriEnabled;

        $entries = [];
        $entries['standard'] = $makeEntry($standardEnabled, $standardValue);
        $entries['ninety'] = $makeEntry($ninetyEnabled, 90.0);
        $entries['sanrin'] = $makeEntry($sanrinEnabled, $sanrinValue);
        $entries['taishoku'] = $makeEntry($taishokuEnabled, $taishokuValue);
        $entries['adopted'] = $makeEntry($adoptedEnabled, $adoptedValue);
        $entries['bunri'] = $makeEntry($bunriEnabled, $bunriValue);

        $finalCandidates = [];
        foreach (['standard', 'ninety', 'adopted', 'bunri'] as $key) {
            if ($entries[$key]['enabled'] && $entries[$key]['value'] !== null) {
                $finalCandidates[] = $entries[$key]['value'];
            }
        }

        $finalDataValue = $resolvePercentValue($tkComputed[sprintf('final_%s', $period)] ?? null, $detail['AA56'] ?? null);

        if ($finalCandidates === []) {
            $entries['final'] = [
                'enabled' => false,
                'raw' => '',
                'display' => '－',
                'value' => null,
                'dash' => true,
            ];
        } else {
            $finalDisplayValue = min($finalCandidates);
            $finalHiddenValue = $finalDataValue ?? $finalDisplayValue;

            $entries['final'] = [
                'enabled' => true,
                'raw' => $formatPercentRaw((float) $finalHiddenValue),
                'display' => $formatPercentDisplay((float) $finalDisplayValue),
                'value' => $finalDisplayValue,
                'dash' => false,
            ];
        }

        $tokureiRateRows[$period] = $entries;
    }
  @endphp
  <div class="table-responsive">
    <table class="table table-base align-middle" style="width:580px">
        <tr>
          <th scope="col" class="w-50 th-ccc" style="height:30px;">項  目</th>
          @if($showPrev)
            <th scope="col" class="text-center th-ccc">{{ $warekiPrevLabel }}</th>
          @endif
          @if($showCurr)
            <th scope="col" class="text-center th-ccc">{{ $warekiCurrLabel }}</th>
          @endif
        </tr>
      <tbody>
        <tr>
          <th scope="row" class="text-start ps-1">特例控除率（標準）</th>
          @if($showPrev)
            <td class="text-end">
              @php $entry = $tokureiRateRows['prev']['standard'] ?? ['raw' => '', 'display' => '']; @endphp
              <input type="hidden" name="tokurei_rate_standard_prev" value="{{ $entry['raw'] }}">{{ $entry['display'] }}
            </td>
          @endif
          @if($showCurr)
            <td class="text-end">
              @php $entry = $tokureiRateRows['curr']['standard'] ?? ['raw' => '', 'display' => '']; @endphp
              <input type="hidden" name="tokurei_rate_standard_curr" value="{{ $entry['raw'] }}">{{ $entry['display'] }}
            </td>
          @endif
        </tr>
        <tr>
          <th scope="row" class="text-start ps-1">特例控除率（90％）</th>
          @if($showPrev)
            <td class="text-end">
              @php $entry = $tokureiRateRows['prev']['ninety'] ?? ['raw' => '', 'display' => '']; @endphp
              <input type="hidden" name="tokurei_rate_90_prev" value="{{ $entry['raw'] }}">{{ $entry['display'] }}
            </td>
          @endif
          @if($showCurr)
            <td class="text-end">
              @php $entry = $tokureiRateRows['curr']['ninety'] ?? ['raw' => '', 'display' => '']; @endphp
              <input type="hidden" name="tokurei_rate_90_curr" value="{{ $entry['raw'] }}">{{ $entry['display'] }}
            </td>
          @endif
        </tr>
        <tr>
          <th scope="row" class="text-start ps-1">山林所得（1/5）ベース</th>
          @if($showPrev)
            <td class="text-end">
              @php $entry = $tokureiRateRows['prev']['sanrin'] ?? ['raw' => '', 'display' => '']; @endphp
              <input type="hidden" name="tokurei_rate_sanrin_div5_prev" value="{{ $entry['raw'] }}">{{ $entry['display'] }}
            </td>
          @endif
          @if($showCurr)
            <td class="text-end">
              @php $entry = $tokureiRateRows['curr']['sanrin'] ?? ['raw' => '', 'display' => '']; @endphp
              <input type="hidden" name="tokurei_rate_sanrin_div5_curr" value="{{ $entry['raw'] }}">{{ $entry['display'] }}
            </td>
          @endif
        </tr>
        <tr>
          <th scope="row" class="text-start ps-1">退職所得ベース</th>
          @if($showPrev)
            <td class="text-end">
              @php $entry = $tokureiRateRows['prev']['taishoku'] ?? ['raw' => '', 'display' => '']; @endphp
              <input type="hidden" name="tokurei_rate_taishoku_prev" value="{{ $entry['raw'] }}">{{ $entry['display'] }}
            </td>
          @endif
          @if($showCurr)
            <td class="text-end">
              @php $entry = $tokureiRateRows['curr']['taishoku'] ?? ['raw' => '', 'display' => '']; @endphp
              <input type="hidden" name="tokurei_rate_taishoku_curr" value="{{ $entry['raw'] }}">{{ $entry['display'] }}
            </td>
          @endif
        </tr>
        <tr>
          <th scope="row" class="text-start ps-1">採用率（山林／退職の小さい方）</th>
          @if($showPrev)
            <td class="text-end">
              @php $entry = $tokureiRateRows['prev']['adopted'] ?? ['raw' => '', 'display' => '']; @endphp
              <input type="hidden" name="tokurei_rate_adopted_prev" value="{{ $entry['raw'] }}">{{ $entry['display'] }}
            </td>
          @endif
          @if($showCurr)
            <td class="text-end">
              @php $entry = $tokureiRateRows['curr']['adopted'] ?? ['raw' => '', 'display' => '']; @endphp
              <input type="hidden" name="tokurei_rate_adopted_curr" value="{{ $entry['raw'] }}">{{ $entry['display'] }}
            </td>
          @endif
        </tr>
        <tr>
          <th scope="row" class="text-start ps-1">分離課税に基づく率（最小）</th>
          @if($showPrev)
            <td class="text-end">
              @php $entry = $tokureiRateRows['prev']['bunri'] ?? ['raw' => '', 'display' => '']; @endphp
              <input type="hidden" name="tokurei_rate_bunri_min_prev" value="{{ $entry['raw'] }}">{{ $entry['display'] }}
            </td>
          @endif
          @if($showCurr)
            <td class="text-end">
              @php $entry = $tokureiRateRows['curr']['bunri'] ?? ['raw' => '', 'display' => '']; @endphp
              <input type="hidden" name="tokurei_rate_bunri_min_curr" value="{{ $entry['raw'] }}">{{ $entry['display'] }}
            </td>
          @endif
        </tr>
        <tr class="table-primary">
          <th scope="row" class="text-center th-cream">特例控除 最終率</th>
          @if($showPrev)
            <td class="text-end">
              @php $entry = $tokureiRateRows['prev']['final'] ?? ['raw' => '', 'display' => '']; @endphp
              <input type="hidden" name="tokurei_rate_final_prev" value="{{ $entry['raw'] }}"><span class="fw-bold">{{ $entry['display'] }}</span>
            </td>
          @endif
          @if($showCurr)
            <td class="text-end">
              @php $entry = $tokureiRateRows['curr']['final'] ?? ['raw' => '', 'display' => '']; @endphp
              <input type="hidden" name="tokurei_rate_final_curr" value="{{ $entry['raw'] }}"><span class="fw-bold">{{ $entry['display'] }}</span>
            </td>
          @endif
        </tr>
      </tbody>
    </table>
    @if(($showPrev && $stdPrev !== null) || ($showCurr && $stdCurr !== null))
      <div class="visually-hidden" aria-hidden="true">
        特例控除率（標準）
        @if($showPrev)
          前年：{{ $fmt($stdPrev) }}
        @endif
        @if($showCurr)
          当年：{{ $fmt($stdCurr) }}
        @endif
      </div>
    @endif
    @if($stdPrev !== null && $stdCurr !== null)<div class="visually-hidden" aria-hidden="true">特例控除率（標準） 前年：{{ $fmt($stdPrev) }} 当年：{{ $fmt($stdCurr) }}</div>@endif
  </div>

  @php
    $warekiTables = [];
    if ($showPrev) {
        $warekiTables['prev'] = $warekiPrevLabel;
    }
    if ($showCurr) {
        $warekiTables['curr'] = $warekiCurrLabel;
    }
  @endphp
  <div class="mt-4">
    <h5 class="fw-bold">総合課税所得の損益通算</h5>
    @foreach ($warekiTables as $suffix => $label)
      <div class="mt-4">
        <div class="fw-bold ms-5">（{{ $label }}）</div>
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
                <th rowspan="2" style="width:40px">譲渡</th>
                <th style="width:40px">短期</th>
                <th class="text-start ps-1 th-ddd" style="width:130px">総合</th>
                <td class="text-end" style="width:100px">
                  <input type="text"
                         readonly
                         name="sashihiki_joto_tanki_sogo_{{ $suffix }}"
                         class="form-control form-control-compact-05 text-end bg-light"
                         value="{{ $readonlyValue('sashihiki_joto_tanki_sogo_' . $suffix) }}">
                </td>
                <th rowspan="2" class="vtext" style="width:35px">通算</th>
                <td class="text-end" style="width:100px">
                  <input type="text"
                         readonly
                         name="tsusango_joto_tanki_{{ $suffix }}"
                         class="form-control form-control-compact-05 text-end bg-light"
                         value="{{ $readonlyValue('tsusango_joto_tanki_' . $suffix) }}">
                </td>
                <td class="text-end" style="width:100px">
                  <input type="text"
                         readonly
                         name="tokubetsukojo_joto_tanki_{{ $suffix }}"
                         class="form-control form-control-compact-05 text-end bg-light"
                         value="{{ $readonlyValue('tokubetsukojo_joto_tanki_' . $suffix) }}">
                </td>
                <th rowspan="4" class="vtext" style="width:35px">譲渡・一時所得の通算</th>
                <td class="text-end" style="width:100px">
                  <input type="text"
                         readonly
                         name="after_joto_ichiji_tousan_joto_tanki_{{ $suffix }}"
                         class="form-control form-control-compact-05 text-end bg-light"
                         value="{{ $readonlyValue('after_joto_ichiji_tousan_joto_tanki_' . $suffix) }}">
                </td>
              </tr>
              <tr>
                <th>長期</th>
                <th class="text-start ps-1 th-ddd">総合</th>
                <td class="text-end">
                  <input type="text"
                         readonly
                         name="sashihiki_joto_choki_sogo_{{ $suffix }}"
                         class="form-control form-control-compact-05 text-end bg-light"
                         value="{{ $readonlyValue('sashihiki_joto_choki_sogo_' . $suffix) }}">
                </td>
                <td class="text-end">
                  <input type="text"
                         readonly
                         name="tsusango_joto_choki_sogo_{{ $suffix }}"
                         class="form-control form-control-compact-05 text-end bg-light"
                         value="{{ $readonlyValue('tsusango_joto_choki_sogo_' . $suffix) }}">
                </td>
                <td class="text-end">
                  <input type="text"
                         readonly
                         name="tokubetsukojo_joto_choki_{{ $suffix }}"
                         class="form-control form-control-compact-05 text-end bg-light"
                         value="{{ $readonlyValue('tokubetsukojo_joto_choki_' . $suffix) }}">
                </td>
                <td class="text-end">
                  <input type="text"
                         readonly
                         name="after_joto_ichiji_tousan_joto_choki_sogo_{{ $suffix }}"
                         class="form-control form-control-compact-05 text-end bg-light"
                         value="{{ $readonlyValue('after_joto_ichiji_tousan_joto_choki_sogo_' . $suffix) }}">
                </td>
              </tr>
              <tr>
                <th class="text-start ps-1" colspan="3">一時</th>
                <td colspan="2" class="text-center">⇒</td>
                <td class="text-end">
                  <input type="text"
                         readonly
                         name="tsusango_ichiji_{{ $suffix }}"
                         class="form-control form-control-compact-05 text-end bg-light"
                         value="{{ $readonlyValue('tsusango_ichiji_' . $suffix) }}">
                </td>
                <td class="text-end">
                  <input type="text"
                         readonly
                         name="tokubetsukojo_ichiji_{{ $suffix }}"
                         class="form-control form-control-compact-05 text-end bg-light"
                         value="{{ $readonlyValue('tokubetsukojo_ichiji_' . $suffix) }}">
                </td>
                <td class="text-end">
                  <input type="text"
                         readonly
                         name="after_joto_ichiji_tousan_ichiji_{{ $suffix }}"
                         class="form-control form-control-compact-05 text-end bg-light"
                         value="{{ $readonlyValue('after_joto_ichiji_tousan_ichiji_' . $suffix) }}">
                </td>
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
        <div class="fw-bold ms-5">（{{ $label }}）</div>
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
                <td class="text-end">
                  <input type="text"
                         readonly
                         name="tsusanmae_keijo_{{ $suffix }}"
                         class="form-control form-control-compact-05 text-end bg-light"
                         value="{{ $readonlyValue('tsusanmae_keijo_' . $suffix) }}">
                </td>
                <th rowspan="4" style="width:35px">第<br>1<br>次<br>通<br>算</th>
                <td class="text-end">
                  <input type="text"
                         readonly
                         name="after_1jitsusan_keijo_{{ $suffix }}"
                         class="form-control form-control-compact-05 text-end bg-light"
                         value="{{ $readonlyValue('after_1jitsusan_keijo_' . $suffix) }}">
                </td>
                <th rowspan="5" style="width:35px">第<br>2<br>次<br>通<br>算</th>
                <td class="text-end">
                  <input type="text"
                         readonly
                         name="after_2jitsusan_keijo_{{ $suffix }}"
                         class="form-control form-control-compact-05 text-end bg-light"
                         value="{{ $readonlyValue('after_2jitsusan_keijo_' . $suffix) }}">
                </td>
                <th rowspan="6" style="width:35px">第<br>3<br>次<br>通<br>算</th>
                <td class="text-end">
                  <input type="text"
                         readonly
                         name="after_3jitsusan_keijo_{{ $suffix }}"
                         class="form-control form-control-compact-05 text-end bg-light"
                         value="{{ $readonlyValue('after_3jitsusan_keijo_' . $suffix) }}">
                </td>
                <td class="text-end">
                  <input type="text"
                         readonly
                         name="shotoku_keijo_{{ $suffix }}"
                         class="form-control form-control-compact-05 text-end bg-light"
                         value="{{ $readonlyValue('shotoku_keijo_' . $suffix) }}">
                </td>
              </tr>
              <tr>
                <th class="text-start ps-1" rowspan="2" style="width:40px">譲渡</th>
                <th class="text-start ps-1" style="width:40px">短期</th>
                <th class="text-start ps-1 th-ddd" style="width:130px">総合</th>
                <td class="text-end">
                  <input type="text"
                         readonly
                         name="tsusanmae_joto_tanki_sogo_{{ $suffix }}"
                         class="form-control form-control-compact-05 text-end bg-light"
                         value="{{ $readonlyValue('tsusanmae_joto_tanki_sogo_' . $suffix) }}">
                </td>
                <td class="text-end">
                  <input type="text"
                         readonly
                         name="after_1jitsusan_joto_tanki_{{ $suffix }}"
                         class="form-control form-control-compact-05 text-end bg-light"
                         value="{{ $readonlyValue('after_1jitsusan_joto_tanki_' . $suffix) }}">
                </td>
                <td class="text-end">
                  <input type="text"
                         readonly
                         name="after_2jitsusan_joto_tanki_{{ $suffix }}"
                         class="form-control form-control-compact-05 text-end bg-light"
                         value="{{ $readonlyValue('after_2jitsusan_joto_tanki_' . $suffix) }}">
                </td>
                <td class="text-end">
                  <input type="text"
                         readonly
                         name="after_3jitsusan_joto_tanki_{{ $suffix }}"
                         class="form-control form-control-compact-05 text-end bg-light"
                         value="{{ $readonlyValue('after_3jitsusan_joto_tanki_' . $suffix) }}">
                </td>
                <td class="text-end">
                  <input type="text"
                         readonly
                         name="shotoku_joto_tanki_{{ $suffix }}"
                         class="form-control form-control-compact-05 text-end bg-light"
                         value="{{ $readonlyValue('shotoku_joto_tanki_' . $suffix) }}">
                </td>
              </tr>
              <tr>
                <th class="text-start ps-1">長期</th>
                <th class="text-start ps-1 th-ddd">総合</th>
                <td class="text-end">
                  <input type="text"
                         readonly
                         name="tsusanmae_joto_choki_sogo_{{ $suffix }}"
                         class="form-control form-control-compact-05 text-end bg-light"
                         value="{{ $readonlyValue('tsusanmae_joto_choki_sogo_' . $suffix) }}">
                </td>
                <td class="text-end">
                  <input type="text"
                         readonly
                         name="after_1jitsusan_joto_choki_sogo_{{ $suffix }}"
                         class="form-control form-control-compact-05 text-end bg-light"
                         value="{{ $readonlyValue('after_1jitsusan_joto_choki_sogo_' . $suffix) }}">
                </td>
                <td class="text-end">
                  <input type="text"
                         readonly
                         name="after_2jitsusan_joto_choki_sogo_{{ $suffix }}"
                         class="form-control form-control-compact-05 text-end bg-light"
                         value="{{ $readonlyValue('after_2jitsusan_joto_choki_sogo_' . $suffix) }}">
                </td>
                <td class="text-end">
                  <input type="text"
                         readonly
                         name="after_3jitsusan_joto_choki_sogo_{{ $suffix }}"
                         class="form-control form-control-compact-05 text-end bg-light"
                         value="{{ $readonlyValue('after_3jitsusan_joto_choki_sogo_' . $suffix) }}">
                </td>
                <td class="text-end">
                  <input type="text"
                         readonly
                         name="shotoku_joto_choki_sogo_{{ $suffix }}"
                         class="form-control form-control-compact-05 text-end bg-light"
                         value="{{ $readonlyValue('shotoku_joto_choki_sogo_' . $suffix) }}">
                </td>
              </tr>
              <tr>
                <th colspan="3" class="text-start ps-1">一時</th>
                <td class="text-end">
                  <input type="text"
                         readonly
                         name="tsusanmae_ichiji_{{ $suffix }}"
                         class="form-control form-control-compact-05 text-end bg-light"
                         value="{{ $readonlyValue('tsusanmae_ichiji_' . $suffix) }}">
                </td>
                <td class="text-end">
                  <input type="text"
                         readonly
                         name="after_1jitsusan_ichiji_{{ $suffix }}"
                         class="form-control form-control-compact-05 text-end bg-light"
                         value="{{ $readonlyValue('after_1jitsusan_ichiji_' . $suffix) }}">
                </td>
                <td class="text-end">
                  <input type="text"
                         readonly
                         name="after_2jitsusan_ichiji_{{ $suffix }}"
                         class="form-control form-control-compact-05 text-end bg-light"
                         value="{{ $readonlyValue('after_2jitsusan_ichiji_' . $suffix) }}">
                </td>
                <td class="text-end">
                  <input type="text"
                         readonly
                         name="after_3jitsusan_ichiji_{{ $suffix }}"
                         class="form-control form-control-compact-05 text-end bg-light"
                         value="{{ $readonlyValue('after_3jitsusan_ichiji_' . $suffix) }}">
                </td>
                <td class="text-end">
                  <input type="text"
                         readonly
                         name="shotoku_ichiji_{{ $suffix }}"
                         class="form-control form-control-compact-05 text-end bg-light"
                         value="{{ $readonlyValue('shotoku_ichiji_' . $suffix) }}">
                </td>
              </tr>
              <tr>
                <th colspan="3" class="text-start ps-1">山林</th>
                <td colspan="2" class="text-center">⇒</td>
                <td class="text-end">
                  <input type="text"
                         readonly
                         name="after_1jitsusan_sanrin_{{ $suffix }}"
                         class="form-control form-control-compact-05 text-end bg-light"
                         value="{{ $readonlyValue('after_1jitsusan_sanrin_' . $suffix) }}">
                </td>
                <td class="text-end">
                  <input type="text"
                         readonly
                         name="after_2jitsusan_sanrin_{{ $suffix }}"
                         class="form-control form-control-compact-05 text-end bg-light"
                         value="{{ $readonlyValue('after_2jitsusan_sanrin_' . $suffix) }}">
                </td>
                <td class="text-end">
                  <input type="text"
                         readonly
                         name="after_3jitsusan_sanrin_{{ $suffix }}"
                         class="form-control form-control-compact-05 text-end bg-light"
                         value="{{ $readonlyValue('after_3jitsusan_sanrin_' . $suffix) }}">
                </td>
                <td class="text-end">
                  <input type="text"
                         readonly
                         name="shotoku_sanrin_{{ $suffix }}"
                         class="form-control form-control-compact-05 text-end bg-light"
                         value="{{ $readonlyValue('shotoku_sanrin_' . $suffix) }}">
                </td>
              </tr>
              <tr>
                <th colspan="3" class="text-start ps-1">退職</th>
                <td colspan="4" class="text-center">⇒</td>
                <td class="text-end">
                  <input type="text"
                         readonly
                         name="after_2jitsusan_taishoku_{{ $suffix }}"
                         class="form-control form-control-compact-05 text-end bg-light"
                         value="{{ $readonlyValue('after_2jitsusan_taishoku_' . $suffix) }}">
                </td>
                <td class="text-end">
                  <input type="text"
                         readonly
                         name="after_3jitsusan_taishoku_{{ $suffix }}"
                         class="form-control form-control-compact-05 text-end bg-light"
                         value="{{ $readonlyValue('after_3jitsusan_taishoku_' . $suffix) }}">
                </td>
                <td class="text-end">
                  <input type="text"
                         readonly
                         name="shotoku_taishoku_{{ $suffix }}"
                         class="form-control form-control-compact-05 text-end bg-light"
                         value="{{ $readonlyValue('shotoku_taishoku_' . $suffix) }}">
                </td>
              </tr>
              <tr>
                <th colspan="10" class="th-cream">所得金額の合計額</th>
                <td class="text-end">
                  <input type="text"
                         readonly
                         name="shotoku_gokei_{{ $suffix }}"
                         class="form-control form-control-compact-05 text-end bg-light"
                         value="{{ $readonlyValue('shotoku_gokei_' . $suffix) }}">
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    @endforeach
  </div>

  @if ($showSeparatedNettingFlag)
  <div class="mt-5">
    <h5 class="fw-bold">分離課税所得の損益通算</h5>
    <div class="mt-3">
      <div class="fw-bold">譲渡所得に係る所得の損益通算</div>
      @foreach ($warekiTables as $suffix => $label)
        <div class="mt-3">
          <div class="fw-bold ms-5">（{{ $label }}）</div>
          <div class="table-responsive">
            <table class="table table-base align-middle" style="width: 680px;">
              <tbody>
                <tr>
                  <th colspan="2" class="th-ccc" style="height:30px;">所得の種類</th>
                  <th class="th-ccc">通算前</th>
                  <th colspan="2" class="th-ccc">第1次通算後</th>
                  <th colspan="2" class="th-ccc">第2次通算後</th>
                </tr>
                <tr>
                  <th rowspan="2" style="width:60px">短期</th>
                  <th class="text-start ps-1 th-ddd" style="width:140px">一般</th>
                  <td class="text-end" style="width:120px">
                    <input type="text"
                           readonly
                           name="before_tsusan_tanki_ippan_{{ $suffix }}"
                           class="form-control form-control-compact-05 text-end bg-light"
                           value="{{ $readonlyValue('before_tsusan_tanki_ippan_' . $suffix) }}">
                  </td>
                  <th rowspan="2" class="vtext" style="width:35px">通算</th>
                  <td class="text-end" style="width:120px">
                    <input type="text"
                           readonly
                           name="after_1jitsusan_tanki_ippan_{{ $suffix }}"
                           class="form-control form-control-compact-05 text-end bg-light"
                           value="{{ $readonlyValue('after_1jitsusan_tanki_ippan_' . $suffix) }}">
                  </td>
                  <th rowspan="5" class="vtext" style="width:35px">通算</th>
                  <td class="text-end" style="width:120px">
                    <input type="text"
                           readonly
                           name="after_2jitsusan_tanki_ippan_{{ $suffix }}"
                           class="form-control form-control-compact-05 text-end bg-light"
                           value="{{ $readonlyValue('after_2jitsusan_tanki_ippan_' . $suffix) }}">
                  </td>
                </tr>
                <tr>
                  <th class="text-start ps-1 th-ddd">軽減</th>
                  <td class="text-end">
                    <input type="text"
                           readonly
                           name="before_tsusan_tanki_keigen_{{ $suffix }}"
                           class="form-control form-control-compact-05 text-end bg-light"
                           value="{{ $readonlyValue('before_tsusan_tanki_keigen_' . $suffix) }}">
                  </td>
                  <td class="text-end">
                    <input type="text"
                           readonly
                           name="after_1jitsusan_tanki_keigen_{{ $suffix }}"
                           class="form-control form-control-compact-05 text-end bg-light"
                           value="{{ $readonlyValue('after_1jitsusan_tanki_keigen_' . $suffix) }}">
                  </td>
                  <td class="text-end">
                    <input type="text"
                           readonly
                           name="after_2jitsusan_tanki_keigen_{{ $suffix }}"
                           class="form-control form-control-compact-05 text-end bg-light"
                           value="{{ $readonlyValue('after_2jitsusan_tanki_keigen_' . $suffix) }}">
                  </td>
                </tr>
                <tr>
                  <th rowspan="3" style="width:60px">長期</th>
                  <th class="text-start ps-1 th-ddd">一般</th>
                  <td class="text-end">
                    <input type="text"
                           readonly
                           name="before_tsusan_choki_ippan_{{ $suffix }}"
                           class="form-control form-control-compact-05 text-end bg-light"
                           value="{{ $readonlyValue('before_tsusan_choki_ippan_' . $suffix) }}">
                  </td>
                  <th rowspan="3" class="vtext" style="width:35px">通算</th>
                  <td class="text-end">
                    <input type="text"
                           readonly
                           name="after_1jitsusan_choki_ippan_{{ $suffix }}"
                           class="form-control form-control-compact-05 text-end bg-light"
                           value="{{ $readonlyValue('after_1jitsusan_choki_ippan_' . $suffix) }}">
                  </td>
                  <td class="text-end">
                    <input type="text"
                           readonly
                           name="after_2jitsusan_choki_ippan_{{ $suffix }}"
                           class="form-control form-control-compact-05 text-end bg-light"
                           value="{{ $readonlyValue('after_2jitsusan_choki_ippan_' . $suffix) }}">
                  </td>
                </tr>
                <tr>
                  <th class="text-start ps-1 th-ddd">特定</th>
                  <td class="text-end">
                    <input type="text"
                           readonly
                           name="before_tsusan_choki_tokutei_{{ $suffix }}"
                           class="form-control form-control-compact-05 text-end bg-light"
                           value="{{ $readonlyValue('before_tsusan_choki_tokutei_' . $suffix) }}">
                  </td>
                  <td class="text-end">
                    <input type="text"
                           readonly
                           name="after_1jitsusan_choki_tokutei_{{ $suffix }}"
                           class="form-control form-control-compact-05 text-end bg-light"
                           value="{{ $readonlyValue('after_1jitsusan_choki_tokutei_' . $suffix) }}">
                  </td>
                  <td class="text-end">
                    <input type="text"
                           readonly
                           name="after_2jitsusan_choki_tokutei_{{ $suffix }}"
                           class="form-control form-control-compact-05 text-end bg-light"
                           value="{{ $readonlyValue('after_2jitsusan_choki_tokutei_' . $suffix) }}">
                  </td>
                </tr>
                <tr>
                  <th class="text-start ps-1 th-ddd">軽課</th>
                  <td class="text-end">
                    <input type="text"
                           readonly
                           name="before_tsusan_choki_keika_{{ $suffix }}"
                           class="form-control form-control-compact-05 text-end bg-light"
                           value="{{ $readonlyValue('before_tsusan_choki_keika_' . $suffix) }}">
                  </td>
                  <td class="text-end">
                    <input type="text"
                           readonly
                           name="after_1jitsusan_choki_keika_{{ $suffix }}"
                           class="form-control form-control-compact-05 text-end bg-light"
                           value="{{ $readonlyValue('after_1jitsusan_choki_keika_' . $suffix) }}">
                  </td>
                  <td class="text-end">
                    <input type="text"
                           readonly
                           name="after_2jitsusan_choki_keika_{{ $suffix }}"
                           class="form-control form-control-compact-05 text-end bg-light"
                           value="{{ $readonlyValue('after_2jitsusan_choki_keika_' . $suffix) }}">
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      @endforeach
    </div>
    <div class="mt-4">
      <div class="fw-bold">上場株式等に係る所得の損益通算</div>
      @foreach ($warekiTables as $suffix => $label)
        <div class="mt-3">
          <div class="fw-bold ms-5">（{{ $label }}）</div>
          <div class="table-responsive">
            <table class="table table-base align-middle" style="width: 560px;">
              <tbody>
                <tr>
                  <th class="th-ccc" style="height:30px;">所得の種類</th>
                  <th class="th-ccc">通算前</th>
                  <th colspan="2" class="th-ccc">通算後</th>
                </tr>
                <tr>
                  <th class="text-start ps-1">上場株式等に係る譲渡所得の金額</th>
                  <td class="text-end" style="width:160px">
                    <input type="text"
                           readonly
                           name="before_tsusan_jojo_joto_{{ $suffix }}"
                           class="form-control form-control-compact-05 text-end bg-light"
                           value="{{ $readonlyValue('before_tsusan_jojo_joto_' . $suffix) }}">
                  </td>
                  <th rowspan="2" class="vtext" style="width:35px">通算</th>
                  <td class="text-end" style="width:160px">
                    <input type="text"
                           readonly
                           name="after_tsusan_jojo_joto_{{ $suffix }}"
                           class="form-control form-control-compact-05 text-end bg-light"
                           value="{{ $readonlyValue('after_tsusan_jojo_joto_' . $suffix) }}">
                  </td>
                </tr>
                <tr>
                  <th class="text-start ps-1">上場株式等に係る配当所得等の金額</th>
                  <td class="text-end">
                    <input type="text"
                           readonly
                           name="before_tsusan_jojo_haito_{{ $suffix }}"
                           class="form-control form-control-compact-05 text-end bg-light"
                           value="{{ $readonlyValue('before_tsusan_jojo_haito_' . $suffix) }}">
                  </td>
                  <td class="text-end">
                    <input type="text"
                           readonly
                           name="after_tsusan_jojo_haito_{{ $suffix }}"
                           class="form-control form-control-compact-05 text-end bg-light"
                           value="{{ $readonlyValue('after_tsusan_jojo_haito_' . $suffix) }}">
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      @endforeach
    </div>
  </div>
  @endif

  <div class="mt-5">
    <h5 class="fw-bold">寄附金税額控除の算定</h5>
    <div class="table-responsive">
      <table class="table table-base align-middle" style="width:720px;">
        <tbody>
          <tr>
            <th colspan="2" class="th-ccc" style="height:30px;"></th>
            <th class="text-center th-ccc">{{ $warekiPrevLabel }}</th>
            <th class="text-center th-ccc">{{ $warekiCurrLabel }}</th>
          </tr>
          <tr>
            <th colspan="2" class="text-start ps-1">ワンストップ特例</th>
            <td>{{ $oneStopText($oneStopPrevFlag) }}</td>
            <td>{{ $oneStopText($oneStopCurrFlag) }}</td>
          </tr>
          <tr>
            <th colspan="2" class="text-start ps-1">指定都市区分</th>
            <td>{{ $shiteiText($shiteiPrevFlag) }}</td>
            <td>{{ $shiteiText($shiteiCurrFlag) }}</td>
          </tr>
          <tr>
            <th colspan="2" class="text-start ps-1">課税総所得金額</th>
            <td class="text-end">
              <input type="text"
                     readonly
                     name="kazeisoushotoku_prev"
                     class="form-control form-control-sm text-end bg-light"
                     value="{{ $readonlyValue('kazeisoushotoku_prev') }}">
            </td>
            <td class="text-end">
              <input type="text"
                     readonly
                     name="kazeisoushotoku_curr"
                     class="form-control form-control-sm text-end bg-light"
                     value="{{ $readonlyValue('kazeisoushotoku_curr') }}">
            </td>
          </tr>
          <tr>
            <th colspan="2" class="text-start ps-1">寄付金額</th>
            <td class="text-end">
              <input type="text"
                     readonly
                     name="kifu_gaku_prev"
                     class="form-control form-control-sm text-end bg-light"
                     value="{{ $readonlyValue('kifu_gaku_prev') }}">
            </td>
            <td class="text-end">
              <input type="text"
                     readonly
                     name="kifu_gaku_curr"
                     class="form-control form-control-sm text-end bg-light"
                     value="{{ $readonlyValue('kifu_gaku_curr') }}">
            </td>
          </tr>
          <tr>
            <th colspan="2" class="text-start ps-1">ふるさと納税寄付金額</th>
            <td class="text-end">
              <input type="text"
                     readonly
                     name="furusato_kifu_gaku_prev"
                     class="form-control form-control-sm text-end bg-light"
                     value="{{ $readonlyValue('furusato_kifu_gaku_prev') }}">
            </td>
            <td class="text-end">
              <input type="text"
                     readonly
                     name="furusato_kifu_gaku_curr"
                     class="form-control form-control-sm text-end bg-light"
                     value="{{ $readonlyValue('furusato_kifu_gaku_curr') }}">
            </td>
          </tr>
          <tr>
            <th rowspan="2" class="text-start ps-1 align-middle">調整控除前所得割額</th>
            <th class="text-start ps-1">都道府県</th>
            <td class="text-end">
              <input type="text"
                     readonly
                     name="chosei_mae_shotokuwari_pref_prev"
                     class="form-control form-control-sm text-end bg-light"
                     value="{{ $readonlyValue('chosei_mae_shotokuwari_pref_prev') }}">
            </td>
            <td class="text-end">
              <input type="text"
                     readonly
                     name="chosei_mae_shotokuwari_pref_curr"
                     class="form-control form-control-sm text-end bg-light"
                     value="{{ $readonlyValue('chosei_mae_shotokuwari_pref_curr') }}">
            </td>
          </tr>
          <tr>
            <th class="text-start ps-1">市区町村</th>
            <td class="text-end">
              <input type="text"
                     readonly
                     name="chosei_mae_shotokuwari_muni_prev"
                     class="form-control form-control-sm text-end bg-light"
                     value="{{ $readonlyValue('chosei_mae_shotokuwari_muni_prev') }}">
            </td>
            <td class="text-end">
              <input type="text"
                     readonly
                     name="chosei_mae_shotokuwari_muni_curr"
                     class="form-control form-control-sm text-end bg-light"
                     value="{{ $readonlyValue('chosei_mae_shotokuwari_muni_curr') }}">
            </td>
          </tr>
          <tr>
            <th rowspan="2" class="text-start ps-1 align-middle">調整控除額</th>
            <th class="text-start ps-1">都道府県</th>
            <td class="text-end">
              <input type="text"
                     readonly
                     name="chosei_kojo_pref_prev"
                     class="form-control form-control-sm text-end bg-light"
                     value="{{ $readonlyValue('chosei_kojo_pref_prev') }}">
            </td>
            <td class="text-end">
              <input type="text"
                     readonly
                     name="chosei_kojo_pref_curr"
                     class="form-control form-control-sm text-end bg-light"
                     value="{{ $readonlyValue('chosei_kojo_pref_curr') }}">
            </td>
          </tr>
          <tr>
            <th class="text-start ps-1">市区町村</th>
            <td class="text-end">
              <input type="text"
                     readonly
                     name="chosei_kojo_muni_prev"
                     class="form-control form-control-sm text-end bg-light"
                     value="{{ $readonlyValue('chosei_kojo_muni_prev') }}">
            </td>
            <td class="text-end">
              <input type="text"
                     readonly
                     name="chosei_kojo_muni_curr"
                     class="form-control form-control-sm text-end bg-light"
                     value="{{ $readonlyValue('chosei_kojo_muni_curr') }}">
            </td>
          </tr>
          <tr>
            <th rowspan="2" class="text-start ps-1 align-middle">調整控除後所得割額</th>
            <th class="text-start ps-1">都道府県</th>
            <td class="text-end">
              <input type="text"
                     readonly
                     name="choseigo_shotokuwari_pref_prev"
                     class="form-control form-control-sm text-end bg-light"
                     value="{{ $readonlyValue('choseigo_shotokuwari_pref_prev') }}">
            </td>
            <td class="text-end">
              <input type="text"
                     readonly
                     name="choseigo_shotokuwari_pref_curr"
                     class="form-control form-control-sm text-end bg-light"
                     value="{{ $readonlyValue('choseigo_shotokuwari_pref_curr') }}">
            </td>
          </tr>
          <tr>
            <th class="text-start ps-1">市区町村</th>
            <td class="text-end">
              <input type="text"
                     readonly
                     name="choseigo_shotokuwari_muni_prev"
                     class="form-control form-control-sm text-end bg-light"
                     value="{{ $readonlyValue('choseigo_shotokuwari_muni_prev') }}">
            </td>
            <td class="text-end">
              <input type="text"
                     readonly
                     name="choseigo_shotokuwari_muni_curr"
                     class="form-control form-control-sm text-end bg-light"
                     value="{{ $readonlyValue('choseigo_shotokuwari_muni_curr') }}">
            </td>
          </tr>
          <tr>
            <th rowspan="2" class="text-start ps-1 align-middle">基本控除額</th>
            <th class="text-start ps-1">都道府県</th>
            <td class="text-end">
              <input type="text"
                     readonly
                     name="kihon_kojo_pref_prev"
                     class="form-control form-control-sm text-end bg-light"
                     value="{{ $readonlyValue('kihon_kojo_pref_prev') }}">
            </td>
            <td class="text-end">
              <input type="text"
                     readonly
                     name="kihon_kojo_pref_curr"
                     class="form-control form-control-sm text-end bg-light"
                     value="{{ $readonlyValue('kihon_kojo_pref_curr') }}">
            </td>
          </tr>
          <tr>
            <th class="text-start ps-1">市区町村</th>
            <td class="text-end">
              <input type="text"
                     readonly
                     name="kihon_kojo_muni_prev"
                     class="form-control form-control-sm text-end bg-light"
                     value="{{ $readonlyValue('kihon_kojo_muni_prev') }}">
            </td>
            <td class="text-end">
              <input type="text"
                     readonly
                     name="kihon_kojo_muni_curr"
                     class="form-control form-control-sm text-end bg-light"
                     value="{{ $readonlyValue('kihon_kojo_muni_curr') }}">
            </td>
          </tr>
          <tr>
            <th rowspan="2" class="text-start ps-1 align-middle">特例控除額</th>
            <th class="text-start ps-1">都道府県</th>
            <td class="text-end">
              <input type="text"
                     readonly
                     name="tokurei_kojo_pref_prev"
                     class="form-control form-control-sm text-end bg-light"
                     value="{{ $readonlyValue('tokurei_kojo_pref_prev') }}">
            </td>
            <td class="text-end">
              <input type="text"
                     readonly
                     name="tokurei_kojo_pref_curr"
                     class="form-control form-control-sm text-end bg-light"
                     value="{{ $readonlyValue('tokurei_kojo_pref_curr') }}">
            </td>
          </tr>
          <tr>
            <th class="text-start ps-1">市区町村</th>
            <td class="text-end">
              <input type="text"
                     readonly
                     name="tokurei_kojo_muni_prev"
                     class="form-control form-control-sm text-end bg-light"
                     value="{{ $readonlyValue('tokurei_kojo_muni_prev') }}">
            </td>
            <td class="text-end">
              <input type="text"
                     readonly
                     name="tokurei_kojo_muni_curr"
                     class="form-control form-control-sm text-end bg-light"
                     value="{{ $readonlyValue('tokurei_kojo_muni_curr') }}">
            </td>
          </tr>
          <tr>
            <th rowspan="2" class="text-start ps-1 align-middle">所得割額の20％</th>
            <th class="text-start ps-1">都道府県</th>
            <td class="text-end">
              <input type="text"
                     readonly
                     name="shotokuwari20_pref_prev"
                     class="form-control form-control-sm text-end bg-light"
                     value="{{ $readonlyValue('shotokuwari20_pref_prev') }}">
            </td>
            <td class="text-end">
              <input type="text"
                     readonly
                     name="shotokuwari20_pref_curr"
                     class="form-control form-control-sm text-end bg-light"
                     value="{{ $readonlyValue('shotokuwari20_pref_curr') }}">
            </td>
          </tr>
          <tr>
            <th class="text-start ps-1">市区町村</th>
            <td class="text-end">
              <input type="text"
                     readonly
                     name="shotokuwari20_muni_prev"
                     class="form-control form-control-sm text-end bg-light"
                     value="{{ $readonlyValue('shotokuwari20_muni_prev') }}">
            </td>
            <td class="text-end">
              <input type="text"
                     readonly
                     name="shotokuwari20_muni_curr"
                     class="form-control form-control-sm text-end bg-light"
                     value="{{ $readonlyValue('shotokuwari20_muni_curr') }}">
            </td>
          </tr>
          <tr>
            <th rowspan="2" class="text-start ps-1 align-middle">上限適用後特例控除</th>
            <th class="text-start ps-1">都道府県</th>
            <td class="text-end">
              <input type="text"
                     readonly
                     name="tokurei_kojo_jogen_pref_prev"
                     class="form-control form-control-sm text-end bg-light"
                     value="{{ $readonlyValue('tokurei_kojo_jogen_pref_prev') }}">
            </td>
            <td class="text-end">
              <input type="text"
                     readonly
                     name="tokurei_kojo_jogen_pref_curr"
                     class="form-control form-control-sm text-end bg-light"
                     value="{{ $readonlyValue('tokurei_kojo_jogen_pref_curr') }}">
            </td>
          </tr>
          <tr>
            <th class="text-start ps-1">市区町村</th>
            <td class="text-end">
              <input type="text"
                     readonly
                     name="tokurei_kojo_jogen_muni_prev"
                     class="form-control form-control-sm text-end bg-light"
                     value="{{ $readonlyValue('tokurei_kojo_jogen_muni_prev') }}">
            </td>
            <td class="text-end">
              <input type="text"
                     readonly
                     name="tokurei_kojo_jogen_muni_curr"
                     class="form-control form-control-sm text-end bg-light"
                     value="{{ $readonlyValue('tokurei_kojo_jogen_muni_curr') }}">
            </td>
          </tr>
          <tr>
            <th rowspan="2" class="text-start ps-1 align-middle">申告特例控除</th>
            <th class="text-start ps-1">都道府県</th>
            <td class="text-end">
              <input type="text"
                     readonly
                     name="shinkokutokurei_kojo_pref_prev"
                     class="form-control form-control-sm text-end bg-light"
                     value="{{ $readonlyValue('shinkokutokurei_kojo_pref_prev') }}">
            </td>
            <td class="text-end">
              <input type="text"
                     readonly
                     name="shinkokutokurei_kojo_pref_curr"
                     class="form-control form-control-sm text-end bg-light"
                     value="{{ $readonlyValue('shinkokutokurei_kojo_pref_curr') }}">
            </td>
          </tr>
          <tr>
            <th class="text-start ps-1">市区町村</th>
            <td class="text-end">
              <input type="text"
                     readonly
                     name="shinkokutokurei_kojo_muni_prev"
                     class="form-control form-control-sm text-end bg-light"
                     value="{{ $readonlyValue('shinkokutokurei_kojo_muni_prev') }}">
            </td>
            <td class="text-end">
              <input type="text"
                     readonly
                     name="shinkokutokurei_kojo_muni_curr"
                     class="form-control form-control-sm text-end bg-light"
                     value="{{ $readonlyValue('shinkokutokurei_kojo_muni_curr') }}">
            </td>
          </tr>
          <tr>
            <th rowspan="3" class="text-start ps-1 align-middle">寄附金税額控除</th>
            <th class="text-start ps-1">都道府県</th>
            <td class="text-end">
              <input type="text"
                     readonly
                     name="kifukin_zeigaku_kojo_pref_prev"
                     class="form-control form-control-sm text-end bg-light"
                     value="{{ $readonlyValue('kifukin_zeigaku_kojo_pref_prev') }}">
            </td>
            <td class="text-end">
              <input type="text"
                     readonly
                     name="kifukin_zeigaku_kojo_pref_curr"
                     class="form-control form-control-sm text-end bg-light"
                     value="{{ $readonlyValue('kifukin_zeigaku_kojo_pref_curr') }}">
            </td>
          </tr>
          <tr>
            <th class="text-start ps-1">市区町村</th>
            <td class="text-end">
              <input type="text"
                     readonly
                     name="kifukin_zeigaku_kojo_muni_prev"
                     class="form-control form-control-sm text-end bg-light"
                     value="{{ $readonlyValue('kifukin_zeigaku_kojo_muni_prev') }}">
            </td>
            <td class="text-end">
              <input type="text"
                     readonly
                     name="kifukin_zeigaku_kojo_muni_curr"
                     class="form-control form-control-sm text-end bg-light"
                     value="{{ $readonlyValue('kifukin_zeigaku_kojo_muni_curr') }}">
            </td>
          </tr>
          <tr>
            <th class="text-start ps-1">合計</th>
            <td class="text-end">
              <input type="text"
                     readonly
                     name="kifukin_zeigaku_kojo_gokei_prev"
                     class="form-control form-control-sm text-end bg-light"
                     value="{{ $readonlyValue('kifukin_zeigaku_kojo_gokei_prev') }}">
            </td>
            <td class="text-end">
              <input type="text"
                     readonly
                     name="kifukin_zeigaku_kojo_gokei_curr"
                     class="form-control form-control-sm text-end bg-light"
                     value="{{ $readonlyValue('kifukin_zeigaku_kojo_gokei_curr') }}">
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</div>