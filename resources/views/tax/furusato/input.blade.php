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
<div class="container-blue" style="width: 1200px;">
  <form method="POST" action="{{ route('furusato.save') }}" id="furusato-input-form">
    @csrf
    <input type="hidden" name="data_id" value="{{ $dataId ?? '' }}">
    <input type="hidden" name="redirect_to" value="input">
    <input type="hidden" name="show_result" value="1">

    @php
      $resultsData = $results ?? [];
      $jintekiDiffData = $jintekiDiff ?? [];
      $showResultFlag = (bool) ($showResult ?? false);
      $tokureiStandardRateData = $tokureiStandardRate ?? [];
      $tokureiComputedPercentData = $tokureiComputedPercent ?? [];
      $tokureiEnabledData = $tokureiEnabled ?? [];
      $warekiPrevLabel = $warekiPrev ?? '前年';
      $warekiCurrLabel = $warekiCurr ?? '当年';
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
          <button type="submit" class="btn-base-blue" formnovalidate>保 存</button>
          <button type="submit" class="btn-base-green" name="recalc_all" value="1">再 計 算</button>
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
            $inputs = $out['inputs'] ?? [];
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
                'bunri_syunyu_taishoku',
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
                'syunyu_ichiji',
                'shotoku_jigyo_eigyo',
                'shotoku_fudosan',
                'shotoku_joto_ichiji',
                'shotoku_gokei',
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
            $renderInputs = static function (string $base) use ($inputs, $readonlyBases, $kojoFieldOverrides, $kihuYear, $forceDash) {
                $html = '';
                foreach (['shotoku' => ['prev', 'curr'], 'jumin' => ['prev', 'curr']] as $tax => $periods) {
                    foreach ($periods as $period) {
                        $format = $kojoFieldOverrides[$base][$tax] ?? null;
                        $name = $format ? sprintf($format, $period) : sprintf('%s_%s_%s', $base, $tax, $period);
                        $value = old($name, $inputs[$name] ?? null);
                        $kihuYearInt = isset($kihuYear) ? (int) $kihuYear : null;
                        $isForceDash = $forceDash($base, $tax, $period, $kihuYearInt);
                        $isReadonly = false;

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
                            $html .= '<td><input type="text" class="form-control form-control-sm text-center bg-light" value="－" readonly><input type="hidden" name="' . e($name) . '" value=""></td>';
                        } else {
                            $readonlyAttr = $isReadonly ? ' readonly' : '';
                            $class = 'form-control form-control-sm text-end';
                            if ($isReadonly) {
                                $class .= ' bg-light';
                            }
                            $html .= '<td><input type="number" min="0" step="1" class="' . e($class) . '" name="' . e($name) . '" value="' . e($value) . '"' . $readonlyAttr . '></td>';
                        }
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
          <hb class="card-title mb-3">確定申告書(総合課税)</hb>
          <div class="table-responsive">
            <table class="table table-base align-middle">
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
                            value="kihukin_details"
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
                  <th colspan="3" class="text-start align-middle ps-1">課税所得金額</th>
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
  
      @if ((int) ($bunriFlag ?? 0) === 1)
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
                  <hb class="card-title mb-3">確定申告書(分離課税)</hb>
                  <div class="table-responsive">
                    <table class="table table-base align-middle">
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
                          <td class="text-center align-middle" rowspan="3">
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
                          <th scope="row" colspan="3" class="align-middle text-start ps-1">総合課税の合計額</th>
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
                                <input type="number" min="0" step="1" class="form-control form-control-sm text-end" name="{{ $name }}" value="{{ $value }}">
                              </td>
                            @endforeach
                          @endforeach
                        </tr>
                        <tr>
                          <th scope="row" colspan="3" class="align-middle text-start ps-1">所得から差し引かれる金額</th>
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
                                <input type="number" min="0" step="1" class="form-control form-control-sm text-end" name="{{ $name }}" value="{{ $value }}">
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
                                <input type="number" min="0" step="1" class="form-control form-control-sm text-end" name="{{ $name }}" value="{{ $value }}">
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
                          {!! $renderInputs('bunri_kazeishotoku_tanki') !!}
                        </tr>
                        <tr>
                          <th scope="row" colspan="2" class="align-middle text-start ps-1 th-ddd">長期譲渡</th>
                          <td class="text-center align-middle">
                            <button type="button" class="btn btn-link btn-sm px-0">HELP</button>
                          </td>
                          <td></td>
                          {!! $renderInputs('bunri_kazeishotoku_choki') !!}
                        </tr>
                        <tr>
                          <th scope="row" colspan="2" class="align-middle text-start ps-1 pe-1 th-ddd" nowrap="nowrap">一般・上場株式の譲渡</th>
                          <td class="text-center align-middle">
                            <button type="button" class="btn btn-link btn-sm px-0">HELP</button>
                          </td>
                          <td></td>
                          @foreach (['shotoku' => ['prev', 'curr'], 'jumin' => ['prev', 'curr']] as $tax => $periods)
                            @foreach ($periods as $period)
                              @php
                                $name = sprintf('bunri_kazeishotoku_joto_%s_%s', $tax, $period);
                                $value = old($name, $inputs[$name] ?? null);
                              @endphp
                              <td>
                                <input type="number" min="0" step="1" class="form-control form-control-sm text-end" name="{{ $name }}" value="{{ $value }}">
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
                                $name = sprintf('bunri_kazeishotoku_haito_%s_%s', $tax, $period);
                                $value = old($name, $inputs[$name] ?? null);
                              @endphp
                              <td>
                                <input type="number" min="0" step="1" class="form-control form-control-sm text-end" name="{{ $name }}" value="{{ $value }}">
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
                                $name = sprintf('bunri_kazeishotoku_sakimono_%s_%s', $tax, $period);
                                $value = old($name, $inputs[$name] ?? null);
                              @endphp
                              <td>
                                <input type="number" min="0" step="1" class="form-control form-control-sm text-end" name="{{ $name }}" value="{{ $value }}">
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
                                $name = sprintf('bunri_kazeishotoku_sanrin_%s_%s', $tax, $period);
                                $value = old($name, $inputs[$name] ?? null);
                              @endphp
                              <td>
                                <input type="number" min="0" step="1" class="form-control form-control-sm text-end" name="{{ $name }}" value="{{ $value }}">
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
                                $name = sprintf('bunri_kazeishotoku_taishoku_%s_%s', $tax, $period);
                                $value = old($name, $inputs[$name] ?? null);
                              @endphp
                              <td>
                                <input type="number" min="0" step="1" class="form-control form-control-sm text-end" name="{{ $name }}" value="{{ $value }}">
                              </td>
                            @endforeach
                          @endforeach
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
                                <input type="number" min="0" step="1" class="form-control form-control-sm text-end" name="{{ $name }}" value="{{ $value }}">
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
                                <input type="number" min="0" step="1" class="form-control form-control-sm text-end" name="{{ $name }}" value="{{ $value }}">
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
                                <input type="number" min="0" step="1" class="form-control form-control-sm text-end" name="{{ $name }}" value="{{ $value }}">
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
                                <input type="number" min="0" step="1" class="form-control form-control-sm text-end" name="{{ $name }}" value="{{ $value }}">
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
                                <input type="number" min="0" step="1" class="form-control form-control-sm text-end" name="{{ $name }}" value="{{ $value }}">
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
                                <input type="number" min="0" step="1" class="form-control form-control-sm text-end" name="{{ $name }}" value="{{ $value }}">
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
                                <input type="number" min="0" step="1" class="form-control form-control-sm text-end" name="{{ $name }}" value="{{ $value }}">
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
                                <input type="number" min="0" step="1" class="form-control form-control-sm text-end" name="{{ $name }}" value="{{ $value }}">
                              </td>
                            @endforeach
                          @endforeach
                        </tr>
                        <tr>
                          <th scope="row" colspan="3" class="align-middle text-center th-cream">税額合計</th>
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
                                <input type="number" min="0" step="1" class="form-control form-control-sm text-end" name="{{ $name }}" value="{{ $value }}">
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
  });
</script>
<script>
  document.addEventListener('DOMContentLoaded', function () {
    const shotokuRates = @json($shotokuRatesForScript);
    const taxTypes = ['shotoku', 'jumin'];
    const periods = ['prev', 'curr'];
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
    const readInt = (name) => {
      const input = getInput(name);
      if (!input) {
        return 0;
      }
      const value = Number(input.value);
      return Number.isFinite(value) ? Math.trunc(value) : 0;
    };
    const writeInt = (name, value) => {
      const input = getInput(name);
      if (input) {
        const numeric = Number.isFinite(value) ? Math.trunc(value) : 0;
        input.value = numeric;
      }
    };

    const resolveKojoFieldName = (base, tax, period) => {
      const override = kojoFieldOverrides[base]?.[tax];
      if (override) {
        return `${override}${period}`;
      }

      return `${base}_${tax}_${period}`;
    };

    const findShotokuRate = (amount) => {
      for (const rate of shotokuRates) {
        const lower = Number(rate.lower ?? 0);
        const upper = rate.upper === null || rate.upper === undefined ? null : Number(rate.upper);
        if (amount >= lower && (upper === null || amount <= upper)) {
          return rate;
        }
      }
      return null;
    };

    const calculateShotokuTax = (amount) => {
      const taxable = Math.max(0, Math.trunc(Number(amount) || 0));
      const matched = findShotokuRate(taxable);
      if (!matched) {
        return 0;
      }
      const rateDecimal = Number(matched.rate ?? 0) / 100;
      const deduction = Number(matched.deduction_amount ?? 0);
      const raw = taxable * rateDecimal - deduction;
      return Number.isFinite(raw) ? Math.trunc(raw) : 0;
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

    const recalcTax = () => {
      periods.forEach((period) => {
        const shotokuAmount = readInt(`tax_kazeishotoku_shotoku_${period}`);
        writeInt(`tax_zeigaku_shotoku_${period}`, calculateShotokuTax(shotokuAmount));

        const juminAmount = Math.max(0, readInt(`tax_kazeishotoku_jumin_${period}`));
        writeInt(`tax_zeigaku_jumin_${period}`, juminAmount * 0.1);
      });
    };

    const kojoBasesForEvents = kojoShokeiBases.concat(kojoGokeiExtras);
    kojoBasesForEvents.forEach((base) => {
      taxTypes.forEach((tax) => {
        periods.forEach((period) => {
          const input = getInput(resolveKojoFieldName(base, tax, period));
          if (input) {
            input.addEventListener('blur', recalcKojo);
          }
        });
      });
    });

    taxTypes.forEach((tax) => {
      periods.forEach((period) => {
        const input = getInput(`tax_kazeishotoku_${tax}_${period}`);
        if (input) {
          input.addEventListener('blur', recalcTax);
        }
      });
    });

    recalcKojo();
    recalcTax();
  });
</script>
@endpush