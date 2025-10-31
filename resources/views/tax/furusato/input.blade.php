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
    
    
  </style>
@endpush

@section('content')
@php
    /**
     * ▼ クロスタブ参照の読み取り専用フィールドをサーバ側で算出・注入
     *  - shotoku_gokei_* は result_details の４項目合算
     *  - bunri_kazeishotoku_*_* は bunri_joto_detail の合計欄を採用
     *  - いずれも old() が空のときに $inputs 値が使われる前提のため、ここで $inputs に投入しておく
     */
    $inputs = $out['inputs'] ?? ($inputs ?? []);
    foreach (['prev', 'curr'] as $p) {
        // 総合課税の所得合計（第一表：経常 + 譲渡（短期・長期・一時））
        $keijo = (int)($inputs["shotoku_keijo_{$p}"] ?? 0);
        $tanki = (int)($inputs["shotoku_joto_tanki_sogo_{$p}"] ?? 0);
        $choki = (int)($inputs["shotoku_joto_choki_sogo_{$p}"] ?? 0);
        $ichiji = (int)($inputs["shotoku_ichiji_{$p}"] ?? 0);
        $gokei = $keijo + $tanki + $choki + $ichiji;

        // 所得税・住民税とも同じ合計を表示
        $inputs["shotoku_gokei_shotoku_{$p}"] = $gokei;
        $inputs["shotoku_gokei_jumin_{$p}"]   = $gokei;

        // 分離：短期・長期の課税所得（= 内訳画面の合計欄）
        $tankiGokei = (int)($inputs["joto_shotoku_tanki_gokei_{$p}"] ?? 0); // from bunri_joto_detail
        $chokiGokei = (int)($inputs["joto_shotoku_choki_gokei_{$p}"] ?? 0); // from bunri_joto_detail

        $inputs["bunri_kazeishotoku_tanki_shotoku_{$p}"] = $tankiGokei;
        $inputs["bunri_kazeishotoku_tanki_jumin_{$p}"]   = $tankiGokei;
        $inputs["bunri_kazeishotoku_choki_shotoku_{$p}"] = $chokiGokei;
        $inputs["bunri_kazeishotoku_choki_jumin_{$p}"]   = $chokiGokei;
    }
@endphp
<div class="container-blue" style="width: 1200px;">
  <form method="POST" action="{{ route('furusato.save') }}" id="furusato-input-form">
    @csrf
    <input type="hidden" name="data_id" value="{{ $dataId ?? '' }}">
    <input type="hidden" name="redirect_to" value="input">
    <input type="hidden" name="show_result" value="1">

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
      <div class="card-header d-flex justify-content-between mb-3">
        <div>
          <img src="{{ asset('storage/images/kado_lefttop.jpg') }}" alt="…">
          <h0 class="mb-0 mt-2"> インプット表</h0>
        </div>
        <div class="d-flex flex-wrap me-3 mt-2 gap-2">
          <button type="submit"
                  class="btn-base-blue"
                  formnovalidate
                  name="redirect_to"
                  value="syori">戻 る</button>
          <button type="submit"
                  class="btn-base-blue"
                  formnovalidate
                  name="redirect_to"
                  value="master">マスター</button>
          <button type="submit"
                  class="btn-base-green"
                  name="recalc_all"
                  value="1"
                  data-disable-on-submit
                  data-redirect-to="input">再計算</button>
        </div>
      </div>
    <div class="wrapper">
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
            $inputs = array_replace(($inputs ?? []), ($out['inputs'] ?? []));
            $warekiPrevLabel = $warekiPrev ?? '前年';
            $warekiCurrLabel = $warekiCurr ?? '当年';
            $showTokubetsu = in_array((int) ($kihuYear ?? 0), [2024, 2025], true);
            $readonlyBases = array_fill_keys([
                'shotoku_kyuyo',
                'jumin_kyuyo',
                'shotoku_zatsu_nenkin',
                'jumin_zatsu_nenkin',
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
                'bunri_shotoku_taishoku',
                'bunri_kazeishotoku_tanki',
                'bunri_kazeishotoku_choki',
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
                'tax_sashihiki',
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
                'tax_seito' => [
                    'shotoku' => 'shotokuzei_zeigakukojo_seitoto_tokubetsu_%s',
                    'jumin' => 'juminzei_zeigakukojo_seitoto_tokubetsu_%s',
                ],
            ];
            $shotokuRatesForScript = collect($shotokuRates ?? [])->values()->toArray();
            $forceDash = static function (string $base, string $tax, string $period, ?int $kihuYear): bool {
                $isJumin = $tax === 'jumin';

                if ($kihuYear === 2025 && $period === 'curr' && $base === 'tax_tokubetsu_R6') {
                    return true;
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

            $renderInputs = static function (string $base) use ($inputs, $readonlyBases, $kojoFieldOverrides, $kihuYear, $forceDash, $mirrorFromDetailsBases) {
                $html = '';
                foreach (['shotoku' => ['prev', 'curr'], 'jumin' => ['prev', 'curr']] as $tax => $periods) {
                    foreach ($periods as $period) {
                        $format = $kojoFieldOverrides[$base][$tax] ?? null;
                        $name = $format ? sprintf($format, $period) : sprintf('%s_%s_%s', $base, $tax, $period);
                        $value = old($name, $inputs[$name] ?? null);
                        $kihuYearInt = isset($kihuYear) ? (int) $kihuYear : null;
                        $isForceDash = $forceDash($base, $tax, $period, $kihuYearInt);
                        $isReadonly = false;

                        // ▼ 「総合譲渡・一時」行だけは内訳の所得値を合算してミラー（税目共通）し、常にreadonly
                        if ($base === 'shotoku_joto_ichiji') {
                            $tankiKey  = sprintf('shotoku_joto_tanki_%s', $period);
                            $chokiKey  = sprintf('shotoku_joto_choki_%s', $period);
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
                            ];
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
                        }

                        if ($isForceDash) {
                            $html .= '<td><input type="text" class="form-control form-control-compact-05-compact-05 text-center bg-light" value="－" readonly><input type="hidden" name="' . e($name) . '" value=""></td>';
                        } else {
                            $readonlyAttr = $isReadonly ? ' readonly' : '';
                            $class = 'form-control form-control-compact-05-compact-05 text-end js-comma';
                            if ($isReadonly) {
                                $class .= ' bg-light';
                            }
                            // type="text" に統一し、カンマ表示と送信時のhidden数値化をJSで行う
                            $html .= '<td><input type="text" inputmode="numeric" pattern="[0-9,\\-]*" class="' . e($class) . '" name="' . e($name) . '" value="' . e($value) . '"' . $readonlyAttr . '></td>';
                        }
                    }
                }

                return $html;
            };
            $renderReadonlyBunriKazeishotoku = static function (string $base) use ($inputs) {
                $html = '';
                foreach (['shotoku' => ['prev', 'curr'], 'jumin' => ['prev', 'curr']] as $tax => $periods) {
                    foreach ($periods as $period) {
                        $name = sprintf('%s_%s_%s', $base, $tax, $period);
                        $value = old($name, $inputs[$name] ?? 0);
                        $html .= '<td><input type="text" inputmode="numeric" class="form-control form-control-sm text-end bg-light js-comma" name="' . e($name) . '" value="' . e($value) . '" readonly></td>';
                    }
                }

                return $html;
            };
            $syunyuRowspan = 11;
            $shotokuRowspan = 11;
            $kojoRowspan = 18;
            $taxRowspan = $showTokubetsu ? 10 : 9;
          @endphp
  
          @php ob_start(); @endphp
        <div>
          <hb class="card-title mb-3">確定申告書(総合課税) 第一表</hb>
          <div class="table-responsive">
            <table class="table table-base table-compact-05 align-middle">
                <tr>
                  <th rowspan="2" colspan="6" class="th-ccc">項  目</th>
                  <th colspan="2" style="height:30px;" class="th-ccc">所得税</th>
                  <th colspan="2" class="th-ccc">住民税</th>
                </tr>
                <tr style="height:30px;">
                  <th>{{ $warekiPrevLabel }}</th>
                  <th>{{ $warekiCurrLabel }}</th>
                  <th>{{ $warekiPrevLabel }}</th>
                  <th>{{ $warekiCurrLabel }}</th>
                </tr>
              
              <tbody>
                <tr id="syunyu_row_jigyo_eigyo" data-anchor>
                  <th scope="rowgroup" rowspan="{{ $syunyuRowspan }}" class="text-center align-middle th-ccc">収入金額等</th>
                  <th rowspan="2" class="text-start align-middle ps-1">事業</th>
                  <th colspan="2" class="text-start align-middle th-ddd ps-1">営業等</th>
                  <td class="text-center align-middle">
                    <button type="button" class="btn btn-link btn-sm px-0">HELP</button>
                  </td>
                  <td class="text-center align-middle">
                    <button type="submit"
                            class="btn-base-green"
                            name="redirect_to"
                            value="jigyo"
                            data-return-anchor="syunyu_row_jigyo_eigyo">内訳</button>
                  </td>
                  {!! $renderInputs('syunyu_jigyo_eigyo') !!}
                </tr>
                <tr>
                  <th colspan="2" class="text-start align-middle th-ddd ps-1">農業</th>
                  <td class="text-center align-middle">
                    <button type="button" class="btn btn-link btn-sm px-0">HELP</button>
                  </td>
                  <td class="text-center align-middle"></td>
                  {!! $renderInputs('syunyu_jigyo_nogyo') !!}
                </tr>
                <tr id="syunyu_row_fudosan" data-anchor>
                  <th colspan="3" class="text-start align-middle ps-1">不動産</th>
                  <td class="text-center align-middle">
                    <button type="button" class="btn btn-link btn-sm px-0">HELP</button>
                  </td>
                  <td class="text-center align-middle">
                    <button type="submit"
                            class="btn-base-green"
                            name="redirect_to"
                            value="fudosan"
                            data-return-anchor="syunyu_row_fudosan">内訳</button>
                  </td>
                  {!! $renderInputs('syunyu_fudosan') !!}
                </tr>
                <tr>
                  <th colspan="3" class="text-start align-middle ps-1">配当</th>
                  <td class="text-center align-middle">
                    <button type="button" class="btn btn-link btn-sm px-0">HELP</button>
                  </td>
                  <td class="text-center align-middle"></td>
                  {!! $renderInputs('syunyu_haito') !!}
                </tr>
                <tr>
                  <th colspan="3" class="text-start align-middle ps-1">給与</th>
                  <td class="text-center align-middle">
                    <button type="button" class="btn btn-link btn-sm px-0">HELP</button>
                  </td>
                  <td class="text-center align-middle"></td>
                  {!! $renderInputs('syunyu_kyuyo') !!}
                </tr>
                <tr>
                  <th rowspan="3" class="text-start align-middle ps-1">雑</th>
                  <th colspan="2" class="text-start align-middle th-ddd ps-1">公的年金等</th>
                  <td class="text-center align-middle">
                    <button type="button" class="btn btn-link btn-sm px-0">HELP</button>
                  </td>
                  <td class="text-center align-middle"></td>
                  {!! $renderInputs('syunyu_zatsu_nenkin') !!}
                </tr>
                <tr>
                  <th colspan="2" class="text-start align-middle th-ddd ps-1">業務</th>
                  <td class="text-center align-middle">
                    <button type="button" class="btn btn-link btn-sm px-0">HELP</button>
                  </td>
                  <td class="text-center align-middle"></td>
                  {!! $renderInputs('syunyu_zatsu_gyomu') !!}
                </tr>
                <tr>
                  <th colspan="2" class="text-start align-middle th-ddd ps-1">その他</th>
                  <td class="text-center align-middle">
                    <button type="button" class="btn btn-link btn-sm px-0">HELP</button>
                  </td>
                  <td class="text-center align-middle"></td>
                  {!! $renderInputs('syunyu_zatsu_sonota') !!}
                </tr>
                <tr id="income_joto_ichiji" data-anchor>
                  <th rowspan="2" class="text-start align-middle ps-1">譲渡</th>
                  <th colspan="2" class="text-start align-middle th-ddd ps-1">短期</th>
                  <td class="text-center align-middle">
                    <button type="button" class="btn btn-link btn-sm px-0">HELP</button>
                  </td>
                  <td class="text-center align-middle" rowspan="3">
                    <button type="button"
                            class="btn-base-green js-open-details"
                            data-redirect-to="joto_ichiji"
                            data-origin-anchor="income_joto_ichiji"
                            data-return-anchor="income_joto_ichiji">内訳</button>
                  </td>
                  {!! $renderInputs('syunyu_joto_tanki') !!}
                </tr>
                <tr>
                  <th colspan="2" class="text-start align-middle th-ddd ps-1">長期</th>
                  <td class="text-center align-middle">
                    <button type="button" class="btn btn-link btn-sm px-0">HELP</button>
                  </td>
                  {!! $renderInputs('syunyu_joto_choki') !!}
                </tr>
                <tr>
                  <th colspan="3" class="text-start align-middle ps-1">一時</th>
                  <td class="text-center align-middle">
                    <button type="button" class="btn btn-link btn-sm px-0">HELP</button>
                  </td>
                  {!! $renderInputs('syunyu_ichiji') !!}
                </tr>
                <tr id="shotoku_row_jigyo_eigyo" data-anchor>
                  <th scope="rowgroup" rowspan="{{ $shotokuRowspan }}" class="text-center align-middle th-ccc">所得金額等</th>
                  <th rowspan="2" class="text-start align-middle ps-1">事業</th>
                  <th colspan="2" class="text-start align-middle th-ddd ps-1">営業等</th>
                  <td class="text-center align-middle">
                    <button type="button" class="btn btn-link btn-sm px-0">HELP</button>
                  </td>
                  <td class="text-center align-middle">
                    <button type="submit"
                            class="btn-base-green"
                            name="redirect_to"
                            value="jigyo"
                            data-return-anchor="shotoku_row_jigyo_eigyo">内訳</button>
                  </td>
                  {!! $renderInputs('shotoku_jigyo_eigyo') !!}
                </tr>
                <tr>
                  <th colspan="2" class="text-start align-middle th-ddd ps-1">農業</th>
                  <td class="text-center align-middle">
                    <button type="button" class="btn btn-link btn-sm px-0">HELP</button>
                  </td>
                  <td class="text-center align-middle"></td>
                  {!! $renderInputs('shotoku_jigyo_nogyo') !!}
                </tr>
                <tr id="shotoku_row_fudosan" data-anchor>
                  <th colspan="3" class="text-start align-middle ps-1">不動産</th>
                  <td class="text-center align-middle">
                    <button type="button" class="btn btn-link btn-sm px-0">HELP</button>
                  </td>
                  <td class="text-center align-middle">
                    <button type="submit"
                            class="btn-base-green"
                            nowrap=”nowrap”
                            name="redirect_to"
                            value="fudosan"
                            data-return-anchor="shotoku_row_fudosan">内訳</button>
                  </td>
                  {!! $renderInputs('shotoku_fudosan') !!}
                </tr>
                <tr>
                  <th colspan="3" class="text-start align-middle ps-1">利子</th>
                  <td class="text-center align-middle">
                    <button type="button" class="btn btn-link btn-sm px-0">HELP</button>
                  </td>
                  <td class="text-center align-middle"></td>
                  {!! $renderInputs('shotoku_rishi') !!}
                </tr>
                <tr>
                  <th colspan="3" class="text-start align-middle ps-1">配当</th>
                  <td class="text-center align-middle">
                    <button type="button" class="btn btn-link btn-sm px-0">HELP</button>
                  </td>
                  <td class="text-center align-middle"></td>
                  {!! $renderInputs('shotoku_haito') !!}
                </tr>
                <tr>
                  <th colspan="3" class="text-start align-middle ps-1">給与</th>
                  <td class="text-center align-middle">
                    <button type="button" class="btn btn-link btn-sm px-0">HELP</button>
                  </td>
                  <td class="text-center align-middle"></td>
                  {!! $renderInputs('shotoku_kyuyo') !!}
                </tr>
                <tr>
                  <th rowspan="3" class="text-start align-middle ps-1">雑</th>
                  <th colspan="2" class="text-start align-middle th-ddd ps-1">公的年金等</th>
                  <td class="text-center align-middle">
                    <button type="button" class="btn btn-link btn-sm px-0">HELP</button>
                  </td>
                  <td class="text-center align-middle"></td>
                  {!! $renderInputs('shotoku_zatsu_nenkin') !!}
                </tr>
                <tr>
                  <th colspan="2" class="text-start align-middle th-ddd ps-1">業務</th>
                  <td class="text-center align-middle">
                    <button type="button" class="btn btn-link btn-sm px-0">HELP</button>
                  </td>
                  <td class="text-center align-middle"></td>
                  {!! $renderInputs('shotoku_zatsu_gyomu') !!}
                </tr>
                <tr>
                  <th colspan="2" class="text-start align-middle th-ddd ps-1">その他</th>
                  <td class="text-center align-middle">
                    <button type="button" class="btn btn-link btn-sm px-0">HELP</button>
                  </td>
                  <td class="text-center align-middle"></td>
                  {!! $renderInputs('shotoku_zatsu_sonota') !!}
                </tr>
                <tr id="shotoku_joto_ichiji" data-anchor>
                  <th colspan="3" class="text-start align-middle ps-1">総合譲渡・一時</th>
                  <td class="text-center align-middle">
                    <button type="button" class="btn btn-link btn-sm px-0">HELP</button>
                  </td>
                  <td class="text-center align-middle">
                    <button type="button"
                            class="btn-base-green js-open-details"
                            data-redirect-to="joto_ichiji"
                            data-origin-anchor="shotoku_joto_ichiji"
                            data-return-anchor="shotoku_joto_ichiji">内訳</button>
                  </td>
                  {!! $renderInputs('shotoku_joto_ichiji') !!}
                </tr>
                <tr>
                  <th colspan="3" class="align-middle th-cream">合  計</th>
                  <td colspan="2" class="text-center align-middle"></td>
                  {!! $renderInputs('shotoku_gokei') !!}
                </tr>
                <tr>
                  <th scope="rowgroup" rowspan="{{ $kojoRowspan }}" class="text-center align-middle th-ccc" nowrap="nowrap">所得から差し<br>引かれる金額</th>
                  <th colspan="3" class="text-start align-middle ps-1">社会保険料控除</th>
                  <td class="text-center align-middle">
                    <button type="button" class="btn btn-link btn-sm px-0">HELP</button>
                  </td>
                  <td class="text-center align-middle"></td>
                  {!! $renderInputs('kojo_shakaihoken') !!}
                </tr>
                <tr>
                  <th colspan="3" class="text-start align-middle ps-1 pe-1" nowrap=”nowrap”>小規模企業共済等掛金控除</th>
                  <td class="text-center align-middle">
                    <button type="button" class="btn btn-link btn-sm px-0">HELP</button>
                  </td>
                  <td class="text-center align-middle"></td>
                  {!! $renderInputs('kojo_shokibo') !!}
                </tr>
                <tr id="kojo_seimei_jishin" data-anchor>
                  <th colspan="3" class="text-start align-middle ps-1">生命保険料控除</th>
                  <td class="text-center align-middle">
                    <button type="button" class="btn btn-link btn-sm px-0">HELP</button>
                  </td>
                  <td class="text-center align-middle" rowspan="2">
                    <button type="button"
                            class="btn-base-green js-open-details"
                            data-redirect-to="kojo_seimei_jishin"
                            data-origin-anchor="kojo_seimei_jishin"
                            data-return-anchor="kojo_seimei_jishin">内訳</button>
                  </td>
                  {!! $renderInputs('kojo_seimei') !!}
                </tr>
                <tr>
                  <th colspan="3" class="text-start align-middle ps-1">地震保険料控除</th>
                  <td class="text-center align-middle">
                    <button type="button" class="btn btn-link btn-sm px-0">HELP</button>
                  </td>
                  {!! $renderInputs('kojo_jishin') !!}
                </tr>
                <tr id="kojo_jinteki" data-anchor>
                  <th colspan="3" class="text-start align-middle ps-1">寡婦控除</th>
                  <td class="text-center align-middle">
                    <button type="button" class="btn btn-link btn-sm px-0">HELP</button>
                  </td>
                  <td class="text-center align-middle" rowspan="8">
                    <button type="button"
                            class="btn-base-green js-open-details"
                            data-redirect-to="kojo_jinteki"
                            data-origin-anchor="kojo_jinteki"
                            data-return-anchor="kojo_jinteki">内訳</button>
                  </td>
                  {!! $renderInputs('kojo_kafu') !!}
                </tr>
                <tr>
                  <th colspan="3" class="text-start align-middle ps-1">ひとり親控除</th>
                  <td class="text-center align-middle">
                    <button type="button" class="btn btn-link btn-sm px-0">HELP</button>
                  </td>
                  {!! $renderInputs('kojo_hitorioya') !!}
                </tr>
                <tr>
                  <th colspan="3" class="text-start align-middle ps-1">勤労学生控除</th>
                  <td class="text-center align-middle">
                    <button type="button" class="btn btn-link btn-sm px-0">HELP</button>
                  </td>
                  {!! $renderInputs('kojo_kinrogakusei') !!}
                </tr>
                <tr>
                  <th colspan="3" class="text-start align-middle ps-1">障害者控除</th>
                  <td class="text-center align-middle">
                    <button type="button" class="btn btn-link btn-sm px-0">HELP</button>
                  </td>
                  {!! $renderInputs('kojo_shogaisha') !!}
                </tr>
                <tr>
                  <th colspan="3" class="text-start align-middle ps-1">配偶者控除</th>
                  <td class="text-center align-middle">
                    <button type="button" class="btn btn-link btn-sm px-0">HELP</button>
                  </td>
                  {!! $renderInputs('kojo_haigusha') !!}
                </tr>
                <tr>
                  <th colspan="3" class="text-start align-middle ps-1">配偶者特別控除</th>
                  <td class="text-center align-middle">
                    <button type="button" class="btn btn-link btn-sm px-0">HELP</button>
                  </td>
                  {!! $renderInputs('kojo_haigusha_tokubetsu') !!}
                </tr>
                <tr>
                  <th colspan="3" class="text-start align-middle ps-1">扶養控除</th>
                  <td class="text-center align-middle">
                    <button type="button" class="btn btn-link btn-sm px-0">HELP</button>
                  </td>
                  {!! $renderInputs('kojo_fuyo') !!}
                </tr>
                <tr>
                  <th colspan="3" class="text-start align-middle ps-1">特定親族特別控除</th>
                  <td class="text-center align-middle">
                    <button type="button" class="btn btn-link btn-sm px-0">HELP</button>
                  </td>
                  {!! $renderInputs('kojo_tokutei_shinzoku') !!}
                </tr>
                <tr>
                  <th colspan="3" class="text-start align-middle ps-1">基礎控除</th>
                  <td class="text-center align-middle">
                    <button type="button" class="btn btn-link btn-sm px-0">HELP</button>
                  </td>
                  <td class="text-center align-middle"></td>
                  {!! $renderInputs('kojo_kiso') !!}
                </tr>
                <tr>
                  <th colspan="3" class="align-middle">小  計</th>
                  <td colspan="2" class="text-center align-middle"></td>
                  {!! $renderInputs('kojo_shokei') !!}
                </tr>
                <tr>
                  <th colspan="3" class="text-start align-middle ps-1">雑損控除</th>
                  <td class="text-center align-middle">
                    <button type="button" class="btn btn-link btn-sm px-0">HELP</button>
                  </td>
                  <td class="text-center align-middle"></td>
                  {!! $renderInputs('kojo_zasson') !!}
                </tr>
                <tr id="kojo_iryo" data-anchor>
                  <th colspan="3" class="text-start align-middle ps-1">医療費控除</th>
                  <td class="text-center align-middle">
                    <button type="button" class="btn btn-link btn-sm px-0">HELP</button>
                  </td>
                  <td class="text-center align-middle">
                    <button type="button"
                            class="btn-base-green js-open-details"
                            data-redirect-to="kojo_iryo"
                            data-origin-anchor="kojo_iryo"
                            data-return-anchor="kojo_iryo">内訳</button>
                  </td>
                  {!! $renderInputs('kojo_iryo') !!}
                </tr>
                <tr id="kojo_row_kifukin" data-anchor>
                  <th colspan="3" class="text-start align-middle ps-1">寄付金控除</th>
                  <td class="text-center align-middle">
                    <button type="button" class="btn btn-link btn-sm px-0">HELP</button>
                  </td>
                  <td class="text-center align-middle">
                    <button type="submit"
                            class="btn-base-green"
                            name="redirect_to"
                            value="kifukin_details"
                            data-return-anchor="kojo_row_kifukin">内訳</button>
                  </td>
                  {!! $renderInputs('kojo_kifukin') !!}
                </tr>
                <tr>
                  <th colspan="3" class="align-middle th-cream">合  計</th>
                  <td class="text-center align-middle">
                    <button type="button" class="btn btn-link btn-sm px-0">HELP</button>
                  </td>
                  <td class="text-center align-middle"></td>
                  {!! $renderInputs('kojo_gokei') !!}
                </tr>
                <tr>
                  <th scope="rowgroup" rowspan="{{ $taxRowspan }}" class="text-center align-middle th-ccc">税金の金額</th>
                  <th colspan="3" class="text-start align-middle ps-1">課税所得金額又は第三表</th>
                  <td class="text-center align-middle">
                    <button type="button" class="btn btn-link btn-sm px-0">HELP</button>
                  </td>
                  <td class="text-center align-middle"></td>
                  {!! $renderInputs('tax_kazeishotoku') !!}
                </tr>
                <tr>
                  <th colspan="3" class="text-start align-middle ps-1">税額</th>
                  <td class="text-center align-middle">
                    <button type="button" class="btn btn-link btn-sm px-0">HELP</button>
                  </td>
                  <td class="text-center align-middle"></td>
                  {!! $renderInputs('tax_zeigaku') !!}
                </tr>
                <tr>
                  <th colspan="3" class="text-start align-middle ps-1">配当控除</th>
                  <td class="text-center align-middle">
                    <button type="button" class="btn btn-link btn-sm px-0">HELP</button>
                  </td>
                  <td class="text-center align-middle"></td>
                  {!! $renderInputs('tax_haito') !!}
                </tr>
                <tr>
                  <th colspan="3" class="text-start align-middle ps-1">住宅借入金等特別控除</th>
                  <td class="text-center align-middle">
                    <button type="button" class="btn btn-link btn-sm px-0">HELP</button>
                  </td>
                  <td class="text-center align-middle"></td>
                  {!! $renderInputs('tax_jutaku') !!}
                </tr>
                <tr>
                  <th colspan="3" class="text-start align-middle ps-1e">政党等寄付金等特別控除</th>
                  <td class="text-center align-middle">
                    <button type="button" class="btn btn-link btn-sm px-0">HELP</button>
                  </td>
                  <td class="text-center align-middle"></td>
                  {!! $renderInputs('tax_seito') !!}
                </tr>
                <tr>
                  <th colspan="3" class="text-start align-middle ps-1">差引所得税額</th>
                  <td class="text-center align-middle">
                    <button type="button" class="btn btn-link btn-sm px-0">HELP</button>
                  </td>
                  <td class="text-center align-middle"></td>
                  {!! $renderInputs('tax_sashihiki') !!}
                </tr>
                @if ($showTokubetsu)
                <tr>
                  <th colspan="3" class="text-start align-middle ps-1">令和6年度分特別税額控除</th>
                  <td class="text-center align-middle">
                    <button type="button" class="btn btn-link btn-sm px-0">HELP</button>
                  </td>
                  <td class="text-center align-middle"></td>
                  {!! $renderInputs('tax_tokubetsu_R6') !!}
                </tr>
                @endif
                <tr>
                  <th colspan="3" class="text-start align-middle ps-1">基準所得税額</th>
                  <td class="text-center align-middle">
                    <button type="button" class="btn btn-link btn-sm px-0">HELP</button>
                  </td>
                  <td class="text-center align-middle"></td>
                  {!! $renderInputs('tax_kijun') !!}
                </tr>
                <tr>
                  <th colspan="3" class="text-start align-middle ps-1">復興所得税額</th>
                  <td class="text-center align-middle">
                    <button type="button" class="btn btn-link btn-sm px-0">HELP</button>
                  </td>
                  <td class="text-center align-middle"></td>
                  {!! $renderInputs('tax_fukkou') !!}
                </tr>
                <tr>
                  <th colspan="3" class="align-middle th-cream">合  計</th>
                  <td class="text-center align-middle">
                    <button type="button" class="btn btn-link btn-sm px-0">HELP</button>
                  </td>
                  <td class="text-center align-middle"></td>
                  {!! $renderInputs('tax_gokei') !!}
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      @php $sogoContent = ob_get_clean(); @endphp
  
      @if ($showSeparatedNettingFlag)
        <div class="card mb-4">
          <div class="card-header pb-0">
            <ul class="nav nav-tabs card-header-tabs" id="furusato-input-tabs" role="tablist">
              <li class="nav-item" role="presentation">
                <button class="nav-link active" id="tab-sogo" data-bs-toggle="tab" data-bs-target="#pane-sogo" type="button" role="tab" aria-controls="pane-sogo" aria-selected="true">確定申告書(総合課税)</button>
              </li>
              <li class="nav-item" role="presentation">
                <button class="nav-link" id="tab-bunri" data-bs-toggle="tab" data-bs-target="#pane-bunri" type="button" role="tab" aria-controls="pane-bunri" aria-selected="false">確定申告書(分離課税)</button>
              </li>
            </ul>
          </div>
          <div class="card-body">
            <div class="tab-content" id="furusato-input-tab-content">
              <div class="tab-pane fade show active" id="pane-sogo" role="tabpanel" aria-labelledby="tab-sogo">
                {!! $sogoContent !!}
              </div>
              <div class="tab-pane fade" id="pane-bunri" role="tabpanel" aria-labelledby="tab-bunri">
                <div>
                  <hb class="card-title mb-3">確定申告書(分離課税) 第三表</hb>
                  <div class="table-responsive">
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
                        <tr>
                          <th scope="row" colspan="2" class="align-middle text-start th-ddd ps-1">一般株式等の譲渡</th>
                          <td class="text-center align-middle">
                            <button type="button" class="btn btn-link btn-sm px-0">HELP</button>
                          </td>
                          <td class="text-center nowrap align-middle" rowspan="3">
                            <button type="submit"
                                    class="btn-base-green"
                                    name="redirect_to"
                                    value="bunri_kabuteki"
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
                        <tr>
                          <th scope="row" colspan="2" class="align-middle text-start th-ddd ps-1">先物取引</th>
                          <td class="text-center align-middle">
                            <button type="button" class="btn btn-link btn-sm px-0">HELP</button>
                          </td>
                          <td class="text-center align-middle">
                            <button type="submit"
                                    class="btn-base-green"
                                    name="redirect_to"
                                    value="bunri_sakimono"
                                    data-return-anchor="bunri_income_sakimono">内訳</button>
                          </td>
                          {!! $renderInputs('bunri_syunyu_sakimono') !!}
                        </tr>
                        <tr>
                          <th scope="row" colspan="2" class="align-middle text-start th-ddd ps-1">山林</th>
                          <td class="text-center align-middle">
                            <button type="button" class="btn btn-link btn-sm px-0">HELP</button>
                          </td>
                          <td class="text-center align-middle">
                            <button type="submit"
                                    class="btn-base-green"
                                    name="redirect_to"
                                    value="bunri_sanrin"
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
                        <tr>
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
                        <tr>
                          <th scope="row" colspan="2" class="align-middle text-start th-ddd ps-1">一般株式等の譲渡</th>
                          <td class="text-center align-middle">
                            <button type="button" class="btn btn-link btn-sm px-0">HELP</button>
                          </td>
                          <td class="text-center align-middle" rowspan="3">
                            <button type="submit"
                                    class="btn-base-green"
                                    name="redirect_to"
                                    value="bunri_kabuteki"
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
                        <tr>
                          <th scope="row" colspan="2" class="align-middle text-start th-ddd ps-1">先物取引</th>
                          <td class="text-center align-middle">
                            <button type="button" class="btn btn-link btn-sm px-0">HELP</button>
                          </td>
                          <td class="text-center align-middle">
                            <button type="submit"
                                    class="btn-base-green"
                                    name="redirect_to"
                                    value="bunri_sakimono"
                                    data-return-anchor="bunri_shotoku_sakimono">内訳</button>
                          </td>
                          {!! $renderInputs('bunri_shotoku_sakimono') !!}
                        </tr>
                        <tr>
                          <th scope="row" colspan="2" class="align-middle text-start th-ddd ps-1">山林</th>
                          <td class="text-center align-middle">
                            <button type="button" class="btn btn-link btn-sm px-0">HELP</button>
                          </td>
                          <td class="text-center align-middle">
                            <button type="submit"
                                    class="btn-base-green"
                                    name="redirect_to"
                                    value="bunri_sanrin"
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
                          <th scope="rowgroup" rowspan="8" class="align-middle text-start ps-1" nowrap=”nowrap”>課税所得<br>金額</th>
                          <th scope="row" colspan="2" class="align-middle text-start ps-1 th-ddd">総合課税</th>
                          <td class="text-center align-middle">
                            <button type="button" class="btn btn-link btn-sm px-0">HELP</button>
                          </td>
                          <td></td>
                          @foreach (['shotoku' => ['prev', 'curr'], 'jumin' => ['prev', 'curr']] as $tax => $periods)
                            @foreach ($periods as $period)
                              @php
                                $name = sprintf('bunri_kazeishotoku_sogo_%s_%s', $tax, $period);
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
                          {!! $renderReadonlyBunriKazeishotoku('bunri_kazeishotoku_tanki') !!}
                        </tr>
                        <tr>
                          <th scope="row" colspan="2" class="align-middle text-start ps-1 th-ddd">長期譲渡</th>
                          <td class="text-center align-middle">
                            <button type="button" class="btn btn-link btn-sm px-0">HELP</button>
                          </td>
                          <td></td>
                          {!! $renderReadonlyBunriKazeishotoku('bunri_kazeishotoku_choki') !!}
                        </tr>
                        <tr>
                          <th scope="row" colspan="2" class="align-middle text-start ps-1 pe-1 th-ddd" nowrap="nowrap">一般・上場株式の譲渡</th>
                          <td class="text-center align-middle">
                            <button type="button" class="btn btn-link btn-sm px-0">HELP</button>
                          </td>
                          <td></td>
                          {!! $renderReadonlyBunriKazeishotoku('bunri_kazeishotoku_joto') !!}
                        </tr>
                        <tr>
                          <th scope="row" colspan="2" class="align-middle text-start ps-1 th-ddd">上場株式の配当等</th>
                          <td class="text-center align-middle">
                            <button type="button" class="btn btn-link btn-sm px-0">HELP</button>
                          </td>
                          <td></td>
                          {!! $renderReadonlyBunriKazeishotoku('bunri_kazeishotoku_haito') !!}
                        </tr>
                        <tr>
                          <th scope="row" colspan="2" class="align-middle text-start ps-1 th-ddd">先物取引</th>
                          <td class="text-center align-middle">
                            <button type="button" class="btn btn-link btn-sm px-0">HELP</button>
                          </td>
                          <td></td>
                          {!! $renderReadonlyBunriKazeishotoku('bunri_kazeishotoku_sakimono') !!}
                        </tr>
                        <tr>
                          <th scope="row" colspan="2" class="align-middle text-start ps-1 th-ddd">山林</th>
                          <td class="text-center align-middle">
                            <button type="button" class="btn btn-link btn-sm px-0">HELP</button>
                          </td>
                          <td></td>
                          {!! $renderReadonlyBunriKazeishotoku('bunri_kazeishotoku_sanrin') !!}
                        </tr>
                        <tr>
                          <th scope="row" colspan="2" class="align-middle text-start ps-1 th-ddd">退職</th>
                          <td class="text-center align-middle">
                            <button type="button" class="btn btn-link btn-sm px-0">HELP</button>
                          </td>
                          <td></td>
                          {!! $renderReadonlyBunriKazeishotoku('bunri_kazeishotoku_taishoku') !!}
                        </tr>
                        <tr>
                          <th scope="rowgroup" rowspan="8" class="text-center align-middle text-start ps-1">税 額</th>
                          <th scope="row" colspan="2" class="align-middle text-start ps-1 th-ddd">総合課税</th>
                          <td class="text-center align-middle">
                            <button type="button" class="btn btn-link btn-sm px-0">HELP</button>
                          </td>
                          <td></td>
                          @foreach (['shotoku' => ['prev', 'curr'], 'jumin' => ['prev', 'curr']] as $tax => $periods)
                            @foreach ($periods as $period)
                              @php
                                $name = sprintf('bunri_zeigaku_sogo_%s_%s', $tax, $period);
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
                          @foreach (['shotoku' => ['prev', 'curr'], 'jumin' => ['prev', 'curr']] as $tax => $periods)
                            @foreach ($periods as $period)
                              @php
                                $name = sprintf('bunri_zeigaku_tanki_%s_%s', $tax, $period);
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
                          <th scope="row" colspan="2" class="align-middle text-start ps-1 th-ddd">長期譲渡</th>
                          <td class="text-center align-middle">
                            <button type="button" class="btn btn-link btn-sm px-0">HELP</button>
                          </td>
                          <td></td>
                          @foreach (['shotoku' => ['prev', 'curr'], 'jumin' => ['prev', 'curr']] as $tax => $periods)
                            @foreach ($periods as $period)
                              @php
                                $name = sprintf('bunri_zeigaku_choki_%s_%s', $tax, $period);
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
                          <th scope="row" colspan="2" class="align-middle text-start ps-1 th-ddd">一般・上場株式の譲渡</th>
                          <td class="text-center align-middle">
                            <button type="button" class="btn btn-link btn-sm px-0">HELP</button>
                          </td>
                          <td></td>
                          @foreach (['shotoku' => ['prev', 'curr'], 'jumin' => ['prev', 'curr']] as $tax => $periods)
                            @foreach ($periods as $period)
                              @php
                                $name = sprintf('bunri_zeigaku_joto_%s_%s', $tax, $period);
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
                          <th scope="row" colspan="2" class="align-middle text-start ps-1 th-ddd">上場株式の配当等</th>
                          <td class="text-center align-middle">
                            <button type="button" class="btn btn-link btn-sm px-0">HELP</button>
                          </td>
                          <td></td>
                          @foreach (['shotoku' => ['prev', 'curr'], 'jumin' => ['prev', 'curr']] as $tax => $periods)
                            @foreach ($periods as $period)
                              @php
                                $name = sprintf('bunri_zeigaku_haito_%s_%s', $tax, $period);
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
                          <th scope="row" colspan="2" class="align-middle text-start ps-1 th-ddd">先物取引</th>
                          <td class="text-center align-middle">
                            <button type="button" class="btn btn-link btn-sm px-0">HELP</button>
                          </td>
                          <td></td>
                          @foreach (['shotoku' => ['prev', 'curr'], 'jumin' => ['prev', 'curr']] as $tax => $periods)
                            @foreach ($periods as $period)
                              @php
                                $name = sprintf('bunri_zeigaku_sakimono_%s_%s', $tax, $period);
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
                          <th scope="row" colspan="2" class="align-middle text-start ps-1 th-ddd">山林</th>
                          <td class="text-center align-middle">
                            <button type="button" class="btn btn-link btn-sm px-0">HELP</button>
                          </td>
                          <td></td>
                          @foreach (['shotoku' => ['prev', 'curr'], 'jumin' => ['prev', 'curr']] as $tax => $periods)
                            @foreach ($periods as $period)
                              @php
                                $name = sprintf('bunri_zeigaku_sanrin_%s_%s', $tax, $period);
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
                          <th scope="row" colspan="2" class="align-middle text-start ps-1 th-ddd">退職</th>
                          <td class="text-center align-middle">
                            <button type="button" class="btn btn-link btn-sm px-0">HELP</button>
                          </td>
                          <td></td>
                          @foreach (['shotoku' => ['prev', 'curr'], 'jumin' => ['prev', 'curr']] as $tax => $periods)
                            @foreach ($periods as $period)
                              @php
                                $name = sprintf('bunri_zeigaku_taishoku_%s_%s', $tax, $period);
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
                          <th scope="row" colspan="3" class="align-middle text-center th-cream">合計（第一表へ）</th>
                          <td class="text-center align-middle">
                            <button type="button" class="btn btn-link btn-sm px-0">HELP</button>
                          </td>
                          <td></td>
                          @foreach (['shotoku' => ['prev', 'curr'], 'jumin' => ['prev', 'curr']] as $tax => $periods)
                            @foreach ($periods as $period)
                              @php
                                $name = sprintf('bunri_zeigaku_gokei_%s_%s', $tax, $period);
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
@endsection
@push('scripts')
<script>
  // 対象キーの入力を「読み取り専用」に固定し、隠しミラー（raw整数）も同期
  document.addEventListener('DOMContentLoaded', () => {
    (function restoreDetachedNames() {
      document.querySelectorAll('input[data-name-detached]').forEach((el) => {
        const n = el.getAttribute('data-name-detached');
        if (!n) return;
        // 既に name があればスキップ
        if (!el.name) {
          el.name = n;
        }
        el.removeAttribute('data-name-detached');
        // 重複 hidden が残っていれば整理（最新1つだけ残す）
        const hiddens = Array.from(document.querySelectorAll(`input[type="hidden"][name="${n}"]`));
        if (hiddens.length > 1) {
          // 最後の1つだけ残して他は削除
          hiddens.slice(0, -1).forEach(h => h.remove());
        }
      });
    })();

    const roKeys = [
      // 総合課税の所得合計
      'shotoku_gokei_shotoku_prev','shotoku_gokei_jumin_prev',
      'shotoku_gokei_shotoku_curr','shotoku_gokei_jumin_curr',
      // 分離（短期）
      'bunri_kazeishotoku_tanki_shotoku_prev','bunri_kazeishotoku_tanki_jumin_prev',
      'bunri_kazeishotoku_tanki_shotoku_curr','bunri_kazeishotoku_tanki_jumin_curr',
      // 分離（長期）
      'bunri_kazeishotoku_choki_shotoku_prev','bunri_kazeishotoku_choki_jumin_prev',
      'bunri_kazeishotoku_choki_shotoku_curr','bunri_kazeishotoku_choki_jumin_curr',
    ];

    const toRawInt = (v) => {
      if (typeof v !== 'string') return '';
      const s = v.replace(/,/g, '').trim();
      if (s === '' || s === '-') return '';
      if (!/^(-)?\d+$/.test(s)) return '';
      const n = parseInt(s, 10);
      return Number.isNaN(n) ? '' : String(n);
    };

    roKeys.forEach((name) => {
      const input = document.querySelector(`[data-format="comma-int"][data-name="${name}"]`);
      if (!input) return;
      input.readOnly = true;
      input.classList.add('bg-light','text-end');

      // 隠しミラー（raw整数）を確実に用意・同期
      let hidden = document.querySelector(`input[type="hidden"][name="${name}"]`);
      if (!hidden) {
        hidden = document.createElement('input');
        hidden.type = 'hidden';
        hidden.name = name;
        hidden.dataset.commaMirror = '1';
        (input.parentElement || document.body).appendChild(hidden);
      }
      const raw = toRawInt(input.value ?? '');
      hidden.value = raw;
    });
  });
</script>
@endpush
@push('scripts')
<script>
  document.addEventListener('DOMContentLoaded', function () {
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

    const clearOriginFields = () => {
      if (!form) {
        return;
      }
      ['origin_tab', 'origin_anchor'].forEach((name) => {
        const input = form.querySelector(`input[name="${name}"]`);
        if (input) {
          input.remove();
        }
      });
      if (form.dataset) {
        delete form.dataset.returnAnchor;
      }
    };

    if (form) {
      const setOriginFields = (anchor) => {
        if (form.dataset) {
          form.dataset.returnAnchor = anchor;
        }
        ensureHiddenField('origin_tab', 'input');
        ensureHiddenField('origin_anchor', anchor);
      };

      const detailButtons = form.querySelectorAll('.js-open-details');
      detailButtons.forEach((button) => {
        button.addEventListener('click', (event) => {
          event.preventDefault();
          const redirectTo = button.getAttribute('data-redirect-to') || '';
          const anchor = button.getAttribute('data-origin-anchor')
            || button.getAttribute('data-return-anchor')
            || '';

          if (anchor) {
            setOriginFields(anchor);
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

      const readLocationHash = () => {
        if (typeof window === 'undefined' || !window.location) {
          return '';
        }

        const { hash } = window.location;
        if (!hash || hash.length <= 1) {
          return '';
        }

        return hash.substring(1);
      };

      form.addEventListener('click', (event) => {
        const target = event.target instanceof Element ? event.target.closest('[data-return-anchor]') : null;
        if (target) {
          const anchor = target.getAttribute('data-return-anchor') || '';
          setOriginFields(anchor);
          return;
        }

        clearOriginFields();
      });

      form.addEventListener('submit', () => {
        const datasetAnchor = form.dataset && form.dataset.returnAnchor ? form.dataset.returnAnchor : '';
        if (datasetAnchor) {
          setOriginFields(datasetAnchor);
          return;
        }

        const hashAnchor = readLocationHash();
        if (hashAnchor) {
          ensureHiddenField('origin_tab', 'input');
          ensureHiddenField('origin_anchor', hashAnchor);
          return;
        }

        clearOriginFields();
      });
    }

    if (!showResultFlag && initialTab === 'input') {
      const inputTab = document.getElementById('furusato-tab-input-nav');
      if (inputTab) {
        if (typeof bootstrap !== 'undefined' && typeof bootstrap.Tab !== 'undefined') {
          bootstrap.Tab.getOrCreateInstance(inputTab).show();
        } else {
          const navContainer = inputTab.closest('.nav');
          if (navContainer) {
            navContainer.querySelectorAll('.nav-link').forEach((link) => {
              link.classList.remove('active');
            });
          }
          inputTab.classList.add('active');

          const tabPane = document.getElementById('furusato-tab-input');
          if (tabPane) {
            const tabContent = tabPane.closest('.tab-content');
            if (tabContent) {
              tabContent.querySelectorAll('.tab-pane').forEach((pane) => {
                pane.classList.remove('show', 'active');
              });
            }
            tabPane.classList.add('show', 'active');
          }
        }
      }
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

    const scrollToAnchor = () => {
      const { hash } = window.location;
      if (!hash || hash.length <= 1) {
        return;
      }

      let id = hash.substring(1);
      try {
        id = decodeURIComponent(id);
      } catch (error) {
        // ignore decode errors and use raw id
      }

      if (!id) {
        return;
      }

      const target = document.getElementById(id);
      if (!target) {
        return;
      }

      const currentScroll = typeof window.scrollY === 'number' ? window.scrollY : window.pageYOffset || 0;
      const rect = target.getBoundingClientRect();
      const top = rect.top + currentScroll - restoreScrollConfig.offsetPx;
      window.scrollTo({ top, behavior: 'auto' });

      if (restoreScrollConfig.focusMode === 'silent' && typeof target.focus === 'function') {
        target.setAttribute('data-silent-focus', '1');
        target.focus({ preventScroll: true });
        setTimeout(() => {
          if (typeof target.blur === 'function') {
            target.blur();
          }
          target.removeAttribute('data-silent-focus');
        }, 0);
      } else if (restoreScrollConfig.focusMode === 'visible' && typeof target.focus === 'function') {
        target.focus({ preventScroll: true });
      }
    };

    const scheduleScrollToAnchor = () => {
      if (typeof window.requestAnimationFrame === 'function') {
        window.requestAnimationFrame(scrollToAnchor);
      } else {
        setTimeout(scrollToAnchor, 0);
      }
    };

    if (!showResultFlag && initialTab === 'input') {
      scheduleScrollToAnchor();
    } else {
      scrollToAnchor();
    }

    // -------------------------------
    // 孤立した数値入力（<td> 外に出たもの）を隠す
    // -------------------------------
    (function sanitizeOrphanTaxInputs() {
      const formEl = document.getElementById('furusato-input-form');
      if (!formEl) return;
      // 画面表示させない対象（名前の接頭辞）
      const mustBeHiddenPrefixes = [
        'tax_kazeishotoku_', // 「課税所得金額又は第三表」行の各セル
        'tax_zeigaku_',      // 税額
      ];
      // フィールド名が対象かを判定
      const isTargetName = (name) => {
        return mustBeHiddenPrefixes.some(pref => name && name.startsWith(pref));
      };
      // フォーム内の input を走査し、<td> の外にある対象は hidden 化
      Array.from(formEl.querySelectorAll('input[name]')).forEach((el) => {
        if (!(el instanceof HTMLInputElement)) return;
        const name = el.getAttribute('name') || '';
        if (!isTargetName(name)) return;
        // テーブルセル内にいれば OK。セル外なら隠す。
        const insideCell = !!el.closest('td');
        if (!insideCell) {
          el.type = 'hidden';
          // 表示用クラスは意味がないので除去（誤表示防止）
          el.classList.remove('bg-light', 'text-end');
          // readonly は hidden では不要だが付いていても害はないためそのままでも可
        }
      });
    })();
  });
</script>
<script>
  document.addEventListener('DOMContentLoaded', function () {
    const shotokuRates = @json($shotokuRatesForScript);
    const taxTypes = ['shotoku', 'jumin'];
    const periods = ['prev', 'curr'];
    const bunriFlags = {
      prev: (Number(@json($syoriSettings['bunri_flag_prev'] ?? $syoriSettings['bunri_flag'] ?? 0)) === 1),
      curr: (Number(@json($syoriSettings['bunri_flag_curr'] ?? $syoriSettings['bunri_flag'] ?? 0)) === 1),
    };
    const shotokuGokeiBases = [
      'shotoku_jigyo_eigyo',
      'shotoku_jigyo_nogyo',
      'shotoku_fudosan',
      'shotoku_rishi',
      'shotoku_haito',
      'shotoku_kyuyo',
      'shotoku_zatsu_nenkin',
      'shotoku_zatsu_gyomu',
      'shotoku_zatsu_sonota',
      'shotoku_joto_ichiji',
    ];
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
    //    - markServerLock(name) で data-server-lock と data-server-raw を付与
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
    const markServerLock = (name) => {
      const el = getInput(name);
      if (!el) return;
      const raw = String(toInt(el.value));
      el.dataset.serverLock = '1';
      el.dataset.serverRaw = raw; // カンマ無し整数を保持
      el.readOnly = true;
      el.classList.add('bg-light','text-end');
      const hidden = ensureHiddenFor(name);
      if (hidden) hidden.value = raw;
    };
    const enforceServerLocks = () => {
      const form = document.getElementById('furusato-input-form');
      if (!form) return;
      form.querySelectorAll('input[data-server-lock="1"]').forEach((el) => {
        const name = el.name;
        const raw  = el.dataset.serverRaw || '';
        // 表示側
        el.value = raw === '' ? '' : formatComma(raw);
        // hidden 側
        const hidden = ensureHiddenFor(name);
        if (hidden) hidden.value = raw;
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
        if (!el.classList.contains('form-control-sm')) el.classList.add('form-control-sm');
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

    function mirrorRetirementToJumin() {
      ['prev', 'curr'].forEach((period) => {
        const srcIncome = `bunri_syunyu_taishoku_shotoku_${period}`;
        const dstIncome = `bunri_syunyu_taishoku_jumin_${period}`;
        writeInt(dstIncome, readInt(srcIncome));
        makeReadonlyNumber(dstIncome);

        const srcShotoku = `bunri_shotoku_taishoku_shotoku_${period}`;
        const dstShotoku = `bunri_shotoku_taishoku_jumin_${period}`;
        writeInt(dstShotoku, readInt(srcShotoku));
        makeReadonlyNumber(dstShotoku);
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
      disp.className = 'form-control form-control-compact-05 form-control form-control-compact-05-sm text-center bg-light';
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
        const taxable = readInt(`bunri_kazeishotoku_sogo_shotoku_${period}`);
        const tax = calculateShotokuTax(taxable);
        const field = `bunri_zeigaku_sogo_shotoku_${period}`;
        writeInt(field, tax);
        makeReadonlyNumber(field);
      });
    };

    const recalcBunriZeigakuShotokuAll = () => {
      ['prev', 'curr'].forEach((period) => {
        const sanTaxable = readInt(`bunri_kazeishotoku_sanrin_shotoku_${period}`);
        const san = sanTaxable <= 0 ? 0 : calcShotokuTaxByBand(sanTaxable / 5) * 5;
        writeInt(`bunri_zeigaku_sanrin_shotoku_${period}`, san);
        makeReadonlyNumber(`bunri_zeigaku_sanrin_shotoku_${period}`);

        const taTaxable = readInt(`bunri_kazeishotoku_taishoku_shotoku_${period}`);
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

        const jotoTaxable = readInt(`bunri_kazeishotoku_joto_shotoku_${period}`);
        writeInt(`bunri_zeigaku_joto_shotoku_${period}`, Math.trunc(jotoTaxable * 0.15));
        makeReadonlyNumber(`bunri_zeigaku_joto_shotoku_${period}`);

        const haitoTaxable = readInt(`bunri_kazeishotoku_haito_shotoku_${period}`);
        writeInt(`bunri_zeigaku_haito_shotoku_${period}`, Math.trunc(haitoTaxable * 0.15));
        makeReadonlyNumber(`bunri_zeigaku_haito_shotoku_${period}`);

        const sakiTaxable = readInt(`bunri_kazeishotoku_sakimono_shotoku_${period}`);
        writeInt(`bunri_zeigaku_sakimono_shotoku_${period}`, Math.trunc(sakiTaxable * 0.15));
        makeReadonlyNumber(`bunri_zeigaku_sakimono_shotoku_${period}`);
      });
    };

    const recalcBunriZeigakuJuminAll = () => {
      ['prev', 'curr'].forEach((period) => {
        writeInt(
          `bunri_zeigaku_sogo_jumin_${period}`,
          Math.trunc(readInt(`bunri_kazeishotoku_sogo_jumin_${period}`) * 0.10),
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
          Math.trunc(readInt(`bunri_kazeishotoku_joto_jumin_${period}`) * 0.05),
        );
        makeReadonlyNumber(`bunri_zeigaku_joto_jumin_${period}`);

        writeInt(
          `bunri_zeigaku_haito_jumin_${period}`,
          Math.trunc(readInt(`bunri_kazeishotoku_haito_jumin_${period}`) * 0.05),
        );
        makeReadonlyNumber(`bunri_zeigaku_haito_jumin_${period}`);

        writeInt(
          `bunri_zeigaku_sakimono_jumin_${period}`,
          Math.trunc(readInt(`bunri_kazeishotoku_sakimono_jumin_${period}`) * 0.05),
        );
        makeReadonlyNumber(`bunri_zeigaku_sakimono_jumin_${period}`);

        writeInt(
          `bunri_zeigaku_sanrin_jumin_${period}`,
          Math.trunc(readInt(`bunri_kazeishotoku_sanrin_jumin_${period}`) * 0.10),
        );
        makeReadonlyNumber(`bunri_zeigaku_sanrin_jumin_${period}`);

        writeInt(
          `bunri_zeigaku_taishoku_jumin_${period}`,
          Math.trunc(readInt(`bunri_kazeishotoku_taishoku_jumin_${period}`) * 0.10),
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
            tax === 'jumin'
              ? `bunri_zeigaku_taishoku_${tax}_${period}`
              : `bunri_zeigaku_taishoku_${tax}_${period}`,
          ];
          const sum = names.reduce((acc, name) => acc + readInt(name), 0);
          const field = `bunri_zeigaku_gokei_${tax}_${period}`;
          writeInt(field, sum);
          makeReadonlyNumber(field);
        });
      });
    };

    const recalcShotokuGokei = () => {
      taxTypes.forEach((tax) => {
        periods.forEach((period) => {
          let sum = 0;
          shotokuGokeiBases.forEach((base) => {
            const name = `${base}_${tax}_${period}`;
            sum += readInt(name);
          });
          writeInt(`shotoku_gokei_${tax}_${period}`, sum);
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
      ['prev', 'curr'].forEach((period) => {
        ['shotoku', 'jumin'].forEach((tax) => {
          const name = `tax_kazeishotoku_${tax}_${period}`;
          if (bunriFlags[period]) {
            dashify(name);
          } else {
            const g = readInt(`shotoku_gokei_${tax}_${period}`);
            const k = readInt(`kojo_gokei_${tax}_${period}`);
            undashifySetNumber(name, g - k);
          }
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
        let v = floorToThousands(sogo - sashihiki);
        writeInt(`bunri_kazeishotoku_sogo_shotoku_${period}`, v);
        let el = getInput(`bunri_kazeishotoku_sogo_shotoku_${period}`);
        if (el) { el.readOnly = true; el.classList.add('bg-light'); }

        sogo = readInt(`bunri_sogo_gokeigaku_jumin_${period}`);
        sashihiki = readInt(`bunri_sashihiki_gokei_jumin_${period}`);
        v = floorToThousands(sogo - sashihiki);
        writeInt(`bunri_kazeishotoku_sogo_jumin_${period}`, v);
        el = getInput(`bunri_kazeishotoku_sogo_jumin_${period}`);
        if (el) { el.readOnly = true; el.classList.add('bg-light'); }
      });
    };

    const recalcBunriKazeishotokuGroup = () => {
      periods.forEach((period) => {
        const calcForTax = (tax) => {
          const read = (base) => readInt(`${base}_${tax}_${period}`);
          const write = (base, value) => {
            const name = `${base}_${tax}_${period}`;
            writeInt(name, value);
            addReadonlyBg(name);
          };

          const t1 = read('bunri_shotoku_tanki_ippan');
          const t2 = read('bunri_shotoku_tanki_keigen');
          write('bunri_kazeishotoku_tanki', floorToThousandsSigned(t1 + t2));

          const c1 = read('bunri_shotoku_choki_ippan');
          const c2 = read('bunri_shotoku_choki_tokutei');
          const c3 = read('bunri_shotoku_choki_keika');
          write('bunri_kazeishotoku_choki', floorToThousandsSigned(c1 + c2 + c3));

          const j1 = read('bunri_shotoku_ippan_kabuteki_joto');
          const j2 = read('bunri_shotoku_jojo_kabuteki_joto');
          write('bunri_kazeishotoku_joto', floorToThousandsSigned(j1 + j2));

          const h1 = read('bunri_shotoku_jojo_kabuteki_haito');
          write('bunri_kazeishotoku_haito', floorToThousandsSigned(h1));

          const s1 = read('bunri_shotoku_sakimono');
          write('bunri_kazeishotoku_sakimono', floorToThousandsSigned(s1));

          const sanA = read('bunri_shotoku_sanrin');
          const sanKojo = read('kojo_gokei');
          const sanSogo = read('bunri_sogo_gokeigaku');
          const sanAdj = Math.max(0, sanKojo - sanSogo);
          write('bunri_kazeishotoku_sanrin', floorToThousandsSigned(Math.max(0, sanA - sanAdj)));

          const taA = read('bunri_shotoku_taishoku');
          const taKojo = read('kojo_gokei');
          const taSogo = read('bunri_sogo_gokeigaku');
          const sanJumin = readInt(`bunri_shotoku_sanrin_jumin_${period}`);
          const taAdj = Math.max(0, taKojo - taSogo - sanJumin);
          write('bunri_kazeishotoku_taishoku', floorToThousandsSigned(Math.max(0, taA - taAdj)));
        };

        taxTypes.forEach(calcForTax);
      });
    };

    const recalcTaxPipeline = () => {
      periods.forEach((period) => {
        taxTypes.forEach((tax) => {
          const name = `tax_zeigaku_${tax}_${period}`;
          let value = 0;
          if (bunriFlags?.[period]) {
            value = readInt(`bunri_zeigaku_gokei_${tax}_${period}`);
          } else {
            const taxable = readInt(`tax_kazeishotoku_${tax}_${period}`);
            if (tax === 'shotoku') {
              value = calcShotokuTaxByBand(taxable);
            } else {
              value = Math.trunc(taxable * 0.10);
            }
          }
          writeInt(name, value);
          makeReadonlyNumber(name);
        });

        taxTypes.forEach((tax) => {
          const zeigaku = readInt(`tax_zeigaku_${tax}_${period}`);
          const haito = readInt(`tax_haito_${tax}_${period}`);
          const jutaku = readInt(`tax_jutaku_${tax}_${period}`);
          const seitoKeyPrefix = tax === 'shotoku'
            ? 'shotokuzei_zeigakukojo_seitoto_tokubetsu'
            : 'juminzei_zeigakukojo_seitoto_tokubetsu';
          const seito = readInt(`${seitoKeyPrefix}_${period}`);
          const sashihiki = zeigaku - haito - jutaku - seito;
          const sashihikiName = `tax_sashihiki_${tax}_${period}`;
          writeInt(sashihikiName, sashihiki);
          makeReadonlyNumber(sashihikiName);
        });

        const kijunShotoku = readInt(`tax_sashihiki_shotoku_${period}`) - readInt(`tax_tokubetsu_R6_shotoku_${period}`);
        writeInt(`tax_kijun_shotoku_${period}`, kijunShotoku);
        makeReadonlyNumber(`tax_kijun_shotoku_${period}`);

        const kijunJumin = readInt(`tax_sashihiki_jumin_${period}`);
        writeInt(`tax_kijun_jumin_${period}`, kijunJumin);
        makeReadonlyNumber(`tax_kijun_jumin_${period}`);

        const fukkouShotokuName = `tax_fukkou_shotoku_${period}`;
        writeInt(fukkouShotokuName, Math.trunc(kijunShotoku * 0.021));
        makeReadonlyNumber(fukkouShotokuName);

        writeDashWithHidden(`tax_fukkou_jumin_${period}`);

        const gokeiShotoku = readInt(`tax_kijun_shotoku_${period}`) + readInt(`tax_fukkou_shotoku_${period}`);
        writeInt(`tax_gokei_shotoku_${period}`, gokeiShotoku);
        makeReadonlyNumber(`tax_gokei_shotoku_${period}`);

        writeInt(`tax_gokei_jumin_${period}`, kijunJumin);
        makeReadonlyNumber(`tax_gokei_jumin_${period}`);
      });
    };

    const runFullRecalcChain = () => {
      recalcShotokuGokei();
      recalcKojo();
      recalcTaxableSogo();
      recalcBunriSogoMirror();
      mirrorRetirementToJumin();
      recalcBunriSashihikiGokei();
      recalcBunriKazeishotokuSogo();
      recalcShotokuTaxFromMaster();
      recalcBunriZeigakuShotokuAll();
      recalcBunriZeigakuJuminAll();
      recalcZeigakuGokeiAll();
      recalcTaxPipeline();
      recalcBunriKazeishotokuGroup();
      enforceServerLocks();
    };

    // ====== ここが不足していると ReferenceError になります ======
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
          `tax_kazeishotoku_${tax}_${period}`,
          `bunri_zeigaku_gokei_${tax}_${period}`,
          `tax_zeigaku_${tax}_${period}`,
          `tax_haito_${tax}_${period}`,
          `tax_jutaku_${tax}_${period}`,
          `tax_sashihiki_${tax}_${period}`,
        ].forEach(registerTaxPipelineBlur);
      });

      registerTaxPipelineBlur(`shotokuzei_zeigakukojo_seitoto_tokubetsu_${period}`);
      registerTaxPipelineBlur(`juminzei_zeigakukojo_seitoto_tokubetsu_${period}`);
      registerTaxPipelineBlur(`tax_tokubetsu_R6_shotoku_${period}`);
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

    shotokuGokeiBases.forEach((base) => {
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
        `bunri_kazeishotoku_sanrin_shotoku_${period}`,
        `bunri_kazeishotoku_taishoku_shotoku_${period}`,
        `bunri_kazeishotoku_joto_shotoku_${period}`,
        `bunri_kazeishotoku_haito_shotoku_${period}`,
        `bunri_kazeishotoku_sakimono_shotoku_${period}`,
      ].forEach((name) => {
        const input = getInput(name);
        if (input) {
          input.addEventListener('blur', runFullRecalcChain);
        }
      });
    });

    ['prev', 'curr'].forEach((period) => {
      [
        `bunri_kazeishotoku_sanrin_jumin_${period}`,
        `bunri_kazeishotoku_taishoku_jumin_${period}`,
        `bunri_kazeishotoku_joto_jumin_${period}`,
        `bunri_kazeishotoku_haito_jumin_${period}`,
        `bunri_kazeishotoku_sakimono_jumin_${period}`,
      ].forEach((name) => {
        const input = getInput(name);
        if (input) {
          input.addEventListener('blur', runFullRecalcChain);
        }
      });
    });

    ['prev', 'curr'].forEach((period) => {
      const taxableInput = getInput(`bunri_kazeishotoku_sogo_shotoku_${period}`);
      if (taxableInput) {
        taxableInput.addEventListener('blur', () => {
          recalcShotokuTaxFromMaster();
          recalcZeigakuGokeiAll();
        });
      }
    });

    ['prev', 'curr'].forEach((period) => {
      const taxableInput = getInput(`bunri_kazeishotoku_sogo_jumin_${period}`);
      if (taxableInput) {
        taxableInput.addEventListener('blur', () => {
          recalcBunriZeigakuJuminAll();
          recalcZeigakuGokeiAll();
        });
      }
    });

    ['prev', 'curr'].forEach((period) => {
      const incomeInput = getInput(`bunri_syunyu_taishoku_shotoku_${period}`);
      const shotokuInput = getInput(`bunri_shotoku_taishoku_shotoku_${period}`);
      if (incomeInput) {
        incomeInput.addEventListener('blur', () => {
          mirrorRetirementToJumin();
          runFullRecalcChain();
        });
      }
      if (shotokuInput) {
        shotokuInput.addEventListener('blur', () => {
          mirrorRetirementToJumin();
          runFullRecalcChain();
        });
      }
    });

    runFullRecalcChain();
    recalcBunriZeigakuJuminAll();
    recalcZeigakuGokeiAll();
    recalcTaxPipeline();
    mirrorRetirementToJumin();
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
    ];

    // 1) 画面読み込み直後にも強制同期（サーバ再計算後の再描画でも反映）
    bulkMirrorNow(kyuyoNenkinShotokuPairs);
    // ロック再適用
    enforceServerLocks();

    // 2) 再計算ボタン押下直前にも強制同期してから送信
    const formEl = document.getElementById('furusato-input-form');
    // ===== 3桁カンマ維持のまま数値を安全送信する仕組み =====
    // 送信直前に、表示用input（カンマ付き）の name を外し、
    // 同名の hidden を生成してカンマ除去数値をPOSTする
    function materializeHiddenNumericForSubmit(form) {
      const namedInputs = Array.from(form.querySelectorAll('input[name]'))
        .filter(el => el.type === 'text' && el.name && !el.disabled);
      namedInputs.forEach((el) => {
        // ★ ロック済みは name を外さない（hidden だけを最新rawに同期）
        if (el.dataset && el.dataset.serverLock === '1') {
          const raw = String(toInt(el.value));
          let hidden = form.querySelector(`input[type="hidden"][name="${el.name}"]`);
          if (!hidden) {
            hidden = document.createElement('input');
            hidden.type = 'hidden';
            hidden.name = el.name;
            form.appendChild(hidden);
          }
          hidden.value = raw;
          return; // ← name は保持
        }
        const name = el.name;
        // 既存hiddenのクリーンアップ（重複防止）
        Array.from(form.querySelectorAll(`input[type="hidden"][name="${name}"]`)).forEach(h => h.remove());
        const hidden = document.createElement('input');
        hidden.type = 'hidden';
        hidden.name = name;
        hidden.value = String(toInt(el.value));
        el.removeAttribute('name'); // 表示側はnameを外して見た目はそのまま
        el.setAttribute('data-name-detached', name); // 送信後の復元用（遷移が走らなくても）
        form.appendChild(hidden);
      });
    }

    // blur時に3桁カンマ化
    function attachCommaBlurFormatting(root) {
      Array.from(root.querySelectorAll('input.js-comma')).forEach((el) => {
        el.addEventListener('blur', () => {
          el.value = el.value === '－' ? '－' : formatComma(el.value);
        });
      });
    }
    attachCommaBlurFormatting(document);

    // 初期表示時に全てカンマ整形（既にカンマ無し数値が入っている場合）
    Array.from(document.querySelectorAll('input.js-comma[name]')).forEach((el) => {
      if (el.readOnly) {
        el.value = el.value === '－' ? '－' : formatComma(el.value);
      } else {
        // 入力中のUXのため、空文字は触らない
        if (String(el.value).trim() !== '') {
          el.value = formatComma(el.value);
        }
      }
    });

    // ─────────────────────────────────────────────────────
    // ③ ロック対象をマーク（サーバ注入値を上書き不可にする）→ 初回再計算の“前”に実行すること！
    //    ※ prev/curr 両方、所得税/住民税 両方
    // ─────────────────────────────────────────────────────
    (function markAllServerLocksBeforeRecalc() {
      ['prev','curr'].forEach((p) => {
        ['shotoku','jumin'].forEach((tax) => {
          markServerLock(`shotoku_gokei_${tax}_${p}`);
        });
        ['tanki','choki'].forEach((kind) => {
          ['shotoku','jumin'].forEach((tax) => {
            markServerLock(`bunri_kazeishotoku_${kind}_${tax}_${p}`);
          });
        });
      });
      enforceServerLocks(); // ロック値を即時反映
    })();

    if (formEl) {
      formEl.addEventListener('submit', (e) => {
        recalcShotokuGokei();
        recalcKojo();
        recalcTaxableSogo();
        recalcBunriSogoMirror();
        mirrorRetirementToJumin();
        recalcBunriSashihikiGokei();
        recalcBunriKazeishotokuSogo();
        recalcShotokuTaxFromMaster();
        recalcBunriZeigakuShotokuAll();
        recalcBunriZeigakuJuminAll();
        recalcZeigakuGokeiAll();
        recalcTaxPipeline();
        recalcBunriKazeishotokuGroup();
        enforceServerLocks();
        materializeHiddenNumericForSubmit(formEl);
        // 念のため送信直前にも孤立要素を hidden 化
        (function sanitizeOrphanTaxInputsOnSubmit() {
          const mustBeHiddenPrefixes = ['tax_kazeishotoku_', 'tax_zeigaku_'];
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
        // 送信トリガーのボタンを判定（再計算以外でも同期しておくと安全）
        const submitter = (e.submitter && e.submitter instanceof Element) ? e.submitter : null;
        const isRecalc = submitter
          ? (submitter.getAttribute('name') === 'recalc_all' || submitter.dataset.redirectTo === 'input')
          : false;
        // 再計算に限らず常に同期しておく（保存でもズレを防止）
        bulkMirrorNow(kyuyoNenkinShotokuPairs);
      });
    }

    // 収入（金額等）系：所得税→住民税（prev）
    mirrorOnBlur('syunyu_jigyo_nogyo_shotoku_prev',  'syunyu_jigyo_nogyo_jumin_prev');
    mirrorOnBlur('shotoku_rishi_shotoku_prev',       'shotoku_rishi_jumin_prev');
    mirrorOnBlur('syunyu_haito_shotoku_prev',        'syunyu_haito_jumin_prev');
    mirrorOnBlur('syunyu_kyuyo_shotoku_prev',        'syunyu_kyuyo_jumin_prev');           // 給与
    mirrorOnBlur('syunyu_zatsu_nenkin_shotoku_prev', 'syunyu_zatsu_nenkin_jumin_prev');    // 公的年金等
    mirrorOnBlur('syunyu_zatsu_gyomu_shotoku_prev',  'syunyu_zatsu_gyomu_jumin_prev');
    mirrorOnBlur('syunyu_zatsu_sonota_shotoku_prev', 'syunyu_zatsu_sonota_jumin_prev');
    // 所得（控除後）系：所得税→住民税（prev）
    mirrorOnBlur('shotoku_jigyo_nogyo_shotoku_prev',  'shotoku_jigyo_nogyo_jumin_prev');
    mirrorOnBlur('shotoku_haito_shotoku_prev',        'shotoku_haito_jumin_prev');
    mirrorOnBlur('shotoku_kyuyo_shotoku_prev',        'shotoku_kyuyo_jumin_prev');         // 給与
    mirrorOnBlur('shotoku_zatsu_nenkin_shotoku_prev', 'shotoku_zatsu_nenkin_jumin_prev');  // 公的年金等
    mirrorOnBlur('shotoku_zatsu_gyomu_shotoku_prev',  'shotoku_zatsu_gyomu_jumin_prev');
    mirrorOnBlur('shotoku_zatsu_sonota_shotoku_prev', 'shotoku_zatsu_sonota_jumin_prev');    

    // 収入（金額等）系：所得税→住民税（curr）
    mirrorOnBlur('syunyu_jigyo_nogyo_shotoku_curr',  'syunyu_jigyo_nogyo_jumin_curr');
    mirrorOnBlur('shotoku_rishi_shotoku_curr',       'shotoku_rishi_jumin_curr');
    mirrorOnBlur('syunyu_haito_shotoku_curr',        'syunyu_haito_jumin_curr');
    mirrorOnBlur('syunyu_kyuyo_shotoku_curr',        'syunyu_kyuyo_jumin_curr');           // 給与
    mirrorOnBlur('syunyu_zatsu_nenkin_shotoku_curr', 'syunyu_zatsu_nenkin_jumin_curr');    // 公的年金等
    mirrorOnBlur('syunyu_zatsu_gyomu_shotoku_curr',  'syunyu_zatsu_gyomu_jumin_curr');
    mirrorOnBlur('syunyu_zatsu_sonota_shotoku_curr', 'syunyu_zatsu_sonota_jumin_curr');

    // 所得（控除後）系：所得税→住民税（curr）
    mirrorOnBlur('shotoku_jigyo_nogyo_shotoku_curr',  'shotoku_jigyo_nogyo_jumin_curr');
    mirrorOnBlur('shotoku_haito_shotoku_curr',        'shotoku_haito_jumin_curr');
    mirrorOnBlur('shotoku_kyuyo_shotoku_curr',        'shotoku_kyuyo_jumin_curr');         // 給与
    mirrorOnBlur('shotoku_zatsu_nenkin_shotoku_curr', 'shotoku_zatsu_nenkin_jumin_curr');  // 公的年金等
    mirrorOnBlur('shotoku_zatsu_gyomu_shotoku_curr',  'shotoku_zatsu_gyomu_jumin_curr');
    mirrorOnBlur('shotoku_zatsu_sonota_shotoku_curr', 'shotoku_zatsu_sonota_jumin_curr');
  });
</script>
@endpush