<!-- resources/views/tax/furusato/input.blade.php -->
@extends('layouts.min')

@push('styles')
  <style>
        
    :target {
      outline: none !important;
    }

    [data-silent-focus="1"]:focus,
    [data-silent-focus="1"]:focus-visible {
      outline: none !important;
      box-shadow: none !important;
    }

    [data-anchor] {
      scroll-margin-top: var(--restore-scroll-offset, 80px);
    }

    /* 第一表・第三表：ヘッダー固定＋ボディのみ縦スクロール */
    .furusato-table-scroll {
      max-height: 720px;         /* 必要に応じて 500〜600px で微調整 */
      overflow-y: auto;          /* ボディ側だけをスクロールさせる */
    }
    .furusato-table-scroll table {
      margin-bottom: 0;          /* スクロール内で余白を詰める */
    }

    /* 第一表専用：ヘッダー＋ボディをラップするコンテナ */
    .furusato-sogo-table-wrapper .table {
      margin-bottom: 0;          /* ヘッダー／ボディ両方とも余分な下マージンを無くす */
    }
    .furusato-sogo-table-header {
      border-bottom: 1px solid #dee2e6; /* ヘッダーとボディの境界線を明示 */
    }

    /* ヘッダー用テーブルとボディ用テーブルの列幅を合わせやすくする */
    .furusato-sogo-table-header table,
    .furusato-sogo-table-wrapper .furusato-table-scroll table {
      table-layout: fixed;
      width: 100%;
    }

    /* ▼ 余白を消してコンパクトに（内容幅まで縮める） */
    #furusato-input-form .furusato-table-scroll table.table {
      width: max-content !important;   /* 余白が出ない */
    }
    
    /* ============================
     *  縦書きTH（rowgroup見出し用）
     *  - display:flex は table-cell を崩すので使わない
     *  - 中身(span)だけを absolute でセル中央に固定する
     * ============================ */
    .th-vertical {
      position: relative;
      vertical-align: middle !important; /* 既存CSSで上書きされる対策 */
      padding: 0 !important;             /* 余白は inner 側へ */
      min-width: 28px;                   /* 必要なら 24〜40px で調整 */
    }
    .th-vertical .th-vertical-inner {
      position: absolute;
      top: 50%;
      left: 50%;
      transform: translate(-50%, -50%);
      writing-mode: vertical-rl;  /* 縦書き（右→左） */
      text-orientation: mixed;    /* 数字は横向き混在 */
      white-space: nowrap;
      letter-spacing: 0.05em;
      line-height: 1;
      padding: 6px 4px;
    }
    /* ============================
     * DEBUG: readonly を確実に赤くする（最終勝ち）
     * - readonly 属性/プロパティ/サーバロック/Bootstrap bg-light を全部まとめて潰す
     * - まずは「当たるかどうか」の切り分け用
     * ============================ */
    #furusato-input-form input.form-control[readonly],
    #furusato-input-form input.form-control:read-only,
    #furusato-input-form textarea.form-control[readonly],
    #furusato-input-form textarea.form-control:read-only,
    #furusato-input-form select.form-select[readonly],
    #furusato-input-form select.form-select:read-only,
    #furusato-input-form input.form-control.bg-light,
    #furusato-input-form textarea.form-control.bg-light,
    #furusato-input-form select.form-select.bg-light,
    #furusato-input-form input.form-control[data-server-lock="1"],
    #furusato-input-form textarea.form-control[data-server-lock="1"],
    #furusato-input-form select.form-select[data-server-lock="1"] {
      background-color: #EFF2F5 !important;
      color: #000 !important;
    }
 
    /* ============================
     * PDF出力中 オーバーレイ（全画面）
     * ============================ */
    #furusato-pdf-overlay {
      position: fixed;
      inset: 0;
      z-index: 20000;
      display: none;
      align-items: center;
      justify-content: center;
      background: rgba(0, 0, 0, 0.35);
      backdrop-filter: blur(2px);
    }
    #furusato-pdf-overlay.is-active { display: flex; }
    #furusato-pdf-overlay .overlay-card {
      width: min(520px, calc(100% - 32px));
      background: #fff;
      border-radius: 12px;
      box-shadow: 0 10px 28px rgba(0,0,0,0.25);
      padding: 18px 18px 14px;
      text-align: left;
    }
    #furusato-pdf-overlay .overlay-title {
      font-size: 15px;
      font-weight: 700;
      margin: 0 0 8px 0;
      color: #111;
    }
    #furusato-pdf-overlay .overlay-sub {
      font-size: 13px;
      margin: 0;
      color: #444;
      line-height: 1.6;
    }
    #furusato-pdf-overlay .overlay-row {
      display: flex;
      gap: 12px;
      align-items: center;
      margin-top: 10px;
    }
    /* Bootstrapに依存しない簡易スピナー */
    #furusato-pdf-overlay .mini-spinner {
      width: 34px;
      height: 34px;
      border-radius: 50%;
      border: 3px solid rgba(0,0,0,0.12);
      border-top-color: rgba(0,0,0,0.55);
      animation: furusatoSpin 0.9s linear infinite;
      flex: 0 0 auto;
    }
    @keyframes furusatoSpin { to { transform: rotate(360deg); } }

  /* helpModalCommon（他モーダルに影響させない） */

    /* 全体：フォントを本文に寄せる（サイズ15px） */
    #helpModalCommon .modal-content {
      font-family: inherit;
      font-size: 15px;
    }
    
    /* 本文だけ：左右余白を追加（上下は触らない） */
    #helpModalCommon .modal-body {
      padding-left: 2rem;
      padding-right: 2rem;
    }
    
    /* 横幅：最大幅550px */
    #helpModalCommon .modal-dialog {
      max-width: 550px;
    }
    /* このHELPモーダル内の強調表示（○〜 のラベル） */
    #helpModalCommon #helpModalBody strong {
      font-weight: 700;
      /* 色を付けたい場合はここ（例） */
       color: #192C4B; 
  }
    /* 「(1)」など：太字のみ（色は変更しない＝継承） */
    #helpModalZatsu #helpModalBodyZatsu strong.help-num {
      font-weight: 700;
  }
  </style>
@endpush

@section('content')
<div class="container-blue" style="width: 1000px;">
  <form method="POST" action="{{ route('furusato.save') }}" id="furusato-input-form">
    @csrf
    <input type="hidden" name="data_id" value="{{ $dataId ?? '' }}">
    <input type="hidden" name="redirect_to" value="input">
    <input type="hidden" name="show_result" value="1">
    {{-- ▼ PDF出力ボタン押下判定（将来のPDF機能拡張用：現時点では再計算トリガとしてのみ利用） --}}
    <input type="hidden" name="pdf_prepare" id="furusato-pdf-prepare" value="0">
    @php
      $syoriSettings = $syoriSettings ?? [];
      $resultsData = $results ?? [];
      $jintekiDiffData = $jintekiDiff ?? [];
      $showResultFlag = (bool) ($showResult ?? false);
      $tokureiStandardRateData = $tokureiStandardRate ?? [];
      $tokureiComputedPercentData = $tokureiComputedPercent ?? [];
      $tokureiEnabledData = $tokureiEnabled ?? [];
      $warekiPrevLabel = $warekiPrev ?? '前年';
      $warekiCurrLabel = $warekiCurr ?? '当年';
      $showSeparatedNettingFlag = (bool) ($showSeparatedNetting ?? false);
      $inputTabActiveClass = $showResultFlag ? '' : 'active';
      $inputPaneActiveClass = $showResultFlag ? '' : 'show active';
      $detailsTabActiveClass = $showResultFlag ? 'active' : '';
      $detailsPaneActiveClass = $showResultFlag ? 'show active' : '';
    @endphp
      <div class="card-header d-flex align-items-center justify-content-between gap-2">
        <div class="d-flex align-items-center gap-2">
          @include('components.kado_lefttop_img')
          <h0 class="mt-2 mb-0">インプット表</h0>
        </div>
        <div class="d-flex align-items-center justify-content-end gap-2 flex-wrap ms-auto mt-2 me-5">
          
          <button type="submit"
                  class="btn-base-blue"
                  formnovalidate
                  name="redirect_to"
                  value="master">マスター</button>
          <button type="submit"
                  id="furusato-recalc-button"
                  class="btn-base-red d-none"
                  name="recalc_all"
                  value="1"
                  data-disable-on-submit
                  data-redirect-to="input">再計算</button>
          {{-- ▼ 新規：PDF出力（現時点では「常時表示の再計算トリガ」） --}}
          @php
            $oneStopCurr = (string)($syoriSettings['one_stop_flag_curr'] ?? $syoriSettings['one_stop_flag'] ?? '1');
            $oneStopPdfGuard = is_array($oneStopPdfGuard ?? null) ? $oneStopPdfGuard : [];
            $isOneStopPdfBlocked = (bool) ($oneStopPdfGuard['is_blocked'] ?? false);
            $oneStopPdfGuardMessage = (string) ($oneStopPdfGuardMessage ?? "給与所得者であっても、次のいずれかに当てはまるなど一定の条件を満たす人は、確定申告が必要です。\n"
              . "1．給与の年間収入金額が2,000万円を超える人\n"
              . "2．給与所得、退職所得を除く、その他の所得金額の合計額が20万円を超える人\n"
              . "現在の入力内容ではワンストップtクレイの対象外となる可能性があるため、上限額の再計算及び帳票出力はできません。処理メニューに戻り、ワンストップ特例を「利用しない」に変更してください。");
            $syoriBackUrl = route('furusato.syori', ['data_id' => (int)($dataId ?? 0)]);
            // ▼ いったん「表紙＋寄附金上限額（1ページ目）」を2ページで出す（report=furusato_bundle）
            $bundleUrl = route('pdf.download', ['report' => 'furusato_bundle'])
              . '?data_id=' . urlencode((string)($dataId ?? ''))
              . '&one_stop_flag_curr=' . urlencode($oneStopCurr)
              . '&mode=fast'
              . '&engine=dompdf';
            // ▼ 生成状況確認（ready/building/failed）
            $bundleStatusUrl = route('pdf.status', ['report' => 'furusato_bundle'])
              . '?data_id=' . urlencode((string)($dataId ?? ''))
              . '&one_stop_flag_curr=' . urlencode($oneStopCurr)
              . '&mode=fast'
              . '&engine=dompdf';
          @endphp
          <a id="furusato-pdf-button"
             class="btn-base-green"
             href="#"
             data-download-url="{{ $bundleUrl }}"
             data-status-url="{{ $bundleStatusUrl }}"
             data-one-stop-pdf-blocked="{{ $isOneStopPdfBlocked ? '1' : '0' }}"
             data-one-stop-pdf-message="{{ $oneStopPdfGuardMessage }}"
             data-syori-url="{{ $syoriBackUrl }}">PDF出力</a>

          {{-- ▼ 新規：帳票プレビュー（サムネ一覧→拡大） --}}
{{--
          @php
            $bundlePreviewJsonUrl = route('pdf.preview', ['report' => 'furusato_bundle'])
              . '?data_id=' . urlencode((string)($dataId ?? ''))
              . '&one_stop_flag_curr=' . urlencode($oneStopCurr)
              . '&pdf_variant=max'
              . '&format=json';
          @endphp
          <a id="furusato-preview-button"
             class="btn-base-green"
             href="#"
             data-preview-json-url="{{ $bundlePreviewJsonUrl }}">帳票プレビュー</a>
--}}
          <button type="submit"
                  class="btn-base-blue"
                  formnovalidate
                  name="redirect_to"
                  value="syori">戻 る</button>
        </div>
      </div> 
<!--      @includeWhen(config('app.debug'), 'components.furusato.totals_debug') -->
    <div class="wrapper mt-3">
      @if (session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
      @endif
  
      @if ($errors->any())
        <div class="alert alert-danger">
          <ul class="mb-0">
            @foreach ($errors->all() as $error)
              <li>{{ $error }}</li>
            @endforeach
          </ul>
        </div>
      @endif
  
      <ul class="nav nav-tabs" id="furusato-main-tabs" role="tablist">
        <li class="nav-item" role="presentation">
          <button class="nav-link {{ $inputTabActiveClass }}" id="furusato-tab-input-nav" data-bs-toggle="tab" data-bs-target="#furusato-tab-input" type="button" role="tab" aria-controls="furusato-tab-input" aria-selected="{{ $showResultFlag ? 'false' : 'true' }}">データ入力</button>
        </li>
        <li class="nav-item" role="presentation">
          <button class="nav-link {{ $detailsTabActiveClass }}" id="furusato-tab-result-details-nav" data-bs-toggle="tab" data-bs-target="#furusato-tab-result-details" type="button" role="tab" aria-controls="furusato-tab-result-details" aria-selected="{{ $showResultFlag ? 'true' : 'false' }}">計算結果詳細</button>
        </li>
        <li class="nav-item" role="presentation">
          <button class="nav-link" id="furusato-tab-result-upper-nav" data-bs-toggle="tab" data-bs-target="#furusato-tab-result-upper" type="button" role="tab" aria-controls="furusato-tab-result-upper" aria-selected="false">ふるさと納税上限額</button>
        </li>
      </ul>
  
      <div class="tab-content" id="furusato-main-tab-content">
        <div class="tab-pane fade {{ $inputPaneActiveClass }}" id="furusato-tab-input" role="tabpanel" aria-labelledby="furusato-tab-input-nav">
  
          @php
            // ここで $inputs を最終確定させる（tab直前の差し替え後に合計を上書き）
            $inputs = array_replace(($inputs ?? []), ($out['inputs'] ?? []));
            $normalizeServerInt = static function ($value): int {
                if ($value === null || $value === '') return 0;
                if (is_numeric($value)) return (int)$value;
                $normalized = preg_replace('/[^0-9\-]/u', '', (string)$value);
                if ($normalized === '' || $normalized === '-') return 0;
                return (int)$normalized;
            };
            foreach (['prev','curr'] as $p) {
                // 第一表 合計 = 経常 + 短期(総合) + 長期(総合) + 一時（負は0扱い）
                $keijo = $normalizeServerInt($inputs["shotoku_keijo_{$p}"] ?? 0);
                $tanki = $normalizeServerInt($inputs["shotoku_joto_tanki_sogo_{$p}"] ?? 0);
                $choki = $normalizeServerInt($inputs["shotoku_joto_choki_sogo_{$p}"] ?? 0);
                $ichiji = $normalizeServerInt($inputs["shotoku_ichiji_{$p}"] ?? 0);
                $gokei  = $keijo + $tanki + $choki + max(0, $ichiji);
                $inputs["shotoku_gokei_shotoku_{$p}"] = $gokei;
                $inputs["shotoku_gokei_jumin_{$p}"]   = $gokei;
            }
            $warekiPrevLabel = $warekiPrev ?? '前年';
            $warekiCurrLabel = $warekiCurr ?? '当年';
            $showTokubetsu = in_array((int) ($kihuYear ?? 0), [2024, 2025], true);
            // ▼ 税額控除ブロック：令和6年度分特別税額控除は「2024/2025のときだけ表示」
            //   - それ以外（例: 2026）は「行自体を非表示」
            //   - rowspan は表示有無で切替（ズレ防止）
            $taxCreditRowspan = $showTokubetsu ? 8 : 7;
            // ▼ 税金の金額（税額控除 + 税額3行）
            $taxAmountRowspan = $taxCreditRowspan + 3;
            // ▼ 住民税：調整控除（県+市）合計（UI表示専用）
            $choseiPrev = $normalizeServerInt($inputs['chosei_kojo_pref_prev'] ?? 0) + $normalizeServerInt($inputs['chosei_kojo_muni_prev'] ?? 0);
            $choseiCurr = $normalizeServerInt($inputs['chosei_kojo_pref_curr'] ?? 0) + $normalizeServerInt($inputs['chosei_kojo_muni_curr'] ?? 0);
            // ▼ 住民税：寄附金税額控除（県+市）合計（UI表示専用）
            $kifukinZeigakuPrev = $normalizeServerInt($inputs['kifukin_zeigaku_kojo_pref_prev'] ?? 0) + $normalizeServerInt($inputs['kifukin_zeigaku_kojo_muni_prev'] ?? 0);
            $kifukinZeigakuCurr = $normalizeServerInt($inputs['kifukin_zeigaku_kojo_pref_curr'] ?? 0) + $normalizeServerInt($inputs['kifukin_zeigaku_kojo_muni_curr'] ?? 0);
            $readonlyBases = array_fill_keys([
                'shotoku_kyuyo',
                'jumin_kyuyo',
                'shotoku_zatsu_nenkin',
                'jumin_zatsu_nenkin',
                'syunyu_kyuyo',
                'syunyu_zatsu_nenkin',
                'syunyu_zatsu_gyomu',
                'syunyu_zatsu_sonota',
                'shotoku_zatsu_gyomu',
                'shotoku_zatsu_sonota',
                'syunyu_jigyo_eigyo',
                'syunyu_fudosan',
                'shotoku_joto_tanki',
                'shotoku_joto_choki',
                'shotoku_ichiji',
                'syunyu_joto_tanki',
                'syunyu_joto_choki',
                'syunyu_ichiji',
                'bunri_syunyu_tanki_ippan',
                'bunri_syunyu_tanki_keigen',
                'bunri_syunyu_choki_ippan',
                'bunri_syunyu_choki_tokutei',
                'bunri_syunyu_choki_keika',
                'bunri_syunyu_ippan_kabuteki_joto',
                'bunri_syunyu_jojo_kabuteki_joto',
                'bunri_syunyu_jojo_kabuteki_haito',
                'bunri_syunyu_sakimono',
                'bunri_syunyu_sanrin',
                'bunri_shotoku_tanki_ippan',
                'bunri_shotoku_tanki_keigen',
                'bunri_shotoku_choki_ippan',
                'bunri_shotoku_choki_tokutei',
                'bunri_shotoku_choki_keika',
                'bunri_shotoku_ippan_kabuteki_joto',
                'bunri_shotoku_jojo_kabuteki_joto',
                'bunri_shotoku_jojo_kabuteki_haito',
                'bunri_shotoku_sakimono',
                'bunri_shotoku_sanrin',
                'shotoku_jigyo_eigyo',
                'shotoku_fudosan',
                'shotoku_joto_ichiji',
                'shotoku_gokei',
                'bunri_sogo_gokeigaku',
                'kojo_seimei',
                'kojo_jishin',
                'kojo_shokei',
                'kojo_kafu',
                'kojo_hitorioya',
                'kojo_kinrogakusei',
                'kojo_shogaisha',
                'kojo_haigusha',
                'kojo_haigusha_tokubetsu',
                'kojo_fuyo',
                'kojo_tokutei_shinzoku',
                'kojo_gokei',
                'tax_zeigaku',
                'kojo_iryo',
                'kojo_kifukin',
                'kojo_kiso',
                'tax_seito',
                'tax_kijun',
                'tax_fukkou',
                'tax_gokei',
                'tax_kazeishotoku',
            ], true);
            $kojoFieldOverrides = [
                'kojo_kiso' => [
                    'shotoku' => 'shotokuzei_kojo_kiso_%s',
                    'jumin' => 'juminzei_kojo_kiso_%s',
                ],
                'kojo_kifukin' => [
                    'shotoku' => 'shotokuzei_kojo_kifukin_%s',
                    'jumin' => 'juminzei_kojo_kifukin_%s',
                ],
            ];
            $shotokuRatesForScript = collect($shotokuRates ?? [])->values()->toArray();
            $forceDash = static function (string $base, string $tax, string $period, ?int $kihuYear): bool {
                $isJumin = $tax === 'jumin';

                // ▼ 令和6年度分特別税額控除（所得税のみ）
                //   - 対象年：2024年分のみ表示（それ以外はダッシュ）
                //   - kihu_year=2025 の場合：prev=2024 が対象
                //   - kihu_year=2024 の場合：curr=2024 が対象
                //   - 住民税側は常にダッシュ
                if ($base === 'tax_tokubetsu_R6') {
                    if ($isJumin) {
                        return true;
                    }
                    $targetYear = null;
                    if ($kihuYear !== null) {
                        $targetYear = ($period === 'prev') ? ($kihuYear - 1) : $kihuYear;
                    }
                    return $targetYear !== 2024;
                }

                if ($kihuYear === 2025 && $period === 'prev' && $base === 'kojo_tokutei_shinzoku') {
                    return true;
                }

                if ($isJumin && in_array($base, ['kojo_kifukin', 'tax_fukkou'], true)) {
                    return true;
                }

                return false;
            };
            // 第一表の一部収入項目は、内訳画面（総合譲渡・一時）で入力した period 単位の値を
            // 税目（所得税/住民税）共通でミラーして readonly 表示に固定する
            // 対象: syunyu_joto_tanki / syunyu_joto_choki / syunyu_ichiji の prev/curr
            $mirrorFromDetailsBases = [
                'syunyu_joto_tanki' => true,
                'syunyu_joto_choki' => true,
                'syunyu_ichiji'     => true,
            ];

            // ▼ server-only（old() を通さず inputs のみを描画・常時 readonly・POSTは抑止）に固定する base
            //   - 収入（第一表・上段）：kyuyo / zatsu_{nenkin|gyomu|sonota} は details の鏡像（表示専用）
            //   - 所得（第一表・下段）：KyuyoNenkinCalculator の SoT（表示専用）
            $serverOnlyBases = [
              'syunyu_kyuyo'         => true,
              'syunyu_zatsu_nenkin'  => true,
              'syunyu_zatsu_gyomu'   => true,
              'syunyu_zatsu_sonota'  => true,
              'shotoku_kyuyo'        => true,
              'shotoku_zatsu_nenkin' => true,
              'shotoku_zatsu_gyomu'  => true,
              'shotoku_zatsu_sonota' => true,
            ];

            $renderInputs = static function (string $base) use ($inputs, $readonlyBases, $kojoFieldOverrides, $kihuYear, $forceDash, $mirrorFromDetailsBases, $serverOnlyBases) {
                $html = '';
                foreach (['shotoku' => ['prev', 'curr'], 'jumin' => ['prev', 'curr']] as $tax => $periods) {
                    foreach ($periods as $period) {
                        $format = $kojoFieldOverrides[$base][$tax] ?? null;
                        $name = $format ? sprintf($format, $period) : sprintf('%s_%s_%s', $base, $tax, $period);
                        // ▼ 分離・第三表（bunri_*/tb_*）は server 計算値を表示する運用。
                        //    退職（bunri_*_taishoku_*）も手入力は許可せず、住民税側は 0・readonly 前提。
                        $allowRetirementManual = false;
                        $isBunriThird =
                            ! $allowRetirementManual && (
                                str_starts_with($base, 'bunri_syunyu_') ||
                                str_starts_with($base, 'bunri_shotoku_') ||
                                str_starts_with($base, 'tb_')
                            );
                        // 分離・第三表 または server-only base は server 計算値のみ表示（old()は採用しない）
                        if ($isBunriThird || isset($serverOnlyBases[$base])) {
                            $value = $inputs[$name] ?? null;
                        } else {
                            $value = old($name, $inputs[$name] ?? null);
                        }
                        // data-server-raw 用にカンマ除去した整数文字列を用意
                        $valueRaw = null;
                        if ($isBunriThird || isset($serverOnlyBases[$base])) {
                            $v = $value;
                            if ($v !== null && $v !== '') {
                                $v = preg_replace('/,/', '', (string)$v);
                                $v = trim((string)$v);
                                if ($v !== '' && preg_match('/^-?\d+$/', $v) === 1) {
                                    $valueRaw = $v;
                                }
                            }
                        }
                        $kihuYearInt = isset($kihuYear) ? (int) $kihuYear : null;
                        $isForceDash = $forceDash($base, $tax, $period, $kihuYearInt);
                        $isReadonly = false;

                        // ▼ 「総合譲渡・一時」行だけは内訳の所得値を合算してミラー（税目共通）し、常にreadonly
                        if ($base === 'shotoku_joto_ichiji') {
                            $tankiKey  = sprintf('shotoku_joto_tanki_sogo_%s', $period);
                            $chokiKey  = sprintf('shotoku_joto_choki_sogo_%s', $period);
                            $ichijiKey = sprintf('shotoku_ichiji_%s', $period);
                            $sum = 0;
                            foreach ([$tankiKey, $chokiKey, $ichijiKey] as $k) {
                                $v = $inputs[$k] ?? null;
                                if ($v !== null && $v !== '') {
                                    $sum += (int) str_replace(',', '', (string) $v);
                                }
                            }
                            $value = $sum;
                            $isReadonly = true;
                        }

                        // ▼ 内訳画面の period 単位値をミラーする場合（tax 共通）
                        if (isset($mirrorFromDetailsBases[$base])) {
                            $detailsKey = sprintf('%s_%s', $base, $period); // ex) syunyu_joto_tanki_prev
                            // old() は tax 付き name が来る可能性があるため、まず detailsKey 側を優先的に参照
                            $mirrored = old($detailsKey, $inputs[$detailsKey] ?? null);
                            if ($mirrored !== null && $mirrored !== '') {
                                $value = $mirrored;
                            }
                            // ミラー対象は常に readonly
                            $isReadonly = true;
                        }

                        if ($isForceDash) {
                            $isReadonly = true;
                        } elseif ($tax === 'jumin') {
                            $editableJuminBases = [
                                'kojo_zasson',
                                'tax_haito',
                                'tax_jutaku',
                                'tax_saigai_genmen',
                            ];
                            // server-only は住民税側も常時 readonly
                            if (isset($serverOnlyBases[$base])) $isReadonly = true;
                            $editableR6 = $base === 'tax_tokubetsu_R6'
                                && $period === 'prev'
                                && (int) ($kihuYear ?? 0) === 2025;

                            if ($editableR6) {
                                $isReadonly = false;
                            } elseif (in_array($base, $editableJuminBases, true)) {
                                $isReadonly = false;
                            } else {
                                $isReadonly = true;
                            }
                        } else {
                            $isReadonly = isset($readonlyBases[$base]);
                            // server-only は所得税側も常時 readonly
                            if (isset($serverOnlyBases[$base])) $isReadonly = true;
                        }

                        if ($isForceDash) {
                            $html .= '<td><input type="text" class="form-control suji11s text-center bg-light" value="－" readonly><input type="hidden" name="' . e($name) . '" value=""></td>';
                         } else {
                            $readonlyAttr = $isReadonly ? ' readonly' : '';
                            $class = 'form-control suji11s text-end js-comma';
                            if ($isReadonly) {
                                $class .= ' bg-light';
                            }
                            // 分離・第三表／server-only は data-server-lock＋（可能なら）data-server-raw を付与
                            // 退職（bunri_*_taishoku_*）も分離・第三表としてロック対象
                            $isLocked = $isBunriThird || isset($serverOnlyBases[$base]);
                            $lockAttr = $isLocked ? ' data-server-lock="1"' : '';
                            $rawAttr  = ($isLocked && $valueRaw !== null) ? ' data-server-raw="' . e($valueRaw) . '"' : '';
                            $html .= '<td><input type="text" inputmode="numeric" pattern="[0-9,\\-]*" class="' . e($class) . '" name="' . e($name) . '" value="' . e($value) . '"' . $readonlyAttr . $lockAttr . $rawAttr . '></td>';
                        }
                    }
                }

                return $html;
            };
            $normalizeInt = static function ($value): int {
                if ($value === null || $value === '') {
                    return 0;
                }

                if (is_numeric($value)) {
                    return (int) $value;
                }

                $normalized = preg_replace('/[^0-9\-]/u', '', (string) $value);
                if ($normalized === '' || $normalized === '-') {
                    return 0;
                }

                return (int) $normalized;
            };
            /**
             * ▼ 第三表課税標準を tb_* で表示（合算行は表示のみ合算、POSTは個別 tb_* hidden）
             */
            $renderSeparatedTb = static function (array|string $keysOrOne, array $inputs) {
                $keys = is_array($keysOrOne) ? $keysOrOne : [$keysOrOne];
                $html = '';
                foreach (['shotoku'=>['prev','curr'],'jumin'=>['prev','curr']] as $tax=>$periods) {
                    foreach ($periods as $p) {
                        $disp = 0;
                        foreach ($keys as $k) {
                            $tb = sprintf('%s_%s_%s', $k, $tax, $p);
                            $disp += (int)($inputs[$tb] ?? 0);
                      }
                        $html .= '<td>';
                        $html .= '<input type="text" class="form-control suji11s text-end bg-light js-comma" value="' . e(number_format($disp)) . '" readonly>';
                        foreach ($keys as $k) {
                            $tb = sprintf('%s_%s_%s', $k, $tax, $p);
                            $raw = (int)($inputs[$tb] ?? 0);
                            $html .= '<input type="hidden" name="' . e($tb) . '" value="' . e((string)$raw) . '">';
                        }
                        $html .= '</td>';
                    }
                }
                return $html;
            };
            $renderServerLockedInput = static function (string $name, int $raw, string $class = 'form-control suji11s text-end js-comma bg-light') use ($normalizeInt): string {
                $display = number_format($raw);

                return '<td><input type="text" inputmode="numeric" pattern="[0-9,\\-]*" class="' . e($class) . '" name="' . e($name) . '" value="' . e($display) . '" readonly data-server-lock="1" data-server-raw="' . e((string) $raw) . '"></td>';
            };
            // shotoku_gokei_* 等「クライアント再計算可能」な readonly 表示用
             $renderReadonlyInput = static function (string $name, int $raw, string $class = 'form-control suji11s text-end js-comma bg-light') use ($normalizeInt): string {
                $raw = $normalizeInt($raw);
                $display = number_format($raw);

                return '<td><input type="text" inputmode="numeric" pattern="[0-9,\\-]*" class="' . e($class) . '" name="' . e($name) . '" value="' . e($display) . '" readonly></td>';
            };
            $syunyuRowspan = 11;
            $shotokuRowspan = 11;
            $kojoRowspan = 18;
            $taxRowspan = $showTokubetsu ? 10 : 9;
          @endphp


          {{-- ============================================================
               ▼ 第三表（分離課税）の行を「第一表の中」に挿入するためのHTML断片
               - 収入金額：syunyu_ichiji 行の直下へ挿入
               - 所得金額：shotoku_gokei（所得金額等 合計）直下へ挿入
               - 税金計算：kojo_gokei（所得控除 合計）直下へ挿入
             ============================================================ --}}
          @php ob_start(); @endphp
            @if ($showSeparatedNettingFlag)
              {{-- ▼ 分離課税（第三表）収入金額：短期〜退職 --}}
              <tr id="bunri_income_shortlong_top" data-anchor>
                <th scope="rowgroup" rowspan="9" class="text-start align-middle ps-1 th-vertical">
                  <span class="th-vertical-inner">分離課税</span>
                </th>
                <th scope="rowgroup" rowspan="2" class="text-start align-middle ps-1">短期譲渡</th>
                <th scope="row" class="align-middle text-start th-ddd ps-1">一般分</th>
                <td class="text-center align-middle" rowspan="5">
                  <button type="submit"
                          class="btn-base-free-green"
                          style="height:94px;"
                          name="redirect_to"
                          value="bunri_joto"
                          data-origin-subtab="bunri"
                          data-return-anchor="bunri_income_shortlong_top">内 訳</button>
                </td>
                {!! $renderInputs('bunri_syunyu_tanki_ippan') !!}
              </tr>
              <tr>
                <th scope="row" class="align-middle text-start th-ddd ps-1">軽減分</th>
                {!! $renderInputs('bunri_syunyu_tanki_keigen') !!}
              </tr>
              <tr>
                <th scope="rowgroup" rowspan="3" class="text-start align-middle ps-1">長期譲渡</th>
                <th scope="row" class="align-middle text-start th-ddd ps-1">一般分</th>
                {!! $renderInputs('bunri_syunyu_choki_ippan') !!}
              </tr>
              @foreach (['tokutei' => '特定分', 'keika' => '軽課分'] as $type => $label)
              <tr>
                <th scope="row" class="align-middle text-start th-ddd ps-1">{{ $label }}</th>
                {!! $renderInputs('bunri_syunyu_choki_' . $type) !!}
              </tr>
              @endforeach
              <tr id="bunri_income_kabuteki_top" data-anchor>
                <th scope="row" colspan="2" class="align-middle text-start ps-1">一般株式等の譲渡</th>
                <td class="text-center nowrap align-middle" rowspan="3">
                  <button type="submit"
                          class="btn-base-free-green"
                          style="height:50px;"
                          name="redirect_to"
                          value="bunri_kabuteki"
                          data-origin-subtab="bunri"
                          data-return-anchor="bunri_income_kabuteki_top">内 訳</button>
                </td>
                {!! $renderInputs('bunri_syunyu_ippan_kabuteki_joto') !!}
              </tr>
              <tr>
                <th scope="row" colspan="2" class="align-middle text-start ps-1">上場株式等の譲渡</th>
                {!! $renderInputs('bunri_syunyu_jojo_kabuteki_joto') !!}
              </tr>
              <tr>
                <th scope="row" colspan="2" class="align-middle text-start ps-1">上場株式等の配当等</th>
                {!! $renderInputs('bunri_syunyu_jojo_kabuteki_haito') !!}
              </tr>
              <tr id="bunri_income_sakimono" data-anchor>
                <th scope="row" colspan="2" class="align-middle text-start ps-1">先物取引</th>
                <td class="text-center align-middle">
                  <button type="submit"
                          class="btn-base-low-green"
                          name="redirect_to"
                          value="bunri_sakimono"
                          data-origin-subtab="bunri"
                          data-return-anchor="bunri_income_sakimono">内 訳</button>
                </td>
                {!! $renderInputs('bunri_syunyu_sakimono') !!}
              </tr>
              <tr id="bunri_income_sanrin" data-anchor>
                <th scope="row" colspan="3" class="align-middle text-start ps-1">山林</th>
                <td class="text-center align-middle">
                  <button type="submit"
                          class="btn-base-low-green"
                          name="redirect_to"
                          value="bunri_sanrin"
                          data-origin-subtab="bunri"
                          data-return-anchor="bunri_income_sanrin">内 訳</button>
                </td>
                {!! $renderInputs('bunri_syunyu_sanrin') !!}
              </tr>
              <tr>
                <th scope="row" colspan="3" class="align-middle text-start ps-1">退職</th>
                  <td class="text-center align-middle">
                    <button type="button" class="btn-base-low-blue">HELP</button>
                  </td>
                {!! $renderInputs('bunri_syunyu_taishoku') !!}
              </tr>
            @endif
          @php $bunriIncomeRows = ob_get_clean(); @endphp

          @php ob_start(); @endphp
            @if ($showSeparatedNettingFlag)
              {{-- ▼ 分離課税（第三表）所得金額：短期〜退職 --}}
              <tr id="bunri_shotoku_shortlong_top" data-anchor>
                <th scope="rowgroup" rowspan="9" class="text-start align-middle ps-1 th-vertical">
                  <span class="th-vertical-inner">分離課税</span>
                </th>
                <th scope="rowgroup" rowspan="2" class="text-start align-middle ps-1">短期譲渡</th>
                <th scope="row" class="align-middle text-start th-ddd ps-1">一般分</th>
                <td class="text-center align-middle" rowspan="5">
                  <button type="submit"
                          class="btn-base-free-green"
                          style="height:80px;"
                          name="redirect_to"
                          value="bunri_joto"
                          data-origin-subtab="bunri"
                          data-return-anchor="bunri_shotoku_shortlong_top">内 訳</button>
                </td>
                {!! $renderInputs('bunri_shotoku_tanki_ippan') !!}
              </tr>
              <tr>
                <th scope="row" class="align-middle text-start th-ddd ps-1">軽減分</th>
                {!! $renderInputs('bunri_shotoku_tanki_keigen') !!}
              </tr>
              <tr>
                <th scope="rowgroup" rowspan="3" class="text-start align-middle ps-1">長期譲渡</th>
                <th scope="row" class="align-middle text-start th-ddd ps-1">一般分</th>
                {!! $renderInputs('bunri_shotoku_choki_ippan') !!}
              </tr>
              @foreach (['tokutei' => '特定分', 'keika' => '軽課分'] as $type => $label)
              <tr>
                <th scope="row" class="align-middle text-start th-ddd ps-1">{{ $label }}</th>
                {!! $renderInputs('bunri_shotoku_choki_' . $type) !!}
              </tr>
              @endforeach
              <tr id="bunri_shotoku_kabuteki_top" data-anchor>
                <th scope="row" colspan="2" class="align-middle text-start ps-1">一般株式等の譲渡</th>
                <td class="text-center align-middle" rowspan="3">
                  <button type="submit"
                          class="btn-base-free-green"
                          style="height:52px;"
                          name="redirect_to"
                          value="bunri_kabuteki"
                          data-origin-subtab="bunri"
                          data-return-anchor="bunri_shotoku_kabuteki_top">内 訳</button>
                </td>
                {!! $renderInputs('bunri_shotoku_ippan_kabuteki_joto') !!}
              </tr>
              <tr>
                <th scope="row" colspan="2" class="align-middle text-start ps-1">上場株式等の譲渡</th>
                {!! $renderInputs('bunri_shotoku_jojo_kabuteki_joto') !!}
              </tr>
              <tr>
                <th scope="row" colspan="2" class="align-middle text-start ps-1">上場株式等の配当等</th>
                {!! $renderInputs('bunri_shotoku_jojo_kabuteki_haito') !!}
              </tr>
              <tr id="bunri_shotoku_sakimono" data-anchor>
                <th scope="row" colspan="2" class="align-middle text-start ps-1">先物取引</th>
                <td class="text-center align-middle">
                  <button type="submit"
                          class="btn-base-low-green"
                          name="redirect_to"
                          value="bunri_sakimono"
                          data-origin-subtab="bunri"
                          data-return-anchor="bunri_shotoku_sakimono">内 訳</button>
                </td>
                {!! $renderInputs('bunri_shotoku_sakimono') !!}
              </tr>
              <tr id="bunri_shotoku_sanrin" data-anchor>
                <th scope="row" colspan="3" class="align-middle text-start ps-1">山林</th>
                <td class="text-center align-middle">
                  <button type="submit"
                          class="btn-base-low-green"
                          name="redirect_to"
                          value="bunri_sanrin"
                          data-origin-subtab="bunri"
                          data-return-anchor="bunri_shotoku_sanrin">内 訳</button>
                </td>
                {!! $renderInputs('bunri_shotoku_sanrin') !!}
              </tr>
              <tr>
                <th scope="row" colspan="3" class="align-middle text-start ps-1">退職</th>
                  <td class="text-center align-middle">
                    <button type="button" class="btn-base-low-blue">HELP</button>
                  </td>
                {!! $renderInputs('bunri_shotoku_taishoku') !!}
              </tr>
              @php
                // ▼「所得金額等」ブロックの合計（UI表示専用）
                // 方針：行の寄せ集め合計ではなく、サーバ確定SoT（CommonSumsCalculator）を表示する。
                //   SoT: sum_for_sogoshotoku_etc_{tax}_{prev|curr}
                //   - 総所得金額 + 退職/山林 + 分離（繰越控除“後”）の正値合計
                //   - 退職は税目別入力（所得税/住民税）を反映した税目別SoTを使う
                //   - max(0, ...) で負は潰されるため、UI上の -300,000 などが出なくなる
                $sumEtcShotokuPrev = $normalizeServerInt(
                  $inputs['sum_for_sogoshotoku_etc_shotoku_prev']
                  ?? $inputs['sum_for_sogoshotoku_etc_prev']
                  ?? 0
                );
                $sumEtcShotokuCurr = $normalizeServerInt(
                  $inputs['sum_for_sogoshotoku_etc_shotoku_curr']
                  ?? $inputs['sum_for_sogoshotoku_etc_curr']
                  ?? 0
                );
                $sumEtcJuminPrev = $normalizeServerInt(
                  $inputs['sum_for_sogoshotoku_etc_jumin_prev']
                  ?? $inputs['sum_for_sogoshotoku_etc_prev']
                  ?? 0
                );
                $sumEtcJuminCurr = $normalizeServerInt(
                  $inputs['sum_for_sogoshotoku_etc_jumin_curr']
                  ?? $inputs['sum_for_sogoshotoku_etc_curr']
                  ?? 0
                );
              @endphp
              <tr class="js-bold-row">
                <th scope="row" colspan="4" class="align-middle text-center th-cream">合&nbsp;&nbsp;&nbsp;&nbsp;計</th>
                <td><input type="text" class="form-control suji11s text-end js-comma bg-light" value="{{ number_format($sumEtcShotokuPrev) }}" readonly data-ui-only="1"></td>
                <td><input type="text" class="form-control suji11s text-end js-comma bg-light" value="{{ number_format($sumEtcShotokuCurr) }}" readonly data-ui-only="1"></td>
                <td><input type="text" class="form-control suji11s text-end js-comma bg-light" value="{{ number_format($sumEtcJuminPrev) }}" readonly data-ui-only="1"></td>
                <td><input type="text" class="form-control suji11s text-end js-comma bg-light" value="{{ number_format($sumEtcJuminCurr) }}" readonly data-ui-only="1"></td>
              </tr>
            @endif
          @php $bunriShotokuRows = ob_get_clean(); @endphp

          @php ob_start(); @endphp
            @if ($showSeparatedNettingFlag)
              {{-- ▼ 分離課税（第三表）税金計算：課税所得金額〜税額合計（第一表へ） --}}
              <tr>
                <th scope="rowgroup" rowspan="8" class="text-center align-middle th-ccc ps-1 pe-1 th-vertical" nowrap="nowrap">
                  <span class="th-vertical-inner">課税所得金額</span>
                </th>
                <th scope="row" colspan="3" class="align-middle text-start ps-1">総合課税</th>
                  <td class="text-center align-middle">
                    <button type="button" class="btn-base-low-blue">HELP</button>
                  </td>
                @php
                  foreach (['shotoku' => ['prev', 'curr'], 'jumin' => ['prev', 'curr']] as $tax => $periods) {
                      foreach ($periods as $period) {
                          $name = sprintf('tb_sogo_%s_%s', $tax, $period);
                          $raw  = (int) ($inputs[$name] ?? 0);
                          echo $renderServerLockedInput($name, $raw);
                      }
                  }
                @endphp
              </tr>
              <tr>
                <th scope="row" colspan="3" class="align-middle text-start ps-1">短期譲渡</th>
                  <td class="text-center align-middle">
                    <button type="button" class="btn-base-low-blue">HELP</button>
                  </td>
                @php
                  foreach (['shotoku' => ['prev', 'curr'], 'jumin' => ['prev', 'curr']] as $tax => $periods) {
                      foreach ($periods as $period) {
                          $name = sprintf('tb_joto_tanki_%s_%s', $tax, $period);
                          $raw  = (int) ($inputs[$name] ?? 0);
                          echo $renderServerLockedInput($name, $raw);
                      }
                  }
                @endphp
              </tr>
              <tr>
                <th scope="row" colspan="3" class="align-middle text-start ps-1">長期譲渡</th>
                  <td class="text-center align-middle">
                    <button type="button" class="btn-base-low-blue">HELP</button>
                  </td>
                @php
                  foreach (['shotoku' => ['prev', 'curr'], 'jumin' => ['prev', 'curr']] as $tax => $periods) {
                      foreach ($periods as $period) {
                          $name = sprintf('tb_joto_choki_%s_%s', $tax, $period);
                          $raw  = (int) ($inputs[$name] ?? 0);
                          echo $renderServerLockedInput($name, $raw);
                      }
                  }
                @endphp
              </tr>
              <tr>
                <th scope="row" colspan="3" class="align-middle text-start ps-1 pe-1" nowrap="nowrap">一般・上場株式の譲渡</th>
                  <td class="text-center align-middle">
                    <button type="button" class="btn-base-low-blue">HELP</button>
                  </td>
                {!! $renderSeparatedTb(['tb_ippan_kabuteki_joto','tb_jojo_kabuteki_joto'], $inputs) !!}
              </tr>
              <tr>
                <th scope="row" colspan="3" class="align-middle text-start ps-1">上場株式の配当等</th>
                  <td class="text-center align-middle">
                    <button type="button" class="btn-base-low-blue">HELP</button>
                  </td>
                {!! $renderSeparatedTb('tb_jojo_kabuteki_haito', $inputs) !!}
              </tr>
              <tr>
                <th scope="row" colspan="3" class="align-middle text-start ps-1">先物取引</th>
                  <td class="text-center align-middle">
                    <button type="button" class="btn-base-low-blue">HELP</button>
                  </td>
                {!! $renderSeparatedTb('tb_sakimono', $inputs) !!}
              </tr>
              <tr>
                <th scope="row" colspan="3" class="align-middle text-start ps-1">山林</th>
                  <td class="text-center align-middle">
                    <button type="button" class="btn-base-low-blue">HELP</button>
                  </td>
                {!! $renderSeparatedTb('tb_sanrin', $inputs) !!}
              </tr>
              <tr>
                <th scope="row" colspan="3" class="align-middle text-start ps-1">退職</th>
                  <td class="text-center align-middle">
                    <button type="button" class="btn-base-low-blue">HELP</button>
                  </td>
                {!! $renderSeparatedTb('tb_taishoku', $inputs) !!}
              </tr>

              <tr>
                <th scope="rowgroup" rowspan="{{ 13 + $taxCreditRowspan }}" class="text-center align-middle th-ccc ps-1 pe-1 th-vertical" nowrap="nowrap">
                  <span class="th-vertical-inner">税額計算</span>
                </th>
                <th scope="row" colspan="3" class="align-middle text-start ps-1">総合課税</th>
                  <td class="text-center align-middle">
                    <button type="button" class="btn-base-low-blue">HELP</button>
                  </td>
                @php
                  foreach (['shotoku' => ['prev', 'curr'], 'jumin' => ['prev', 'curr']] as $tax => $periods) {
                      foreach ($periods as $period) {
                          $name = sprintf('bunri_zeigaku_sogo_%s_%s', $tax, $period);
                          $raw  = (int) ($inputs[$name] ?? 0);
                          echo $renderServerLockedInput($name, $raw);
                      }
                  }
                @endphp
              </tr>
              <tr>
                <th scope="row" colspan="3" class="align-middle text-start ps-1">短期譲渡</th>
                  <td class="text-center align-middle">
                    <button type="button" class="btn-base-low-blue">HELP</button>
                  </td>
                @php
                  foreach (['shotoku' => ['prev', 'curr'], 'jumin' => ['prev', 'curr']] as $tax => $periods) {
                      foreach ($periods as $period) {
                          $name = sprintf('bunri_zeigaku_tanki_%s_%s', $tax, $period);
                          $raw  = (int) ($inputs[$name] ?? 0);
                          echo $renderServerLockedInput($name, $raw);
                      }
                  }
                @endphp
              </tr>
              <tr>
                <th scope="row" colspan="3" class="align-middle text-start ps-1">長期譲渡</th>
                  <td class="text-center align-middle">
                    <button type="button" class="btn-base-low-blue">HELP</button>
                  </td>
                @php
                  foreach (['shotoku' => ['prev', 'curr'], 'jumin' => ['prev', 'curr']] as $tax => $periods) {
                      foreach ($periods as $period) {
                          $name = sprintf('bunri_zeigaku_choki_%s_%s', $tax, $period);
                          $raw  = (int) ($inputs[$name] ?? 0);
                          echo $renderServerLockedInput($name, $raw);
                      }
                  }
                @endphp
              </tr>
              <tr>
                <th scope="row" colspan="3" class="align-middle text-start ps-1">一般・上場株式の譲渡</th>
                  <td class="text-center align-middle">
                    <button type="button" class="btn-base-low-blue">HELP</button>
                  </td>
                @php
                  foreach (['shotoku' => ['prev', 'curr'], 'jumin' => ['prev', 'curr']] as $tax => $periods) {
                      foreach ($periods as $period) {
                          $name = sprintf('bunri_zeigaku_joto_%s_%s', $tax, $period);
                          $raw  = (int) ($inputs[$name] ?? 0);
                          echo $renderServerLockedInput($name, $raw);
                      }
                  }
                @endphp
              </tr>
              <tr>
                <th scope="row" colspan="3" class="align-middle text-start ps-1">上場株式の配当等</th>
                  <td class="text-center align-middle">
                    <button type="button" class="btn-base-low-blue">HELP</button>
                  </td>
                @php
                  foreach (['shotoku' => ['prev', 'curr'], 'jumin' => ['prev', 'curr']] as $tax => $periods) {
                      foreach ($periods as $period) {
                          $name = sprintf('bunri_zeigaku_haito_%s_%s', $tax, $period);
                          $raw  = (int) ($inputs[$name] ?? 0);
                          echo $renderServerLockedInput($name, $raw);
                      }
                  }
                @endphp
              </tr>
              <tr>
                <th scope="row" colspan="3" class="align-middle text-start ps-1">先物取引</th>
                  <td class="text-center align-middle">
                    <button type="button" class="btn-base-low-blue">HELP</button>
                  </td>
                @php
                  foreach (['shotoku' => ['prev', 'curr'], 'jumin' => ['prev', 'curr']] as $tax => $periods) {
                      foreach ($periods as $period) {
                          $name = sprintf('bunri_zeigaku_sakimono_%s_%s', $tax, $period);
                          $raw  = (int) ($inputs[$name] ?? 0);
                          echo $renderServerLockedInput($name, $raw);
                      }
                  }
                @endphp
              </tr>
              <tr>
                <th scope="row" colspan="3" class="align-middle text-start ps-1">山林</th>
                  <td class="text-center align-middle">
                    <button type="button" class="btn-base-low-blue">HELP</button>
                  </td>
                @php
                  foreach (['shotoku' => ['prev', 'curr'], 'jumin' => ['prev', 'curr']] as $tax => $periods) {
                      foreach ($periods as $period) {
                          $name = sprintf('bunri_zeigaku_sanrin_%s_%s', $tax, $period);
                          $raw  = (int) ($inputs[$name] ?? 0);
                          echo $renderServerLockedInput($name, $raw);
                      }
                  }
                @endphp
              </tr>
              <tr>
                <th scope="row" colspan="3" class="align-middle text-start ps-1">退職</th>
                  <td class="text-center align-middle">
                    <button type="button" class="btn-base-low-blue">HELP</button>
                  </td>
                @php
                  foreach (['shotoku' => ['prev', 'curr'], 'jumin' => ['prev', 'curr']] as $tax => $periods) {
                      foreach ($periods as $period) {
                          $name = sprintf('bunri_zeigaku_taishoku_%s_%s', $tax, $period);
                          $raw  = (int) ($inputs[$name] ?? 0);
                          echo $renderServerLockedInput($name, $raw);
                      }
                  }
                @endphp
              </tr>
              <tr class="js-bold-row">
                <th scope="row" colspan="4" class="align-middle text-center th-cream">合計(調整控除前所得割額)</th>
                @php
                  foreach (['shotoku' => ['prev', 'curr'], 'jumin' => ['prev', 'curr']] as $tax => $periods) {
                      foreach ($periods as $period) {
                          $name = sprintf('bunri_zeigaku_gokei_%s_%s', $tax, $period);
                          $raw  = (int) ($inputs[$name] ?? 0);
                          echo $renderServerLockedInput($name, $raw);
                      }
                  }
                @endphp
              </tr>

              {{-- =========================
                   ▼ 税額控除（ここから「税金の金額」を統合）
                 ========================= --}}
              <tr>
                <th rowspan="{{ $taxCreditRowspan }}" class="text-start align-middle ps-1 th-vertical">
                  <span class="th-vertical-inner">税額控除</span>
                </th>
                <th colspan="2" class="text-start align-middle ps-1">調整控除</th>
                  <td class="text-center align-middle">
                    <button type="button"
                      class="btn-base-low-blue js-help-btn"
                      data-help-key="chousei_koujo"
                      data-bs-toggle="modal"
                      data-bs-target="#helpModalCommon">HELP</button>
                  </td>
                {{-- 所得税：ダッシュ --}}
                <td><input type="text" class="form-control suji11s text-center bg-light" value="－" readonly></td>
                <td><input type="text" class="form-control suji11s text-center bg-light" value="－" readonly></td>
               {{-- 住民税：調整控除（県+市）合計（UI表示専用／POSTしない） --}}
                <td><input type="text" class="form-control suji11s text-end js-comma bg-light" value="{{ number_format($choseiPrev) }}" readonly data-ui-only="1"></td>
                <td><input type="text" class="form-control suji11s text-end js-comma bg-light" value="{{ number_format($choseiCurr) }}" readonly data-ui-only="1"></td>
              </tr>
              <tr>
                <th colspan="2" class="text-start align-middle ps-1">配当控除</th>
                  <td class="text-center align-middle">
                    <button type="button"
                      class="btn-base-low-blue js-help-btn"
                      data-help-key="haitou_koujo"
                      data-bs-toggle="modal"
                      data-bs-target="#helpModalCommon">HELP</button>
                  </td>
                {!! $renderInputs('tax_haito') !!}
              </tr>
              <tr>
                <th colspan="2" class="text-start align-middle ps-1">住宅借入金等特別控除</th>
                <td class="text-center align-middle">
                  <button type="button"
                          class="btn-base-low-green js-open-details"
                          data-redirect-to="kojo_tokubetsu_jutaku_loan"
                          data-origin-anchor="tax_jutaku">内 訳</button>
                </td>
                {!! $renderInputs('tax_jutaku') !!}
              </tr>
              <tr>
                <th colspan="2" class="text-start align-middle ps-1">政党等寄附金等特別控除</th>
                  <td class="text-center align-middle">
                    <button type="button"
                            class="btn-base-low-blue js-help-btn"
                            data-help-key="seitoto_kifu_tokubetsu"
                            data-bs-toggle="modal"
                            data-bs-target="#helpModalCommon">HELP</button>
                  </td>
                {!! $renderInputs('tax_seito') !!}
              </tr>
              <tr>
                <th colspan="2" class="text-start align-middle ps-1">住宅耐震改修特別控除</th>
                  <td class="text-center align-middle">
                    <button type="button" class="btn-base-low-blue">HELP</button>
                  </td>
                {{-- 所得税：ユーザー手入力（prev/curr） --}}
                <td>
                  <input type="text"
                         inputmode="numeric"
                         pattern="[0-9,\-]*"
                         class="form-control suji11s text-end js-comma"
                         name="tax_kaisyu_shotoku_prev"
                         value="{{ old('tax_kaisyu_shotoku_prev', $inputs['tax_kaisyu_shotoku_prev'] ?? 0) }}">
                </td>
                <td>
                  <input type="text"
                         inputmode="numeric"
                         pattern="[0-9,\-]*"
                         class="form-control suji11s text-end js-comma"
                         name="tax_kaisyu_shotoku_curr"
                         value="{{ old('tax_kaisyu_shotoku_curr', $inputs['tax_kaisyu_shotoku_curr'] ?? 0) }}">
                </td>
                {{-- 住民税：概念なし → 「－」 --}}
                <td><input type="text" class="form-control suji11s text-center bg-light" value="－" readonly></td>
                <td><input type="text" class="form-control suji11s text-center bg-light" value="－" readonly></td>
              </tr>
              <tr>
                <th colspan="2" class="text-start align-middle ps-1">寄附金税額控除</th>
                  <td class="text-center align-middle">
                    <button type="button"
                            class="btn-base-low-blue js-help-btn"
                            data-help-key="kifukin_zeigaku_koujo"
                            data-bs-toggle="modal"
                            data-bs-target="#helpModalCommon">HELP</button>
                  </td>
                {{-- 所得税：ダッシュ --}}
                <td><input type="text" class="form-control suji11s text-center bg-light" value="－" readonly></td>
                <td><input type="text" class="form-control suji11s text-center bg-light" value="－" readonly></td>
                 {{-- 住民税：寄附金税額控除（県+市）合計（UI表示専用／POSTしない） --}}
                <td><input type="text" class="form-control suji11s text-end js-comma bg-light" value="{{ number_format($kifukinZeigakuPrev) }}" readonly data-ui-only="1"></td>
                <td><input type="text" class="form-control suji11s text-end js-comma bg-light" value="{{ number_format($kifukinZeigakuCurr) }}" readonly data-ui-only="1"></td>
             </tr>
              <tr>
                <th colspan="2" class="text-start align-middle ps-1">災害減免額</th>
                  <td class="text-center align-middle">
                    <button type="button"
                            class="btn-base-low-blue js-help-btn"
                            data-help-key="saigai_genmen"
                            data-bs-toggle="modal"
                            data-bs-target="#helpModalCommon">HELP</button>
                  </td>
                {!! $renderInputs('tax_saigai_genmen') !!}
              </tr>
              @if ($showTokubetsu)
              <tr>
                <th colspan="2" class="text-start align-middle ps-1">令和6年度分特別税額控除</th>
                  <td class="text-center align-middle">
                    <button type="button" class="btn-base-low-blue">HELP</button>
                  </td>
                {!! $renderInputs('tax_tokubetsu_R6') !!}
              </tr>
              @endif

              {{-- =========================
                   ▼ 税額（3行）
                 ========================= --}}
              <tr>
                <th rowspan="3" class="text-start align-middle ps-1 th-vertical">
                  <span class="th-vertical-inner">税額</span>
                </th>
                <th colspan="2" class="text-start align-middle ps-1">基準所得税額(所得割額)</th>
                  <td class="text-center align-middle">
                    <button type="button" class="btn-base-low-blue">HELP</button>
                  </td>
                {!! $renderInputs('tax_kijun') !!}
              </tr>
              <tr>
                <th colspan="2" class="text-start align-middle ps-1">復興特別所得税額</th>
                  <td class="text-center align-middle">
                    <button type="button" class="btn-base-low-blue">HELP</button>
                  </td>
                {!! $renderInputs('tax_fukkou') !!}
              </tr>
              <tr class="js-bold-row">
                <th colspan="3" class="align-middle th-cream">合&nbsp;&nbsp;&nbsp;&nbsp;計</th>
                {!! $renderInputs('tax_gokei') !!}
              </tr>
            @endif
          @php $bunriTaxRows = ob_get_clean(); @endphp

          @php ob_start(); @endphp
        <div>
          <hb class="card-title ms-5 mb-1">確定申告書</hb>
          <div class="table-responsive furusato-table-scroll">
            <table class="table table-base table-compact-05 align-middle mb-3">
              <thead>
                <tr>
                  <th rowspan="2" colspan="5" class="th-ccc">項  目</th>
                  <th colspan="2" style="height:30px;" class="th-ccc">所得税</th>
                  <th colspan="2" class="th-ccc">住民税</th>
                </tr>
                <tr style="height:30px;">
                  <th>{{ $warekiPrevLabel }}</th>
                  <th>{{ $warekiCurrLabel }}</th>
                  <th>{{ $warekiPrevLabel }}</th>
                  <th>{{ $warekiCurrLabel }}</th>
                </tr>
              </thead>
              <tbody>
                <tr id="syunyu_row_jigyo_eigyo" data-anchor>
                  <th scope="rowgroup" rowspan="22" class="text-center align-middle th-ccc th-vertical">
                    <span class="th-vertical-inner">収入金額等</span>
                  </th>
                  <th scope="rowgroup" rowspan="11" class="text-start align-middle ps-1 th-vertical">
                    <span class="th-vertical-inner">総合課税</span>
                  </th>
                  <th rowspan="2" class="text-start align-middle ps-1">事業</th>
                  <th class="text-start align-middle th-ddd ps-1">営業等</th>
                  <td class="text-center align-middle">
                    <button type="submit"
                            class="btn-base-low-green"
                            name="redirect_to"
                            value="jigyo"
                            data-return-anchor="syunyu_row_jigyo_eigyo">内 訳</button>
                  </td>
                  {!! $renderInputs('syunyu_jigyo_eigyo') !!}
                </tr>
                <tr>
                  <th class="text-start align-middle th-ddd ps-1">農業</th>
                  <td class="text-center align-middle">
                    <button type="button"
                            class="btn-base-low-blue js-help-btn"
                            data-help-key="nogyo"
                            data-bs-toggle="modal"
                            data-bs-target="#helpModalCommon">HELP</button>
                  </td>
                  {!! $renderInputs('syunyu_jigyo_nogyo') !!}
                </tr>
                <tr id="syunyu_row_fudosan" data-anchor>
                  <th colspan="2" class="text-start align-middle ps-1">不動産</th>
                  <td class="text-center align-middle">
                    <button type="submit"
                            class="btn-base-low-green"
                            name="redirect_to"
                            value="fudosan"
                            data-return-anchor="syunyu_row_fudosan">内 訳</button>
                  </td>
                  {!! $renderInputs('syunyu_fudosan') !!}
                </tr>
                <tr>
                  <th colspan="2" class="text-start align-middle ps-1">配当</th>
                  <td class="text-center align-middle">
                    <button type="button" class="btn-base-low-blue">HELP</button>
                  </td>
                  {!! $renderInputs('syunyu_haito') !!}
                </tr>
                <tr id="syunyu_row_kyuyo" data-anchor>
                  <th colspan="2" class="text-start align-middle ps-1">給与</th>
                  <td class="text-center align-middle" rowspan="4">
                    <button type="button"
                            class="btn-base-free-green js-open-details"
                            style="height:67px;"
                            data-redirect-to="kyuyo_zatsu"
                            data-origin-subtab="sogo"
                            data-origin-anchor="syunyu_row_kyuyo">内 訳</button>
                  </td>
                  {!! $renderInputs('syunyu_kyuyo') !!}
                </tr>
                <tr>
                  <th rowspan="3" class="text-start align-middle ps-1">雑</th>
                  <th class="text-start align-middle th-ddd ps-1">公的年金等</th>
                  {!! $renderInputs('syunyu_zatsu_nenkin') !!}
                </tr>
                <tr>
                  <th class="text-start align-middle th-ddd ps-1">業務</th>
                  {!! $renderInputs('syunyu_zatsu_gyomu') !!}
                </tr>
                <tr>
                  <th class="text-start align-middle th-ddd ps-1">その他</th>
                  {!! $renderInputs('syunyu_zatsu_sonota') !!}
                </tr>
                <tr id="income_joto_ichiji" data-anchor>
                  <th rowspan="2" class="text-start align-middle ps-1">譲渡</th>
                  <th class="text-start align-middle th-ddd ps-1">短期</th>
                  <td class="text-center align-middle" rowspan="3">
                    <button type="button"
                            class="btn-base-free-green js-open-details"
                            style="height:49px;"
                            data-redirect-to="joto_ichiji"
                            data-origin-anchor="income_joto_ichiji"
                            data-return-anchor="income_joto_ichiji">内 訳</button>
                  </td>
                  {!! $renderInputs('syunyu_joto_tanki') !!}
                </tr>
                <tr>
                  <th class="text-start align-middle th-ddd ps-1">長期</th>
                  {!! $renderInputs('syunyu_joto_choki') !!}
                </tr>
                <tr>
                  <th colspan="2" class="text-start align-middle ps-1">一時</th>
                  {!! $renderInputs('syunyu_ichiji') !!}
                </tr>
                {!! $bunriIncomeRows !!}
                <tr id="shotoku_row_jigyo_eigyo" data-anchor>
                  <th scope="rowgroup" rowspan="23" class="text-center align-middle th-ccc th-vertical">
                    <span class="th-vertical-inner">所得金額等(総所得金額等)</span>
                  </th>
                  <th scope="rowgroup" rowspan="11" class="text-start align-middle ps-1 th-vertical">
                    <span class="th-vertical-inner">総合課税</span>
                  </th>
                  <th rowspan="2" class="text-start align-middle ps-1">事業</th>
                  <th class="text-start align-middle th-ddd ps-1">営業等</th>
                  <td class="text-center align-middle">
                    <button type="submit"
                            class="btn-base-low-green"
                            name="redirect_to"
                            value="jigyo"
                            data-return-anchor="shotoku_row_jigyo_eigyo">内 訳</button>
                  </td>
                  {!! $renderInputs('shotoku_jigyo_eigyo') !!}
                </tr>
                <tr>
                  <th class="text-start align-middle th-ddd ps-1">農業</th>
                  <td class="text-center align-middle">
                    <button type="button" class="btn-base-low-blue">HELP</button>
                  </td>
                  {!! $renderInputs('shotoku_jigyo_nogyo') !!}
                </tr>
                <tr id="shotoku_row_fudosan" data-anchor>
                  <th colspan="2" class="text-start align-middle ps-1">不動産</th>
                  <td class="text-center align-middle">
                    <button type="submit"
                            class="btn-base-low-green"
                            nowrap="nowrap"
                            name="redirect_to"
                            value="fudosan"
                            data-return-anchor="shotoku_row_fudosan">内 訳</button>
                  </td>
                  {!! $renderInputs('shotoku_fudosan') !!}
                </tr>
                <tr>
                  <th colspan="2" class="text-start align-middle ps-1">利子</th>
                  <td class="text-center align-middle">
                    <button type="button" class="btn-base-low-blue">HELP</button>
                  </td>
                  {!! $renderInputs('shotoku_rishi') !!}
                </tr>
                <tr>
                  <th colspan="2" class="text-start align-middle ps-1">配当</th>
                  <td class="text-center align-middle">
                    <button type="button" class="btn-base-low-blue">HELP</button>
                  </td>
                  {!! $renderInputs('shotoku_haito') !!}
                </tr>
                <tr id="shotoku_row_kyuyo" data-anchor>
                  <th colspan="2" class="text-start align-middle ps-1">給与</th>
                  <td class="text-center align-middle" rowspan="4">
                    <button type="button"
                            class="btn-base-free-green js-open-details"
                            style="height:68px;"
                            data-redirect-to="kyuyo_zatsu"
                            data-origin-subtab="sogo"
                            data-origin-anchor="shotoku_row_kyuyo">内 訳</button>
                  </td>
                  {!! $renderInputs('shotoku_kyuyo') !!}
                </tr>
                <tr>
                  <th rowspan="3" class="text-start align-middle ps-1">雑</th>
                  <th class="text-start align-middle th-ddd ps-1">公的年金等</th>
                  {!! $renderInputs('shotoku_zatsu_nenkin') !!}
                </tr>
                <tr>
                  <th class="text-start align-middle th-ddd ps-1">業務</th>
                  {!! $renderInputs('shotoku_zatsu_gyomu') !!}
                </tr>
                <tr>
                  <th class="text-start align-middle th-ddd ps-1">その他</th>
                  {!! $renderInputs('shotoku_zatsu_sonota') !!}
                </tr>
                <tr id="shotoku_joto_ichiji" data-anchor>
                  <th colspan="2" class="text-start align-middle ps-1">総合譲渡・一時</th>
                  <td class="text-center align-middle">
                    <button type="button"
                            class="btn-base-low-green js-open-details"
                            data-redirect-to="joto_ichiji"
                            data-origin-anchor="shotoku_joto_ichiji"
                            data-return-anchor="shotoku_joto_ichiji">内 訳</button>
                  </td>
                  {!! $renderInputs('shotoku_joto_ichiji') !!}
                </tr>
                <tr class="js-bold-row">
                  <th colspan="3" class="align-middle th-cream">合&nbsp;&nbsp;&nbsp;&nbsp;計</th>
                  @php
                   // 合計行だけ見た目を変える（計算やJSには影響しない）
                    // 太字: fw-bold
                    // 背景: bg-warning-subtle（環境により未対応でも“効かないだけ”でエラーにはならない）
                    $totalInputClass ='form-control suji11s text-end js-comma bg-light';
                    foreach (['shotoku' => ['prev', 'curr'], 'jumin' => ['prev', 'curr']] as $tax => $periods) {
                        foreach ($periods as $period) {
                            $name = sprintf('shotoku_gokei_%s_%s', $tax, $period);
                            $raw = $normalizeInt($inputs[$name] ?? 0);
                            // shotoku_gokei_* はクライアント側で常に再計算するため lock は付けない
                            echo $renderReadonlyInput($name, $raw, $totalInputClass);
                        }
                    }
                  @endphp
                </tr>
                {!! $bunriShotokuRows !!}
                <tr>
                  <th scope="rowgroup" rowspan="{{ $kojoRowspan }}" class="text-center align-middle th-ccc th-vertical" nowrap="nowrap">
                    <span class="th-vertical-inner">所得から差し引かれる金額</span>
                  </th>
                  <th colspan="3" class="text-start align-middle ps-1">社会保険料控除</th>
                  <td class="text-center align-middle">
                    <button type="button" class="btn-base-low-blue">HELP</button>
                  </td>
                  {!! $renderInputs('kojo_shakaihoken') !!}
                </tr>
                <tr>
                  <th colspan="3" class="text-start align-middle ps-1 pe-1" nowrap="nowrap">小規模企業共済等掛金控除</th>
                  <td class="text-center align-middle">
                    <button type="button" class="btn-base-low-blue">HELP</button>
                  </td>
                  {!! $renderInputs('kojo_shokibo') !!}
                </tr>
                <tr id="kojo_seimei_jishin" data-anchor>
                  <th colspan="3" class="text-start align-middle ps-1">生命保険料控除</th>
                  <td class="text-center align-middle" rowspan="2">
                    <button type="button"
                            class="btn-base-free-green js-open-details"
                            style="height:32px;"
                            data-redirect-to="kojo_seimei_jishin"
                            data-origin-anchor="kojo_seimei_jishin"
                            data-return-anchor="kojo_seimei_jishin">内 訳</button>
                  </td>
                  {!! $renderInputs('kojo_seimei') !!}
                </tr>
                <tr>
                  <th colspan="3" class="text-start align-middle ps-1">地震保険料控除</th>
                  {!! $renderInputs('kojo_jishin') !!}
                </tr>
                <tr id="kojo_jinteki" data-anchor>
                  <th colspan="3" class="text-start align-middle ps-1">寡婦控除</th>
                  <td class="text-center align-middle" rowspan="8">
                    <button type="button"
                            class="btn-base-free-green js-open-details"
                            style="height:143px;"
                            data-redirect-to="kojo_jinteki"
                            data-origin-anchor="kojo_jinteki"
                            data-return-anchor="kojo_jinteki">内 訳</button>
                  </td>
                  {!! $renderInputs('kojo_kafu') !!}
                </tr>
                <tr>
                  <th colspan="3" class="text-start align-middle ps-1">ひとり親控除</th>
                  {!! $renderInputs('kojo_hitorioya') !!}
                </tr>
                <tr>
                  <th colspan="3" class="text-start align-middle ps-1">勤労学生控除</th>
                  {!! $renderInputs('kojo_kinrogakusei') !!}
                </tr>
                <tr>
                  <th colspan="3" class="text-start align-middle ps-1">障害者控除</th>
                  {!! $renderInputs('kojo_shogaisha') !!}
                </tr>
                <tr>
                  <th colspan="3" class="text-start align-middle ps-1">配偶者控除</th>
                  {!! $renderInputs('kojo_haigusha') !!}
                </tr>
                <tr>
                  <th colspan="3" class="text-start align-middle ps-1">配偶者特別控除</th>
                  {!! $renderInputs('kojo_haigusha_tokubetsu') !!}
                </tr>
                <tr>
                  <th colspan="3" class="text-start align-middle ps-1">扶養控除</th>
                  {!! $renderInputs('kojo_fuyo') !!}
                </tr>
                <tr>
                  <th colspan="3" class="text-start align-middle ps-1">特定親族特別控除</th>
                  {!! $renderInputs('kojo_tokutei_shinzoku') !!}
                </tr>
                <tr>
                  <th colspan="3" class="text-start align-middle ps-1">基礎控除</th>
                  <td class="text-center align-middle">
                    <button type="button" class="btn-base-low-blue">HELP</button>
                  </td>
                  {!! $renderInputs('kojo_kiso') !!}
                </tr>
                <tr>
                  <th colspan="4" class="align-middle">小  計</th>
                  {!! $renderInputs('kojo_shokei') !!}
                </tr>
                <tr>
                  <th colspan="3" class="text-start align-middle ps-1">雑損控除</th>
                  <td class="text-center align-middle">
                    <button type="button" class="btn-base-low-blue">HELP</button>
                  </td>
                  {!! $renderInputs('kojo_zasson') !!}
                </tr>
                <tr id="kojo_iryo" data-anchor>
                  <th colspan="3" class="text-start align-middle ps-1">医療費控除</th>
                  <td class="text-center align-middle">
                    <button type="button"
                            class="btn-base-low-green js-open-details"
                            data-redirect-to="kojo_iryo"
                            data-origin-anchor="kojo_iryo"
                            data-return-anchor="kojo_iryo">内 訳</button>
                  </td>
                  {!! $renderInputs('kojo_iryo') !!}
                </tr>
                <tr id="kojo_row_kifukin" data-anchor>
                  <th colspan="3" class="text-start align-middle ps-1">寄附金控除</th>
                  <td class="text-center align-middle">
                    <button type="submit"
                            class="btn-base-low-green"
                            name="redirect_to"
                            value="kifukin_details"
                            data-return-anchor="kojo_row_kifukin">内 訳</button>
                  </td>
                  {!! $renderInputs('kojo_kifukin') !!}
                </tr>
                <tr class="js-bold-row">
                  <th colspan="4" class="align-middle th-cream">合&nbsp;&nbsp;&nbsp;&nbsp;計</th>
                  {!! $renderInputs('kojo_gokei') !!}
                </tr>
                {!! $bunriTaxRows !!}
{{--
                <tr>
                  <th scope="rowgroup" rowspan="{{ $taxRowspan }}" class="text-center align-middle th-ccc">税金の金額</th>
                  <th colspan="3" class="text-start align-middle ps-1">課税所得金額又は第三表</th>
                  <td class="text-center align-middle">
                    <button type="button" class="btn btn-link btn-sm px-0">HELP</button>
                  </td>
                  <td class="text-center align-middle"></td>
                  @php
                    // SoT：tb_sogo_* をそのまま表示＆POST
                    $cells = [
                      ['tb_sogo_shotoku_prev',  $inputs['tb_sogo_shotoku_prev']  ?? 0],
                      ['tb_sogo_shotoku_curr',  $inputs['tb_sogo_shotoku_curr']  ?? 0],
                      ['tb_sogo_jumin_prev',    $inputs['tb_sogo_jumin_prev']    ?? 0],
                      ['tb_sogo_jumin_curr',    $inputs['tb_sogo_jumin_curr']    ?? 0],
                    ];
                  @endphp
                  @foreach ($cells as [$name,$raw])
                    <td>
                      <input type="text" class="form-control form-control-compact-05 text-end js-comma bg-light" value="{{ number_format((int)$raw) }}" readonly>
                      <input type="hidden" name="{{ $name }}" value="{{ (int)$raw }}">
                    </td>
                  @endforeach
                </tr>
                <tr>
                  <th colspan="3" class="text-start align-middle ps-1">税額</th>
                  <td class="text-center align-middle">
                    <button type="button" class="btn btn-link btn-sm px-0">HELP</button>
                  </td>
                  <td class="text-center align-middle"></td>
                  {!! $renderInputs('tax_zeigaku') !!}
                </tr>
--}}
              </tbody>
            </table>
          </div>
        </div>
      @php $sogoContent = ob_get_clean(); @endphp

{{--  
      @if ($showSeparatedNettingFlag)
        <div class="card mb-4">
        <div class="card-header pb-0">
            @php
              $initialSubtab = (string) request()->query('subtab', '');
            $isBunri = $initialSubtab === 'bunri';
              $sogoActive  = $isBunri ? '' : 'active';
              $sogoShown   = $isBunri ? '' : 'show active';
              $bunriActive = $isBunri ? 'active' : '';
              $bunriShown  = $isBunri ? 'show active' : '';
            @endphp
            <ul class="nav nav-tabs card-header-tabs" id="furusato-input-tabs" role="tablist">
              <li class="nav-item" role="presentation">
                <button class="nav-link {{ $sogoActive }}" id="tab-sogo" data-bs-toggle="tab" data-bs-target="#pane-sogo" type="button" role="tab" aria-controls="pane-sogo" aria-selected="{{ $isBunri ? 'false' : 'true' }}">確定申告書(総合課税)</button>
              </li>
              <li class="nav-item" role="presentation">
                <button class="nav-link {{ $bunriActive }}" id="tab-bunri" data-bs-toggle="tab" data-bs-target="#pane-bunri" type="button" role="tab" aria-controls="pane-bunri" aria-selected="{{ $isBunri ? 'true' : 'false' }}">確定申告書(分離課税)</button>
              </li>
            </ul>
          </div>
          <div class="card-body">
            <div class="tab-content" id="furusato-input-tab-content">
              <div class="tab-pane fade {{ $sogoShown }}" id="pane-sogo" role="tabpanel" aria-labelledby="tab-sogo">
                {!! $sogoContent !!}
              </div>
              <div class="tab-pane fade {{ $bunriShown }}" id="pane-bunri" role="tabpanel" aria-labelledby="tab-bunri">
                <div>
                  <hb class="card-title mb-3">確定申告書(分離課税) 第三表</hb>
                  <div class="table-responsive furusato-table-scroll">
                    <table class="table table-base table-compact-05 align-middle">
                      <thead class="table-light text-center align-middle">
                        <tr style="height:30px;">
                          <th rowspan="2" colspan="6" class="th-ccc">項  目</th>
                          <th colspan="2" class="th-ccc">所得税</th>
                          <th colspan="2" class="th-ccc">住民税</th>
                        </tr>
                        <tr>
                          <th style="height:30px;">{{ $warekiPrevLabel }}</th>
                          <th>{{ $warekiCurrLabel }}</th>
                          <th>{{ $warekiPrevLabel }}</th>
                          <th>{{ $warekiCurrLabel }}</th>
                        </tr>
                      </thead>
                      <tbody>
                        <tr id="bunri_income_shortlong_top" data-anchor>
                          <th scope="rowgroup" rowspan="11" class="text-center align-middle th-ccc">収入金額</th>
                          <th scope="rowgroup" rowspan="11" class="text-start align-middle ps-1">分離課税</th>
                          <th scope="rowgroup" rowspan="2" class="text-start align-middle th-ddd ps-1">短 期</th>
                          <th scope="row" class="align-middle text-start th-ddd ps-1">一般分</th>
                          <td class="text-center align-middle">
                            <button type="button" class="btn btn-link btn-sm px-0">HELP</button>
                          </td>
                          <td class="text-center align-middle" rowspan="5">
                            <button type="submit"
                                    class="btn-base-green"
                                    name="redirect_to"
                                    value="bunri_joto"
                                    data-origin-subtab="bunri"
                                    data-return-anchor="bunri_income_shortlong_top">内訳</button>
                          </td>
                          {!! $renderInputs('bunri_syunyu_tanki_ippan') !!}
                        </tr>
                        <tr>
                          <th scope="row" class="align-middle text-start th-ddd ps-1">軽減分</th>
                          <td class="text-center align-middle">
                            <button type="button" class="btn btn-link btn-sm px-0">HELP</button>
                          </td>
                          {!! $renderInputs('bunri_syunyu_tanki_keigen') !!}
                        </tr>
                        <tr>
                          <th scope="rowgroup" rowspan="3" class="text-start th-ddd align-middle ps-1">長 期</th>
                          <th scope="row" class="align-middle text-start th-ddd ps-1">一般分</th>
                          <td class="text-center align-middle">
                            <button type="button" class="btn btn-link btn-sm px-0">HELP</button>
                          </td>
                          {!! $renderInputs('bunri_syunyu_choki_ippan') !!}
                        </tr>
                        @foreach (['tokutei' => '特定分', 'keika' => '軽課分'] as $type => $label)
                        <tr>
                          <th scope="row" class="align-middle text-start th-ddd ps-1">{{ $label }}</th>
                          <td class="text-center align-middle">
                            <button type="button" class="btn btn-link btn-sm px-0">HELP</button>
                          </td>
                          {!! $renderInputs('bunri_syunyu_choki_' . $type) !!}
                        </tr>
                        @endforeach
                        <tr id="bunri_income_kabuteki_top" data-anchor>
                          <th scope="row" colspan="2" class="align-middle text-start th-ddd ps-1">一般株式等の譲渡</th>
                          <td class="text-center align-middle">
                            <button type="button" class="btn btn-link btn-sm px-0">HELP</button>
                          </td>
                          <td class="text-center nowrap align-middle" rowspan="3">
                            <button type="submit"
                                    class="btn-base-green"
                                    name="redirect_to"
                                    value="bunri_kabuteki"
                                    data-origin-subtab="bunri"
                                    data-return-anchor="bunri_income_kabuteki_top">内訳</button>
                          </td>
                          {!! $renderInputs('bunri_syunyu_ippan_kabuteki_joto') !!}
                        </tr>
                        <tr>
                          <th scope="row" colspan="2" class="align-middle text-start th-ddd ps-1">上場株式等の譲渡</th>
                          <td class="text-center align-middle">
                            <button type="button" class="btn btn-link btn-sm px-0">HELP</button>
                          </td>
                          {!! $renderInputs('bunri_syunyu_jojo_kabuteki_joto') !!}
                        </tr>
                        <tr>
                          <th scope="row" colspan="2" class="align-middle text-start th-ddd ps-1">上場株式等の配当等</th>
                          <td class="text-center align-middle">
                            <button type="button" class="btn btn-link btn-sm px-0">HELP</button>
                          </td>
                          {!! $renderInputs('bunri_syunyu_jojo_kabuteki_haito') !!}
                        </tr>
                        <tr id="bunri_income_sakimono" data-anchor>
                          <th scope="row" colspan="2" class="align-middle text-start th-ddd ps-1">先物取引</th>
                          <td class="text-center align-middle">
                            <button type="button" class="btn btn-link btn-sm px-0">HELP</button>
                          </td>
                          <td class="text-center align-middle">
                            <button type="submit"
                                    class="btn-base-green"
                                    name="redirect_to"
                                    value="bunri_sakimono"
                                    data-origin-subtab="bunri"
                                    data-return-anchor="bunri_income_sakimono">内訳</button>
                          </td>
                          {!! $renderInputs('bunri_syunyu_sakimono') !!}
                        </tr>
                        <tr id="bunri_income_sanrin" data-anchor>
                          <th scope="row" colspan="2" class="align-middle text-start th-ddd ps-1">山林</th>
                          <td class="text-center align-middle">
                            <button type="button" class="btn btn-link btn-sm px-0">HELP</button>
                          </td>
                          <td class="text-center align-middle">
                            <button type="submit"
                                    class="btn-base-green"
                                    name="redirect_to"
                                    value="bunri_sanrin"
                                    data-origin-subtab="bunri"
                                    data-return-anchor="bunri_income_sanrin">内訳</button>
                          </td>
                          {!! $renderInputs('bunri_syunyu_sanrin') !!}
                        </tr>
                        <tr>
                          <th scope="row" colspan="2" class="align-middle text-start th-ddd ps-1">退職</th>
                          <td class="text-center align-middle">
                            <button type="button" class="btn btn-link btn-sm px-0">HELP</button>
                          </td>
                          <td></td>
                          {!! $renderInputs('bunri_syunyu_taishoku') !!}
                        </tr>
                        <tr id="bunri_shotoku_shortlong_top" data-anchor>
                          <th scope="rowgroup" rowspan="11" class="text-center align-middle th-ccc">所得金額</th>
                          <th scope="rowgroup" rowspan="11" class="text-start align-middle ps-1">分離課税</th>
                          <th scope="rowgroup" rowspan="2" class="text-start align-middle th-ddd ps-1">短 期</th>
                          <th scope="row" class="align-middle text-start th-ddd ps-1">一般分</th>
                          <td class="text-center align-middle">
                            <button type="button" class="btn btn-link btn-sm px-0">HELP</button>
                          </td>
                          <td class="text-center align-middle" rowspan="5">
                            <button type="submit"
                                    class="btn-base-green"
                                    name="redirect_to"
                                    value="bunri_joto"
                                    data-origin-subtab="bunri"
                                    data-return-anchor="bunri_shotoku_shortlong_top">内訳</button>
                          </td>
                          {!! $renderInputs('bunri_shotoku_tanki_ippan') !!}
                        </tr>
                        <tr>
                          <th scope="row" class="align-middle text-start th-ddd ps-1">軽減分</th>
                          <td class="text-center align-middle">
                            <button type="button" class="btn btn-link btn-sm px-0">HELP</button>
                          </td>
                          {!! $renderInputs('bunri_shotoku_tanki_keigen') !!}
                        </tr>
                        <tr>
                          <th scope="rowgroup" rowspan="3" class="text-start th-ddd align-middle ps-1">長 期</th>
                          <th scope="row" class="align-middle text-start th-ddd ps-1">一般分</th>
                          <td class="text-center align-middle">
                            <button type="button" class="btn btn-link btn-sm px-0">HELP</button>
                          </td>
                          {!! $renderInputs('bunri_shotoku_choki_ippan') !!}
                        </tr>
                        @foreach (['tokutei' => '特定分', 'keika' => '軽課分'] as $type => $label)
                        <tr>
                          <th scope="row" class="align-middle text-start th-ddd ps-1">{{ $label }}</th>
                          <td class="text-center align-middle">
                            <button type="button" class="btn btn-link btn-sm px-0">HELP</button>
                          </td>
                          {!! $renderInputs('bunri_shotoku_choki_' . $type) !!}
                        </tr>
                        @endforeach
                        <tr id="bunri_shotoku_kabuteki_top" data-anchor>
                          <th scope="row" colspan="2" class="align-middle text-start th-ddd ps-1">一般株式等の譲渡</th>
                          <td class="text-center align-middle">
                            <button type="button" class="btn btn-link btn-sm px-0">HELP</button>
                          </td>
                          <td class="text-center align-middle" rowspan="3">
                            <button type="submit"
                                    class="btn-base-green"
                                    name="redirect_to"
                                    value="bunri_kabuteki"
                                    data-origin-subtab="bunri"
                                    data-return-anchor="bunri_shotoku_kabuteki_top">内訳</button>
                          </td>
                          {!! $renderInputs('bunri_shotoku_ippan_kabuteki_joto') !!}
                        </tr>
                        <tr>
                          <th scope="row" colspan="2" class="align-middle text-start th-ddd ps-1">上場株式等の譲渡</th>
                          <td class="text-center align-middle">
                            <button type="button" class="btn btn-link btn-sm px-0">HELP</button>
                          </td>
                          {!! $renderInputs('bunri_shotoku_jojo_kabuteki_joto') !!}
                        </tr>
                        <tr>
                          <th scope="row" colspan="2" class="align-middle text-start th-ddd ps-1">上場株式等の配当等</th>
                          <td class="text-center align-middle">
                            <button type="button" class="btn btn-link btn-sm px-0">HELP</button>
                          </td>
                          {!! $renderInputs('bunri_shotoku_jojo_kabuteki_haito') !!}
                        </tr>
                        <tr id="bunri_shotoku_sakimono" data-anchor>
                          <th scope="row" colspan="2" class="align-middle text-start th-ddd ps-1">先物取引</th>
                          <td class="text-center align-middle">
                            <button type="button" class="btn btn-link btn-sm px-0">HELP</button>
                          </td>
                          <td class="text-center align-middle">
                            <button type="submit"
                                    class="btn-base-green"
                                    name="redirect_to"
                                    value="bunri_sakimono"
                                    data-origin-subtab="bunri"
                                    data-return-anchor="bunri_shotoku_sakimono">内訳</button>
                          </td>
                          {!! $renderInputs('bunri_shotoku_sakimono') !!}
                        </tr>
                        <tr id="bunri_shotoku_sanrin" data-anchor>
                          <th scope="row" colspan="2" class="align-middle text-start th-ddd ps-1">山林</th>
                          <td class="text-center align-middle">
                            <button type="button" class="btn btn-link btn-sm px-0">HELP</button>
                          </td>
                          <td class="text-center align-middle">
                            <button type="submit"
                                    class="btn-base-green"
                                    name="redirect_to"
                                    value="bunri_sanrin"
                                    data-origin-subtab="bunri"
                                    data-return-anchor="bunri_shotoku_sanrin">内訳</button>
                          </td>
                          {!! $renderInputs('bunri_shotoku_sanrin') !!}
                        </tr>
                        <tr>
                          <th scope="row" colspan="2" class="align-middle text-start th-ddd ps-1">退職</th>
                          <td class="text-center align-middle">
                            <button type="button" class="btn btn-link btn-sm px-0">HELP</button>
                          </td>
                          <td></td>
                          {!! $renderInputs('bunri_shotoku_taishoku') !!}
                        </tr>
                        <tr>
                          <th scope="rowgroup" rowspan="19" class="text-center align-middle th-ccc ps-1 pe-1" nowrap="nowrap">税金の計算</th>
                          <th scope="row" colspan="3" class="align-middle text-start ps-1">総合課税の合計額（第一表より）</th>
                          <td class="text-center align-middle">
                            <button type="button" class="btn btn-link btn-sm px-0">HELP</button>
                          </td>
                          <td></td>
                          @foreach (['shotoku' => ['prev', 'curr'], 'jumin' => ['prev', 'curr']] as $tax => $periods)
                            @foreach ($periods as $period)
                              @php
                                $name = sprintf('bunri_sogo_gokeigaku_%s_%s', $tax, $period);
                                $value = old($name, $inputs[$name] ?? null);
                              @endphp
                              <td>
                                <input type="text"
                                       inputmode="numeric"
                                       class="form-control form-control-compact-05 text-end js-comma"
                                       name="{{ $name }}"
                                       value="{{ $value }}">
                              </td>
                            @endforeach
                          @endforeach
                        </tr>
                        <tr>
                          <th scope="row" colspan="3" class="align-middle text-start ps-1">所得から差し引かれる金額（　〃〃　）</th>
                          <td class="text-center align-middle">
                            <button type="button" class="btn btn-link btn-sm px-0">HELP</button>
                          </td>
                          <td></td>
                          @foreach (['shotoku' => ['prev', 'curr'], 'jumin' => ['prev', 'curr']] as $tax => $periods)
                            @foreach ($periods as $period)
                              @php
                                $name = sprintf('bunri_sashihiki_gokei_%s_%s', $tax, $period);
                                $value = old($name, $inputs[$name] ?? null);
                              @endphp
                              <td>
                                <input type="text"
                                       inputmode="numeric"
                                       class="form-control form-control-compact-05 text-end js-comma"
                                       name="{{ $name }}"
                                       value="{{ $value }}">
                              </td>
                            @endforeach
                          @endforeach
                        </tr>
                        <tr>
                          <th scope="rowgroup" rowspan="8" class="align-middle text-start ps-1" nowrap="nowrap">課税所得<br>金額</th>
                          <th scope="row" colspan="2" class="align-middle text-start ps-1 th-ddd">総合課税</th>
                          <td class="text-center align-middle">
                            <button type="button" class="btn btn-link btn-sm px-0">HELP</button>
                          </td>
                          <td></td>
                          @foreach (['shotoku' => ['prev', 'curr'], 'jumin' => ['prev', 'curr']] as $tax => $periods)
                            @foreach ($periods as $period)
                              @php
                                $name = sprintf('tb_sogo_%s_%s', $tax, $period);
                                $value = old($name, $inputs[$name] ?? null);
                              @endphp
                              <td>
                                <input type="text"
                                       inputmode="numeric"
                                       class="form-control form-control-compact-05 text-end js-comma"
                                       name="{{ $name }}"
                                       value="{{ $value }}">
                              </td>
                            @endforeach
                          @endforeach
                        </tr>
                        <tr>
                          <th scope="row" colspan="2" class="align-middle text-start ps-1 th-ddd">短期譲渡</th>
                          <td class="text-center align-middle">
                            <button type="button" class="btn btn-link btn-sm px-0">HELP</button>
                          </td>
                          <td></td>
                          @php
                            foreach (['shotoku' => ['prev', 'curr'], 'jumin' => ['prev', 'curr']] as $tax => $periods) {
                                foreach ($periods as $period) {
                                    $name = sprintf('tb_joto_tanki_%s_%s', $tax, $period);
                                    $sourceKey = sprintf('tb_joto_tanki_%s_%s', $tax, $period);
                                    $raw = $normalizeInt($inputs[$sourceKey] ?? 0);
                                    echo $renderServerLockedInput($name, $raw);
                                }
                            }
                          @endphp
                        </tr>
                        <tr>
                          <th scope="row" colspan="2" class="align-middle text-start ps-1 th-ddd">長期譲渡</th>
                          <td class="text-center align-middle">
                            <button type="button" class="btn btn-link btn-sm px-0">HELP</button>
                          </td>
                          <td></td>
                          @php
                            foreach (['shotoku' => ['prev', 'curr'], 'jumin' => ['prev', 'curr']] as $tax => $periods) {
                                foreach ($periods as $period) {
                                    $name = sprintf('tb_joto_choki_%s_%s', $tax, $period);
                                    $sourceKey = sprintf('tb_joto_choki_%s_%s', $tax, $period);
                                    $raw = $normalizeInt($inputs[$sourceKey] ?? 0);
                                    echo $renderServerLockedInput($name, $raw);
                                }
                            }
                          @endphp
                        </tr>
                        <tr>
                          <th scope="row" colspan="2" class="align-middle text-start ps-1 pe-1 th-ddd" nowrap="nowrap">一般・上場株式の譲渡</th>
                          <td class="text-center align-middle">
                            <button type="button" class="btn btn-link btn-sm px-0">HELP</button>
                          </td>
                          <td></td>
                          {!! $renderSeparatedTb(['tb_ippan_kabuteki_joto','tb_jojo_kabuteki_joto'], $inputs) !!}
                        </tr>
                        <tr>
                          <th scope="row" colspan="2" class="align-middle text-start ps-1 th-ddd">上場株式の配当等</th>
                          <td class="text-center align-middle">
                            <button type="button" class="btn btn-link btn-sm px-0">HELP</button>
                          </td>
                          <td></td>
                          {!! $renderSeparatedTb('tb_jojo_kabuteki_haito', $inputs) !!}
                        </tr>
                        <tr>
                          <th scope="row" colspan="2" class="align-middle text-start ps-1 th-ddd">先物取引</th>
                          <td class="text-center align-middle">
                            <button type="button" class="btn btn-link btn-sm px-0">HELP</button>
                          </td>
                          <td></td>
                          {!! $renderSeparatedTb('tb_sakimono', $inputs) !!}
                        </tr>
                        <tr>
                          <th scope="row" colspan="2" class="align-middle text-start ps-1 th-ddd">山林</th>
                          <td class="text-center align-middle">
                            <button type="button" class="btn btn-link btn-sm px-0">HELP</button>
                          </td>
                          <td></td>
                          {!! $renderSeparatedTb('tb_sanrin', $inputs) !!}
                        </tr>
                        <tr>
                          <th scope="row" colspan="2" class="align-middle text-start ps-1 th-ddd">退職</th>
                          <td class="text-center align-middle">
                            <button type="button" class="btn btn-link btn-sm px-0">HELP</button>
                          </td>
                          <td></td>
                          {!! $renderSeparatedTb('tb_taishoku', $inputs) !!}
                        </tr>
                        <tr>
                          <th scope="rowgroup" rowspan="8" class="text-center align-middle text-start ps-1">税 額</th>
                          <th scope="row" colspan="2" class="align-middle text-start ps-1 th-ddd">総合課税</th>
                          <td class="text-center align-middle">
                            <button type="button" class="btn btn-link btn-sm px-0">HELP</button>
                          </td>
                          <td></td>
                          @php
                            foreach (['shotoku' => ['prev', 'curr'], 'jumin' => ['prev', 'curr']] as $tax => $periods) {
                              foreach ($periods as $period) {
                                $name = sprintf('bunri_zeigaku_sogo_%s_%s', $tax, $period);
                                $raw  = $normalizeInt($inputs[$name] ?? 0);
                                echo $renderServerLockedInput($name, $raw);
                              }
                            }
                          @endphp
                        </tr>
                        <tr>
                          <th scope="row" colspan="2" class="align-middle text-start ps-1 th-ddd">短期譲渡</th>
                          <td class="text-center align-middle">
                            <button type="button" class="btn btn-link btn-sm px-0">HELP</button>
                          </td>
                        <td></td>
                          @php
                            foreach (['shotoku' => ['prev', 'curr'], 'jumin' => ['prev', 'curr']] as $tax => $periods) {
                              foreach ($periods as $period) {
                                $name = sprintf('bunri_zeigaku_tanki_%s_%s', $tax, $period);
                                $raw  = $normalizeInt($inputs[$name] ?? 0);
                                echo $renderServerLockedInput($name, $raw);
                              }
                            }
                          @endphp
                        </tr>
                        <tr>
                          <th scope="row" colspan="2" class="align-middle text-start ps-1 th-ddd">長期譲渡</th>
                          <td class="text-center align-middle">
                            <button type="button" class="btn btn-link btn-sm px-0">HELP</button>
                          </td>
                          <td></td>
                          @php
                            foreach (['shotoku' => ['prev', 'curr'], 'jumin' => ['prev', 'curr']] as $tax => $periods) {
                              foreach ($periods as $period) {
                                $name = sprintf('bunri_zeigaku_choki_%s_%s', $tax, $period);
                                $raw  = $normalizeInt($inputs[$name] ?? 0);
                                echo $renderServerLockedInput($name, $raw);
                              }
                            }
                          @endphp
                        </tr>
                        <tr>
                          <th scope="row" colspan="2" class="align-middle text-start ps-1 th-ddd">一般・上場株式の譲渡</th>
                          <td class="text-center align-middle">
                            <button type="button" class="btn btn-link btn-sm px-0">HELP</button>
                          </td>
                          <td></td>
                          @php
                            foreach (['shotoku' => ['prev', 'curr'], 'jumin' => ['prev', 'curr']] as $tax => $periods) {
                              foreach ($periods as $period) {
                                $name = sprintf('bunri_zeigaku_joto_%s_%s', $tax, $period);
                                $raw  = $normalizeInt($inputs[$name] ?? 0);
                                echo $renderServerLockedInput($name, $raw);
                              }
                            }
                          @endphp
                        </tr>
                        <tr>
                          <th scope="row" colspan="2" class="align-middle text-start ps-1 th-ddd">上場株式の配当等</th>
                          <td class="text-center align-middle">
                            <button type="button" class="btn btn-link btn-sm px-0">HELP</button>
                          </td>
                          <td></td>
                          @php
                            foreach (['shotoku' => ['prev', 'curr'], 'jumin' => ['prev', 'curr']] as $tax => $periods) {
                              foreach ($periods as $period) {
                                $name = sprintf('bunri_zeigaku_haito_%s_%s', $tax, $period);
                                $raw  = $normalizeInt($inputs[$name] ?? 0);
                                echo $renderServerLockedInput($name, $raw);
                              }
                            }
                          @endphp
                        </tr>
                        <tr>
                          <th scope="row" colspan="2" class="align-middle text-start ps-1 th-ddd">先物取引</th>
                          <td class="text-center align-middle">
                            <button type="button" class="btn btn-link btn-sm px-0">HELP</button>
                          </td>
                          <td></td>
                          @php
                            foreach (['shotoku' => ['prev', 'curr'], 'jumin' => ['prev', 'curr']] as $tax => $periods) {
                              foreach ($periods as $period) {
                                $name = sprintf('bunri_zeigaku_sakimono_%s_%s', $tax, $period);
                                $raw  = $normalizeInt($inputs[$name] ?? 0);
                                echo $renderServerLockedInput($name, $raw);
                              }
                            }
                          @endphp
                        </tr>
                        <tr>
                          <th scope="row" colspan="2" class="align-middle text-start ps-1 th-ddd">山林</th>
                          <td class="text-center align-middle">
                            <button type="button" class="btn btn-link btn-sm px-0">HELP</button>
                          </td>
                          <td></td>
                          @php
                            foreach (['shotoku' => ['prev', 'curr'], 'jumin' => ['prev', 'curr']] as $tax => $periods) {
                              foreach ($periods as $period) {
                                $name = sprintf('bunri_zeigaku_sanrin_%s_%s', $tax, $period);
                                $raw  = $normalizeInt($inputs[$name] ?? 0);
                                echo $renderServerLockedInput($name, $raw);
                              }
                            }
                          @endphp
                        </tr>
                        <tr>
                          <th scope="row" colspan="2" class="align-middle text-start ps-1 th-ddd">退職</th>
                          <td class="text-center align-middle">
                            <button type="button" class="btn btn-link btn-sm px-0">HELP</button>
                          </td>
                          <td></td>
                          @php
                            foreach (['shotoku' => ['prev', 'curr'], 'jumin' => ['prev', 'curr']] as $tax => $periods) {
                              foreach ($periods as $period) {
                                $name = sprintf('bunri_zeigaku_taishoku_%s_%s', $tax, $period);
                                $raw  = $normalizeInt($inputs[$name] ?? 0);
                                echo $renderServerLockedInput($name, $raw);
                              }
                            }
                          @endphp
                        </tr>
                        <tr>
                          <th scope="row" colspan="3" class="align-middle text-center th-cream">合計（第一表へ）</th>
                          <td class="text-center align-middle">
                            <button type="button" class="btn btn-link btn-sm px-0">HELP</button>
                          </td>
                          <td></td>
                          @php
                            foreach (['shotoku' => ['prev', 'curr'], 'jumin' => ['prev', 'curr']] as $tax => $periods) {
                              foreach ($periods as $period) {
                                $name = sprintf('bunri_zeigaku_gokei_%s_%s', $tax, $period);
                                $raw  = $normalizeInt($inputs[$name] ?? 0);
                                echo $renderServerLockedInput($name, $raw);
                              }
                            }
                          @endphp
                        </tr>
                      </tbody>
                    </table>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      @else
        <div class="card mb-4">
          <div class="card-body">
            {!! $sogoContent !!}
          </div>
        </div>
      @endif
--}}
        <div class="card mb-4">
          <div class="card-body">
            {!! $sogoContent !!}
          </div>
        </div>      
        </div>
        <div class="tab-pane fade {{ $detailsPaneActiveClass }}" id="furusato-tab-result-details" role="tabpanel" aria-labelledby="furusato-tab-result-details-nav">
          @php
            $resultYearParam = (string) request()->query('result_year', '');
            $activeResultPeriod = in_array($resultYearParam, ['prev', 'curr'], true) ? $resultYearParam : 'curr';
            $prevSubTabActive = $activeResultPeriod === 'prev' ? 'active' : '';
            $prevSubPaneActive = $activeResultPeriod === 'prev' ? 'show active' : '';
            $currSubTabActive = $activeResultPeriod === 'curr' ? 'active' : '';
            $currSubPaneActive = $activeResultPeriod === 'curr' ? 'show active' : '';
          @endphp
          <input type="hidden" name="result_year" id="furusato-result-year-input" value="{{ $activeResultPeriod }}">
          <ul class="nav nav-tabs mt-3" id="furusato-result-details-subtabs" role="tablist">
            <li class="nav-item" role="presentation">
              <button class="nav-link {{ $prevSubTabActive }}" id="furusato-result-details-prev-nav" data-bs-toggle="tab" data-bs-target="#furusato-result-details-prev" type="button" role="tab" aria-controls="furusato-result-details-prev" aria-selected="{{ $activeResultPeriod === 'prev' ? 'true' : 'false' }}">{{ $warekiPrevLabel }}</button>
            </li>
            <li class="nav-item" role="presentation">
              <button class="nav-link {{ $currSubTabActive }}" id="furusato-result-details-curr-nav" data-bs-toggle="tab" data-bs-target="#furusato-result-details-curr" type="button" role="tab" aria-controls="furusato-result-details-curr" aria-selected="{{ $activeResultPeriod === 'curr' ? 'true' : 'false' }}">{{ $warekiCurrLabel }}</button>
            </li>
          </ul>
          <div class="tab-content mt-3" id="furusato-result-details-subtab-content">
            <div class="tab-pane fade {{ $prevSubPaneActive }}" id="furusato-result-details-prev" role="tabpanel" aria-labelledby="furusato-result-details-prev-nav">
              @include('tax.furusato.tabs.result_details', [
                'results' => $resultsData,
                'jintekiDiff' => $jintekiDiffData,
                'tokureiStandardRate' => $tokureiStandardRateData,
                'tokureiComputedPercent' => $tokureiComputedPercentData,
                'tokureiEnabled' => $tokureiEnabledData,
                'inputs' => $out['inputs'] ?? [],
                'warekiPrev' => $warekiPrevLabel,
                'warekiCurr' => $warekiCurrLabel,
                'periodFilter' => 'prev',
                'showSeparatedNetting' => $showSeparatedNetting ?? false,
                'syoriSettings' => $syoriSettings,
              ])
            </div>
            <div class="tab-pane fade {{ $currSubPaneActive }}" id="furusato-result-details-curr" role="tabpanel" aria-labelledby="furusato-result-details-curr-nav">
              @include('tax.furusato.tabs.result_details', [
                'results' => $resultsData,
                'jintekiDiff' => $jintekiDiffData,
                'tokureiStandardRate' => $tokureiStandardRateData,
                'tokureiComputedPercent' => $tokureiComputedPercentData,
                'tokureiEnabled' => $tokureiEnabledData,
                'inputs' => $out['inputs'] ?? [],
                'warekiPrev' => $warekiPrevLabel,
                'warekiCurr' => $warekiCurrLabel,
                'periodFilter' => 'curr',
                'showSeparatedNetting' => $showSeparatedNetting ?? false,
                'syoriSettings' => $syoriSettings,
              ])
            </div>
          </div>
        </div>
        <div class="tab-pane fade" id="furusato-tab-result-upper" role="tabpanel" aria-labelledby="furusato-tab-result-upper-nav">
          @include('tax.furusato.tabs.result_upper_furusato', ['results' => $resultsData])
        </div>
      </div>
    </div>
  </form>
</div>

{{-- ============================
   PDF出力中オーバーレイ（全画面）
   - input/result_details どのタブ表示中でも覆う
   ============================ --}}
<div id="furusato-pdf-overlay" aria-hidden="true">
  <div class="overlay-card" role="status" aria-live="polite">
    <div class="overlay-title">PDFを出力しています…</div>
    <p class="overlay-sub text-center">自動でダウンロードが開始されます。<br>この画面は閉じずにお待ちください。</p>
    <div class="overlay-row">
      <div class="mini-spinner" aria-hidden="true"></div>
      <div class="overlay-sub" id="furusato-pdf-overlay-detail">準備中…</div>
    </div>
  </div>
</div>

{{-- 帳票プレビュー モーダル群（Blade分割） --}}
<div class="modal fade" id="furusato-one-stop-pdf-block-modal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">確定申告が必要です。</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="閉じる"></button>
      </div>
      <div class="modal-body">
        <p class="mb-0" id="furusato-one-stop-pdf-block-message" style="white-space: pre-line;">{{ $oneStopPdfGuardMessage ?? '' }}</p>
      </div>
      <div class="modal-footer">
        <a class="btn btn-base-blue" id="furusato-one-stop-pdf-block-back" href="{{ $syoriBackUrl ?? '#' }}">処理メニューへ戻る</a>
      </div>
    </div>
  </div>
</div>

@include('tax.furusato.partials.report_preview_modal')

{{-- ============================
   PDF出力モーダル（3択）
   - プレビュー無し：選択後にそのまま download を開始
   ============================ --}}
<div class="modal fade" id="furusato-pdf-mode-modal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered" style="max-width:400px;">
    <div class="modal-content">
      <div class="modal-header">
        <h15 class="modal-title">どの条件でPDFを出力しますか？</h15>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="閉じる"></button>
      </div>
      <div class="modal-body">
        <div class="d-flex flex-column align-items-center gap-2">
          <button type="button" class="btn btn-base-blue" data-pdf-variant="current" style="height:40px; width:260px;">
            今までに寄附した額でPDFを出力する
          </button>
          <button type="button" class="btn btn-base-blue" data-pdf-variant="max" style="height:40px; width:260px;">
            上限額まで寄附した場合でPDFを出力する
          </button>
          <button type="button" class="btn btn-base-blue" data-pdf-variant="both" style="height:40px; width:260px;">
            両方ともPDFを出力する
          </button>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn  btn-base-blue" data-bs-dismiss="modal">キャンセル</button>
      </div>
    </div>
  </div>
</div>

{{-- ============================
   help出力モーダル（共通）
   -- resources/views/partials/help_texts_modal.phpにリンク本文はそちら
   ============================ --}}

  @php
    // このページのHELP辞書（return配列のphpファイル）
    // ※ファイル未作成でも500にならないようにガードする
    $helpPath = resource_path('views/tax/furusato/helps/help_texts_modal.php');
    $HELP_TEXTS = file_exists($helpPath) ? require $helpPath : [];
  @endphp

  {{-- 共通HELPモーダル（このページで1個だけ） --}}
  <div class="modal fade" id="helpModalCommon" tabindex="-1" aria-hidden="true">
    {{-- サイズ統一：最大幅550px --}}
    <div class="modal-dialog" style="max-width: 550px;">
      <div class="modal-content">
        <div class="modal-header mb-0">
          <button type="button" class="btn btn-vp me-2">HELP</button><h15 class="modal-title" id="helpModalTitle">HELP</h15>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body text-start mt-0 ms-2 mb-2">
          <div id="helpModalBody" class="small" style="white-space: pre-wrap;"></div>
        </div>
        {{--<div class="modal-footer">
          <button type="button" class="btn btn-base-blue" data-bs-dismiss="modal">閉じる</button>
        </div>--}}
      </div>
    </div>
  </div>

  {{-- HELP辞書をJSへ渡す（ページ専用） --}}
  <script>
    window.__PAGE_HELP_TEXTS__ = @json($HELP_TEXTS, JSON_UNESCAPED_UNICODE);
  </script>
  
  
@endsection
@push('scripts')
<script>
  // 表示はサーバSoT(tb_*)に従う：ここでは見た目のカンマ整形と若干のフォーム整頓のみ行う
  document.addEventListener('DOMContentLoaded', () => {
    // 3桁カンマ整形
    const fmt = n => new Intl.NumberFormat('ja-JP').format((() => {
      const s = String(n ?? '').replace(/,/g, '').trim();
      if (s === '' || s === '-') return 0;
      const v = Number(s); return Number.isFinite(v) ? Math.trunc(v) : 0;
    })());
    document.querySelectorAll('input.js-comma[name]').forEach(el => {
      // 初期表示時も、値が入っていれば必ずカンマ整形（'－' は除外）
      if (String(el.value).trim() !== '' && el.value !== '－') {
        el.value = fmt(el.value);
      }
      el.addEventListener('blur', () => { if (el.value !== '－') el.value = fmt(el.value); });
    });
    // SoTで上書きされる課税標準/分離課税標準は編集不可（tb_* のみ）
    const lockPrefixes = [
      'tb_sogo_shotoku_', 'tb_sogo_jumin_',
      'tb_joto_tanki_shotoku_', 'tb_joto_tanki_jumin_',
      'tb_joto_choki_shotoku_', 'tb_joto_choki_jumin_',
      'tb_ippan_kabuteki_joto_shotoku_', 'tb_ippan_kabuteki_joto_jumin_',
      'tb_jojo_kabuteki_joto_shotoku_',  'tb_jojo_kabuteki_joto_jumin_',
      'tb_jojo_kabuteki_haito_shotoku_', 'tb_jojo_kabuteki_haito_jumin_',
      'tb_sakimono_shotoku_', 'tb_sakimono_jumin_',
      'tb_sanrin_shotoku_',   'tb_sanrin_jumin_',
      'tb_taishoku_shotoku_', 'tb_taishoku_jumin_',
    ];
    document.querySelectorAll('input[name]').forEach(el => {
      const name = el.getAttribute('name') || '';
      if (lockPrefixes.some(p => name.startsWith(p))) {
        el.readOnly = true;
        el.classList.add('bg-light', 'text-end');
      }
    });
  });
</script>
@endpush
 
{{-- Enter移動（ふるさと全画面共通） --}}
@include('tax.furusato.partials.enter_nav')

@push('scripts')
<script>
  document.addEventListener('DOMContentLoaded', function () {
    // 以降はナビ/アンカー復元と送信整頓のみ（計算処理は撤去）
    const params = new URLSearchParams(window.location.search);
    const targetTab = params.get('tab');
    const targetSubtab = params.get('subtab');
    const readHash = () => {
      const hash = (typeof window !== 'undefined' && window.location) ? window.location.hash : '';
    if (!hash || hash.length <= 1) return '';
      try { return decodeURIComponent(hash.slice(1)); } catch { return hash.slice(1); }
    };
    const anchorId = readHash();
    window.__furusatoNav = {
      targetTab, targetSubtab, anchorId,
      initialTab: @json((string) request()->query('tab', session('return_tab', ''))),
      showResultFlag: Boolean(@json($showResultFlag)),
    };
    const initialTab = @json((string) request()->query('tab', session('return_tab', '')));
    const showResultFlag = Boolean(@json($showResultFlag));
    const form = document.getElementById('furusato-input-form');

    const ensureHiddenField = (name, value) => {
      if (!form) {
        return null;
      }
      let input = form.querySelector(`input[name="${name}"]`);
      if (!input) {
        input = document.createElement('input');
        input.type = 'hidden';
        input.name = name;
        form.appendChild(input);
      }
      input.value = value;
      return input;
    };

    const removeHiddenField = (name) => {
      if (!form) {
        return;
      }
      const input = form.querySelector(`input[name="${name}"]`);
      if (input) {
        input.remove();
      }
    };
/*
 *    const inferSubtabFromAnchor = (anchor) => {
 *      if (typeof anchor !== 'string' || anchor === '') {
 *        return '';
 *      }
 *      if (anchor.startsWith('bunri_')) {
 *        return 'bunri';
 *      }
 *      return '';
 *    };
 */
    // ▼ 第三表タブは統合済みのため、アンカーから subtab は推定しない
    const inferSubtabFromAnchor = () => '';
    const clearOriginFields = () => {
      if (!form) {
        return;
      }
      ['origin_tab', 'origin_subtab', 'origin_anchor'].forEach((name) => removeHiddenField(name));
      if (form.dataset) {
        delete form.dataset.returnAnchor;
        delete form.dataset.returnSubtab;
      }
    };

    const setOriginFields = (anchor, subtab) => {
      if (!form) {
        return;
      }
      const resolvedSubtab = subtab || inferSubtabFromAnchor(anchor);
      const hasAnchor = typeof anchor === 'string' && anchor !== '';
      const hasSubtab = typeof resolvedSubtab === 'string' && resolvedSubtab !== '';

      if (!hasAnchor && !hasSubtab) {
        clearOriginFields();
        return;
      }

      ensureHiddenField('origin_tab', 'input');

      if (hasAnchor) {
        ensureHiddenField('origin_anchor', anchor);
      } else {
        removeHiddenField('origin_anchor');
      }

      if (hasSubtab) {
        ensureHiddenField('origin_subtab', resolvedSubtab);
      } else {
        removeHiddenField('origin_subtab');
      }

      if (form.dataset) {
        if (hasAnchor) {
          form.dataset.returnAnchor = anchor;
        } else {
          delete form.dataset.returnAnchor;
        }
        if (hasSubtab) {
          form.dataset.returnSubtab = resolvedSubtab;
        } else {
          delete form.dataset.returnSubtab;
        }
      }
    };

    if (form) {
      const detailButtons = form.querySelectorAll('.js-open-details');
      detailButtons.forEach((button) => {
        button.addEventListener('click', (event) => {
          event.preventDefault();
          const redirectTo = button.getAttribute('data-redirect-to') || '';
          const anchor = button.getAttribute('data-origin-anchor')
            || button.getAttribute('data-return-anchor')
            || '';
          const subtab = button.getAttribute('data-origin-subtab') || '';

          if (anchor || subtab) {
            setOriginFields(anchor, subtab);
          } else {
            clearOriginFields();
          }

          if (redirectTo !== '') {
            ensureHiddenField('redirect_to', redirectTo);
          }

          form.submit();
        });
      });

      const resultYearField = form.querySelector('#furusato-result-year-input');
      const setResultYearValue = (value) => {
        if (resultYearField) {
          resultYearField.value = value;
        }
      };
      const resultDetailsSubtabs = document.getElementById('furusato-result-details-subtabs');
      if (resultDetailsSubtabs) {
        resultDetailsSubtabs.addEventListener('shown.bs.tab', (event) => {
          const targetSelector = event.target instanceof Element ? event.target.getAttribute('data-bs-target') : '';
          if (targetSelector === '#furusato-result-details-prev') {
            setResultYearValue('prev');
          } else if (targetSelector === '#furusato-result-details-curr') {
            setResultYearValue('curr');
          }
        });
        resultDetailsSubtabs.addEventListener('click', (event) => {
          const button = event.target instanceof Element ? event.target.closest('button[data-bs-target]') : null;
          if (!button) {
            return;
          }
          const targetSelector = button.getAttribute('data-bs-target') || '';
          if (targetSelector === '#furusato-result-details-prev') {
            setResultYearValue('prev');
          } else if (targetSelector === '#furusato-result-details-curr') {
            setResultYearValue('curr');
          }
        });
      }

      form.addEventListener('click', (event) => {
        const target = event.target instanceof Element ? event.target.closest('[data-return-anchor]') : null;
        if (target) {
          const anchor = target.getAttribute('data-return-anchor') || '';
          const subtab = target.getAttribute('data-origin-subtab') || '';
          setOriginFields(anchor, subtab);
          return;
        }

        clearOriginFields();
      });

      form.addEventListener('submit', () => {
        const datasetAnchor = form.dataset && form.dataset.returnAnchor ? form.dataset.returnAnchor : '';
        const datasetSubtab = form.dataset && form.dataset.returnSubtab ? form.dataset.returnSubtab : '';
        if (datasetAnchor || datasetSubtab) {
          setOriginFields(datasetAnchor, datasetSubtab);
          return;
        }

        const hashAnchor = readHash();
        if (hashAnchor) {
          setOriginFields(hashAnchor, '');
          return;
        }

        clearOriginFields();
      });
    }

    const resolveRestoreScrollConfig = () => {
      const config = window.RESTORE_SCROLL || {};
      const rawOffset = Number(config.offsetPx);
      const offsetPx = Number.isFinite(rawOffset) ? rawOffset : 80;
      const rawFocusMode = typeof config.focusMode === 'string' ? config.focusMode.toLowerCase() : '';
      const allowedModes = ['none', 'silent', 'visible'];
      const focusMode = allowedModes.includes(rawFocusMode) ? rawFocusMode : 'none';
      return { offsetPx, focusMode };
    };

    const restoreScrollConfig = resolveRestoreScrollConfig();

    if (document.documentElement && document.documentElement.style) {
      document.documentElement.style.setProperty('--restore-scroll-offset', `${restoreScrollConfig.offsetPx}px`);
    }

    // 最寄りの「実際にスクロール量がある」コンテナを見つける
    const getScrollableAncestor = (el) => {
      let node = el?.parentElement;
      while (node && node !== document.body) {
        const style = window.getComputedStyle(node);
        const oy = style.overflowY;
        const hasScroll = (oy === 'auto' || oy === 'scroll') &&
                          (node.scrollHeight - node.clientHeight > 1);
        if (hasScroll) return node;
        node = node.parentElement;
      }
      // どれもスクロールできない場合はページ本体
      return document.scrollingElement || document.documentElement;
    };

    // アンカーへスクロール（table-responsive 等の内側も対応）
    const scrollToAnchorIfPresent = () => {
      if (!anchorId) return;
      const target = document.getElementById(String(anchorId));
      if (!target) return;

      let container = getScrollableAncestor(target);

      // container がページ本体かどうかでスクロール方法を切替
      const isPage =
        container === document.scrollingElement ||
        container === document.documentElement ||
        container === document.body;

      if (isPage) {
        const currentScroll = typeof window.scrollY === 'number'
          ? window.scrollY
          : (window.pageYOffset || 0);
        const rect = target.getBoundingClientRect();
        const top = rect.top + currentScroll - restoreScrollConfig.offsetPx;
        window.scrollTo({ top, behavior: 'auto' });
      } else {
        // コンテナ内にスクロール余地がない場合はページ本体へフォールバック
        if (container.scrollHeight - container.clientHeight <= 1) {
          container = document.scrollingElement || document.documentElement;
          const rect = target.getBoundingClientRect();
          const cur = typeof window.scrollY === 'number' ? window.scrollY : (window.pageYOffset || 0);
          const top = rect.top + cur - restoreScrollConfig.offsetPx;
          window.scrollTo({ top, behavior: 'auto' });
        } else {
          // コンテナ内オフセットでスクロール
          const crect = container.getBoundingClientRect();
          const trect = target.getBoundingClientRect();
          const delta = trect.top - crect.top;
          const top = container.scrollTop + delta - restoreScrollConfig.offsetPx;
          container.scrollTo({ top, behavior: 'auto' });
        }
      }

      // フォーカス（視覚/サイレント）
      if (typeof target.focus === 'function') {
        if (restoreScrollConfig.focusMode === 'silent') {
          target.setAttribute('data-silent-focus', '1');
          target.focus({ preventScroll: true });
          setTimeout(() => {
            if (typeof target.blur === 'function') target.blur();
            target.removeAttribute('data-silent-focus');
          }, 0);
        } else if (restoreScrollConfig.focusMode === 'visible') {
          target.focus({ preventScroll: true });
        }
      }
    };

    // 要素の存在と可視(レイアウト確定)を待ってからスクロール
    const waitFor = (pred, timeout = 1200, step = 50) => new Promise((resolve) => {
      const t0 = Date.now();
      (function loop(){
        if (pred()) return resolve(true);
        if (Date.now() - t0 >= timeout) return resolve(false);
        setTimeout(loop, step);
      })();
    });
    const scheduleScrollToAnchor = async () => {
      if (!anchorId) return;
      // 2フレーム待機（タブ描画/高さ確定の猶予）
      await new Promise(r => requestAnimationFrame(() => requestAnimationFrame(r)));
      // 目標要素が実体化しているか待つ
      await waitFor(() => !!document.getElementById(String(anchorId)));
      // スクロール実行＋軽い追撃（レイアウト揺れ対策）
      scrollToAnchorIfPresent();
      setTimeout(scrollToAnchorIfPresent, 60);
      setTimeout(scrollToAnchorIfPresent, 140);
    };
    window.__scrollToAnchorNow = scrollToAnchorIfPresent;

    // レイアウト確定が遅い環境向けに再試行を薄く入れる（描画完了待ち）
    const scheduleScrollRetry = () => {
      if (!anchorId) return;
      setTimeout(scrollToAnchorIfPresent, 50);
      setTimeout(scrollToAnchorIfPresent, 150);
    };

    // タブを「shown」後に進める Promise 版に置換
    const showTabById = (btnId) => new Promise((resolve) => {
      const button = document.getElementById(btnId);
      if (!button) return resolve(false);
      const TabClass = (typeof bootstrap !== 'undefined' && bootstrap.Tab) ? bootstrap.Tab : null;
      if (TabClass && typeof TabClass.getOrCreateInstance === 'function') {
        const handler = () => { button.removeEventListener('shown.bs.tab', handler); resolve(true); };
        button.addEventListener('shown.bs.tab', handler, { once: true });
        const instance = TabClass.getOrCreateInstance(button);
        const alreadyActive = button.classList.contains('active');
        instance.show();
        // すでに active だった場合は shown が出ないことがある → 即時resolve
        if (alreadyActive) return resolve(true);
        return;
      }
      // フォールバック（手動クラス切替）
      const nav = button.closest('.nav');
      if (nav) nav.querySelectorAll('.nav-link').forEach((l) => l.classList.remove('active'));
      button.classList.add('active');
      const sel = button.getAttribute('data-bs-target');
      if (sel) {
        const pane = document.querySelector(sel);
        if (pane) {
          const tc = pane.closest('.tab-content');
          if (tc) tc.querySelectorAll('.tab-pane').forEach((p) => p.classList.remove('show','active'));
          pane.classList.add('show','active');
        }
      }
      resolve(true);
    });

    const hasTargetTab = typeof targetTab === 'string' && targetTab.trim() !== '';
    const normalizedTargetTab = hasTargetTab ? targetTab.trim() : '';
    const normalizedTargetSubtab = typeof targetSubtab === 'string' ? targetSubtab.trim() : '';

    (async () => {
      if (hasTargetTab) {
        if (normalizedTargetTab === 'input') {
          await showTabById('furusato-tab-input-nav');
//          if (normalizedTargetSubtab === 'bunri')      await showTabById('tab-bunri');
//          else if (normalizedTargetSubtab === 'sogo')  await showTabById('tab-sogo');
          await scheduleScrollToAnchor(); scheduleScrollRetry();
        } else if (normalizedTargetTab === 'result-details') {
          await showTabById('furusato-tab-result-details-nav');
          if (normalizedTargetSubtab === 'prev')       await showTabById('furusato-result-details-prev-nav');
          else if (normalizedTargetSubtab === 'curr')  await showTabById('furusato-result-details-curr-nav');
          await scheduleScrollToAnchor(); scheduleScrollRetry();
        } else if (normalizedTargetTab === 'result-upper') {
          await showTabById('furusato-tab-result-upper-nav');
          await scheduleScrollToAnchor(); scheduleScrollRetry();
        } else {
          await scheduleScrollToAnchor(); scheduleScrollRetry();
        }
      } else {
//        const impliedSubtab = inferSubtabFromAnchor(anchorId);
        if (!showResultFlag && initialTab === 'input') {
          await showTabById('furusato-tab-input-nav');
//          if (impliedSubtab === 'bunri')      await showTabById('tab-bunri');
//          else if (impliedSubtab === 'sogo')  await showTabById('tab-sogo');
          await scheduleScrollToAnchor(); scheduleScrollRetry();
        } else {
          await scheduleScrollToAnchor(); scheduleScrollRetry();
        }
      }
    })();

    // 孤立して表に出ていない tax_* は hidden 化（誤送信防止）
    (function sanitizeOrphanTaxInputs() {
      const formEl = document.getElementById('furusato-input-form');
      if (!formEl) return;
      const mustHide = ['tax_zeigaku_'];
      Array.from(formEl.querySelectorAll('input[name]')).forEach((el) => {
        if (!(el instanceof HTMLInputElement)) return;
        const name = el.getAttribute('name') || '';
        if (!mustHide.some(p => name.startsWith(p))) return;
        if (!el.closest('td')) {
          el.type = 'hidden';
          el.classList.remove('bg-light','text-end');
        }
      });
    })();
  });
</script>
<script>
  document.addEventListener('DOMContentLoaded', function () {
    const shotokuRates = @json($shotokuRatesForScript);
    // kihu_year（Dataの年度）：令和6年度分特別税額控除（tax_tokubetsu_R6）の入力可否判定に使用
    const kihuYear = Number(@json($kihuYear ?? 0)) || 0;
    const taxTypes = ['shotoku', 'jumin'];
    const periods = ['prev', 'curr'];
    const bunriFlags = {
      prev: (Number(@json($syoriSettings['bunri_flag_prev'] ?? $syoriSettings['bunri_flag'] ?? 0)) === 1),
      curr: (Number(@json($syoriSettings['bunri_flag_curr'] ?? $syoriSettings['bunri_flag'] ?? 0)) === 1),
    };
    // ▼ UI専用（POSTしない）固定値：調整控除／住民税寄附金税額控除（県+市合計）
    const uiFixedCredits = {
      chosei: {
        prev: Number(@json($choseiPrev ?? 0)) || 0,
        curr: Number(@json($choseiCurr ?? 0)) || 0,
      },
      juminKifukinZeigaku: {
        prev: Number(@json($kifukinZeigakuPrev ?? 0)) || 0,
        curr: Number(@json($kifukinZeigakuCurr ?? 0)) || 0,
      },
    };
    const kojoShokeiBases = [
      'kojo_shakaihoken',
      'kojo_shokibo',
      'kojo_seimei',
      'kojo_jishin',
      'kojo_kafu',
      'kojo_hitorioya',
      'kojo_kinrogakusei',
      'kojo_shogaisha',
      'kojo_haigusha',
      'kojo_haigusha_tokubetsu',
      'kojo_fuyo',
      'kojo_tokutei_shinzoku',
      'kojo_kiso',
    ];
    const kojoGokeiExtras = ['kojo_zasson', 'kojo_iryo', 'kojo_kifukin'];
    const kojoFieldOverrides = {
      kojo_kiso: {
        shotoku: 'shotokuzei_kojo_kiso_',
        jumin: 'juminzei_kojo_kiso_',
      },
      kojo_kifukin: {
        shotoku: 'shotokuzei_kojo_kifukin_',
        jumin: 'juminzei_kojo_kifukin_',
      },
    };

    const getInput = (name) => document.querySelector('[name="' + name + '"]');
    // カンマあり文字列を安全に整数へ
    const toInt = (val) => {
      if (val === null || val === undefined) return 0;
      const s = String(val).replace(/,/g, '').trim();
      if (s === '' || s === '-') return 0;
      const n = Number(s);
      return Number.isFinite(n) ? Math.trunc(n) : 0;
    };
    const readInt = (name) => {
      const input = getInput(name);
      if (!input) return 0;
      return toInt(input.value);
    };
    // 画面は常にカンマ付きで表示
    const formatComma = (n) => new Intl.NumberFormat('ja-JP').format(toInt(n));
    // ② サーバから注入された値を「ロック」し、再計算による上書きを防ぐ仕組み
    //    - Blade 側で data-server-lock/data-server-raw を設定済み
    //    - enforceServerLocks() で表示値/hidden を常にサーバ値へ戻す
    const ensureHiddenFor = (name) => {
      const form = document.getElementById('furusato-input-form');
      if (!form) return null;
      let hidden = form.querySelector(`input[type="hidden"][name="${name}"]`);
      if (!hidden) {
        hidden = document.createElement('input');
        hidden.type = 'hidden';
        hidden.name = name;
        form.appendChild(hidden);
      }
      return hidden;
    };
    const enforceServerLocks = () => {
      const form = document.getElementById('furusato-input-form');
      if (!form) return;
      form.querySelectorAll('input[data-server-lock="1"]').forEach((el) => {
        if (el.dataset && el.dataset.serverLockDashed === '1') {
          return;
        }
        const name = el.name;
        // raw が無ければ現在値から補完（カンマ除去・数値判定）
        let raw = (el.dataset && typeof el.dataset.serverRaw === 'string') ? el.dataset.serverRaw : '';
        if (!raw) {
          const s = (el.value ?? '').toString().replace(/,/g, '').trim();
          if (s !== '' && /^-?\d+$/.test(s)) raw = s;
        }
        // 表示側（raw が無いなら現状維持にする）
        if (raw) el.value = formatComma(raw);
        // hidden 側も同期
        const hidden = ensureHiddenFor(name);
        if (hidden) hidden.value = raw || '';
      });
    };
    // writeInt を利用する箇所が多いため、ロック済みは上書きしないガードを追加
    const writeInt = (name, value) => {
      const input = getInput(name);
      if (input) {
        if (input.dataset && input.dataset.serverLock === '1') {
          // ロック済みはサーバ値を優先
          return;
        }
        input.value = formatComma(value);
      }
    };
    const makeReadonlyNumber = (name) => {
      const el = getInput(name);
      if (el) {
        el.readOnly = true;
        el.classList.add('bg-light', 'text-end');
        // typeはtextのまま（カンマ許容）
      }
    };
    const makeEditableNumber = (name) => {
      const el = getInput(name);
      if (el) {
        el.readOnly = false;
        el.classList.remove('bg-light');
      }
    };

    // 令和6年度分特別税額控除（tax_tokubetsu_R6）の入力可否
    // - 住民税側は常に「－」(入力不可)
    // - 所得税側は targetYear === 2024 のセルのみ入力可
    //   targetYear = (period==='prev') ? (kihu_year-1) : kihu_year
    const isEditableTokubetsuR6 = (tax, period) => {
      if (tax !== 'shotoku') return false;
      const targetYear = (period === 'prev') ? (kihuYear - 1) : kihuYear;
      return targetYear === 2024;
    };
    function writeDashWithHidden(name) {
      const form = document.getElementById('furusato-input-form');
      if (!form) return;

      const hiddenList = Array.from(form.querySelectorAll(`input[type="hidden"][name="${name}"]`));
      let hidden = hiddenList.shift() || null;
      hiddenList.forEach((node) => node.remove());
      if (!hidden) {
        hidden = document.createElement('input');
        hidden.type = 'hidden';
        hidden.name = name;
        form.appendChild(hidden);
      }
      hidden.value = '';

      const finalizeDisplay = (el) => {
        if (!el) return;
        el.removeAttribute('name');
        el.removeAttribute('data-dash-original-name');
        el.dataset.dashName = name;
        el.type = 'text';
        el.value = '－';
        el.readOnly = true;
        el.classList.add('bg-light', 'text-center');
        el.classList.remove('text-end');
        if (!el.classList.contains('form-control')) el.classList.add('form-control');
        if (!el.classList.contains('suji11s')) el.classList.add('suji11s');
        if (hidden) {
          hidden.value = '';
          hidden.remove();
          el.insertAdjacentElement('afterend', hidden);
        }
      };

      let display = null;

      Array.from(form.querySelectorAll(`input[type="text"][data-dash-original-name="${name}"]`)).forEach((el) => {
        if (!display) {
          display = el;
        } else {
          el.remove();
        }
      });

      Array.from(form.querySelectorAll(`input[type="text"][data-dash-name="${name}"]`)).forEach((el) => {
        if (!display) {
          display = el;
        } else if (el !== display) {
          el.remove();
        }
      });

      if (!display) {
        const source = form.querySelector(`input[name="${name}"]`);
        if (source) {
          display = source;
        }
      }

      if (!display) {
        const fallbackCandidates = hidden.parentNode
          ? Array.from(hidden.parentNode.querySelectorAll('input[type="text"].bg-light.text-center'))
            .filter((el) => el.value === '－' && !el.name)
          : [];
        if (fallbackCandidates.length > 0) {
          display = fallbackCandidates.shift();
          fallbackCandidates.forEach((el) => el.remove());
        }
      }

      if (!display) {
        display = document.createElement('input');
        display.type = 'text';
        display.readOnly = true;
        display.classList.add('bg-light', 'text-center', 'form-control', 'form-control-sm');
        if (hidden.parentNode) {
          hidden.parentNode.insertBefore(display, hidden);
        } else {
          form.appendChild(display);
        }
      }

      finalizeDisplay(display);
    }

    function restoreNumberInput(name, value) {
      const form = document.getElementById('furusato-input-form');
      if (!form) return;

      const dashDisplays = Array.from(form.querySelectorAll(`input[type="text"][data-dash-name="${name}"]`));
      const legacyDisplays = Array.from(form.querySelectorAll(`input[type="text"][data-dash-original-name="${name}"]`));
      let number = form.querySelector(`input[name="${name}"]`);

      const primaryDisplay = dashDisplays[0] || legacyDisplays[0] || null;

      dashDisplays.forEach((el) => {
        if (el !== primaryDisplay) {
          el.remove();
        }
      });
      legacyDisplays.forEach((el) => {
        if (el !== primaryDisplay) {
          el.remove();
        }
      });

      if (!number && primaryDisplay) {
        number = primaryDisplay;
      }

      if (number) {
        number.name = name;
        number.type = 'text';
        number.readOnly = true;
        number.classList.add('bg-light', 'text-end');
        number.classList.remove('text-center');
        number.removeAttribute('data-dash-name');
        number.removeAttribute('data-dash-original-name');
        number.value = formatComma(value);
      }

      if (primaryDisplay && primaryDisplay !== number) {
        primaryDisplay.remove();
      }

      Array.from(form.querySelectorAll(`input[type="hidden"][name="${name}"]`)).forEach((node) => node.remove());
    }
 
    // 第三表のセルを「－」表示にし、POST 対象から外すためのヘルパー
    function dashifyInput(el) {
      if (!el) return;
      if (el.dataset && el.dataset.bunriDashed === '1') return;

      if (el.dataset) {
        el.dataset.bunriDashed = '1';
        // server-lock されているセルは enforceServerLocks の対象から外す
        if (el.dataset.serverLock === '1') {
          el.dataset.serverLockDashed = '1';
        }
      }

      el.value = '－';
      el.readOnly = true;
      el.classList.add('bg-light', 'text-center');
      el.classList.remove('text-end');
      if (el.name) {
        el.removeAttribute('name');
      }
    }

    // 第三表 1 セル（1 期間分の td）を「－」にし、name を外す
    function dashifyBunriCellForPeriod(td, period) {
      if (!td) return;
      const suffix = `_${period}`;

      // まず、このセルにぶら下がる name 付き input（text/hidden）を処理
      const namedInputs = td.querySelectorAll(`input[name$="${suffix}"]`);
      namedInputs.forEach((input) => {
        if (input.type === 'hidden') {
          // hidden は name を外すだけ（POST 抑止）
          input.removeAttribute('name');
          return;
        }
        // text 等は「－」表示にして name を外す
        dashifyInput(input);
      });

      // 集約表示専用の text（name を持たない display 用）も「－」にする
      const displayTexts = td.querySelectorAll('input[type="text"]');
      displayTexts.forEach((input) => {
        // すでに上のループで処理したもの（name を持っていて suffix が付くもの）はスキップ
        if (input.name && input.name.endsWith(suffix)) return;
        dashifyInput(input);
      });
    }

    // 分離なし年の第三表列（prev/curr）をまるごと「－」にする
    const dashBunriColumnsForDisabledPeriods = () => {
      const pane = document.getElementById('pane-bunri');
      if (!pane) return;
      ['prev', 'curr'].forEach((period) => {
        // 分離課税を採用している年はそのまま
        if (bunriFlags[period]) return;
        const suffix = `_${period}`;
        // 当該 period の name を持つ input から、その列の td を特定
        const inputs = pane.querySelectorAll(`input[name$="${suffix}"]`);
        const tds = new Set();
        inputs.forEach((el) => {
          const td = el.closest('td');
          if (td) tds.add(td);
        });
        tds.forEach((td) => dashifyBunriCellForPeriod(td, period));
      });
    };

    // 山林の「所得金額（表示）」は“損益通算後の最終値（result_details の shotoku_sanrin_*）”をミラーする
    function mirrorSanrinShotokuDisplay() {
      ['prev', 'curr'].forEach((period) => {
        const finalSanrin = readInt(`shotoku_sanrin_${period}`); // = after_3 と同値が供給される前提
        // 表示用（分離・所得金額）にそのまま反映（丸めなし）
        writeInt(`bunri_shotoku_sanrin_shotoku_${period}`, finalSanrin);
        writeInt(`bunri_shotoku_sanrin_jumin_${period}`,  finalSanrin);
        makeReadonlyNumber(`bunri_shotoku_sanrin_shotoku_${period}`);
        makeReadonlyNumber(`bunri_shotoku_sanrin_jumin_${period}`);
      });
    }

    const floorToThousands = (x) => {
      const n = Math.trunc(Number(x) || 0);
      if (n === 0) return 0;
      return n >= 0 ? Math.floor(n / 1000) * 1000 : -Math.ceil(Math.abs(n) / 1000) * 1000;
    };

    const floorToThousandsSigned = (x) => {
      const n = Number.isFinite(x) ? Math.trunc(x) : 0;
      if (n === 0) return 0;
      return n > 0 ? Math.floor(n / 1000) * 1000 : -Math.ceil(Math.abs(n) / 1000) * 1000;
    };

    const addReadonlyBg = (name) => {
      const input = getInput(name);
      if (input) {
        input.readOnly = true;
        input.classList.add('bg-light');
      }
    };

    const dashify = (name) => {
      const num = getInput(name);
      if (!num) return;
      if (num.dataset && num.dataset.dashed === '1') return;
      num.type = 'hidden';
      num.value = '';
      num.dataset.dashed = '1';
      const disp = document.createElement('input');
      disp.type = 'text';
      disp.readOnly = true;
      disp.className = 'form-control suji11s text-center bg-light';
      disp.value = '－';
      num.insertAdjacentElement('afterend', disp);
    };

    const undashifySetNumber = (name, value) => {
      const num = getInput(name);
      if (!num) return;
      const next = num.nextElementSibling;
      if (num.dataset && num.dataset.dashed === '1') {
        if (next && next.readOnly && next.value === '－') next.remove();
        num.dataset.dashed = '0';
      }
      num.type = 'text';
      if (num.dataset) {
        num.dataset.dashed = '0';
      }
      num.readOnly = true;
      num.classList.add('bg-light');
      num.value = formatComma(value);
    };

    const resolveKojoFieldName = (base, tax, period) => {
      const override = kojoFieldOverrides[base]?.[tax];
      if (override) {
        return `${override}${period}`;
      }

      return `${base}_${tax}_${period}`;
    };

    const findShotokuRateBand = (amount) => {
      const taxable = Math.max(0, Math.trunc(Number(amount) || 0));
      let fallback = null;
      let fallbackLower = Number.MAX_SAFE_INTEGER;
      for (const rate of shotokuRates) {
        const lower = Number(rate.lower ?? 0);
        const upper = rate.upper === null || rate.upper === undefined ? null : Number(rate.upper);
        if (lower < fallbackLower) {
          fallbackLower = lower;
          fallback = rate;
        }
        if (taxable < lower) {
          continue;
        }
        if (upper !== null && taxable > upper) {
          continue;
        }
        return rate;
      }
      return fallback;
    };

    const calcShotokuTaxByBand = (amount) => {
      const taxable = Math.max(0, Math.trunc(Number(amount) || 0));
      if (taxable <= 0) {
        return 0;
      }
      const matched = findShotokuRateBand(taxable);
      if (!matched) {
        return 0;
      }
      const rateDecimal = Number(matched.rate ?? 0) / 100;
      const deduction = Math.trunc(Number(matched.deduction_amount ?? 0));
      const raw = taxable * rateDecimal - deduction;
      const floored = Math.trunc(raw);
      return Number.isFinite(floored) ? floored : 0;
    };

    const calculateShotokuTax = (amount) => calcShotokuTaxByBand(amount);

    const recalcShotokuTaxFromMaster = () => {
      ['prev', 'curr'].forEach((period) => {
        if (!bunriFlags[period]) return;
        
        const taxable = readInt(`tb_sogo_shotoku_${period}`);
        const tax = calculateShotokuTax(taxable);
        const field = `bunri_zeigaku_sogo_shotoku_${period}`;
        writeInt(field, tax);
        makeReadonlyNumber(field);
      });
    };

    const recalcBunriZeigakuShotokuAll = () => {
      ['prev', 'curr'].forEach((period) => {
      
        if (!bunriFlags[period]) return;
        const sanTaxable = readInt(`tb_sanrin_shotoku_${period}`);
        const san = sanTaxable <= 0 ? 0 : calcShotokuTaxByBand(sanTaxable / 5) * 5;
        writeInt(`bunri_zeigaku_sanrin_shotoku_${period}`, san);
        makeReadonlyNumber(`bunri_zeigaku_sanrin_shotoku_${period}`);

        const taTaxable = readInt(`tb_taishoku_shotoku_${period}`);
        const ta = taTaxable <= 0 ? 0 : calcShotokuTaxByBand(taTaxable);
        writeInt(`bunri_zeigaku_taishoku_shotoku_${period}`, ta);
        makeReadonlyNumber(`bunri_zeigaku_taishoku_shotoku_${period}`);

        const tIppan = readInt(`bunri_shotoku_tanki_ippan_shotoku_${period}`);
        const tKeigen = readInt(`bunri_shotoku_tanki_keigen_shotoku_${period}`);
        writeInt(`bunri_zeigaku_tanki_shotoku_${period}`, Math.trunc(tIppan * 0.30 + tKeigen * 0.15));
        makeReadonlyNumber(`bunri_zeigaku_tanki_shotoku_${period}`);

        const cIppan = readInt(`bunri_shotoku_choki_ippan_shotoku_${period}`);
        const cTokutei = readInt(`bunri_shotoku_choki_tokutei_shotoku_${period}`);
        const cKeika = readInt(`bunri_shotoku_choki_keika_shotoku_${period}`);
        const tokuteiTax = cTokutei <= 20_000_000
          ? Math.trunc(cTokutei * 0.10)
          : Math.trunc((cTokutei - 20_000_000) * 0.15 + 2_000_000);
        const keikaTax = cKeika <= 60_000_000
          ? Math.trunc(cKeika * 0.10)
          : Math.trunc((cKeika - 60_000_000) * 0.15 + 6_000_000);
        writeInt(
          `bunri_zeigaku_choki_shotoku_${period}`,
          Math.trunc(cIppan * 0.15 + tokuteiTax + keikaTax),
        );
        makeReadonlyNumber(`bunri_zeigaku_choki_shotoku_${period}`);

        const jotoTaxable =
          readInt(`tb_ippan_kabuteki_joto_shotoku_${period}`) +
          readInt(`tb_jojo_kabuteki_joto_shotoku_${period}`);
        writeInt(`bunri_zeigaku_joto_shotoku_${period}`, Math.trunc(jotoTaxable * 0.15));
        makeReadonlyNumber(`bunri_zeigaku_joto_shotoku_${period}`);

        const haitoTaxable = readInt(`tb_jojo_kabuteki_haito_shotoku_${period}`);
        writeInt(`bunri_zeigaku_haito_shotoku_${period}`, Math.trunc(haitoTaxable * 0.15));
        makeReadonlyNumber(`bunri_zeigaku_haito_shotoku_${period}`);

        const sakiTaxable = readInt(`tb_sakimono_shotoku_${period}`);
        writeInt(`bunri_zeigaku_sakimono_shotoku_${period}`, Math.trunc(sakiTaxable * 0.15));
        makeReadonlyNumber(`bunri_zeigaku_sakimono_shotoku_${period}`);
      });
    };

    const recalcBunriZeigakuJuminAll = () => {
      ['prev', 'curr'].forEach((period) => {
        if (!bunriFlags[period]) return;
        writeInt(
          `bunri_zeigaku_sogo_jumin_${period}`,
          Math.trunc(readInt(`tb_sogo_jumin_${period}`) * 0.10),
        );
        makeReadonlyNumber(`bunri_zeigaku_sogo_jumin_${period}`);

        const tIppan = readInt(`bunri_shotoku_tanki_ippan_jumin_${period}`);
        const tKeigen = readInt(`bunri_shotoku_tanki_keigen_jumin_${period}`);
        writeInt(
          `bunri_zeigaku_tanki_jumin_${period}`,
          Math.trunc(tIppan * 0.09 + tKeigen * 0.05),
        );
        makeReadonlyNumber(`bunri_zeigaku_tanki_jumin_${period}`);

        const cIppan = readInt(`bunri_shotoku_choki_ippan_jumin_${period}`);
        const cTokutei = readInt(`bunri_shotoku_choki_tokutei_jumin_${period}`);
        const cKeika = readInt(`bunri_shotoku_choki_keika_jumin_${period}`);
        const tokuteiTax = Math.trunc(
          Math.min(20_000_000, cTokutei) * 0.04 + Math.max(0, cTokutei - 20_000_000) * 0.05,
        );
        const keikaTax = Math.trunc(
          Math.min(60_000_000, cKeika) * 0.04 + Math.max(0, cKeika - 60_000_000) * 0.05,
        );
        writeInt(
          `bunri_zeigaku_choki_jumin_${period}`,
          Math.trunc(cIppan * 0.05 + tokuteiTax + keikaTax),
        );
        makeReadonlyNumber(`bunri_zeigaku_choki_jumin_${period}`);

        writeInt(
          `bunri_zeigaku_joto_jumin_${period}`,
          Math.trunc((readInt(`tb_ippan_kabuteki_joto_jumin_${period}`) + readInt(`tb_jojo_kabuteki_joto_jumin_${period}`)) * 0.05),
        );
        makeReadonlyNumber(`bunri_zeigaku_joto_jumin_${period}`);

        writeInt(
          `bunri_zeigaku_haito_jumin_${period}`,
          Math.trunc(readInt(`tb_jojo_kabuteki_haito_jumin_${period}`) * 0.05),
        );
        makeReadonlyNumber(`bunri_zeigaku_haito_jumin_${period}`);

        writeInt(
          `bunri_zeigaku_sakimono_jumin_${period}`,
          Math.trunc(readInt(`tb_sakimono_jumin_${period}`) * 0.05),
        );
        makeReadonlyNumber(`bunri_zeigaku_sakimono_jumin_${period}`);

        writeInt(
          `bunri_zeigaku_sanrin_jumin_${period}`,
          Math.trunc(readInt(`tb_sanrin_jumin_${period}`) * 0.10),
        );
        makeReadonlyNumber(`bunri_zeigaku_sanrin_jumin_${period}`);

        writeInt(
          `bunri_zeigaku_taishoku_jumin_${period}`,
          Math.trunc(readInt(`tb_taishoku_jumin_${period}`) * 0.10),
        );
        makeReadonlyNumber(`bunri_zeigaku_taishoku_jumin_${period}`);
      });
    };

    const recalcZeigakuGokeiAll = () => {
      ['prev', 'curr'].forEach((period) => {
        ['shotoku', 'jumin'].forEach((tax) => {
          const names = [
            `bunri_zeigaku_sogo_${tax}_${period}`,
            `bunri_zeigaku_tanki_${tax}_${period}`,
            `bunri_zeigaku_choki_${tax}_${period}`,
            `bunri_zeigaku_joto_${tax}_${period}`,
            `bunri_zeigaku_haito_${tax}_${period}`,
            `bunri_zeigaku_sakimono_${tax}_${period}`,
            `bunri_zeigaku_sanrin_${tax}_${period}`,
            `bunri_zeigaku_taishoku_${tax}_${period}`,
          ];
          const sum = names.reduce((acc, name) => acc + readInt(name), 0);
          const field = `bunri_zeigaku_gokei_${tax}_${period}`;
          writeInt(field, sum);
          makeReadonlyNumber(field);
        });
      });
    };

    const recalcKojo = () => {
      taxTypes.forEach((tax) => {
        periods.forEach((period) => {
          let shokei = 0;
          kojoShokeiBases.forEach((base) => {
            shokei += readInt(resolveKojoFieldName(base, tax, period));
          });
          writeInt(`kojo_shokei_${tax}_${period}`, shokei);

          let gokei = shokei;
          kojoGokeiExtras.forEach((base) => {
            gokei += readInt(resolveKojoFieldName(base, tax, period));
          });
          writeInt(`kojo_gokei_${tax}_${period}`, gokei);
        });
      });
    };

    const recalcTaxableSogo = () => {
      // tb_sogo_* は「1セル＝表示用input(js-comma)＋hidden(name付き)」を前提とし、
      //   ・内部(hidden) には常に計算値を保持
//      //   ・分離あり年度は表示だけ「－」にする
      ['prev', 'curr'].forEach((period) => {
        ['shotoku', 'jumin'].forEach((tax) => {
          const name   = `tb_sogo_${tax}_${period}`;        // hidden 側 name
          const hidden = getInput(name);
          const td     = hidden ? hidden.closest('td') : null;
          const disp   = td
            ? (td.querySelector('input.js-comma') || td.querySelector('input[type="text"]'))
            : null;

          if (!hidden || !disp) {
            return;
          }

          const g = readInt(`shotoku_gokei_${tax}_${period}`);
          const k = readInt(`kojo_gokei_${tax}_${period}`);
          const raw = g - k;
          // 下限0 → 千円未満切捨て
          const floored = floorToThousands(Math.max(0, raw));
          hidden.value = String(floored);

//          if (bunriFlags[period]) {
//            // 分離課税あり：内部値は保持しつつ、表示だけ「－」
//            disp.value = '－';
//          } else {
//            disp.value = formatComma(floored);
//          }
          // ▼ 第三表タブ統合：常に数値表示（dash 表示は撤去）
          disp.value = formatComma(floored);
        });
      });
    };

    const recalcBunriSogoMirror = () => {
      ['prev', 'curr'].forEach((period) => {
        ['shotoku', 'jumin'].forEach((tax) => {
          const v = readInt(`shotoku_gokei_${tax}_${period}`);
          writeInt(`bunri_sogo_gokeigaku_${tax}_${period}`, v);
          const el = getInput(`bunri_sogo_gokeigaku_${tax}_${period}`);
          if (el) { el.readOnly = true; el.classList.add('bg-light'); }
        });
      });
    };

    const recalcBunriSashihikiGokei = () => {
      ['prev', 'curr'].forEach((period) => {
        if (!bunriFlags[period]) return;
        let a = readInt(`kojo_gokei_shotoku_${period}`);
        let b = readInt(`bunri_sogo_gokeigaku_shotoku_${period}`);
        let v = Math.min(a, b);
        writeInt(`bunri_sashihiki_gokei_shotoku_${period}`, v);
        let el = getInput(`bunri_sashihiki_gokei_shotoku_${period}`);
        if (el) { el.readOnly = true; el.classList.add('bg-light'); }

        a = readInt(`kojo_gokei_jumin_${period}`);
        b = readInt(`bunri_sogo_gokeigaku_jumin_${period}`);
        v = Math.min(a, b);
        writeInt(`bunri_sashihiki_gokei_jumin_${period}`, v);
        el = getInput(`bunri_sashihiki_gokei_jumin_${period}`);
        if (el) { el.readOnly = true; el.classList.add('bg-light'); }
      });
    };

    const recalcBunriKazeishotokuSogo = () => {
      ['prev', 'curr'].forEach((period) => {
        let sogo = readInt(`bunri_sogo_gokeigaku_shotoku_${period}`);
        let sashihiki = readInt(`bunri_sashihiki_gokei_shotoku_${period}`);
        // 下限0 → 千円未満切捨て
        let v = floorToThousands(Math.max(0, sogo - sashihiki));

        sogo = readInt(`bunri_sogo_gokeigaku_jumin_${period}`);
        sashihiki = readInt(`bunri_sashihiki_gokei_jumin_${period}`);
        // 下限0 → 千円未満切捨て
        v = floorToThousands(Math.max(0, sogo - sashihiki));
      });
    };

    const recalcBunriKazeishotokuGroup = () => {
      periods.forEach((period) => {
        if (!bunriFlags[period]) return;
        const calcForTax = (tax) => {
          const read = (base) => readInt(`${base}_${tax}_${period}`);
          const write = (base, value) => {
            const name = `${base}_${tax}_${period}`;
            writeInt(name, value);
            addReadonlyBg(name);
          };

          // ① 分離・カテゴリ別 原始額を読込
          const tanki = read('bunri_shotoku_tanki_ippan') + read('bunri_shotoku_tanki_keigen');
          const choki = read('bunri_shotoku_choki_ippan') + read('bunri_shotoku_choki_tokutei') + read('bunri_shotoku_choki_keika');
          const kabuIppan = read('bunri_shotoku_ippan_kabuteki_joto');
          const kabuJojo  = read('bunri_shotoku_jojo_kabuteki_joto');
          const haito     = read('bunri_shotoku_jojo_kabuteki_haito');
          const sakimono  = read('bunri_shotoku_sakimono');
          const sanrinA   = read('bunri_shotoku_sanrin');     // 山林
          const taishokuA = read('bunri_shotoku_taishoku');   // 退職

          if (tax === 'shotoku') {
            // 所得税の控除配賦順：総合 → 山林 → 退職（千円切捨ては課税所得金額の確定時のみ）
            // 総合に充当した後の控除残
            const kojoTotal = read('kojo_gokei');
            const sogoTotal = read('bunri_sogo_gokeigaku');
            const residualAfterSogo = Math.max(0, kojoTotal - sogoTotal);

            // 山林（after_3 と同値の表示値 sanrinA）にまず充当
            const sanrinConsumed = Math.min(residualAfterSogo, Math.max(0, sanrinA));
            const sanrinTaxable  = Math.max(0, sanrinA - residualAfterSogo);

            // 残りを退職へ
            const residualAfterSanrin = Math.max(0, residualAfterSogo - sanrinConsumed);
            const taishokuTaxable     = Math.max(0, taishokuA - residualAfterSanrin);
            return;
          }

          // ② 住民税：控除残（総合に当てた後の残り）を分離内へ順序配賦
          //    順序：短期 → 長期 → 配当 → 一般株式譲渡 → 上場株式譲渡 → 先物 → 山林 → 退職
          let residual = Math.max(0, read('kojo_gokei') - read('bunri_sogo_gokeigaku'));
          const consume = (amt) => {
            const use = Math.min(Math.max(0, amt), residual);
            residual -= use;
            return amt - use;
          };

          // 分離内の各カテゴリーに順次ぶつける
          const tankiAdj   = consume(tanki);
          const chokiAdj   = consume(choki);
          const haitoAdj   = consume(haito);
          const kabuIppAdj = consume(kabuIppan);
          const kabuJjAdj  = consume(kabuJojo);
          const sakiAdj    = consume(sakimono);
          const sanrinAdj  = consume(sanrinA);
          const taishokuAdj= consume(taishokuA);
        };

        taxTypes.forEach(calcForTax);
      });
    };

    const recalcTaxPipeline = () => {
      periods.forEach((period) => {
        // 税額（tax_zeigaku_* / bunri_zeigaku_gokei_*）はサーバ値を維持（readonly化のみ）
        ['shotoku','jumin'].forEach((tax) => {
          makeReadonlyNumber(`tax_zeigaku_${tax}_${period}`);
          makeReadonlyNumber(`tax_jutaku_${tax}_${period}`);
          makeReadonlyNumber(`tax_seito_${tax}_${period}`);
          // 令和6年度分特別税額控除：条件を満たすセルだけ入力可（readonlyにしない）
          if (isEditableTokubetsuR6(tax, period)) {
            makeEditableNumber(`tax_tokubetsu_R6_${tax}_${period}`);
          } else {
            makeReadonlyNumber(`tax_tokubetsu_R6_${tax}_${period}`);
          }
        });

        // ▼ 基準税額（UIの見せ方）：税額合計 −（各控除）
        //   ※「差引所得税額」行は廃止するため、tax_sashihiki_* に依存しない
        const baseShotoku =
          (readInt(`bunri_zeigaku_gokei_shotoku_${period}`) || 0) ||
          (readInt(`tax_zeigaku_shotoku_${period}`) || 0);
        const baseJumin =
          (readInt(`bunri_zeigaku_gokei_jumin_${period}`) || 0) ||
          (readInt(`tax_zeigaku_jumin_${period}`) || 0);

        // 所得税：配当控除（入力）＋住宅ローン控除（SoT）＋政党等（SoT=税額控除合計）＋災害減免（入力）＋令和6特別（入力/ダッシュ）
        const creditShotoku =
          Math.max(0, readInt(`tax_haito_shotoku_${period}`)) +
          Math.max(0, readInt(`tax_jutaku_shotoku_${period}`)) +
          Math.max(0, readInt(`tax_seito_shotoku_${period}`)) +
          Math.max(0, readInt(`tax_kaisyu_shotoku_${period}`)) +
          Math.max(0, readInt(`tax_saigai_genmen_shotoku_${period}`)) +
          Math.max(0, readInt(`tax_tokubetsu_R6_shotoku_${period}`));
        // ▼ 百円未満切捨てはしない（差引後の値をそのまま）
        const kijunShotokuRaw = Math.max(0, baseShotoku - creditShotoku);
        const kijunShotoku = Math.trunc(kijunShotokuRaw);
        writeInt(`tax_kijun_shotoku_${period}`, kijunShotoku);
        makeReadonlyNumber(`tax_kijun_shotoku_${period}`);

        // 住民税：調整控除（UI固定SoT）＋配当控除（入力）＋住宅ローン控除（SoT）＋寄附金税額控除（UI固定SoT）＋災害減免（入力）
        const creditJumin =
          Math.max(0, uiFixedCredits.chosei[period] || 0) +
          Math.max(0, readInt(`tax_haito_jumin_${period}`)) +
          Math.max(0, readInt(`tax_jutaku_jumin_${period}`)) +
          Math.max(0, uiFixedCredits.juminKifukinZeigaku[period] || 0) +
          Math.max(0, readInt(`tax_saigai_genmen_jumin_${period}`));
        // ▼ 百円未満切捨てはしない（差引後の値をそのまま）
        const kijunJuminRaw = Math.max(0, baseJumin - creditJumin);
        const kijunJumin = Math.trunc(kijunJuminRaw);
        // ▼ 住民税：基準（所得割残）を反映
        writeInt(`tax_kijun_jumin_${period}`, kijunJumin);
        makeReadonlyNumber(`tax_kijun_jumin_${period}`);

        // ▼ 復興特別所得税額は「1円単位」表示（100円未満切捨てしない）
        //    ※基準所得税額（kijunShotoku）は100円単位に寄せた後の値を使う
        const fukkouShotokuName = `tax_fukkou_shotoku_${period}`;
        const fukkouRaw = Math.trunc(kijunShotoku * 0.021); // 1円単位
        writeInt(fukkouShotokuName, Math.max(0, fukkouRaw));
        makeReadonlyNumber(fukkouShotokuName);

        // 住民側の復興はダッシュ表示（既存仕様）
        writeDashWithHidden(`tax_fukkou_jumin_${period}`);

        const gokeiShotokuRaw =
          readInt(`tax_kijun_shotoku_${period}`) + readInt(`tax_fukkou_shotoku_${period}`);
        // ▼ 合計も百円未満切捨てはしない
        const gokeiShotoku = Math.trunc(gokeiShotokuRaw);
        writeInt(`tax_gokei_shotoku_${period}`, gokeiShotoku);
        makeReadonlyNumber(`tax_gokei_shotoku_${period}`);

        // 住民税合計も切捨てしない
        writeInt(`tax_gokei_jumin_${period}`, kijunJumin);
        makeReadonlyNumber(`tax_gokei_jumin_${period}`);
      });
    };

    const runFullRecalcChain = () => {
      recalcKojo();
      recalcTaxableSogo();
      recalcBunriSogoMirror();
      mirrorSanrinShotokuDisplay();
      recalcBunriSashihikiGokei();
      recalcBunriKazeishotokuSogo();
      recalcShotokuTaxFromMaster();
      // bunri_zeigaku_* / bunri_zeigaku_gokei_* はサーバ計算専用に変更
      // recalcBunriZeigakuShotokuAll();
      // recalcBunriZeigakuJuminAll();
      // recalcZeigakuGokeiAll();
      recalcTaxPipeline();
      recalcBunriKazeishotokuGroup();
      enforceServerLocks();
      dashBunriColumnsForDisabledPeriods();
    };

    // blur 時に所得税→住民税へ値をコピーするヘルパ
    const mirrorOnBlur = (srcName, dstName) => {
      const src = getInput(srcName);
      const dst = getInput(dstName);
      if (!src || !dst) return;
      src.addEventListener('blur', () => {
        dst.value = src.value;
        // 下流の再計算に効くようにイベントも発火
        dst.dispatchEvent(new Event('input',  { bubbles: true }));
        dst.dispatchEvent(new Event('change', { bubbles: true }));
        runFullRecalcChain();
      });
    };

    const registerTaxPipelineBlur = (name) => {
      const input = getInput(name);
      if (input) {
        input.addEventListener('blur', recalcTaxPipeline);
      }
    };

    periods.forEach((period) => {
      taxTypes.forEach((tax) => {
        [
          `tb_sogo_${tax}_${period}`,
          `bunri_zeigaku_gokei_${tax}_${period}`,
          `tax_zeigaku_${tax}_${period}`,
          `tax_haito_${tax}_${period}`,
          `tax_jutaku_${tax}_${period}`,
          `tax_saigai_genmen_${tax}_${period}`,
        ].forEach(registerTaxPipelineBlur);
      });

      registerTaxPipelineBlur(`shotokuzei_zeigakukojo_seitoto_tokubetsu_${period}`);
      registerTaxPipelineBlur(`juminzei_zeigakukojo_seitoto_tokubetsu_${period}`);
      registerTaxPipelineBlur(`tax_tokubetsu_R6_shotoku_${period}`);
      registerTaxPipelineBlur(`tax_kaisyu_shotoku_${period}`);
    });

    const kojoBasesForEvents = kojoShokeiBases.concat(kojoGokeiExtras);
    kojoBasesForEvents.forEach((base) => {
      taxTypes.forEach((tax) => {
        periods.forEach((period) => {
          const input = getInput(resolveKojoFieldName(base, tax, period));
          if (input) {
            input.addEventListener('blur', runFullRecalcChain);
          }
        });
      });
    });

    ['shotoku_gokei', 'kojo_gokei', 'bunri_sogo_gokeigaku'].forEach((base) => {
      taxTypes.forEach((tax) => {
        periods.forEach((period) => {
          const name = `${base}_${tax}_${period}`;
          const input = getInput(name);
          if (input) {
            input.addEventListener('blur', runFullRecalcChain);
          }
        });
      });
    });

    const bunriKazeishotokuBases = [
      'bunri_shotoku_tanki_ippan',
      'bunri_shotoku_tanki_keigen',
      'bunri_shotoku_choki_ippan',
      'bunri_shotoku_choki_tokutei',
      'bunri_shotoku_choki_keika',
      'bunri_shotoku_ippan_kabuteki_joto',
      'bunri_shotoku_jojo_kabuteki_joto',
      'bunri_shotoku_jojo_kabuteki_haito',
      'bunri_shotoku_sakimono',
      'bunri_shotoku_sanrin',
      'bunri_shotoku_taishoku',
    ];

    bunriKazeishotokuBases.forEach((base) => {
      taxTypes.forEach((tax) => {
        periods.forEach((period) => {
          const name = `${base}_${tax}_${period}`;
          const input = getInput(name);
          if (input) {
            input.addEventListener('blur', runFullRecalcChain);
          }
        });
      });
    });

    ['prev', 'curr'].forEach((period) => {
      [
      ].forEach((name) => {
        const input = getInput(name);
        if (input) {
          input.addEventListener('blur', runFullRecalcChain);
        }
      });
    });

    ['prev', 'curr'].forEach((period) => {
      [
      ].forEach((name) => {
        const input = getInput(name);
        if (input) {
          input.addEventListener('blur', runFullRecalcChain);
        }
      });
    });

    runFullRecalcChain();
    // ▼ bunri_zeigaku_* はサーバSoTに統一済みのため、JSで再計算しない（混入源を潰す）
    // recalcBunriZeigakuJuminAll();
    // recalcZeigakuGokeiAll();
    recalcTaxPipeline();
    dashBunriColumnsForDisabledPeriods();
    // ============================
    // 再計算時・初期表示時にも
    // 「給与」「公的年金等」の所得税→住民税を強制同期
    // （フォーカス→blurをしなくても表示されるように）
    // ============================
    const bulkMirrorNow = (pairs) => {
      pairs.forEach(([srcName, dstName]) => {
        const src = getInput(srcName);
        const dst = getInput(dstName);
        if (!src || !dst) return;
        dst.value = src.value;
        // 下流の合計・税額などが拾えるよう発火
        dst.dispatchEvent(new Event('input',  { bubbles: true }));
        dst.dispatchEvent(new Event('change', { bubbles: true }));
        dst.dispatchEvent(new Event('blur',   { bubbles: true })); // 念のため
      });
    };

    // 同期対象（prev/curr 両方）
    const kyuyoNenkinShotokuPairs = [
      // 給与（所得金額）
      ['shotoku_kyuyo_shotoku_prev',        'shotoku_kyuyo_jumin_prev'],
      ['shotoku_kyuyo_shotoku_curr',        'shotoku_kyuyo_jumin_curr'],
      // 雑（公的年金等：所得金額）
      ['shotoku_zatsu_nenkin_shotoku_prev', 'shotoku_zatsu_nenkin_jumin_prev'],
      ['shotoku_zatsu_nenkin_shotoku_curr', 'shotoku_zatsu_nenkin_jumin_curr'],
      // 社保・小規模：所得税 → 住民税（読み取り専用でミラー）
      ['kojo_shakaihoken_shotoku_prev',     'kojo_shakaihoken_jumin_prev'],
      ['kojo_shakaihoken_shotoku_curr',     'kojo_shakaihoken_jumin_curr'],
      ['kojo_shokibo_shotoku_prev',         'kojo_shokibo_jumin_prev'],
      ['kojo_shokibo_shotoku_curr',         'kojo_shokibo_jumin_curr'],
    ];

    // 社会保険料／小規模共済（prev）のみを個別に同期したい場合のペア（※配列外で宣言）
    const kojoMirrorPrevPairs = [
      ['kojo_shakaihoken_shotoku_prev','kojo_shakaihoken_jumin_prev'],
      ['kojo_shokibo_shotoku_prev',    'kojo_shokibo_jumin_prev'],
    ];

    // 事業（農業）・利子・配当も「常に住民税側へミラー」する
    const jigyoNogyoShotokuPairs = [
      // 収入（事業・農業）
      ['syunyu_jigyo_nogyo_shotoku_prev',   'syunyu_jigyo_nogyo_jumin_prev'],
      ['syunyu_jigyo_nogyo_shotoku_curr',   'syunyu_jigyo_nogyo_jumin_curr'],
      // 所得（事業・農業）
      ['shotoku_jigyo_nogyo_shotoku_prev',  'shotoku_jigyo_nogyo_jumin_prev'],
      ['shotoku_jigyo_nogyo_shotoku_curr',  'shotoku_jigyo_nogyo_jumin_curr'],
    ];

    const rishiHaitoShotokuPairs = [
      // 利子：所得のみ（収入行は第一表にない）
      ['shotoku_rishi_shotoku_prev',        'shotoku_rishi_jumin_prev'],
      ['shotoku_rishi_shotoku_curr',        'shotoku_rishi_jumin_curr'],
      // 配当：収入＋所得
      ['syunyu_haito_shotoku_prev',         'syunyu_haito_jumin_prev'],
      ['syunyu_haito_shotoku_curr',         'syunyu_haito_jumin_curr'],
      ['shotoku_haito_shotoku_prev',        'shotoku_haito_jumin_prev'],
      ['shotoku_haito_shotoku_curr',        'shotoku_haito_jumin_curr'],
    ];

    // 1) 画面読み込み直後にも強制同期（サーバ再計算後の再描画でも反映）
    bulkMirrorNow(kyuyoNenkinShotokuPairs);
    bulkMirrorNow(kojoMirrorPrevPairs);
    bulkMirrorNow(jigyoNogyoShotokuPairs);
    bulkMirrorNow(rishiHaitoShotokuPairs);
    // 2) blur 時にも「所得税 → 住民税」へ自動コピー（給与・年金・農業・利子・配当）
    const mirrorPairsForBlur = [
      ...kyuyoNenkinShotokuPairs,
      ...jigyoNogyoShotokuPairs,
      ...rishiHaitoShotokuPairs,
    ];
    mirrorPairsForBlur.forEach(([srcName, dstName]) => mirrorOnBlur(srcName, dstName));

    // ロック再適用
    enforceServerLocks();
    // 社保・小規模＋農業・利子・配当の住民税側は常に読み取り専用で表示固定
    [
      'kojo_shakaihoken_jumin_prev','kojo_shakaihoken_jumin_curr',
      'kojo_shokibo_jumin_prev','kojo_shokibo_jumin_curr',
      'syunyu_jigyo_nogyo_jumin_prev','syunyu_jigyo_nogyo_jumin_curr',
      'shotoku_jigyo_nogyo_jumin_prev','shotoku_jigyo_nogyo_jumin_curr',
      'shotoku_rishi_jumin_prev','shotoku_rishi_jumin_curr',
      'syunyu_haito_jumin_prev','syunyu_haito_jumin_curr',
      'shotoku_haito_jumin_prev','shotoku_haito_jumin_curr',
    ].forEach(makeReadonlyNumber);

    // 2) 再計算ボタン押下直前にも強制同期してから送信
    const formEl = document.getElementById('furusato-input-form');

    // ============================
    // 入力値の変更検知 → 再計算ボタン表示制御
    // ============================
    (function setupRecalcButtonDirtyWatcher () {
      const recalcButton = document.getElementById('furusato-recalc-button');
      if (!formEl || !recalcButton) {
        return;
      }

      const normalizeForCompare = (v) => String(v ?? '').replace(/,/g, '').trim();

      // ユーザーが編集可能な input のみ監視
      const watchedInputs = Array.from(
        formEl.querySelectorAll('input[name]')
      ).filter((el) => {
        if (!(el instanceof HTMLInputElement)) return false;
        if (el.type !== 'text' && el.type !== 'number') return false;
        if (el.readOnly) return false;
        if (el.disabled) return false;
        if (el.dataset && el.dataset.serverLock === '1') return false;
        return true;
      });

      // ページ表示直後の値を「最新（サーバ計算済み）」として保持
      watchedInputs.forEach((el) => {
        el.dataset.initialValue = normalizeForCompare(el.value);
      });

      const updateRecalcVisibility = () => {
        const isDirty = watchedInputs.some((el) => {
          const initial = el.dataset.initialValue ?? '';
          const current = normalizeForCompare(el.value);
          return initial !== current;
        });
        if (isDirty) {
          recalcButton.classList.remove('d-none');
        } else {
          recalcButton.classList.add('d-none');
        }
      };

      // 初期状態：サーバ値＝最新とみなして非表示
      recalcButton.classList.add('d-none');

      // 値が変わったら dirty 判定を更新
      watchedInputs.forEach((el) => {
        ['input', 'change', 'blur'].forEach((ev) => {
          el.addEventListener(ev, updateRecalcVisibility);
        });
      });
    })();

    // ============================
    // ▼ PDF出力ボタン：押下時に pdf_prepare=1 を立てる
    //   - 現時点では再計算（recalc_all=1）を実行して input 再表示するだけ
    //   - 将来：Controller 側で pdf_prepare=1 を見て PDF 生成へ分岐できる
    // ============================
    (function setupPdfButtonFlag () {
      const form = document.getElementById('furusato-input-form');
      if (!form) return;
      const btn = document.getElementById('furusato-pdf-button');
      const flag = document.getElementById('furusato-pdf-prepare');
      if (!btn || !flag) return;

      const overlay = document.getElementById('furusato-pdf-overlay');
      const overlayDetail = document.getElementById('furusato-pdf-overlay-detail');
      const showOverlay = (msg) => {
        if (!overlay) return;
        if (overlayDetail && typeof msg === 'string' && msg !== '') {
          overlayDetail.textContent = msg;
        } else if (overlayDetail) {
          overlayDetail.textContent = '準備中…';
        }
        overlay.classList.add('is-active');
        overlay.setAttribute('aria-hidden', 'false');
      };
      const hideOverlay = () => {
        if (!overlay) return;
        overlay.classList.remove('is-active');
        overlay.setAttribute('aria-hidden', 'true');
      };

      // ▼ PDF出力：まずモーダルで条件選択 → hidden iframe でDL（popup blocker 回避）
      const modalEl = document.getElementById('furusato-pdf-mode-modal');
      const modal = (modalEl && typeof bootstrap !== 'undefined' && bootstrap.Modal)
        ? bootstrap.Modal.getOrCreateInstance(modalEl)
        : null;

      const buildUrlWithVariant = (baseUrl, variant) => {
        const u = new URL(baseUrl, window.location.origin);
        u.searchParams.set('pdf_variant', String(variant || 'max'));
        return u.toString();
      };

      const startDownloadFlow = async (variant) => {
        flag.value = '1';

        const baseStatusUrl = btn.getAttribute('data-status-url') || '';
        const baseDownloadUrl = btn.getAttribute('data-download-url') || '';
        if (!baseStatusUrl || !baseDownloadUrl) {
          alert('PDF出力URLが初期化されていません。画面をリロードしてください。');
          return;
        }
        const statusUrl = buildUrlWithVariant(baseStatusUrl, variant);
        const downloadUrl = buildUrlWithVariant(baseDownloadUrl, variant);

        const ensureDownloadFrame = () => {
          let frame = document.getElementById('furusato-pdf-download-frame');
          if (frame) return frame;
          frame = document.createElement('iframe');
          frame.id = 'furusato-pdf-download-frame';
          frame.style.display = 'none';
          frame.style.width = '0';
          frame.style.height = '0';
          frame.style.border = '0';
          document.body.appendChild(frame);
          return frame;
        };
        const buildDlToken = () => {
          const r = Math.random().toString(16).slice(2);
          return `${Date.now()}_${r}`;
        };
        const cookieNameFor = (token) => `pdf_dl_${String(token).replace(/[^a-zA-Z0-9_\-]/g,'')}`;
        const hasCookie = (name) => {
          return document.cookie.split(';').some((c) => c.trim().startsWith(name + '='));
        };
        const clearCookie = (name) => {
          // SameSite=Lax を合わせておく（無くても大抵消えるが保険）
          document.cookie = `${name}=; Max-Age=0; path=/; SameSite=Lax`;
        };
        const waitForDownloadStart = async (token, timeoutMs = 30000) => {
          const name = cookieNameFor(token);
          const t0 = Date.now();
          while (Date.now() - t0 < timeoutMs) {
            if (hasCookie(name)) {
              clearCookie(name);
              return true;
            }
            await sleep(120);
          }
          return false;
        };

        const triggerDownload = (url, token) => {
          const frame = ensureDownloadFrame();
          const u = new URL(url, window.location.origin);
          u.searchParams.set('_dl', String(Date.now())); // 同URLでも発火させる
          u.searchParams.set('dl_token', token);         // ★サーバへ token を渡す
          frame.src = u.toString();
        };

        const dlToken = buildDlToken();
        btn.setAttribute('aria-disabled', 'true');
        btn.style.pointerEvents = 'none';
        showOverlay('PDF生成状況を確認しています…');

        const sleep = (ms) => new Promise((r) => setTimeout(r, ms));
        const fetchStatus = async () => {
          try {
            const res = await fetch(statusUrl, {
              method: 'GET',
              headers: { 'Accept': 'application/json' },
              credentials: 'same-origin',
            });
            const json = await res.json().catch(() => ({}));
            return {
              st: String(json.status || ''),
              message: String(json.message || ''),
            };
          } catch (_err) {
            return { st: '', message: '' };
          }
        };

        try {
          // ログ上、初回10秒程度かかるので最大18秒待つ
          for (let i = 0; i < 30; i++) {
            const { st, message } = await fetchStatus();
            if (st === 'ready') {
              showOverlay('PDFのダウンロードを開始します…');
              triggerDownload(downloadUrl, dlToken);
              // ★「ダウンロード開始（=cookieが立つ）」まで待ってから閉じる
              await waitForDownloadStart(dlToken, 30000);
              return;
            }
            if (st === 'failed') {
              hideOverlay();
              alert('PDF生成に失敗しました。再計算後に再度お試しください。' + (message ? '\n' + message : ''));
              return;
            }
            showOverlay('PDFを生成中です…（しばらくお待ちください）');
            await sleep(600);
          }
          // タイムアウト：一応ダウンロードを試す（生成が間に合っていればDLされる）
          showOverlay('PDFのダウンロードを開始します…');
          triggerDownload(downloadUrl, dlToken);
          await waitForDownloadStart(dlToken, 30000);
        } finally {
          btn.style.pointerEvents = '';
          btn.removeAttribute('aria-disabled');
          hideOverlay();
        }
      };

      const blockModalEl = document.getElementById('furusato-one-stop-pdf-block-modal');
      const blockModal = (blockModalEl && typeof bootstrap !== 'undefined' && bootstrap.Modal)
        ? bootstrap.Modal.getOrCreateInstance(blockModalEl)
        : null;

      btn.addEventListener('click', async (e) => {
        e.preventDefault();
        e.stopPropagation();

        const blocked = btn.getAttribute('data-one-stop-pdf-blocked') === '1';
        if (blocked) {
          const msg = btn.getAttribute('data-one-stop-pdf-message') || '';
          const syoriUrl = btn.getAttribute('data-syori-url') || '';
          const msgEl = document.getElementById('furusato-one-stop-pdf-block-message');
          const backEl = document.getElementById('furusato-one-stop-pdf-block-back');
          if (msgEl && msg) msgEl.textContent = msg;
          if (backEl && syoriUrl) backEl.setAttribute('href', syoriUrl);
          if (blockModal) {
            blockModal.show();
            return;
          }
          if (msg) alert(msg);
          if (syoriUrl) {
            window.location.href = syoriUrl;
          }
          return;
        }

        // モーダルが使えるなら3択を出す。使えない環境は従来互換（max）
        if (modal) {
          modal.show();
          return;
        }
        await startDownloadFlow('max');
      }, { passive: false });

      // モーダルのボタン（current/max/both）
      if (modalEl) {
        modalEl.querySelectorAll('[data-pdf-variant]').forEach((el) => {
          el.addEventListener('click', async () => {
            const variant = el.getAttribute('data-pdf-variant') || 'max';
            try {
              if (modal) modal.hide();
            } catch (_e) {}
            await startDownloadFlow(variant);
          });
        });
      }

      // 他の submit のときは 0 に戻す（誤作動防止）
      form.addEventListener('submit', (ev) => {
        const submitter = ev.submitter;
        if (submitter && submitter.id === 'furusato-pdf-button') return;
        flag.value = '0';
      });
    })();

    // 3桁カンマを維持したまま、安全に整数をPOSTする
    function materializeHiddenNumericForSubmit(form) {
      const namedInputs = Array.from(form.querySelectorAll('input[name]'))
        .filter(el => el.type === 'text' && el.name && !el.disabled);
      // ▼ server-only（details→input鏡像／K/N SoT）の8ベースは POST に乗せない（hidden複製を作らない）
      const blockPrefixes = [
        'syunyu_kyuyo_', 'syunyu_zatsu_nenkin_', 'syunyu_zatsu_gyomu_', 'syunyu_zatsu_sonota_',
        'shotoku_kyuyo_', 'shotoku_zatsu_nenkin_', 'shotoku_zatsu_gyomu_', 'shotoku_zatsu_sonota_'
      ];
      namedInputs.forEach((el) => {
        const nm = String(el.name || '');
        // data-server-lock 明示 or 8ベースのいずれかなら送信抑止
        if ((el.dataset && el.dataset.serverLock === '1') || blockPrefixes.some(p => nm.startsWith(p))) {
          return;
        }
        // tb_*由来の只読セルも name を保持したまま hidden にrawを同期
        const raw = String((() => {
          const s = String(el.value ?? '').replace(/,/g, '').trim();
          if (s === '' || s === '-') return '';
          return (/^-?\d+$/).test(s) ? s : '';
        })());
        let hidden = form.querySelector(`input[type="hidden"][name="${el.name}"]`);
        if (!hidden) {
          hidden = document.createElement('input');
          hidden.type = 'hidden';
          hidden.name = el.name;
          form.appendChild(hidden);
        }
        hidden.value = raw;
        const name = el.name;
      });
    }

    if (formEl) {
      formEl.addEventListener('submit', (e) => {
        materializeHiddenNumericForSubmit(formEl);
        (function sanitizeOrphanTaxInputsOnSubmit() {
          const mustBeHiddenPrefixes = ['tax_zeigaku_'];
          const isTargetName = (name) => mustBeHiddenPrefixes.some(pref => name && name.startsWith(pref));
          Array.from(formEl.querySelectorAll('input[name]')).forEach((el) => {
            if (!(el instanceof HTMLInputElement)) return;
            const name = el.getAttribute('name') || '';
            if (!isTargetName(name)) return;
            if (!el.closest('td')) {
              el.type = 'hidden';
              el.classList.remove('bg-light', 'text-end');
            }
          });
        })();
      });
    }
  });
</script>
@endpush

@push('scripts')
  {{-- SortableJS（ドラッグ並び替え） --}}
  <script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.2/Sortable.min.js"></script>
  {{-- 帳票プレビュー（HTMLサムネ） --}}
  @php
    $fp = public_path('js/common/furusato_report_preview.js');
    $vPreview = is_file($fp) ? filemtime($fp) : time();
  @endphp
  <script src="{{ asset('js/common/furusato_report_preview.js') }}?v={{ $vPreview }}" defer></script>
@endpush
@push('styles')
<style>
      /* 太字にしたい行：行に js-bold-row を付ければ入力は必ず太字（readonly/lock に勝つ） */
      tr.js-bold-row input,
      tr.js-bold-row .form-control,
      tr.js-bold-row input.form-control,
      tr.js-bold-row textarea,
      tr.js-bold-row select,
      tr.js-bold-row input[readonly],
      tr.js-bold-row input:read-only,
      tr.js-bold-row textarea[readonly],
      tr.js-bold-row textarea:read-only {
        font-weight: 700 !important;
      }
  
      /* overlay は画面全体、カードだけ幅を絞る */
      #furusato-pdf-overlay .overlay-card {
        max-width: 300px;
        margin-left: auto;
        margin-right: auto;
      }

      /* 3択モーダル内の btn-base-blue：hover/focus/active の色を統一（プレビュー＆PDF） */
      #furusato-preview-mode-modal .btn.btn-base-blue:hover,
      #furusato-preview-mode-modal .btn.btn-base-blue:focus,
      #furusato-preview-mode-modal .btn.btn-base-blue:active,
      #furusato-preview-mode-modal .btn.btn-base-blue.active,
      #furusato-pdf-mode-modal .btn.btn-base-blue:hover,
      #furusato-pdf-mode-modal .btn.btn-base-blue:focus,
      #furusato-pdf-mode-modal .btn.btn-base-blue:active,
      #furusato-pdf-mode-modal .btn.btn-base-blue.active {
         background-color: #4193d0;
         border-color: #4193d0 !important;
        color: #ffffff !important;
       }
</style>
@endpush
    {{-- モーダル--}}
@push('scripts')
 <script>
  // 共通ルール：HELPボタン(.js-help-btn)クリック時に辞書から本文を差し替える
  document.addEventListener('click', function (e) {
    const btn = e.target.closest('.js-help-btn');
    if (!btn) return;

    const key = btn.getAttribute('data-help-key') || '';
    const dict = window.__PAGE_HELP_TEXTS__ || {};
    const item = dict[key];

    const title = item?.title ?? 'HELP';
    const body  = item?.body  ?? '（この項目のHELPは未登録です）';

    const titleEl = document.getElementById('helpModalTitle');
    const bodyEl  = document.getElementById('helpModalBody');
    if (titleEl) titleEl.textContent = title;
    if (bodyEl) {
      // body はテキスト。行ごとに処理して「○〜」の先頭ラベルだけ太字にする
      const escapeHtml = (s) => String(s)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');

      const html = String(body)
        .split('\n')
        .map((line) => {
          // 空行はそのまま改行にする
          if (line === '') return '';

          // 「○〜」行：先頭の見出し（○〜）部分を太字にする（全角スペースやタブも許容）
          const m = line.match(/^(\s*○\s*[^・…：:　]+?)(\s*・・・\s*.*)?$/);
          if (m) {
            const head = escapeHtml(m[1]);
            const rest = escapeHtml(m[2] ?? '');
            return `<strong>${head}</strong>${rest}`;
          }
          return escapeHtml(line);
        })
        .join('<br>');

      bodyEl.innerHTML = html;
    }
  });
 </script>
 @endpush
 {{-- モーダルここまで--}}