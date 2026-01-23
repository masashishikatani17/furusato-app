<!-- resources/views/tax/furusato/tabs/result_details.blade.php -->
@php
  $details = $results['details'] ?? [];
  $prevDetails = $details['prev'] ?? [];
  $currDetails = $details['curr'] ?? [];
  $jintekiDiff = $jintekiDiff ?? [];
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
  // ▼ 分離課税フラグ
  $bunriPrevOff = (int)($syoriSettings['bunri_flag_prev'] ?? $syoriSettings['bunri_flag'] ?? 0) === 0;
  $bunriCurrOff = (int)($syoriSettings['bunri_flag_curr'] ?? $syoriSettings['bunri_flag'] ?? 0) === 0;
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

  // サーバ計算専用の表示値（before_tsusan_*, after_1jitsusan_*, after_2jitsusan_* 用）
  // - フロントからは POST しない（input に name を付けない）
  // - 値のソース優先度：$resultsUpper → $details(prev/curr) → $inputs
  $serverDisplay = static function (string $key) use ($resultsUpper, $prevDetails, $currDetails, $inputs): string {
      // 1) 上段結果（upper）を最優先
      $value = $resultsUpper[$key] ?? null;

      // 2) details[prev/curr] にあればそこから
      if ($value === null) {
          if (str_ends_with($key, '_prev')) {
              $baseKey = substr($key, 0, -5); // '_prev' を除去
              $value = $prevDetails[$baseKey] ?? null;
          } elseif (str_ends_with($key, '_curr')) {
              $baseKey = substr($key, 0, -5); // '_curr' を除去
            $value = $currDetails[$baseKey] ?? null;
          }
      }

    // 3) 最後の保険として inputs も見る（Calculator が payload にだけ詰めている場合）
      if ($value === null) {
          $value = $inputs[$key] ?? null;
      }

      if ($value === null || $value === '') {
          return '';
      }

      $normalized = str_replace(',', '', (string) $value);
      return is_numeric($normalized) ? number_format((int) $normalized) : (string) $value;
  };

  $floorDisplay = static function (?int $value): ?int {
      if ($value === null) {
          return null;
      }

      if ($value <= 0) {
          return 0;
      }

      return (int) (floor($value / 1000) * 1000);
  };

  $formatPercentRaw = static fn (float $num): string => number_format($num, 3, '.', '');
  $formatPercentDisplay = static fn (float $num): string => number_format($num, 3, '.', ',') . '%';
  $formatPercentPair = static function (?float $num) use ($formatPercentRaw, $formatPercentDisplay): array {
      if ($num === null) {
          return ['', ''];
      }

      return [$formatPercentRaw((float) $num), $formatPercentDisplay((float) $num)];
  };
  // - $valPercent($inputs, $key, $computedFallback, $aaFallback)
  //   inputsに値があればそれを％として使い、なければ computed→AA×100 の順で採用
  $valPercent = static function (array $ins, string $key, $computedFallback, $aaFallback) use ($formatPercentPair): array {
      // 1) inputs優先
      if (array_key_exists($key, $ins) && $ins[$key] !== null && $ins[$key] !== '') {
          $v = (float) str_replace([',', ' '], '', (string) $ins[$key]);
          return $formatPercentPair($v);
      }
      // 2) computed（すでに％の数値: 0〜100）
      if ($computedFallback !== null && $computedFallback !== '') {
          return $formatPercentPair((float) $computedFallback);
      }
      // 3) AA（0.xx）→％化
      if ($aaFallback !== null && $aaFallback !== '') {
          return $formatPercentPair(((float) $aaFallback) * 100.0);
      }
      return ['', ''];
  };
  // - $valPercentEnabled($inputs, $key, $enabled, $computedValue)
  //   有効フラグがfalseなら空欄。trueなら inputs→computed の順で％を出す（AAは使わない仕様）
  $valPercentEnabled = static function (array $ins, string $key, bool $enabled, $computedValue) use ($formatPercentPair): array {
      if (! $enabled) {
          return ['', ''];
      }
      if (array_key_exists($key, $ins) && $ins[$key] !== null && $ins[$key] !== '') {
          $v = (float) str_replace([',', ' '], '', (string) $ins[$key]);
          return $formatPercentPair($v);
      }
      if ($computedValue !== null && $computedValue !== '') {
          return $formatPercentPair((float) $computedValue);
      }
      return ['', ''];
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
  $readonlyValue = static function (string $key, $fallback = null) use ($inputs, $syoriSettings): string {
      $value = old($key, $inputs[$key] ?? $fallback);
      // ▼ 一時所得(tsusango_ichiji_*) だけは 0 未満を許容しない（0 下限）
      $isTsusangoIchiji = str_starts_with($key, 'tsusango_ichiji_');
      if ($value === null || $value === '') {
          return '';
      }

      $isSeparated = static function (string $key, array $syoriSettings): bool {
          $parts = explode('_', $key);
          $period = end($parts);

          if (! in_array($period, ['prev', 'curr'], true)) {
              return false;
          }

          $flagKey = sprintf('bunri_flag_%s', $period);
          $flag = $syoriSettings[$flagKey] ?? $syoriSettings['bunri_flag'] ?? null;

          return (int) $flag === 1;
      };

      if (str_starts_with($key, 'tb_sogo_shotoku_')) {
          $parts = explode('_', $key);
          $period = end($parts);

          if (in_array($period, ['prev', 'curr'], true)) {
              if ($isSeparated($key, $syoriSettings)) {
                  return '－';
              }
          }
      }

      if (str_starts_with($key, 'tb_sogo_jumin_')) {
          if ($isSeparated($key, $syoriSettings)) {
              return '－';
          }
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

      // 一時所得だけマイナス禁止、それ以外の tsusango_*（短期・長期など）はマイナスもそのまま表示
      if ($isTsusangoIchiji && $number < 0) {
          $number = 0;
      }

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
  /**
   * ▼ 表示ガードと値計算（prev/curr 共通）
   *  - 調整後課税: human_adjusted_taxable_{p} は raw（負も許容）を保持
   *  - 課税総所得（所得税）の参照先は分離フラグで切替
   *  - 採用(min)、90%、分離最小の相互作用はあなたの指示どおり
   */
  $periods = ['prev', 'curr'];

  // 分離フラグの解決（処理メニュー）
  $bunriFlag = [
    'prev' => (int) ($syoriSettings['bunri_flag_prev'] ?? $syoriSettings['bunri_flag'] ?? 0),
    'curr' => (int) ($syoriSettings['bunri_flag_curr'] ?? $syoriSettings['bunri_flag'] ?? 0),
  ];

  // 人的控除差の合計（差 = 所得税 − 住民税）
  $humanDiffSum = [
    'prev' => (int) ($jintekiDiff['sum']['prev'] ?? $inputs['human_diff_sum_prev'] ?? 0),
    'curr' => (int) ($jintekiDiff['sum']['curr'] ?? $inputs['human_diff_sum_curr'] ?? 0),
  ];

  // 分離系の金額（判定用）…SoT=tb_*（住民税側）へ統一
  // part は 'tanki','choki','joto','haito','sakimono','sanrin','taishoku' を想定
  $bunriShotoku = function(string $part, string $p) use ($inputs): int {
      $map = [
          'tanki'    => [sprintf('tb_joto_tanki_jumin_%s', $p)],
          'choki'    => [sprintf('tb_joto_choki_jumin_%s', $p)],
          // 「一般・上場の譲渡」は表示上は合算なので、tb_を和で評価
          'joto'     => [
              sprintf('tb_ippan_kabuteki_joto_jumin_%s', $p),
              sprintf('tb_jojo_kabuteki_joto_jumin_%s', $p),
          ],
          'haito'    => [sprintf('tb_jojo_kabuteki_haito_jumin_%s', $p)],
          'sakimono' => [sprintf('tb_sakimono_jumin_%s', $p)],
          'sanrin'   => [sprintf('tb_sanrin_jumin_%s', $p)],
          'taishoku' => [sprintf('tb_taishoku_jumin_%s', $p)],
      ];
      $keys = $map[$part] ?? [];
      $sum  = 0;
      foreach ($keys as $k) {
          $v = $inputs[$k] ?? null;
          if ($v !== null && $v !== '') {
              $sum += (int) $v;
          }
      }
      return $sum;
  };

  // 課税総所得金額（住民税側）：SoT（tb_sogo_jumin_*）で統一
  $taxableBase = [];
  foreach ($periods as $p) {
    $v = $inputs[sprintf('tb_sogo_jumin_%s', $p)] ?? null;
    $taxableBase[$p] = ($v === '' || $v === null) ? 0 : (int) $v;
  }

  // D_raw = 課税総所得金額（住民税側）－人的控除差調整額（負もOK）
  $humanAdjTaxable = [
    'prev' => $taxableBase['prev'] - $humanDiffSum['prev'],
    'curr' => $taxableBase['curr'] - $humanDiffSum['curr'],
  ];

  // 総合課税の差（第一表：総所得合計 − 控除合計）。空は0扱い。
  $taxableDiff = [];
  foreach ($periods as $p) {
    $g = (int) ($inputs[sprintf('shotoku_gokei_shotoku_%s', $p)] ?? 0);
    $k = (int) ($inputs[sprintf('kojo_gokei_shotoku_%s', $p)] ?? 0);
    $taxableDiff[$p] = $g - $k;
  }

  // === 表示ガード ===
  $show = [
    'standard' => ['prev' => false, 'curr' => false],
    'sanrin'   => ['prev' => false, 'curr' => false],
    'taishoku' => ['prev' => false, 'curr' => false],
    'adopted'  => ['prev' => false, 'curr' => false],
    'rate90'   => ['prev' => false, 'curr' => false],
    'bunrimin' => ['prev' => false, 'curr' => false],
  ];

  // 率（％）のraw（小数、0〜100）をどこから読むか：既存の計算結果（tkComputed） or details
  //  - 標準: $tokureiComputedPercent['standard_*'] か $prevDetails['AA50']*100 等
  //  - 90%  : 固定 90.000
  //  - 山林 : $tokureiComputedPercent['sanrin_*']
  //  - 退職 : $tokureiComputedPercent['taishoku_*']
  //  - 採用 : min(山林,退職)
  //  - 分離最小: 短期>0 → 59.370, それ以外の分離>0 → 74.685
  $tk = $tokureiComputedPercent ?? [];
  $rate = [
    'standard' => [
      'prev' => isset($tk['standard_prev']) ? (float)$tk['standard_prev'] : (isset($prevDetails['AA50']) ? (float)$prevDetails['AA50']*100.0 : null),
      'curr' => isset($tk['standard_curr']) ? (float)$tk['standard_curr'] : (isset($currDetails['AA50']) ? (float)$currDetails['AA50']*100.0 : null),
    ],
    'sanrin' => [
      'prev' => isset($tk['sanrin_prev']) ? (float)$tk['sanrin_prev'] : null,
      'curr' => isset($tk['sanrin_curr']) ? (float)$tk['sanrin_curr'] : null,
    ],
    'taishoku' => [
      'prev' => isset($tk['taishoku_prev']) ? (float)$tk['taishoku_prev'] : null,
      'curr' => isset($tk['taishoku_curr']) ? (float)$tk['taishoku_curr'] : null,
    ],
    // 採用と分離最小はこの後で決定
  ];

  foreach ($periods as $p) {
    // (1) 標準：課税総所得金額を有し、D_raw>=0
    $show['standard'][$p] =
      ($taxableBase[$p] > 0) &&
      ($humanAdjTaxable[$p] >= 0);

    // (3) 山林：[(Sあり & D<0) または (Sなし)] かつ 山林あり
    $show['sanrin'][$p] =
      (
        ($taxableBase[$p] > 0 && $humanAdjTaxable[$p] < 0)
        || ($taxableBase[$p] === 0)
      ) && ($bunriShotoku('sanrin', $p) > 0);

    // (3) 退職：[(Sあり & D<0) または (Sなし)] かつ 退職あり
    $show['taishoku'][$p] =
      (
        ($taxableBase[$p] > 0 && $humanAdjTaxable[$p] < 0)
        || ($taxableBase[$p] === 0)
      ) && ($bunriShotoku('taishoku', $p) > 0);

    // 採用（両方表示のときだけ）
    if ($show['sanrin'][$p] && $show['taishoku'][$p]) {
      $rateAdopt = null;
      if ($rate['sanrin'][$p] !== null && $rate['taishoku'][$p] !== null) {
        $rateAdopt = min((float)$rate['sanrin'][$p], (float)$rate['taishoku'][$p]);
      }
      $rate['adopted'][$p] = $rateAdopt;
      $show['adopted'][$p] = ($rateAdopt !== null);
    } else {
      $rate['adopted'][$p] = null;
      $show['adopted'][$p] = false;
    }

    // 分離有無（いずれか>0）
    $anyBunriIncome =
      ($bunriShotoku('tanki', $p) > 0) ||
      ($bunriShotoku('choki', $p) > 0) ||
      ($bunriShotoku('joto',  $p) > 0) ||
      ($bunriShotoku('haito', $p) > 0) ||
      ($bunriShotoku('sakimono', $p) > 0);

    // (2) 90%：Sあり & D<0 & 山林/退職なし & 分離なし
    $show['rate90'][$p] =
      ($taxableBase[$p] > 0) &&
      ($humanAdjTaxable[$p] < 0) &&
      (!$show['sanrin'][$p]) &&
      (!$show['taishoku'][$p]) &&
      (!$anyBunriIncome);

    // (4) 分離最小：
    //  - (2)(3)に該当する場合（Sあり& D<0、または Sなし）又は S/F/R を全て有しない場合
    //  - かつ分離所得あり
    // ※ここでは「Sなし（taxableBase==0）」も対象に含めるため、分離だけでも表示される
    $cond24 = (($taxableBase[$p] > 0 && $humanAdjTaxable[$p] < 0) || ($taxableBase[$p] === 0));
    $show['bunrimin'][$p] = $cond24 && $anyBunriIncome;
    if ($show['bunrimin'][$p]) {
      // 係数：短期>0 →59.370、短期=0かつ他>0 →74.685
      if ($bunriShotoku('tanki', $p) > 0) {
        $rate['bunrimin'][$p] = 59.370;
      } elseif ($anyBunriIncome) {
        $rate['bunrimin'][$p] = 74.685;
      } else {
        $rate['bunrimin'][$p] = null;
      }
    } else {
      $rate['bunrimin'][$p] = null;
    }
  }

  // 表示用フォーマッタ（% 文字列／空欄対応）
  $fmtPct = function($v) {
    if ($v === null || $v === '' ) return '';
    // 画面セルは xx.xxx% 固定
    $s = number_format((float)$v, 3, '.', '');
    return $s . '%';
  };

  // 最終率（表示用）：(1)(2)(3)(4) の候補から min を採用
  foreach ($periods as $p) {
    $cands = [];
    if ($show['standard'][$p] && $rate['standard'][$p] !== null) {
      $cands[] = (float) $rate['standard'][$p];
    }
    if ($show['rate90'][$p]) {
      $cands[] = 90.000;
    }
    if ($show['adopted'][$p] && isset($rate['adopted'][$p]) && $rate['adopted'][$p] !== null) {
      $cands[] = (float) $rate['adopted'][$p];
    }
    if ($show['bunrimin'][$p] && isset($rate['bunrimin'][$p]) && $rate['bunrimin'][$p] !== null) {
      $cands[] = (float) $rate['bunrimin'][$p];
    }

    if ($cands === []) {
      $rate['final'][$p] = null;
      $show['final'][$p] = false;
    } else {
      $rate['final'][$p] = min($cands);
      $show['final'][$p] = true;
    }
  }
@endphp
@php
  // テスト互換用：上表の「調整後課税」を素テキストで 1 行出す（視覚的に非表示）
  // 優先：payload(human_adjusted_taxable_*) → view内計算（住民税tb−人的控除差）
  $adjPrevDisplay = array_key_exists('human_adjusted_taxable_prev', $inputs)
      ? (int)($inputs['human_adjusted_taxable_prev'] ?? 0)
      : $floorDisplay($humanAdjTaxable['prev'] ?? null);
  $adjCurrDisplay = array_key_exists('human_adjusted_taxable_curr', $inputs)
      ? (int)($inputs['human_adjusted_taxable_curr'] ?? 0)
      : $floorDisplay($humanAdjTaxable['curr'] ?? null);
  $adjPrevText = $adjPrevDisplay !== null ? number_format((int) $adjPrevDisplay) : '';
  $adjCurrText = $adjCurrDisplay !== null ? number_format((int) $adjCurrDisplay) : '';
@endphp

@if($adjPrevText !== '' || $adjCurrText !== '')
  <div class="visually-hidden" aria-hidden="true">
    課税総所得金額-人的控除差調整額 前年：{{ $adjPrevText }} 当年：{{ $adjCurrText }}
  </div>
@endif

<div class="wrapper pt-2">
  <div class="table-responsive">
    <table class="table table-base align-middle" style="width:370px">
            <colgroup>
              <col style="width:250px">
              <col style="width:120px">
            </colgroup>
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
                  // ▼ 方針変更（定義の固定）：
                  // 基礎控除の人的控除差は常に 50,000 円で固定して表示する。
                  if (($row['key'] ?? '') === 'kiso') {
                      $raw = '50000';
                      $displayValue = 50000;
                  } else {
                      $raw = $rawInt($inputs, $inputPrev, $fallbackPrev);
                      $displayValue = $fallbackPrev !== null ? (int) $fallbackPrev : (is_numeric($raw) ? (int) $raw : null);
                  }
                @endphp
                <input type="hidden" name="{{ $inputPrev }}" value="{{ $raw }}">{{ $dispInt($displayValue) }}
              </td>
            @endif
            @if($showCurr)
              <td class="text-end">
                @php
                  // ▼ 方針変更（定義の固定）：
                  // 基礎控除の人的控除差は常に 50,000 円で固定して表示する。
                  if (($row['key'] ?? '') === 'kiso') {
                      $raw = '50000';
                      $displayValue = 50000;
                  } else {
                      $raw = $rawInt($inputs, $inputCurr, $fallbackCurr);
                      $displayValue = $fallbackCurr !== null ? (int) $fallbackCurr : (is_numeric($raw) ? (int) $raw : null);
                  }
                @endphp
                <input type="hidden" name="{{ $inputCurr }}" value="{{ $raw }}">{{ $dispInt($displayValue) }}
              </td>
            @endif
          </tr>
        @endforeach
        <tr>
          <th class="th-cream">課税総所得金額-人的控除差調整額</th>
          @php
            // ▼ 表示は「サーバ確定(human_adjusted_taxable_*)」が最優先
            //   無い場合のみ「住民税 tb_sogo_jumin − 人的控除差」を千円切捨てで計算
            $fallbackPrev = array_key_exists('human_adjusted_taxable_prev', $inputs)
                ? (int)($inputs['human_adjusted_taxable_prev'] ?? 0)
                : $floorDisplay($humanAdjTaxable['prev'] ?? null);
            $fallbackCurr = array_key_exists('human_adjusted_taxable_curr', $inputs)
                ? (int)($inputs['human_adjusted_taxable_curr'] ?? 0)
                : $floorDisplay($humanAdjTaxable['curr'] ?? null);
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
    $fmt = static function ($v): string {
        if ($v === null) {
            return '';
        }

        $formatted = number_format((float) $v, 3, '.', '');
        $trimmed = rtrim(rtrim($formatted, '0'), '.');

        return $trimmed . '%';
    };

    // ▼ 標準率の raw/display（後段の「特例控除率（標準）」行で使用）
    [$stdPrevRaw, $stdPrevDisp] = $formatPercentPair($stdPrev);
    [$stdCurrRaw, $stdCurrDisp] = $formatPercentPair($stdCurr);
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
            $resultsUpper[sprintf('human_adjusted_taxable_%s', $period)] ?? null,
            $inputs[sprintf('human_adjusted_taxable_%s', $period)] ?? null,
            $humanAdjTaxable[$period] ?? null,
        ]);

        $sanrinBase = $firstNumber([
            $resultsUpper[sprintf('tb_sanrin_shotoku_%s', $period)] ?? null,
            $inputs[sprintf('tb_sanrin_shotoku_%s', $period)] ?? null,
        ]);
        $taishokuBase = $firstNumber([
            $resultsUpper[sprintf('tb_taishoku_shotoku_%s', $period)] ?? null,
            $inputs[sprintf('tb_taishoku_shotoku_%s', $period)] ?? null,
        ]);

        $shortBase = $firstNumber([
            $resultsUpper[sprintf('tb_joto_tanki_shotoku_%s', $period)] ?? null,
            $inputs[sprintf('tb_joto_tanki_shotoku_%s', $period)] ?? null,
        ]);
        $longBase = $firstNumber([
            $resultsUpper[sprintf('tb_joto_choki_shotoku_%s', $period)] ?? null,
            $inputs[sprintf('tb_joto_choki_shotoku_%s', $period)] ?? null,
        ]);
        $haitoBase = $firstNumber([
            $resultsUpper[sprintf('tb_jojo_kabuteki_haito_shotoku_%s', $period)] ?? null,
            $inputs[sprintf('tb_jojo_kabuteki_haito_shotoku_%s', $period)] ?? null,
        ]);
        $sakimonoBase = $firstNumber([
            $resultsUpper[sprintf('tb_sakimono_shotoku_%s', $period)] ?? null,
            $inputs[sprintf('tb_sakimono_shotoku_%s', $period)] ?? null,
        ]);
        $jotoBase = $firstNumber([
            ($resultsUpper[sprintf('tb_ippan_kabuteki_joto_shotoku_%s', $period)] ?? 0) +
            ($resultsUpper[sprintf('tb_jojo_kabuteki_joto_shotoku_%s',  $period)] ?? 0),
            ($inputs[sprintf('tb_ippan_kabuteki_joto_shotoku_%s', $period)] ?? 0) +
            ($inputs[sprintf('tb_jojo_kabuteki_joto_shotoku_%s',  $period)] ?? 0),
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
    <table class="table table-base align-middle" style="width:370px">
            <colgroup>
              <col style="width:250px">
              <col style="width:120px">
            </colgroup>
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
              @php $out = $show['standard']['prev'] ? $stdPrevDisp : ''; @endphp
              <input type="hidden" name="tokurei_rate_standard_prev" value="{{ $show['standard']['prev'] ? $stdPrevRaw : '' }}">{{ $out }}
            </td>
          @endif
          @if($showCurr)
            <td class="text-end">
              @php $out = $show['standard']['curr'] ? $stdCurrDisp : ''; @endphp
              <input type="hidden" name="tokurei_rate_standard_curr" value="{{ $show['standard']['curr'] ? $stdCurrRaw : '' }}">{{ $out }}
            </td>
          @endif
        </tr>
        <tr>
          <th scope="row" class="text-start ps-1">特例控除率（90％）</th>
          @if($showPrev)
            <td class="text-end">
              @php [$raw, $disp] = $valPercent($inputs, 'tokurei_rate_90_prev', $tkComputed['ninety_prev'] ?? 90.000, $prevDetails['AA51'] ?? 0.90); @endphp
              <input type="hidden" name="tokurei_rate_90_prev" value="{{ $show['rate90']['prev'] ? $raw : '' }}">{{ $show['rate90']['prev'] ? $disp : '' }}
            </td>
          @endif
          @if($showCurr)
            <td class="text-end">
              @php [$raw, $disp] = $valPercent($inputs, 'tokurei_rate_90_curr', $tkComputed['ninety_curr'] ?? 90.000, $currDetails['AA51'] ?? 0.90); @endphp
              <input type="hidden" name="tokurei_rate_90_curr" value="{{ $show['rate90']['curr'] ? $raw : '' }}">{{ $show['rate90']['curr'] ? $disp : '' }}
            </td>
          @endif
        </tr>
        <tr>
          <th scope="row" class="text-start ps-1">山林所得（1/5）ベース</th>
          @if($showPrev)
            <td class="text-end">
              @php [$raw, $disp] = $valPercentEnabled($inputs, 'tokurei_rate_sanrin_div5_prev', true, $tkComputed['sanrin_prev'] ?? null); @endphp
              <input type="hidden" name="tokurei_rate_sanrin_div5_prev" value="{{ $show['sanrin']['prev'] ? $raw : '' }}">{{ $show['sanrin']['prev'] ? $disp : '' }}
            </td>
          @endif
          @if($showCurr)
            <td class="text-end">
              @php [$raw, $disp] = $valPercentEnabled($inputs, 'tokurei_rate_sanrin_div5_curr', true, $tkComputed['sanrin_curr'] ?? null); @endphp
              <input type="hidden" name="tokurei_rate_sanrin_div5_curr" value="{{ $show['sanrin']['curr'] ? $raw : '' }}">{{ $show['sanrin']['curr'] ? $disp : '' }}
            </td>
          @endif
        </tr>
        <tr>
          <th scope="row" class="text-start ps-1">退職所得ベース</th>
          @if($showPrev)
            <td class="text-end">
              @php [$raw, $disp] = $valPercentEnabled($inputs, 'tokurei_rate_taishoku_prev', true, $tkComputed['taishoku_prev'] ?? null); @endphp
              <input type="hidden" name="tokurei_rate_taishoku_prev" value="{{ $show['taishoku']['prev'] ? $raw : '' }}">{{ $show['taishoku']['prev'] ? $disp : '' }}
            </td>
          @endif
          @if($showCurr)
            <td class="text-end">
              @php [$raw, $disp] = $valPercentEnabled($inputs, 'tokurei_rate_taishoku_curr', true, $tkComputed['taishoku_curr'] ?? null); @endphp
              <input type="hidden" name="tokurei_rate_taishoku_curr" value="{{ $show['taishoku']['curr'] ? $raw : '' }}">{{ $show['taishoku']['curr'] ? $disp : '' }}
            </td>
          @endif
        </tr>
        <tr>
          <th scope="row" class="text-start ps-1">採用率（山林／退職の小さい方）</th>
          @if($showPrev)
            <td class="text-end">
              @php
                $raw = $show['adopted']['prev'] ? number_format((float)$rate['adopted']['prev'], 3, '.', '') : '';
                $disp = $show['adopted']['prev'] ? $fmtPct($rate['adopted']['prev']) : '';
              @endphp
              <input type="hidden" name="tokurei_rate_adopted_prev" value="{{ $raw }}">{{ $disp }}
            </td>
          @endif
          @if($showCurr)
            <td class="text-end">
              @php
                $raw = $show['adopted']['curr'] ? number_format((float)$rate['adopted']['curr'], 3, '.', '') : '';
                $disp = $show['adopted']['curr'] ? $fmtPct($rate['adopted']['curr']) : '';
              @endphp
              <input type="hidden" name="tokurei_rate_adopted_curr" value="{{ $raw }}">{{ $disp }}
            </td>
          @endif
        </tr>
        <tr>
          <th scope="row" class="text-start ps-1">分離課税に基づく率（最小）</th>
          @if($showPrev)
            <td class="text-end">
              @php
                $raw = $show['bunrimin']['prev'] && isset($rate['bunrimin']['prev']) ? number_format((float)$rate['bunrimin']['prev'], 3, '.', '') : '';
                $disp = $show['bunrimin']['prev'] && isset($rate['bunrimin']['prev']) ? $fmtPct($rate['bunrimin']['prev']) : '';
              @endphp
              <input type="hidden" name="tokurei_rate_bunri_min_prev" value="{{ $raw }}">{{ $disp }}
            </td>
          @endif
          @if($showCurr)
            <td class="text-end">
              @php
                $raw = $show['bunrimin']['curr'] && isset($rate['bunrimin']['curr']) ? number_format((float)$rate['bunrimin']['curr'], 3, '.', '') : '';
                $disp = $show['bunrimin']['curr'] && isset($rate['bunrimin']['curr']) ? $fmtPct($rate['bunrimin']['curr']) : '';
              @endphp
              <input type="hidden" name="tokurei_rate_bunri_min_curr" value="{{ $raw }}">{{ $disp }}
            </td>
          @endif
        </tr>
        <tr class="table-primary">
          <th scope="row" class="text-center th-cream">特例控除 最終率</th>
          @if($showPrev)
            <td class="text-end">
              @php
                $raw = ($show['final']['prev'] && $rate['final']['prev'] !== null)
                  ? number_format((float)$rate['final']['prev'], 3, '.', '')
                  : '';
                $disp = ($show['final']['prev'] && $rate['final']['prev'] !== null)
                  ? $fmtPct($rate['final']['prev'])
                  : '－';
              @endphp
              <input type="hidden" name="tokurei_rate_final_prev" value="{{ $raw }}"><span class="fw-bold">{{ $disp }}</span>
            </td>
          @endif
          @if($showCurr)
            <td class="text-end">
              @php
                $raw = ($show['final']['curr'] && $rate['final']['curr'] !== null)
                  ? number_format((float)$rate['final']['curr'], 3, '.', '')
                  : '';
                $disp = ($show['final']['curr'] && $rate['final']['curr'] !== null)
                  ? $fmtPct($rate['final']['curr'])
                  : '－';
              @endphp
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
      @php $isBunriOff = ($suffix === 'prev') ? $bunriPrevOff : $bunriCurrOff; @endphp
      <div class="mt-4">
        <div class="fw-bold ms-10">（{{ $label }}）</div>
        <div class="table-responsive">
          <table class="table table-input align-middle" style="width:737px">
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
                <th class="th-ddd" style="width:40px">総合</th>
                <td class="text-end">
                  <input type="text"
                         readonly
                         name="sashihiki_joto_tanki_sogo_{{ $suffix }}"
                         class="form-control suji11 text-end bg-light"
                         value="{{ $readonlyValue('sashihiki_joto_tanki_sogo_' . $suffix) }}">
                </td>
                <th rowspan="2" class="vtext" style="width:35px">通算</th>
                <td class="text-end">
                  <input type="text"
                         readonly
                         name="tsusango_joto_tanki_sogo_{{ $suffix }}"
                         class="form-control suji11 text-end bg-light"
                         value="{{ $readonlyValue('tsusango_joto_tanki_sogo_' . $suffix) }}">
                </td>
                <td class="text-end">
                  <input type="text"
                         readonly
                         name="tokubetsukojo_joto_tanki_sogo_{{ $suffix }}"
                         class="form-control suji11 text-end bg-light"
                         value="{{ $readonlyValue('tokubetsukojo_joto_tanki_sogo_' . $suffix) }}">
                </td>
                <th rowspan="4" class="lh-1" style="width:50px">所譲<br>得渡<br>の  ・<br>通一<br>算時</th>
                <td class="text-end">
                  <input type="text"
                         readonly
                         name="after_joto_ichiji_tousan_joto_tanki_sogo_{{ $suffix }}"
                         class="form-control suji11 text-end bg-light"
                         value="{{ $readonlyValue('after_joto_ichiji_tousan_joto_tanki_sogo_' . $suffix) }}">
                </td>
              </tr>
              <tr>
                <th>長期</th>
                <th class="th-ddd">総合</th>
                <td class="text-end">
                  <input type="text"
                         readonly
                         name="sashihiki_joto_choki_sogo_{{ $suffix }}"
                         class="form-control suji11 text-end bg-light"
                         value="{{ $readonlyValue('sashihiki_joto_choki_sogo_' . $suffix) }}">
                </td>
                <td class="text-end">
                  <input type="text"
                         readonly
                        name="tsusango_joto_choki_sogo_{{ $suffix }}"
                         class="form-control suji11 text-end bg-light"
                         value="{{ $readonlyValue('tsusango_joto_choki_sogo_' . $suffix) }}">
                </td>
                <td class="text-end">
                  <input type="text"
                         readonly
                        name="tokubetsukojo_joto_choki_sogo_{{ $suffix }}"
                         class="form-control suji11 text-end bg-light"
                         value="{{ $readonlyValue('tokubetsukojo_joto_choki_sogo_' . $suffix) }}">
                </td>
                <td class="text-end">
                  <input type="text"
                         readonly
                        name="after_joto_ichiji_tousan_joto_choki_sogo_{{ $suffix }}"
                         class="form-control suji11 text-end bg-light"
                         value="{{ $readonlyValue('after_joto_ichiji_tousan_joto_choki_sogo_' . $suffix) }}">
                </td>
              </tr>
              <tr>
              <tr>
                <th class="text-start ps-1" colspan="3">一時</th>
                <td colspan="2" class="text-center">⇒</td>
                @php
                  // 一時所得の「差引金額」＝収入－必要経費（0未満は0）をその場で再計算
                  $syunyuIchiji  = (int)($inputs['syunyu_ichiji_' . $suffix] ?? 0);
                  $keihiIchiji   = (int)($inputs['keihi_ichiji_'  . $suffix] ?? 0);
                  $sashihikiIchiji = max(0, $syunyuIchiji - $keihiIchiji);
                @endphp
                <td class="text-end">
                  <input type="text"
                         readonly
                         name="tsusango_ichiji_{{ $suffix }}"
                         class="form-control suji11 text-end bg-light"
                         value="{{ number_format($sashihikiIchiji) }}">
                </td>
                <td class="text-end">
                  <input type="text"
                         readonly
                         name="tokubetsukojo_ichiji_{{ $suffix }}"
                         class="form-control suji11 text-end bg-light"
                         value="{{ $readonlyValue('tokubetsukojo_ichiji_' . $suffix) }}">
                </td>
                <td class="text-end">
                  <input type="text"
                         readonly
                         name="after_joto_ichiji_tousan_ichiji_{{ $suffix }}"
                         class="form-control suji11 text-end bg-light"
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
        <div class="fw-bold ms-10">（{{ $label }}）</div>
        <div class="table-responsive">
          <table class="table table-input align-middle" style="width:885px">
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
                <td class="text-end" style="width:132px">
                  <input type="text"
                         readonly
                         name="tsusanmae_keijo_{{ $suffix }}"
                         class="form-control suji11 text-end bg-light"
                         value="{{ $readonlyValue('tsusanmae_keijo_' . $suffix) }}">
                </td>
                <th rowspan="4" class="lh-1" style="width:35px">第<br>1<br>次<br>通<br>算</th>
                <td class="text-end" style="width:132px">
                  <input type="text"
                         readonly
                         name="after_1jitsusan_keijo_{{ $suffix }}"
                         class="form-control suji11 text-end bg-light"
                         value="{{ $readonlyValue('after_1jitsusan_keijo_' . $suffix) }}">
                </td>
                <th rowspan="5" style="width:35px">第<br>2<br>次<br>通<br>算</th>
                <td class="text-end" style="width:132px">
                  <input type="text"
                         readonly
                         name="after_2jitsusan_keijo_{{ $suffix }}"
                         class="form-control suji11 text-end bg-light"
                         value="{{ $readonlyValue('after_2jitsusan_keijo_' . $suffix) }}">
                </td>
                <th rowspan="6" style="width:35px">第<br>3<br>次<br>通<br>算</th>
                <td class="text-end" style="width:132px">
                  <input type="text"
                         readonly
                         name="after_3jitsusan_keijo_{{ $suffix }}"
                         class="form-control suji11 text-end bg-light"
                         value="{{ $readonlyValue('after_3jitsusan_keijo_' . $suffix) }}">
                </td>
                <td class="text-end" style="width:132px">
                  <input type="text"
                         readonly
                         name="shotoku_keijo_{{ $suffix }}"
                         class="form-control suji11 text-end bg-light"
                         value="{{ $readonlyValue('shotoku_keijo_' . $suffix) }}">
                </td>
              </tr>
              <tr>
                <th rowspan="2" style="width:40px">譲渡</th>
                <th style="width:40px">短期</th>
                <th class="th-ddd" style="width:40px">総合</th>
                <td class="text-end">
                  @php
                    $tsusanmaeJotoTankiValue = $readonlyValue('tsusanmae_joto_tanki_sogo_' . $suffix);
                    if ($tsusanmaeJotoTankiValue === '') {
                        $tsusanmaeJotoTankiValue = $readonlyValue('after_joto_ichiji_tousan_joto_tanki_sogo_' . $suffix);
                    }
                  @endphp
                  <input type="text"
                         readonly
                         name="tsusanmae_joto_tanki_sogo_{{ $suffix }}"
                         class="form-control suji11 text-end bg-light"
                         value="{{ $tsusanmaeJotoTankiValue }}">
                </td>
                <td class="text-end">
                  <input type="text"
                         readonly
                         name="after_1jitsusan_joto_tanki_sogo_{{ $suffix }}"
                         class="form-control suji11 text-end bg-light"
                         value="{{ $readonlyValue('after_1jitsusan_joto_tanki_sogo_' . $suffix) }}">
                </td>
                <td class="text-end">
                  <input type="text"
                         readonly
                         name="after_2jitsusan_joto_tanki_sogo_{{ $suffix }}"
                         class="form-control suji11 text-end bg-light"
                         value="{{ $readonlyValue('after_2jitsusan_joto_tanki_sogo_' . $suffix) }}">
                </td>
                <td class="text-end">
                  <input type="text"
                         readonly
                         name="after_3jitsusan_joto_tanki_sogo_{{ $suffix }}"
                         class="form-control suji11 text-end bg-light"
                         value="{{ $readonlyValue('after_3jitsusan_joto_tanki_sogo_' . $suffix) }}">
                </td>
                <td class="text-end">
                  <input type="text"
                         readonly
                        name="shotoku_joto_tanki_sogo_{{ $suffix }}"
                         class="form-control suji11 text-end bg-light"
                         value="{{ $readonlyValue('after_3jitsusan_joto_tanki_sogo_' . $suffix) }}">
                </td>
              </tr>
              <tr>
                <th>長期</th>
                <th class="th-ddd">総合</th>
                <td class="text-end">
                  @php
                    $tsusanmaeJotoChokiValue = $readonlyValue('tsusanmae_joto_choki_sogo_' . $suffix);
                    if ($tsusanmaeJotoChokiValue === '') {
                        $tsusanmaeJotoChokiValue = $readonlyValue('after_joto_ichiji_tousan_joto_choki_sogo_' . $suffix);
                    }
                  @endphp
                  <input type="text"
                         readonly
                         name="tsusanmae_joto_choki_sogo_{{ $suffix }}"
                         class="form-control suji11 text-end bg-light"
                         value="{{ $tsusanmaeJotoChokiValue }}">
                </td>
                <td class="text-end">
                  <input type="text"
                         readonly
                         name="after_1jitsusan_joto_choki_sogo_{{ $suffix }}"
                         class="form-control suji11 text-end bg-light"
                         value="{{ $readonlyValue('after_1jitsusan_joto_choki_sogo_' . $suffix) }}">
                </td>
                <td class="text-end">
                  <input type="text"
                         readonly
                         name="after_2jitsusan_joto_choki_sogo_{{ $suffix }}"
                         class="form-control suji11 text-end bg-light"
                         value="{{ $readonlyValue('after_2jitsusan_joto_choki_sogo_' . $suffix) }}">
                </td>
                <td class="text-end">
                  <input type="text"
                         readonly
                         name="after_3jitsusan_joto_choki_sogo_{{ $suffix }}"
                         class="form-control suji11 text-end bg-light"
                         value="{{ $readonlyValue('after_3jitsusan_joto_choki_sogo_' . $suffix) }}">
                </td>
                <td class="text-end">
                  <input type="text"
                         readonly
                         name="shotoku_joto_choki_sogo_{{ $suffix }}"
                         class="form-control suji11 text-end bg-light"
                         value="{{ $readonlyValue('shotoku_joto_choki_sogo_' . $suffix) }}">
                </td>
              </tr>
              <tr>
                <th colspan="3" class="text-start ps-1">一時</th>
                <td class="text-end">
                  @php
                    $tsusanmaeIchijiValue = $readonlyValue('tsusanmae_ichiji_' . $suffix);
                    if ($tsusanmaeIchijiValue === '') {
                        $tsusanmaeIchijiValue = $readonlyValue('after_joto_ichiji_tousan_ichiji_' . $suffix);
                    }
                  @endphp
                  <input type="text"
                         readonly
                         name="tsusanmae_ichiji_{{ $suffix }}"
                         class="form-control suji11 text-end bg-light"
                         value="{{ $tsusanmaeIchijiValue }}">
                </td>
                <td class="text-end">
                  <input type="text"
                         readonly
                         name="after_1jitsusan_ichiji_{{ $suffix }}"
                         class="form-control suji11 text-end bg-light"
                         value="{{ $readonlyValue('after_1jitsusan_ichiji_' . $suffix) }}">
                </td>
                <td class="text-end">
                  <input type="text"
                         readonly
                         name="after_2jitsusan_ichiji_{{ $suffix }}"
                         class="form-control suji11 text-end bg-light"
                         value="{{ $readonlyValue('after_2jitsusan_ichiji_' . $suffix) }}">
                </td>
                <td class="text-end">
                  <input type="text"
                         readonly
                         name="after_3jitsusan_ichiji_{{ $suffix }}"
                         class="form-control suji11 text-end bg-light"
                         value="{{ $readonlyValue('after_3jitsusan_ichiji_' . $suffix) }}">
                </td>
                <td class="text-end">
                  <input type="text"
                         readonly
                         name="shotoku_ichiji_{{ $suffix }}"
                         class="form-control suji11 text-end bg-light"
                         value="{{ $readonlyValue('shotoku_ichiji_' . $suffix) }}">
                </td>
              </tr>
              <tr>
                <th colspan="3" class="text-start ps-1">山林</th>
                <td colspan="2" class="text-center">⇒</td>
                <td class="text-end">
                  @if($isBunriOff)
                    <input type="text" readonly class="form-control suji11 text-center bg-light" value="－">
                    <input type="hidden" name="after_1jitsusan_sanrin_{{ $suffix }}" value="0">
                  @else
                  @php
                    // after_1 = 差引金額 − 特別控除額（小数点以下切捨て、千円丸めはしない）
                    $rawSashihiki = str_replace(',', '', (string)$readonlyValue('sashihiki_sanrin_' . $suffix));
                    $rawTokubetsu = str_replace(',', '', (string)$readonlyValue('tokubetsukojo_sanrin_' . $suffix));
                    $numSashihiki = is_numeric($rawSashihiki) ? (int) floor((float)$rawSashihiki) : 0;
                    $numTokubetsu = is_numeric($rawTokubetsu) ? (int) floor((float)$rawTokubetsu) : 0;
                    $after1Calc   = $numSashihiki - $numTokubetsu;
                  @endphp
                  <input type="text"
                         readonly
                         name="after_1jitsusan_sanrin_{{ $suffix }}"
                         class="form-control suji11 text-end bg-light"
                         value="{{ number_format($after1Calc) }}">
                  @endif
                </td>
                <td class="text-end">
                  @if($isBunriOff)
                    <input type="text" readonly class="form-control suji11 text-center bg-light" value="－">
                    <input type="hidden" name="after_2jitsusan_sanrin_{{ $suffix }}" value="0">
                  @else
                    <input type="text"
                           readonly
                           name="after_2jitsusan_sanrin_{{ $suffix }}"
                           class="form-control suji11 text-end bg-light"
                           value="{{ $readonlyValue('after_2jitsusan_sanrin_' . $suffix) }}">
                  @endif
                </td>
                <td class="text-end">
                  @if($isBunriOff)
                    <input type="text" readonly class="form-control suji11 text-center bg-light" value="－">
                    <input type="hidden" name="after_3jitsusan_sanrin_{{ $suffix }}" value="0">
                  @else
                    <input type="text"
                           readonly
                           name="after_3jitsusan_sanrin_{{ $suffix }}"
                           class="form-control suji11 text-end bg-light"
                           value="{{ $readonlyValue('after_3jitsusan_sanrin_' . $suffix) }}">
                  @endif
                </td>
                <td class="text-end">
                  @if($isBunriOff)
                    <input type="text" readonly class="form-control suji11 text-center bg-light" value="－">
                    <input type="hidden" name="shotoku_sanrin_{{ $suffix }}" value="0">
                  @else
                    @php
                      // 最終「山林所得金額」＝ 損益通算後（after_3）
                      $valSanrin = $firstNumber([
                        $readonlyValue('after_3jitsusan_sanrin_' . $suffix),
                        $inputs['shotoku_sanrin_' . $suffix] ?? null,
                        $resultsUpper['shotoku_sanrin_' . $suffix] ?? null,
                        $prevDetails['shotoku_sanrin_' . $suffix] ?? null,
                      ]);
                      $valSanrinDisp = $valSanrin === null ? '' : number_format((int)$valSanrin);
                    @endphp
                    <input type="text"
                           readonly
                           name="shotoku_sanrin_{{ $suffix }}"
                           class="form-control suji11 text-end bg-light"
                           value="{{ $valSanrinDisp }}">
                  @endif
                </td>
              </tr>
              <tr>
                <th colspan="3" class="text-start ps-1">退職</th>
                <td colspan="4" class="text-center">⇒</td>
                <td class="text-end">
                  @if($isBunriOff)
                    <input type="text" readonly class="form-control suji11 text-center bg-light" value="－">
                    <input type="hidden" name="after_2jitsusan_taishoku_{{ $suffix }}" value="0">
                  @else
                    <input type="text"
                           readonly
                           name="after_2jitsusan_taishoku_{{ $suffix }}"
                           class="form-control suji11 text-end bg-light"
                           value="{{ $readonlyValue('after_2jitsusan_taishoku_' . $suffix) }}">
                  @endif
                </td>
                <td class="text-end">
                  @if($isBunriOff)
                    <input type="text" readonly class="form-control suji11 text-center bg-light" value="－">
                    <input type="hidden" name="after_3jitsusan_taishoku_{{ $suffix }}" value="0">
                  @else
                    <input type="text"
                           readonly
                           name="after_3jitsusan_taishoku_{{ $suffix }}"
                           class="form-control suji11 text-end bg-light"
                           value="{{ $readonlyValue('after_3jitsusan_taishoku_' . $suffix) }}">
                  @endif
                </td>
                <td class="text-end">
                  @if($isBunriOff)
                    <input type="text" readonly class="form-control suji11 text-center bg-light" value="－">
                    <input type="hidden" name="shotoku_taishoku_{{ $suffix }}" value="0">
                  @else
                    @php
                      $valTaishoku = $firstNumber([
                        $readonlyValue('after_3jitsusan_taishoku_' . $suffix),
                        $inputs['shotoku_taishoku_' . $suffix] ?? null,
                        $resultsUpper['shotoku_taishoku_' . $suffix] ?? null,
                        $prevDetails['shotoku_taishoku_' . $suffix] ?? null,
                      ]);
                      $valTaishokuDisp = $valTaishoku === null ? '' : number_format((int)$valTaishoku);
                    @endphp
                    <input type="text"
                           readonly
                           name="shotoku_taishoku_{{ $suffix }}"
                           class="form-control suji11 text-end bg-light"
                           value="{{ $valTaishokuDisp }}">
                  @endif
                </td>
              </tr>
              <tr>
                <th colspan="10" class="th-cream">所得金額の合計額</th>
                <td class="text-end">
                  <input type="text"
                         readonly
                         name="shotoku_gokei_{{ $suffix }}"
                         class="form-control suji11 text-end bg-light"
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
      <div class="fw-bold ms-5">譲渡所得に係る所得の損益通算</div>
      @foreach ($warekiTables as $suffix => $label)
        @php
          $isBunriOff = ($suffix === 'prev') ? $bunriPrevOff : $bunriCurrOff;
        @endphp
        <div class="mt-3">
          <div class="fw-bold ms-10">（{{ $label }}）</div>
          <div class="table-responsive">
            <table class="table table-input align-middle" style="width: 546px;">
              <tbody>
                <tr>
                  <th colspan="2" class="th-ccc" style="height:30px;">所得の種類</th>
                  <th class="th-ccc">通算前</th>
                  <th colspan="2" class="th-ccc">第1次通算後</th>
                  <th colspan="2" class="th-ccc">第2次通算後</th>
                </tr>
                <tr>
                  <th rowspan="2" style="width:40px">短期</th>
                  <th class="ps-1 th-ddd" style="width:40px">一般</th>
                  <td class="text-end" style="width:132px">
                    <input type="text"
                           readonly
                           class="form-control suji11 {{ $isBunriOff ? 'text-center' : 'text-end' }} bg-light"
                           value="{{ $isBunriOff ? '－' : $serverDisplay('before_tsusan_tanki_ippan_' . $suffix) }}">
                  </td>
                  <th rowspan="2" class="vtext" style="width:35px">通算</th>
                  <td class="text-end" style="width:132px">
                    <input type="text"
                           readonly
                           class="form-control suji11 {{ $isBunriOff ? 'text-center' : 'text-end' }} bg-light"
                           value="{{ $isBunriOff ? '－' : $serverDisplay('after_1jitsusan_tanki_ippan_' . $suffix) }}">
                  </td>
                  <th rowspan="5" class="vtext" style="width:35px">通算</th>
                  <td class="text-end" style="width:132px">
                    <input type="text"
                           readonly
                           class="form-control suji11 {{ $isBunriOff ? 'text-center' : 'text-end' }} bg-light"
                           value="{{ $isBunriOff ? '－' : $serverDisplay('after_2jitsusan_tanki_ippan_' . $suffix) }}">
                  </td>
                </tr>
                <tr>
                  <th class="ps-1 th-ddd">軽減</th>
                  <td class="text-end">
                    <input type="text"
                           readonly
                           class="form-control suji11 {{ $isBunriOff ? 'text-center' : 'text-end' }} bg-light"
                           value="{{ $isBunriOff ? '－' : $readonlyValue('before_tsusan_tanki_keigen_' . $suffix) }}">
                  </td>
                  <td class="text-end">
                    <input type="text"
                           readonly
                           class="form-control suji11 {{ $isBunriOff ? 'text-center' : 'text-end' }} bg-light"
                           value="{{ $isBunriOff ? '－' : $readonlyValue('after_1jitsusan_tanki_keigen_' . $suffix) }}">
                  </td>
                  <td class="text-end">
                    <input type="text"
                           readonly
                           class="form-control suji11 {{ $isBunriOff ? 'text-center' : 'text-end' }} bg-light"
                           value="{{ $isBunriOff ? '－' : $readonlyValue('after_2jitsusan_tanki_keigen_' . $suffix) }}">
                  </td>
                </tr>
                <tr>
                  <th rowspan="3" style="width:60px">長期</th>
                  <th class="ps-1 th-ddd">一般</th>
                  <td class="text-end">
                    <input type="text"
                           readonly
                           class="form-control suji11 {{ $isBunriOff ? 'text-center' : 'text-end' }} bg-light"
                           value="{{ $isBunriOff ? '－' : $readonlyValue('before_tsusan_choki_ippan_' . $suffix) }}">
                  </td>
                  <th rowspan="3" class="vtext" style="width:35px">通算</th>
                  <td class="text-end">
                    <input type="text"
                           readonly
                           class="form-control suji11 {{ $isBunriOff ? 'text-center' : 'text-end' }} bg-light"
                           value="{{ $isBunriOff ? '－' : $readonlyValue('after_1jitsusan_choki_ippan_' . $suffix) }}">
                  </td>
                  <td class="text-end">
                    <input type="text"
                           readonly
                           class="form-control suji11 {{ $isBunriOff ? 'text-center' : 'text-end' }} bg-light"
                           value="{{ $isBunriOff ? '－' : $readonlyValue('after_2jitsusan_choki_ippan_' . $suffix) }}">
                  </td>
                </tr>
                <tr>
                  <th class="ps-1 th-ddd">特定</th>
                  <td class="text-end">
                    <input type="text"
                           readonly
                           class="form-control suji11 {{ $isBunriOff ? 'text-center' : 'text-end' }} bg-light"
                           value="{{ $isBunriOff ? '－' : $readonlyValue('before_tsusan_choki_tokutei_' . $suffix) }}">
                  </td>
                  <td class="text-end">
                    <input type="text"
                           readonly
                           class="form-control suji11 {{ $isBunriOff ? 'text-center' : 'text-end' }} bg-light"
                           value="{{ $isBunriOff ? '－' : $readonlyValue('after_1jitsusan_choki_tokutei_' . $suffix) }}">
                  </td>
                  <td class="text-end">
                    <input type="text"
                           readonly
                           class="form-control suji11 {{ $isBunriOff ? 'text-center' : 'text-end' }} bg-light"
                           value="{{ $isBunriOff ? '－' : $readonlyValue('after_2jitsusan_choki_tokutei_' . $suffix) }}">
                  </td>
                </tr>
                <tr>
                  <th class="ps-1 th-ddd">軽課</th>
                  <td class="text-end">
                    <input type="text"
                           readonly
                           class="form-control suji11 {{ $isBunriOff ? 'text-center' : 'text-end' }} bg-light"
                           value="{{ $isBunriOff ? '－' : $readonlyValue('before_tsusan_choki_keika_' . $suffix) }}">
                  </td>
                  <td class="text-end">
                    <input type="text"
                           readonly
                           class="form-control suji11 {{ $isBunriOff ? 'text-center' : 'text-end' }} bg-light"
                           value="{{ $isBunriOff ? '－' : $readonlyValue('after_1jitsusan_choki_keika_' . $suffix) }}">
                  </td>
                  <td class="text-end">
                    <input type="text"
                           readonly
                           class="form-control suji11 {{ $isBunriOff ? 'text-center' : 'text-end' }} bg-light"
                           value="{{ $isBunriOff ? '－' : $readonlyValue('after_2jitsusan_choki_keika_' . $suffix) }}">
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      @endforeach
    </div>
    <div class="mt-4">
      <div class="fw-bold ms-5">上場株式等に係る所得の損益通算</div>
      @foreach ($warekiTables as $suffix => $label)
        @php
          $isBunriOff = ($suffix === 'prev') ? $bunriPrevOff : $bunriCurrOff;
        @endphp
        <div class="mt-3">
          <div class="fw-bold ms-10">（{{ $label }}）</div>
          <div class="table-responsive">
            <table class="table table-input align-middle" style="width: 519px;">
              <tbody>
                <tr>
                  <th class="th-ccc" style="height:30px;">所得の種類</th>
                  <th class="th-ccc">通算前</th>
                  <th colspan="2" class="th-ccc">通算後</th>
                </tr>
                <tr>
                  <th class="text-start ps-1" style="width:220px">上場株式等に係る譲渡所得の金額</th>
                  <td class="text-end" style="width:132px">
                    <input type="text"
                           readonly
                           name="before_tsusan_jojo_joto_{{ $suffix }}"
                           class="form-control suji11 {{ $isBunriOff ? 'text-center' : 'text-end' }} bg-light"
                           value="{{ $isBunriOff ? '－' : $readonlyValue('before_tsusan_jojo_joto_' . $suffix) }}">
                  </td>
                  <th rowspan="2" class="vtext" style="width:35px">通算</th>
                  <td class="text-end" style="width:132px">
                    <input type="text"
                           readonly
                           name="after_tsusan_jojo_joto_{{ $suffix }}"
                           class="form-control suji11 {{ $isBunriOff ? 'text-center' : 'text-end' }} bg-light"
                           value="{{ $isBunriOff ? '－' : $readonlyValue('after_tsusan_jojo_joto_' . $suffix) }}">
                  </td>
                </tr>
                <tr>
                  <th class="text-start ps-1">上場株式等に係る配当所得等の金額</th>
                  <td class="text-end">
                    <input type="text"
                           readonly
                           name="before_tsusan_jojo_haito_{{ $suffix }}"
                           class="form-control suji11 {{ $isBunriOff ? 'text-center' : 'text-end' }} bg-light"
                           value="{{ $isBunriOff ? '－' : $readonlyValue('before_tsusan_jojo_haito_' . $suffix) }}">
                  </td>
                  <td class="text-end">
                    <input type="text"
                           readonly
                           name="after_tsusan_jojo_haito_{{ $suffix }}"
                           class="form-control suji11 {{ $isBunriOff ? 'text-center' : 'text-end' }} bg-light"
                           value="{{ $isBunriOff ? '－' : $readonlyValue('after_tsusan_jojo_haito_' . $suffix) }}">
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
      <table class="table table-input align-middle" style="width:584px;">
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
                     class="form-control suji11 bg-light"
                     value="{{ number_format((int)($inputs['tb_sogo_jumin_prev'] ?? 0)) }}">
            </td>
            <td class="text-end">
              <input type="text"
                     readonly
                     name="kazeisoushotoku_curr"
                     class="form-control suji11 bg-light"
                     value="{{ number_format((int)($inputs['tb_sogo_jumin_curr'] ?? 0)) }}">
            </td>
          </tr>
          @php
            // ▼ 仕様3（A案）：住民税の寄付金額は pref/muni 入力のみをSoTにする（旧合算キー参照なし）
            $nInt3 = static function ($v): int {
                if ($v === null || $v === '') return 0;
                if (is_string($v)) $v = str_replace([',', ' '], '', $v);
                return is_numeric($v) ? (int) floor((float) $v) : 0;
            };
            $sumJumin3 = static function (array $inputs, string $area, string $period, array $cats) use ($nInt3): int {
                $sum = 0;
                foreach ($cats as $cat) {
                    $k = sprintf('juminzei_zeigakukojo_%s_%s_%s', $area, $cat, $period);
                    $sum += $nInt3($inputs[$k] ?? 0);
                }
                return $sum;
            };
            $furCats3 = ['furusato'];
            $othCats3 = ['kyodobokin_nisseki', 'npo', 'koueki', 'sonota']; // kuni/seito は住民税税額控除の対象外

            $furPrevPref3 = $sumJumin3($inputs, 'pref', 'prev', $furCats3);
            $furPrevMuni3 = $sumJumin3($inputs, 'muni', 'prev', $furCats3);
            $furCurrPref3 = $sumJumin3($inputs, 'pref', 'curr', $furCats3);
            $furCurrMuni3 = $sumJumin3($inputs, 'muni', 'curr', $furCats3);

            $othPrevPref3 = $sumJumin3($inputs, 'pref', 'prev', $othCats3);
            $othPrevMuni3 = $sumJumin3($inputs, 'muni', 'prev', $othCats3);
            $othCurrPref3 = $sumJumin3($inputs, 'pref', 'curr', $othCats3);
            $othCurrMuni3 = $sumJumin3($inputs, 'muni', 'curr', $othCats3);
          @endphp

          {{-- ふるさと納税寄付金額（都道府県／市区町村） --}}
          <tr>
            <th rowspan="2" class="text-start ps-1 align-middle" style="width:200px;">ふるさと納税寄付金額</th>
            <th class="text-start ps-1" style="width:120px;">都道府県</th>
            <td class="text-end" style="width:132px;">
              <input type="text"
                     readonly
                     class="form-control suji11 bg-light"
                     value="{{ number_format($furPrevPref3) }}">
            </td>
            <td class="text-end" style="width:132px;">
              <input type="text"
                     readonly
                     class="form-control suji11 bg-light"
                     value="{{ number_format($furCurrPref3) }}">
            </td>
          </tr>
          <tr>
            <th class="text-start ps-1">市区町村</th>
            <td class="text-end">
              <input type="text"
                     readonly
                     class="form-control suji11 bg-light"
                     value="{{ number_format($furPrevMuni3) }}">
            </td>
            <td class="text-end">
              <input type="text"
                     readonly
                     class="form-control suji11 bg-light"
                     value="{{ number_format($furCurrMuni3) }}">
            </td>
          </tr>

          {{-- その他寄付金額（共同募金等・NPO・公益・その他）（都道府県／市区町村） --}}
          <tr>
            <th rowspan="2" class="text-start ps-1 align-middle">その他寄付金額</th>
            <th class="text-start ps-1">都道府県</th>
            <td class="text-end">
              <input type="text"
                     readonly
                     class="form-control suji11 bg-light"
                     value="{{ number_format($othPrevPref3) }}">
            </td>
            <td class="text-end">
              <input type="text"
                     readonly
                     class="form-control suji11 bg-light"
                     value="{{ number_format($othCurrPref3) }}">
            </td>
          </tr>
          <tr>
            <th class="text-start ps-1">市区町村</th>
            <td class="text-end">
              <input type="text"
                     readonly
                     class="form-control suji11 bg-light"
                     value="{{ number_format($othPrevMuni3) }}">
            </td>
            <td class="text-end">
              <input type="text"
                     readonly
                     class="form-control suji11 bg-light"
                     value="{{ number_format($othCurrMuni3) }}">
            </td>
          </tr>
          <tr>
            <th rowspan="2" class="text-start ps-1 align-middle">調整控除前所得割額</th>
            <th class="text-start ps-1">都道府県</th>
            <td class="text-end">
              <input type="text"
                     readonly
                     name="chosei_mae_shotokuwari_pref_prev"
                     class="form-control suji11 bg-light"
                     value="{{ $readonlyValue('chosei_mae_shotokuwari_pref_prev') }}">
            </td>
            <td class="text-end">
              <input type="text"
                     readonly
                     name="chosei_mae_shotokuwari_pref_curr"
                     class="form-control suji11 bg-light"
                     value="{{ $readonlyValue('chosei_mae_shotokuwari_pref_curr') }}">
            </td>
          </tr>
          <tr>
            <th class="text-start ps-1">市区町村</th>
            <td class="text-end">
              <input type="text"
                     readonly
                     name="chosei_mae_shotokuwari_muni_prev"
                     class="form-control suji11 bg-light"
                     value="{{ $readonlyValue('chosei_mae_shotokuwari_muni_prev') }}">
            </td>
            <td class="text-end">
              <input type="text"
                     readonly
                     name="chosei_mae_shotokuwari_muni_curr"
                     class="form-control suji11 bg-light"
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
                     class="form-control suji11 bg-light"
                     value="{{ $readonlyValue('chosei_kojo_pref_prev') }}">
            </td>
            <td class="text-end">
              <input type="text"
                     readonly
                     name="chosei_kojo_pref_curr"
                     class="form-control suji11 bg-light"
                     value="{{ $readonlyValue('chosei_kojo_pref_curr') }}">
            </td>
          </tr>
          <tr>
            <th class="text-start ps-1">市区町村</th>
            <td class="text-end">
              <input type="text"
                     readonly
                     name="chosei_kojo_muni_prev"
                     class="form-control suji11 bg-light"
                     value="{{ $readonlyValue('chosei_kojo_muni_prev') }}">
            </td>
            <td class="text-end">
              <input type="text"
                     readonly
                     name="chosei_kojo_muni_curr"
                     class="form-control suji11 bg-light"
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
                     class="form-control suji11 bg-light"
                     value="{{ $readonlyValue('choseigo_shotokuwari_pref_prev') }}">
            </td>
            <td class="text-end">
              <input type="text"
                     readonly
                     name="choseigo_shotokuwari_pref_curr"
                     class="form-control suji11 bg-light"
                     value="{{ $readonlyValue('choseigo_shotokuwari_pref_curr') }}">
            </td>
          </tr>
          <tr>
            <th class="text-start ps-1">市区町村</th>
            <td class="text-end">
              <input type="text"
                     readonly
                     name="choseigo_shotokuwari_muni_prev"
                     class="form-control suji11 bg-light"
                     value="{{ $readonlyValue('choseigo_shotokuwari_muni_prev') }}">
            </td>
            <td class="text-end">
              <input type="text"
                     readonly
                     name="choseigo_shotokuwari_muni_curr"
                     class="form-control suji11 bg-light"
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
                     class="form-control suji11 bg-light"
                     value="{{ $readonlyValue('kihon_kojo_pref_prev') }}">
            </td>
            <td class="text-end">
              <input type="text"
                     readonly
                     name="kihon_kojo_pref_curr"
                     class="form-control suji11 bg-light"
                     value="{{ $readonlyValue('kihon_kojo_pref_curr') }}">
            </td>
          </tr>
          <tr>
            <th class="text-start ps-1">市区町村</th>
            <td class="text-end">
              <input type="text"
                     readonly
                     name="kihon_kojo_muni_prev"
                     class="form-control suji11 bg-light"
                     value="{{ $readonlyValue('kihon_kojo_muni_prev') }}">
            </td>
            <td class="text-end">
              <input type="text"
                     readonly
                     name="kihon_kojo_muni_curr"
                     class="form-control suji11 bg-light"
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
                     class="form-control suji11 bg-light"
                     value="{{ $readonlyValue('tokurei_kojo_pref_prev') }}">
            </td>
            <td class="text-end">
              <input type="text"
                     readonly
                     name="tokurei_kojo_pref_curr"
                     class="form-control suji11 bg-light"
                     value="{{ $readonlyValue('tokurei_kojo_pref_curr') }}">
            </td>
          </tr>
          <tr>
            <th class="text-start ps-1">市区町村</th>
            <td class="text-end">
              <input type="text"
                     readonly
                     name="tokurei_kojo_muni_prev"
                     class="form-control suji11 bg-light"
                     value="{{ $readonlyValue('tokurei_kojo_muni_prev') }}">
            </td>
            <td class="text-end">
              <input type="text"
                     readonly
                     name="tokurei_kojo_muni_curr"
                     class="form-control suji11 bg-light"
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
                     class="form-control suji11 bg-light"
                     value="{{ $readonlyValue('shotokuwari20_pref_prev') }}">
            </td>
            <td class="text-end">
              <input type="text"
                     readonly
                     name="shotokuwari20_pref_curr"
                     class="form-control suji11 bg-light"
                     value="{{ $readonlyValue('shotokuwari20_pref_curr') }}">
            </td>
          </tr>
          <tr>
            <th class="text-start ps-1">市区町村</th>
            <td class="text-end">
              <input type="text"
                     readonly
                     name="shotokuwari20_muni_prev"
                     class="form-control suji11 bg-light"
                     value="{{ $readonlyValue('shotokuwari20_muni_prev') }}">
            </td>
            <td class="text-end">
              <input type="text"
                     readonly
                     name="shotokuwari20_muni_curr"
                     class="form-control suji11 bg-light"
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
                     class="form-control suji11 bg-light"
                     value="{{ $readonlyValue('tokurei_kojo_jogen_pref_prev') }}">
            </td>
            <td class="text-end">
              <input type="text"
                     readonly
                     name="tokurei_kojo_jogen_pref_curr"
                     class="form-control suji11 bg-light"
                     value="{{ $readonlyValue('tokurei_kojo_jogen_pref_curr') }}">
            </td>
          </tr>
          <tr>
            <th class="text-start ps-1">市区町村</th>
            <td class="text-end">
              <input type="text"
                     readonly
                     name="tokurei_kojo_jogen_muni_prev"
                     class="form-control suji11 bg-light"
                     value="{{ $readonlyValue('tokurei_kojo_jogen_muni_prev') }}">
            </td>
            <td class="text-end">
              <input type="text"
                     readonly
                     name="tokurei_kojo_jogen_muni_curr"
                     class="form-control suji11 bg-light"
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
                     class="form-control suji11 bg-light"
                     value="{{ $readonlyValue('shinkokutokurei_kojo_pref_prev') }}">
            </td>
            <td class="text-end">
              <input type="text"
                     readonly
                     name="shinkokutokurei_kojo_pref_curr"
                     class="form-control suji11 bg-light"
                     value="{{ $readonlyValue('shinkokutokurei_kojo_pref_curr') }}">
            </td>
          </tr>
          <tr>
            <th class="text-start ps-1">市区町村</th>
            <td class="text-end">
              <input type="text"
                     readonly
                     name="shinkokutokurei_kojo_muni_prev"
                     class="form-control suji11 bg-light"
                     value="{{ $readonlyValue('shinkokutokurei_kojo_muni_prev') }}">
            </td>
            <td class="text-end">
              <input type="text"
                     readonly
                     name="shinkokutokurei_kojo_muni_curr"
                     class="form-control suji11 bg-light"
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
                     class="form-control suji11 bg-light"
                     value="{{ $readonlyValue('kifukin_zeigaku_kojo_pref_prev') }}">
            </td>
            <td class="text-end">
              <input type="text"
                     readonly
                     name="kifukin_zeigaku_kojo_pref_curr"
                     class="form-control suji11 bg-light"
                     value="{{ $readonlyValue('kifukin_zeigaku_kojo_pref_curr') }}">
            </td>
          </tr>
          <tr>
            <th class="text-start ps-1">市区町村</th>
            <td class="text-end">
              <input type="text"
                     readonly
                     name="kifukin_zeigaku_kojo_muni_prev"
                     class="form-control suji11 bg-light"
                     value="{{ $readonlyValue('kifukin_zeigaku_kojo_muni_prev') }}">
            </td>
            <td class="text-end">
              <input type="text"
                     readonly
                     name="kifukin_zeigaku_kojo_muni_curr"
                     class="form-control suji11 bg-light"
                     value="{{ $readonlyValue('kifukin_zeigaku_kojo_muni_curr') }}">
            </td>
          </tr>
          <tr>
            <th class="text-start ps-1">合計</th>
            <td class="text-end">
              <input type="text"
                     readonly
                     name="kifukin_zeigaku_kojo_gokei_prev"
                     class="form-control suji11 bg-light"
                     value="{{ $readonlyValue('kifukin_zeigaku_kojo_gokei_prev') }}">
            </td>
            <td class="text-end">
              <input type="text"
                     readonly
                     name="kifukin_zeigaku_kojo_gokei_curr"
                     class="form-control suji11 bg-light"
                     value="{{ $readonlyValue('kifukin_zeigaku_kojo_gokei_curr') }}">
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</div>