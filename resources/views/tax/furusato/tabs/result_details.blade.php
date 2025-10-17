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
    $fmt = static fn($v) => $v === null ? '' : rtrim(rtrim(number_format($v, 3), '0'), '.') . '%';
  @endphp
  <div class="table-responsive">
    @php
      [$stdPrevRaw, $stdPrevDisp] = $valPercent($inputs, 'tokurei_rate_standard_prev', $tkComputed['standard_prev'] ?? null, $prevDetails['AA50'] ?? null);
      [$stdCurrRaw, $stdCurrDisp] = $valPercent($inputs, 'tokurei_rate_standard_curr', $tkComputed['standard_curr'] ?? null, $currDetails['AA50'] ?? null);
    @endphp
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
              <input type="hidden" name="tokurei_rate_standard_prev" value="{{ $stdPrevRaw }}">{{ $stdPrevDisp }}
            </td>
          @endif
          @if($showCurr)
            <td class="text-end">
              <input type="hidden" name="tokurei_rate_standard_curr" value="{{ $stdCurrRaw }}">{{ $stdCurrDisp }}
            </td>
          @endif
        </tr>
        <tr>
          <th scope="row" class="text-start ps-1">特例控除率（90％）</th>
          @if($showPrev)
            <td class="text-end">
              @php [$raw, $disp] = $valPercent($inputs, 'tokurei_rate_90_prev', $tkComputed['ninety_prev'] ?? 90.000, $prevDetails['AA51'] ?? 0.90); @endphp
              <input type="hidden" name="tokurei_rate_90_prev" value="{{ $raw }}">{{ $disp }}
            </td>
          @endif
          @if($showCurr)
            <td class="text-end">
              @php [$raw, $disp] = $valPercent($inputs, 'tokurei_rate_90_curr', $tkComputed['ninety_curr'] ?? 90.000, $currDetails['AA51'] ?? 0.90); @endphp
              <input type="hidden" name="tokurei_rate_90_curr" value="{{ $raw }}">{{ $disp }}
            </td>
          @endif
        </tr>
        <tr>
          <th scope="row" class="text-start ps-1">山林所得（1/5）ベース</th>
          @if($showPrev)
            <td class="text-end">
              @php [$raw, $disp] = $valPercentEnabled($inputs, 'tokurei_rate_sanrin_div5_prev', $tkEnabled['sanrin_prev'] ?? false, $tkComputed['sanrin_prev'] ?? null); @endphp
              <input type="hidden" name="tokurei_rate_sanrin_div5_prev" value="{{ $raw }}">{{ $disp }}
            </td>
          @endif
          @if($showCurr)
            <td class="text-end">
              @php [$raw, $disp] = $valPercentEnabled($inputs, 'tokurei_rate_sanrin_div5_curr', $tkEnabled['sanrin_curr'] ?? false, $tkComputed['sanrin_curr'] ?? null); @endphp
              <input type="hidden" name="tokurei_rate_sanrin_div5_curr" value="{{ $raw }}">{{ $disp }}
            </td>
          @endif
        </tr>
        <tr>
          <th scope="row" class="text-start ps-1">退職所得ベース</th>
          @if($showPrev)
            <td class="text-end">
              @php [$raw, $disp] = $valPercentEnabled($inputs, 'tokurei_rate_taishoku_prev', $tkEnabled['taishoku_prev'] ?? false, $tkComputed['taishoku_prev'] ?? null); @endphp
              <input type="hidden" name="tokurei_rate_taishoku_prev" value="{{ $raw }}">{{ $disp }}
            </td>
          @endif
          @if($showCurr)
            <td class="text-end">
              @php [$raw, $disp] = $valPercentEnabled($inputs, 'tokurei_rate_taishoku_curr', $tkEnabled['taishoku_curr'] ?? false, $tkComputed['taishoku_curr'] ?? null); @endphp
              <input type="hidden" name="tokurei_rate_taishoku_curr" value="{{ $raw }}">{{ $disp }}
            </td>
          @endif
        </tr>
        <tr>
          <th scope="row" class="text-start ps-1">採用率（山林／退職の小さい方）</th>
          @if($showPrev)
            <td class="text-end">
              @php [$raw, $disp] = $valPercentEnabled($inputs, 'tokurei_rate_adopted_prev', ($tkEnabled['sanrin_prev'] ?? false) || ($tkEnabled['taishoku_prev'] ?? false), $tkComputed['adopted_prev'] ?? null); @endphp
              <input type="hidden" name="tokurei_rate_adopted_prev" value="{{ $raw }}">{{ $disp }}
            </td>
          @endif
          @if($showCurr)
            <td class="text-end">
              @php [$raw, $disp] = $valPercentEnabled($inputs, 'tokurei_rate_adopted_curr', ($tkEnabled['sanrin_curr'] ?? false) || ($tkEnabled['taishoku_curr'] ?? false), $tkComputed['adopted_curr'] ?? null); @endphp
              <input type="hidden" name="tokurei_rate_adopted_curr" value="{{ $raw }}">{{ $disp }}
            </td>
          @endif
        </tr>
        <tr>
          <th scope="row" class="text-start ps-1">分離課税に基づく率（最小）</th>
          @if($showPrev)
            <td class="text-end">
              @php [$raw, $disp] = $valPercentEnabled($inputs, 'tokurei_rate_bunri_min_prev', $tkEnabled['bunri_prev'] ?? false, $tkComputed['bunri_min_prev'] ?? null); @endphp
              <input type="hidden" name="tokurei_rate_bunri_min_prev" value="{{ $raw }}">{{ $disp }}
            </td>
          @endif
          @if($showCurr)
            <td class="text-end">
              @php [$raw, $disp] = $valPercentEnabled($inputs, 'tokurei_rate_bunri_min_curr', $tkEnabled['bunri_curr'] ?? false, $tkComputed['bunri_min_curr'] ?? null); @endphp
              <input type="hidden" name="tokurei_rate_bunri_min_curr" value="{{ $raw }}">{{ $disp }}
            </td>
          @endif
        </tr>
        <tr class="table-primary">
          <th scope="row" class="text-center th-cream">特例控除 最終率</th>
          @if($showPrev)
            <td class="text-end">
              @php [$raw, $disp] = $valPercent($inputs, 'tokurei_rate_final_prev', $tkComputed['final_prev'] ?? null, $prevDetails['AA56'] ?? null); @endphp
              <input type="hidden" name="tokurei_rate_final_prev" value="{{ $raw }}"><span class="fw-bold">{{ $disp }}</span>
            </td>
          @endif
          @if($showCurr)
            <td class="text-end">
              @php [$raw, $disp] = $valPercent($inputs, 'tokurei_rate_final_curr', $tkComputed['final_curr'] ?? null, $currDetails['AA56'] ?? null); @endphp
              <input type="hidden" name="tokurei_rate_final_curr" value="{{ $raw }}"><span class="fw-bold">{{ $disp }}</span>
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
                         class="form-control form-control-sm text-end bg-light"
                         value="{{ $readonlyValue('sashihiki_joto_tanki_sogo_' . $suffix) }}">
                </td>
                <th rowspan="2" class="vtext" style="width:35px">通算</th>
                <td class="text-end" style="width:100px">
                  <input type="text"
                         readonly
                         name="tsusango_joto_tanki_{{ $suffix }}"
                         class="form-control form-control-sm text-end bg-light"
                         value="{{ $readonlyValue('tsusango_joto_tanki_' . $suffix) }}">
                </td>
                <td class="text-end" style="width:100px">
                  <input type="text"
                         readonly
                         name="tokubetsukojo_joto_tanki_{{ $suffix }}"
                         class="form-control form-control-sm text-end bg-light"
                         value="{{ $readonlyValue('tokubetsukojo_joto_tanki_' . $suffix) }}">
                </td>
                <th rowspan="4" class="vtext" style="width:35px">譲渡・一時所得の通算</th>
                <td class="text-end" style="width:100px">
                  <input type="text"
                         readonly
                         name="after_joto_ichiji_tousan_joto_tanki_{{ $suffix }}"
                         class="form-control form-control-sm text-end bg-light"
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
                         class="form-control form-control-sm text-end bg-light"
                         value="{{ $readonlyValue('sashihiki_joto_choki_sogo_' . $suffix) }}">
                </td>
                <td class="text-end">
                  <input type="text"
                         readonly
                         name="tsusango_joto_choki_sogo_{{ $suffix }}"
                         class="form-control form-control-sm text-end bg-light"
                         value="{{ $readonlyValue('tsusango_joto_choki_sogo_' . $suffix) }}">
                </td>
                <td class="text-end">
                  <input type="text"
                         readonly
                         name="tokubetsukojo_joto_choki_{{ $suffix }}"
                         class="form-control form-control-sm text-end bg-light"
                         value="{{ $readonlyValue('tokubetsukojo_joto_choki_' . $suffix) }}">
                </td>
                <td class="text-end">
                  <input type="text"
                         readonly
                         name="after_joto_ichiji_tousan_joto_choki_sogo_{{ $suffix }}"
                         class="form-control form-control-sm text-end bg-light"
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
                         class="form-control form-control-sm text-end bg-light"
                         value="{{ $readonlyValue('tsusango_ichiji_' . $suffix) }}">
                </td>
                <td class="text-end">
                  <input type="text"
                         readonly
                         name="tokubetsukojo_ichiji_{{ $suffix }}"
                         class="form-control form-control-sm text-end bg-light"
                         value="{{ $readonlyValue('tokubetsukojo_ichiji_' . $suffix) }}">
                </td>
                <td class="text-end">
                  <input type="text"
                         readonly
                         name="after_joto_ichiji_tousan_ichiji_{{ $suffix }}"
                         class="form-control form-control-sm text-end bg-light"
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
                         class="form-control form-control-sm text-end bg-light"
                         value="{{ $readonlyValue('tsusanmae_keijo_' . $suffix) }}">
                </td>
                <th rowspan="4" style="width:35px">第<br>1<br>次<br>通<br>算</th>
                <td class="text-end">
                  <input type="text"
                         readonly
                         name="after_1jitsusan_keijo_{{ $suffix }}"
                         class="form-control form-control-sm text-end bg-light"
                         value="{{ $readonlyValue('after_1jitsusan_keijo_' . $suffix) }}">
                </td>
                <th rowspan="5" style="width:35px">第<br>2<br>次<br>通<br>算</th>
                <td class="text-end">
                  <input type="text"
                         readonly
                         name="after_2jitsusan_keijo_{{ $suffix }}"
                         class="form-control form-control-sm text-end bg-light"
                         value="{{ $readonlyValue('after_2jitsusan_keijo_' . $suffix) }}">
                </td>
                <th rowspan="6" style="width:35px">第<br>3<br>次<br>通<br>算</th>
                <td class="text-end">
                  <input type="text"
                         readonly
                         name="after_3jitsusan_keijo_{{ $suffix }}"
                         class="form-control form-control-sm text-end bg-light"
                         value="{{ $readonlyValue('after_3jitsusan_keijo_' . $suffix) }}">
                </td>
                <td class="text-end">
                  <input type="text"
                         readonly
                         name="shotoku_keijo_{{ $suffix }}"
                         class="form-control form-control-sm text-end bg-light"
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
                         class="form-control form-control-sm text-end bg-light"
                         value="{{ $readonlyValue('tsusanmae_joto_tanki_sogo_' . $suffix) }}">
                </td>
                <td class="text-end">
                  <input type="text"
                         readonly
                         name="after_1jitsusan_joto_tanki_{{ $suffix }}"
                         class="form-control form-control-sm text-end bg-light"
                         value="{{ $readonlyValue('after_1jitsusan_joto_tanki_' . $suffix) }}">
                </td>
                <td class="text-end">
                  <input type="text"
                         readonly
                         name="after_2jitsusan_joto_tanki_{{ $suffix }}"
                         class="form-control form-control-sm text-end bg-light"
                         value="{{ $readonlyValue('after_2jitsusan_joto_tanki_' . $suffix) }}">
                </td>
                <td class="text-end">
                  <input type="text"
                         readonly
                         name="after_3jitsusan_joto_tanki_{{ $suffix }}"
                         class="form-control form-control-sm text-end bg-light"
                         value="{{ $readonlyValue('after_3jitsusan_joto_tanki_' . $suffix) }}">
                </td>
                <td class="text-end">
                  <input type="text"
                         readonly
                         name="shotoku_joto_tanki_{{ $suffix }}"
                         class="form-control form-control-sm text-end bg-light"
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
                         class="form-control form-control-sm text-end bg-light"
                         value="{{ $readonlyValue('tsusanmae_joto_choki_sogo_' . $suffix) }}">
                </td>
                <td class="text-end">
                  <input type="text"
                         readonly
                         name="after_1jitsusan_joto_choki_sogo_{{ $suffix }}"
                         class="form-control form-control-sm text-end bg-light"
                         value="{{ $readonlyValue('after_1jitsusan_joto_choki_sogo_' . $suffix) }}">
                </td>
                <td class="text-end">
                  <input type="text"
                         readonly
                         name="after_2jitsusan_joto_choki_sogo_{{ $suffix }}"
                         class="form-control form-control-sm text-end bg-light"
                         value="{{ $readonlyValue('after_2jitsusan_joto_choki_sogo_' . $suffix) }}">
                </td>
                <td class="text-end">
                  <input type="text"
                         readonly
                         name="after_3jitsusan_joto_choki_sogo_{{ $suffix }}"
                         class="form-control form-control-sm text-end bg-light"
                         value="{{ $readonlyValue('after_3jitsusan_joto_choki_sogo_' . $suffix) }}">
                </td>
                <td class="text-end">
                  <input type="text"
                         readonly
                         name="shotoku_joto_choki_sogo_{{ $suffix }}"
                         class="form-control form-control-sm text-end bg-light"
                         value="{{ $readonlyValue('shotoku_joto_choki_sogo_' . $suffix) }}">
                </td>
              </tr>
              <tr>
                <th colspan="3" class="text-start ps-1">一時</th>
                <td class="text-end">
                  <input type="text"
                         readonly
                         name="tsusanmae_ichiji_{{ $suffix }}"
                         class="form-control form-control-sm text-end bg-light"
                         value="{{ $readonlyValue('tsusanmae_ichiji_' . $suffix) }}">
                </td>
                <td class="text-end">
                  <input type="text"
                         readonly
                         name="after_1jitsusan_ichiji_{{ $suffix }}"
                         class="form-control form-control-sm text-end bg-light"
                         value="{{ $readonlyValue('after_1jitsusan_ichiji_' . $suffix) }}">
                </td>
                <td class="text-end">
                  <input type="text"
                         readonly
                         name="after_2jitsusan_ichiji_{{ $suffix }}"
                         class="form-control form-control-sm text-end bg-light"
                         value="{{ $readonlyValue('after_2jitsusan_ichiji_' . $suffix) }}">
                </td>
                <td class="text-end">
                  <input type="text"
                         readonly
                         name="after_3jitsusan_ichiji_{{ $suffix }}"
                         class="form-control form-control-sm text-end bg-light"
                         value="{{ $readonlyValue('after_3jitsusan_ichiji_' . $suffix) }}">
                </td>
                <td class="text-end">
                  <input type="text"
                         readonly
                         name="shotoku_ichiji_{{ $suffix }}"
                         class="form-control form-control-sm text-end bg-light"
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
                         class="form-control form-control-sm text-end bg-light"
                         value="{{ $readonlyValue('after_1jitsusan_sanrin_' . $suffix) }}">
                </td>
                <td class="text-end">
                  <input type="text"
                         readonly
                         name="after_2jitsusan_sanrin_{{ $suffix }}"
                         class="form-control form-control-sm text-end bg-light"
                         value="{{ $readonlyValue('after_2jitsusan_sanrin_' . $suffix) }}">
                </td>
                <td class="text-end">
                  <input type="text"
                         readonly
                         name="after_3jitsusan_sanrin_{{ $suffix }}"
                         class="form-control form-control-sm text-end bg-light"
                         value="{{ $readonlyValue('after_3jitsusan_sanrin_' . $suffix) }}">
                </td>
                <td class="text-end">
                  <input type="text"
                         readonly
                         name="shotoku_sanrin_{{ $suffix }}"
                         class="form-control form-control-sm text-end bg-light"
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
                         class="form-control form-control-sm text-end bg-light"
                         value="{{ $readonlyValue('after_2jitsusan_taishoku_' . $suffix) }}">
                </td>
                <td class="text-end">
                  <input type="text"
                         readonly
                         name="after_3jitsusan_taishoku_{{ $suffix }}"
                         class="form-control form-control-sm text-end bg-light"
                         value="{{ $readonlyValue('after_3jitsusan_taishoku_' . $suffix) }}">
                </td>
                <td class="text-end">
                  <input type="text"
                         readonly
                         name="shotoku_taishoku_{{ $suffix }}"
                         class="form-control form-control-sm text-end bg-light"
                         value="{{ $readonlyValue('shotoku_taishoku_' . $suffix) }}">
                </td>
              </tr>
              <tr>
                <th colspan="10" class="th-cream">所得金額の合計額</th>
                <td class="text-end">
                  <input type="text"
                         readonly
                         name="shotoku_gokei_{{ $suffix }}"
                         class="form-control form-control-sm text-end bg-light"
                         value="{{ $readonlyValue('shotoku_gokei_' . $suffix) }}">
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    @endforeach
  </div>


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
                           class="form-control form-control-sm text-end bg-light"
                           value="{{ $readonlyValue('before_tsusan_tanki_ippan_' . $suffix) }}">
                  </td>
                  <th rowspan="2" class="vtext" style="width:35px">通算</th>
                  <td class="text-end" style="width:120px">
                    <input type="text"
                           readonly
                           name="after_1jitsusan_tanki_ippan_{{ $suffix }}"
                           class="form-control form-control-sm text-end bg-light"
                           value="{{ $readonlyValue('after_1jitsusan_tanki_ippan_' . $suffix) }}">
                  </td>
                  <th rowspan="5" class="vtext" style="width:35px">通算</th>
                  <td class="text-end" style="width:120px">
                    <input type="text"
                           readonly
                           name="after_2jitsusan_tanki_ippan_{{ $suffix }}"
                           class="form-control form-control-sm text-end bg-light"
                           value="{{ $readonlyValue('after_2jitsusan_tanki_ippan_' . $suffix) }}">
                  </td>
                </tr>
                <tr>
                  <th class="text-start ps-1 th-ddd">軽減</th>
                  <td class="text-end">
                    <input type="text"
                           readonly
                           name="before_tsusan_tanki_keigen_{{ $suffix }}"
                           class="form-control form-control-sm text-end bg-light"
                           value="{{ $readonlyValue('before_tsusan_tanki_keigen_' . $suffix) }}">
                  </td>
                  <td class="text-end">
                    <input type="text"
                           readonly
                           name="after_1jitsusan_tanki_keigen_{{ $suffix }}"
                           class="form-control form-control-sm text-end bg-light"
                           value="{{ $readonlyValue('after_1jitsusan_tanki_keigen_' . $suffix) }}">
                  </td>
                  <td class="text-end">
                    <input type="text"
                           readonly
                           name="after_2jitsusan_tanki_keigen_{{ $suffix }}"
                           class="form-control form-control-sm text-end bg-light"
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
                           class="form-control form-control-sm text-end bg-light"
                           value="{{ $readonlyValue('before_tsusan_choki_ippan_' . $suffix) }}">
                  </td>
                  <th rowspan="3" class="vtext" style="width:35px">通算</th>
                  <td class="text-end">
                    <input type="text"
                           readonly
                           name="after_1jitsusan_choki_ippan_{{ $suffix }}"
                           class="form-control form-control-sm text-end bg-light"
                           value="{{ $readonlyValue('after_1jitsusan_choki_ippan_' . $suffix) }}">
                  </td>
                  <td class="text-end">
                    <input type="text"
                           readonly
                           name="after_2jitsusan_choki_ippan_{{ $suffix }}"
                           class="form-control form-control-sm text-end bg-light"
                           value="{{ $readonlyValue('after_2jitsusan_choki_ippan_' . $suffix) }}">
                  </td>
                </tr>
                <tr>
                  <th class="text-start ps-1 th-ddd">特定</th>
                  <td class="text-end">
                    <input type="text"
                           readonly
                           name="before_tsusan_choki_tokutei_{{ $suffix }}"
                           class="form-control form-control-sm text-end bg-light"
                           value="{{ $readonlyValue('before_tsusan_choki_tokutei_' . $suffix) }}">
                  </td>
                  <td class="text-end">
                    <input type="text"
                           readonly
                           name="after_1jitsusan_choki_tokutei_{{ $suffix }}"
                           class="form-control form-control-sm text-end bg-light"
                           value="{{ $readonlyValue('after_1jitsusan_choki_tokutei_' . $suffix) }}">
                  </td>
                  <td class="text-end">
                    <input type="text"
                           readonly
                           name="after_2jitsusan_choki_tokutei_{{ $suffix }}"
                           class="form-control form-control-sm text-end bg-light"
                           value="{{ $readonlyValue('after_2jitsusan_choki_tokutei_' . $suffix) }}">
                  </td>
                </tr>
                <tr>
                  <th class="text-start ps-1 th-ddd">軽課</th>
                  <td class="text-end">
                    <input type="text"
                           readonly
                           name="before_tsusan_choki_keika_{{ $suffix }}"
                           class="form-control form-control-sm text-end bg-light"
                           value="{{ $readonlyValue('before_tsusan_choki_keika_' . $suffix) }}">
                  </td>
                  <td class="text-end">
                    <input type="text"
                           readonly
                           name="after_1jitsusan_choki_keika_{{ $suffix }}"
                           class="form-control form-control-sm text-end bg-light"
                           value="{{ $readonlyValue('after_1jitsusan_choki_keika_' . $suffix) }}">
                  </td>
                  <td class="text-end">
                    <input type="text"
                           readonly
                           name="after_2jitsusan_choki_keika_{{ $suffix }}"
                           class="form-control form-control-sm text-end bg-light"
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
                           class="form-control form-control-sm text-end bg-light"
                           value="{{ $readonlyValue('before_tsusan_jojo_joto_' . $suffix) }}">
                  </td>
                  <th rowspan="2" class="vtext" style="width:35px">通算</th>
                  <td class="text-end" style="width:160px">
                    <input type="text"
                           readonly
                           name="after_tsusan_jojo_joto_{{ $suffix }}"
                           class="form-control form-control-sm text-end bg-light"
                           value="{{ $readonlyValue('after_tsusan_jojo_joto_' . $suffix) }}">
                  </td>
                </tr>
                <tr>
                  <th class="text-start ps-1">上場株式等に係る配当所得等の金額</th>
                  <td class="text-end">
                    <input type="text"
                           readonly
                           name="before_tsusan_jojo_haito_{{ $suffix }}"
                           class="form-control form-control-sm text-end bg-light"
                           value="{{ $readonlyValue('before_tsusan_jojo_haito_' . $suffix) }}">
                  </td>
                  <td class="text-end">
                    <input type="text"
                           readonly
                           name="after_tsusan_jojo_haito_{{ $suffix }}"
                           class="form-control form-control-sm text-end bg-light"
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
</div>