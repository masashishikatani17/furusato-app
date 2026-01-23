{{-- resources/views/pdf/5_sonntokusimulation.blade.php --}}
@extends('pdf.layouts.print')

@section('title','寄附金額別損得シミュレーション')

@section('head')
<style>
  /* A4横 + 余白（上 右 下 左） */
        @page { size: A4 landscape; margin: 10mm 6mm 10mm 6mm; }
        
        /* 中央寄せしたい場合だけ（任意） */
        .page-frame{
          width: calc(297mm - 12mm); /* 例：左右合計12mm を引く */
          margin: 0 auto;
          text-align: center; /* ← inline-block を中央に寄せるため */
        }
        
        /* テーブルの基本（これだけでよいことが多い） */
        table{ border-collapse: collapse; table-layout: fixed; }
        
        }
</style>
@endsection

@section('content')
  @php
    /**
     * ▼ 寄附金額別損得シミュレーション（PDF用）
     *
     * - 帳票レイアウトは一切変更しない（値を埋めるだけ）
     * - 計算の正は Calculator（dry-run runner）
     * - DB/Sessionへ副作用なし（runnerのみ）
     *
     * 期待される入力（report側で注入できるならそれを優先）：
     *   - $sonntoku['left']['rows'][1..30], $sonntoku['right']['rows'][1..30]
     *   - $sonntoku['y_center'] など
     *
     * 注入が無い場合は、PDF生成時点で data_id から SoT payload を読み取り、ここで組み立てる。
     */
    $sonntoku = $sonntoku ?? null;

    // ▼ 重要：report側が $sonntoku を「空配列」で渡してくると計算がスキップされる。
    //   その場合でも必ずここで再構築して、0表示を防ぐ。
    $needsBuild = true;
    if (is_array($sonntoku)) {
        $hasLeft  = isset($sonntoku['left']['rows'])  && is_array($sonntoku['left']['rows'])  && count($sonntoku['left']['rows'])  >= 15;
        $hasRight = isset($sonntoku['right']['rows']) && is_array($sonntoku['right']['rows']) && count($sonntoku['right']['rows']) >= 15;
        $needsBuild = !($hasLeft && $hasRight);
    }

    if ($needsBuild) {
        $dataObj = $data ?? null;
        // PDFでは $dataId が渡ってこないケースがあるため、候補を増やして拾う
        $resolvedDataId = $dataId ?? ($dataObj?->id ?? null) ?? (isset($data_id) ? $data_id : null) ?? (int) request()->query('data_id', 0);
        $resolvedDataId = (int) $resolvedDataId;

        \Log::info('[sonntoku][pdf] needsBuild=1', [
            'given_sonntoku_is_array' => is_array($sonntoku),
            'resolved_data_id' => $resolvedDataId,
        ]);

        if (!$dataObj && $resolvedDataId > 0) {
            $dataObj = \App\Models\Data::with('guest')->find($resolvedDataId);
        }

        $payload = [];
        $syoriSettings = [];
        if ($dataObj) {
            /** @var \App\Domain\Tax\Factory\SyoriSettingsFactory $syoriFactory */
            $syoriFactory = app(\App\Domain\Tax\Factory\SyoriSettingsFactory::class);
            $syoriSettings = $syoriFactory->buildInitial($dataObj);

            // ▼ 最優先：FurusatoResult（再計算後の results payload/upper = SoT が揃っている）
            $storedResult = \App\Models\FurusatoResult::query()
                ->where('data_id', (int) $dataObj->id)
                ->value('payload');
            if (is_array($storedResult)) {
                $candidate = $storedResult['payload'] ?? $storedResult['upper'] ?? $storedResult;
                $payload = is_array($candidate) ? $candidate : [];
            }

            // ▼ フォールバック：FurusatoInput（SoTが揃っていない場合があるので最後）
            if ($payload === []) {
                $storedInput = \App\Models\FurusatoInput::query()
                    ->where('data_id', (int)$dataObj->id)
                    ->value('payload');
                $payload = is_array($storedInput) ? $storedInput : [];
            }

            \Log::info('[sonntoku][pdf] payload snapshot', [
                'data_id' => (int) $dataObj->id,
                'payload_keys_count' => is_array($payload) ? count($payload) : 0,
                'furusato_curr' => $payload['shotokuzei_shotokukojo_furusato_curr'] ?? null,
                'tax_gokei_shotoku_curr' => $payload['tax_gokei_shotoku_curr'] ?? null,
                'tax_gokei_jumin_curr' => $payload['tax_gokei_jumin_curr'] ?? null,
                'tb_sogo_shotoku_curr' => $payload['tb_sogo_shotoku_curr'] ?? null,
                'tb_sogo_jumin_curr' => $payload['tb_sogo_jumin_curr'] ?? null,
                'sum_for_sogoshotoku_etc_curr' => $payload['sum_for_sogoshotoku_etc_curr'] ?? null,
            ]);

            // runner ctx（必要情報をできるだけ埋める）
            $guestBirth = $dataObj?->guest?->birth_date ?? null;
            $guestBirthYmd = null;
            if ($guestBirth instanceof \DateTimeInterface) {
                $guestBirthYmd = $guestBirth->format('Y-m-d');
            } elseif (is_string($guestBirth)) {
                $guestBirthYmd = preg_match('/^\d{4}-\d{2}-\d{2}$/', trim($guestBirth)) === 1 ? trim($guestBirth) : null;
            }
            $taxpayerSex = data_get($dataObj, 'guest.sex');
            if ($taxpayerSex === null) $taxpayerSex = data_get($dataObj, 'guest.gender');
            if ($taxpayerSex === null) $taxpayerSex = data_get($dataObj, 'guest.sex_code');

            $ctx = [
                'syori_settings'   => $syoriSettings,
                'data'            => $dataObj,
                'data_id'          => (int) $dataObj->id,
                'company_id'       => $dataObj->company_id !== null ? (int) $dataObj->company_id : null,
                'kihu_year'        => $dataObj->kihu_year ? (int) $dataObj->kihu_year : 0,
                'master_kihu_year' => 2025,
                'guest_birth_date' => $guestBirthYmd,
                'taxpayer_sex'     => $taxpayerSex,
            ];

            /** @var \App\Domain\Tax\Services\FurusatoSonntokuSimulationService $svc */
            $svc = app(\App\Domain\Tax\Services\FurusatoSonntokuSimulationService::class);
            $sonntoku = $svc->build($payload, $ctx);

            \Log::info('[sonntoku][pdf] built sonntoku', [
                'data_id' => (int) $dataObj->id,
                'y_max_total' => $sonntoku['y_max_total'] ?? null,
                'y_center' => $sonntoku['y_center'] ?? null,
                'left_step' => $sonntoku['left']['step'] ?? null,
                'right_step' => $sonntoku['right']['step'] ?? null,
                'left_15' => $sonntoku['left']['rows'][15]['y'] ?? null,
                'right_15' => $sonntoku['right']['rows'][15]['y'] ?? null,
                'left_15_saved_total' => $sonntoku['left']['rows'][15]['saved_total'] ?? null,
                'right_15_saved_total' => $sonntoku['right']['rows'][15]['saved_total'] ?? null,
            ]);
        } else {
            $sonntoku = [
                'y_center' => 0,
                'left' => ['step'=>0,'rows' => []],
                'right'=> ['step'=>0,'rows' => []],
            ];
        }
    }

    $leftRows  = is_array($sonntoku['left']['rows']  ?? null) ? $sonntoku['left']['rows']  : [];
    $rightRows = is_array($sonntoku['right']['rows'] ?? null) ? $sonntoku['right']['rows'] : [];
    $leftStep  = is_numeric($sonntoku['left']['step']  ?? null) ? (int)$sonntoku['left']['step']  : 0;
    $rightStep = is_numeric($sonntoku['right']['step'] ?? null) ? (int)$sonntoku['right']['step'] : 0;

    $stepLabel = static function (int $step): string {
        $s = max(0, $step);
        // 本仕様では 10,000 円単位で来る前提（2万/3万/5万/10万/25万...）
        if ($s > 0 && $s % 10_000 === 0) {
            return (string) intdiv($s, 10_000) . '万円';
        }
        // 保険（万単位で割れない場合）
        return number_format($s) . '円';
    };

    $nf = static function ($v): string {
        $n = is_numeric($v) ? (int)$v : 0;
        return number_format($n);
    };
  @endphp

  <div class="page-frame text-center"><!-- ここが実効幅281mmの中央寄せコンテナ -->
    <div class="page-content">
    <table class="table b-none "
           style="width: 252mm; border-collapse: collapse;">
      <tr>
        <td class="text-center"><h18>寄附金額別損得シミュレーション</h18></td>
      </tr>
    </table>

    <table class="table b-none no-overlap"
           style="width:252mm; table-layout:fixed; border-collapse:collapse; margin:0 auto;">
      <colgroup>
        <col style="width:123mm;">
        <col style="width:6mm;">
        <col style="width:123mm;">
      </colgroup>
     <tbody>
      <tr>
        <td class="b-none" style="vertical-align:top; padding:0;">
        <table class="table b-none no-overlap mt-1 mb-2"
               style="width: 123mm; table-layout: fixed; border-collapse: collapse;
                      margin: 0 auto; clear:both;">
          <tr>
            <td><h18>■{{ $stepLabel($leftStep) }}ごとの区分</h18></td>
          </tr>
        </table>
        
          <table class="table table-compact-p no-overlap mb-tight table-123mm" 
          style="font-size:13px;line-height:1.2;outline:2px solid #000; outline-offset:-2px;">
            <colgroup>
              <col style="width:8mm">
              <col style="width:23mm">
              <col style="width:23mm">
              <col style="width:23mm">
              <col style="width:23mm">
              <col style="width:23mm">
            </colgroup>
            <tbody>
              <tr>
                <td rowspan="2"><h14>区分</h14></td>
                <td><h14>寄附金額</h14></td>
                <td><h14>減税額</h14></td>
                <td><h14>差  引</h14></td>
                <td><h14>返戻品額</h14></td>
                <td><h14>実質負担額</h14></td>
              </tr>
              <tr>
                <td><h15>①</h15></td>
                <td><h15>②</h15></td>
                <td>①ー②＝③</td>
                <td>①×30％＝④</td>
                <td>③ー④</td>
              </tr>
              @for ($k = 1; $k <= 30; $k++)
                @php
                  $r = $leftRows[$k] ?? null;
                  $y    = is_array($r) ? (int)($r['y'] ?? 0) : 0;
                  $save = is_array($r) ? (int)($r['saved_total'] ?? 0) : 0;
                  $diff = is_array($r) ? (int)($r['diff'] ?? 0) : 0;
                  $gift = is_array($r) ? (int)($r['gift'] ?? 0) : 0;
                  $net  = is_array($r) ? (int)($r['net']  ?? 0) : 0;
                @endphp
                <tr>
                  <td class="text-end">{{ $k }}</td>
                  <td class="text-end">{{ $nf($y) }}</td>
                  <td class="text-end">{{ $nf($save) }}</td>
                  <td class="text-end">{{ $nf($diff) }}</td>
                  <td class="text-end">{{ $nf($gift) }}</td>
                  <td class="text-end">{{ $nf($net) }}</td>
                </tr>
              @endfor
            </tbody>
          </table>
        
        </td>
        <td class="b-none" style="padding:0;">&nbsp;</td>
        <td class="b-none" style="vertical-align:top; padding:0;">
          <table class="table b-none no-overlap mt-1 mb-2"
                 style="width: 123mm; table-layout: fixed; border-collapse: collapse;
                        margin: 0 auto; clear:both;">
            <tr>
              <td><h18>■{{ $stepLabel($rightStep) }}ごとの区分</h18></td>
            </tr>
          </table>
            <table class="table table-compact-p no-overlap mb-tight table-123mm mb-0" 
            style="font-size:13px;line-height:1.2;outline:2px solid #000; outline-offset:-2px;">
              <colgroup>
                <col style="width:8mm">
                <col style="width:23mm">
                <col style="width:23mm">
                <col style="width:23mm">
                <col style="width:23mm">
                <col style="width:23mm">
              </colgroup>
              <tbody>
                <tr>
                  <td rowspan="2"><h14>区分</h14></td>
                  <td><h14>寄附金額</h14></td>
                  <td><h14>減税額</h14></td>
                  <td><h14>差  引</h14></td>
                  <td><h14>返戻品額</h14></td>
                  <td><h14>実質負担額</h14></td>
                </tr>
                <tr>
                  <td><h15>①</h15></td>
                <td><h15>②</h15></td>
                <td>①ー②＝③</td>
                <td>①×30％＝④</td>
                <td>③ー④</td>
                </tr>
                @for ($k = 1; $k <= 30; $k++)
                  @php
                    $r = $rightRows[$k] ?? null;
                    $y    = is_array($r) ? (int)($r['y'] ?? 0) : 0;
                    $save = is_array($r) ? (int)($r['saved_total'] ?? 0) : 0;
                    $diff = is_array($r) ? (int)($r['diff'] ?? 0) : 0;
                    $gift = is_array($r) ? (int)($r['gift'] ?? 0) : 0;
                    $net  = is_array($r) ? (int)($r['net']  ?? 0) : 0;
                  @endphp
                  <tr>
                    <td class="text-end">{{ $k }}</td>
                    <td class="text-end">{{ $nf($y) }}</td>
                    <td class="text-end">{{ $nf($save) }}</td>
                    <td class="text-end">{{ $nf($diff) }}</td>
                    <td class="text-end">{{ $nf($gift) }}</td>
                    <td class="text-end">{{ $nf($net) }}</td>
                  </tr>
                @endfor
              </tbody>
            </table>
        </td>
      </tr>
     </tbody>
    </table>
    </div><!-- 本文終り -->
      <div class="page-footer">
        <div class="footer-inner">
          <table class="table b-none no-overlap mt-2 mb-0">
            <tr>
              <td><h14u>５ページ</h14u></td>
            </tr>
          </table>
        </div>
      </div>
  </div><!-- /.page-frame -->
@endsection

