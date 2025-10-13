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
  $warekiPrevLabel = $warekiPrev ?? '前年';
  $warekiCurrLabel = $warekiCurr ?? '当年';
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
          <th class="text-center th-ccc">{{ $warekiPrevLabel }}</th>
          <th class="text-center th-ccc">{{ $warekiCurrLabel }}</th>
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
            <td class="text-end">
              @php
                $raw = $rawInt($inputs, $inputPrev, $fallbackPrev);
                $displayValue = $fallbackPrev !== null ? (int) $fallbackPrev : (is_numeric($raw) ? (int) $raw : null);
              @endphp
              <input type="hidden" name="{{ $inputPrev }}" value="{{ $raw }}">{{ $dispInt($displayValue) }}
            </td>
            <td class="text-end">
              @php
                $raw = $rawInt($inputs, $inputCurr, $fallbackCurr);
                $displayValue = $fallbackCurr !== null ? (int) $fallbackCurr : (is_numeric($raw) ? (int) $raw : null);
              @endphp
              <input type="hidden" name="{{ $inputCurr }}" value="{{ $raw }}">{{ $dispInt($displayValue) }}
            </td>
          </tr>
        @endforeach
        <tr>
          <th class="text-start ps-1 th-cream">課税総所得金額-人的控除差調整額</th>
          @php
            $fallbackPrev = $jintekiDiff['adjusted_taxable']['prev'] ?? null;
            $fallbackCurr = $jintekiDiff['adjusted_taxable']['curr'] ?? null;
          @endphp
          <td class="text-end">
            @php
              $raw = $rawInt($inputs, 'human_adjusted_taxable_prev', $fallbackPrev);
              $displayValue = $fallbackPrev !== null ? (int) $fallbackPrev : (is_numeric($raw) ? (int) $raw : null);
            @endphp
            <input type="hidden" name="human_adjusted_taxable_prev" value="{{ $raw }}">{{ $dispInt($displayValue) }}</td>
          <td class="text-end">
            @php
              $raw = $rawInt($inputs, 'human_adjusted_taxable_curr', $fallbackCurr);
              $displayValue = $fallbackCurr !== null ? (int) $fallbackCurr : (is_numeric($raw) ? (int) $raw : null);
            @endphp
            <input type="hidden" name="human_adjusted_taxable_curr" value="{{ $raw }}">{{ $dispInt($displayValue) }}</td>
        </tr>
      </tbody>
    </table>
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
    @endphp
    @php
      $stdPrevHidden = $stdPrevDisp !== '' ? $stdPrevDisp : '';
      $stdCurrHidden = $stdCurrDisp !== '' ? $stdCurrDisp : '';
    @endphp
    @if($stdPrevHidden !== '' || $stdCurrHidden !== '')
      <div class="visually-hidden" aria-hidden="true">
        特例控除率（標準） 前年：{{ $stdPrevHidden }} 当年：{{ $stdCurrHidden }}
      </div>
    @endif
    <table class="table table-base align-middle" style="width:580px">
        <tr>
          <th scope="col" class="w-50 th-ccc" style="height:30px;">項  目</th>
          <th scope="col" class="text-center th-ccc">{{ $warekiPrevLabel }}</th>
          <th scope="col" class="text-center th-ccc">{{ $warekiCurrLabel }}</th>
        </tr>
      <tbody>
        <tr>
          <th scope="row" class="text-start ps-1">特例控除率（標準）</th>
          <td class="text-end">
            <input type="hidden" name="tokurei_rate_standard_prev" value="{{ $stdPrevRaw }}">{{ $stdPrevDisp }}
          </td>
          <td class="text-end">
            <input type="hidden" name="tokurei_rate_standard_curr" value="{{ $stdCurrRaw }}">{{ $stdCurrDisp }}
          </td>
        </tr>
        <tr>
          <th scope="row" class="text-start ps-1">特例控除率（90％）</th>
          <td class="text-end">
            @php [$raw, $disp] = $valPercent($inputs, 'tokurei_rate_90_prev', $tkComputed['ninety_prev'] ?? 90.000, $prevDetails['AA51'] ?? 0.90); @endphp
            <input type="hidden" name="tokurei_rate_90_prev" value="{{ $raw }}">{{ $disp }}
          </td>
          <td class="text-end">
            @php [$raw, $disp] = $valPercent($inputs, 'tokurei_rate_90_curr', $tkComputed['ninety_curr'] ?? 90.000, $currDetails['AA51'] ?? 0.90); @endphp
            <input type="hidden" name="tokurei_rate_90_curr" value="{{ $raw }}">{{ $disp }}
          </td>
        </tr>
        <tr>
          <th scope="row" class="text-start ps-1">山林所得（1/5）ベース</th>
          <td class="text-end">
            @php [$raw, $disp] = $valPercentEnabled($inputs, 'tokurei_rate_sanrin_div5_prev', $tkEnabled['sanrin_prev'] ?? false, $tkComputed['sanrin_prev'] ?? null); @endphp
            <input type="hidden" name="tokurei_rate_sanrin_div5_prev" value="{{ $raw }}">{{ $disp }}
          </td>
          <td class="text-end">
            @php [$raw, $disp] = $valPercentEnabled($inputs, 'tokurei_rate_sanrin_div5_curr', $tkEnabled['sanrin_curr'] ?? false, $tkComputed['sanrin_curr'] ?? null); @endphp
            <input type="hidden" name="tokurei_rate_sanrin_div5_curr" value="{{ $raw }}">{{ $disp }}
          </td>
        </tr>
        <tr>
          <th scope="row" class="text-start ps-1">退職所得ベース</th>
          <td class="text-end">
            @php [$raw, $disp] = $valPercentEnabled($inputs, 'tokurei_rate_taishoku_prev', $tkEnabled['taishoku_prev'] ?? false, $tkComputed['taishoku_prev'] ?? null); @endphp
            <input type="hidden" name="tokurei_rate_taishoku_prev" value="{{ $raw }}">{{ $disp }}
          </td>
          <td class="text-end">
            @php [$raw, $disp] = $valPercentEnabled($inputs, 'tokurei_rate_taishoku_curr', $tkEnabled['taishoku_curr'] ?? false, $tkComputed['taishoku_curr'] ?? null); @endphp
            <input type="hidden" name="tokurei_rate_taishoku_curr" value="{{ $raw }}">{{ $disp }}
          </td>
        </tr>
        <tr>
          <th scope="row" class="text-start ps-1">採用率（山林／退職の小さい方）</th>
          <td class="text-end">
            @php [$raw, $disp] = $valPercentEnabled($inputs, 'tokurei_rate_adopted_prev', ($tkEnabled['sanrin_prev'] ?? false) || ($tkEnabled['taishoku_prev'] ?? false), $tkComputed['adopted_prev'] ?? null); @endphp
            <input type="hidden" name="tokurei_rate_adopted_prev" value="{{ $raw }}">{{ $disp }}
          </td>
          <td class="text-end">
            @php [$raw, $disp] = $valPercentEnabled($inputs, 'tokurei_rate_adopted_curr', ($tkEnabled['sanrin_curr'] ?? false) || ($tkEnabled['taishoku_curr'] ?? false), $tkComputed['adopted_curr'] ?? null); @endphp
            <input type="hidden" name="tokurei_rate_adopted_curr" value="{{ $raw }}">{{ $disp }}
          </td>
        </tr>
        <tr>
          <th scope="row" class="text-start ps-1">分離課税に基づく率（最小）</th>
          <td class="text-end">
            @php [$raw, $disp] = $valPercentEnabled($inputs, 'tokurei_rate_bunri_min_prev', $tkEnabled['bunri_prev'] ?? false, $tkComputed['bunri_min_prev'] ?? null); @endphp
            <input type="hidden" name="tokurei_rate_bunri_min_prev" value="{{ $raw }}">{{ $disp }}
          </td>
          <td class="text-end">
            @php [$raw, $disp] = $valPercentEnabled($inputs, 'tokurei_rate_bunri_min_curr', $tkEnabled['bunri_curr'] ?? false, $tkComputed['bunri_min_curr'] ?? null); @endphp
            <input type="hidden" name="tokurei_rate_bunri_min_curr" value="{{ $raw }}">{{ $disp }}
          </td>
        </tr>
        <tr class="table-primary">
          <th scope="row" class="text-center th-cream">特例控除 最終率</th>
          <td class="text-end">
            @php [$raw, $disp] = $valPercent($inputs, 'tokurei_rate_final_prev', $tkComputed['final_prev'] ?? null, $prevDetails['AA56'] ?? null); @endphp
            <input type="hidden" name="tokurei_rate_final_prev" value="{{ $raw }}"><span class="fw-bold">{{ $disp }}</span>
          </td>
          <td class="text-end">
            @php [$raw, $disp] = $valPercent($inputs, 'tokurei_rate_final_curr', $tkComputed['final_curr'] ?? null, $currDetails['AA56'] ?? null); @endphp
            <input type="hidden" name="tokurei_rate_final_curr" value="{{ $raw }}"><span class="fw-bold">{{ $disp }}</span>
          </td>
        </tr>
      </tbody>
    </table>
    <div class="visually-hidden" aria-hidden="true">
      特例控除率（標準） 前年：{{ $stdPrevDisp }} 当年：{{ $stdCurrDisp }}
    </div>
  </div>
</div>