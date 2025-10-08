@extends('layouts.min')

@section('content')
<div class="container" style="min-width: 960px; max-width: 1080px;">
  <form method="POST" action="{{ route('furusato.save') }}" id="furusato-input-form">
    @csrf
    <input type="hidden" name="data_id" value="{{ $dataId ?? '' }}">
    <input type="hidden" name="redirect_to" value="input">
    <input type="hidden" name="show_result" value="1">

    @php
      $resultsData = $results ?? [];
      $showResultFlag = (bool) ($showResult ?? false);
      $inputTabActiveClass = $showResultFlag ? '' : 'active';
      $inputPaneActiveClass = $showResultFlag ? '' : 'show active';
      $detailsTabActiveClass = $showResultFlag ? 'active' : '';
      $detailsPaneActiveClass = $showResultFlag ? 'show active' : '';
    @endphp

    <div class="d-flex justify-content-between align-items-center mb-3">
      <h5 class="mb-0">ふるさと納税：インプット表</h5>
      <div class="d-flex flex-wrap justify-content-end gap-2">
        <button type="submit"
                class="btn btn-outline-secondary btn-sm"
                formnovalidate
                name="redirect_to"
                value="syori">戻る</button>
        <button type="submit"
                class="btn btn-outline-secondary btn-sm"
                formnovalidate
                name="redirect_to"
                value="master">マスター</button>
        <button type="submit" class="btn btn-success btn-sm" formnovalidate>保存</button>
        <button type="submit" class="btn btn-primary btn-sm">送信</button>
      </div>
    </div>

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
        <button class="nav-link {{ $inputTabActiveClass }}" id="furusato-tab-input-nav" data-bs-toggle="tab" data-bs-target="#furusato-tab-input" type="button" role="tab" aria-controls="furusato-tab-input" aria-selected="{{ $showResultFlag ? 'false' : 'true' }}">入力</button>
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
          $renderInputs = static function (string $base) use ($inputs) {
              $html = '';
              foreach (['shotoku' => ['prev', 'curr'], 'jumin' => ['prev', 'curr']] as $tax => $periods) {
                  foreach ($periods as $period) {
                      $name = sprintf('%s_%s_%s', $base, $tax, $period);
                      $value = old($name, $inputs[$name] ?? null);
                      $html .= '<td><input type="number" min="0" step="1" class="form-control form-control-sm text-end" name="' . e($name) . '" value="' . e($value) . '"></td>';
                  }
              }

              return $html;
          };
          $syunyuRowspan = 11;
          $shotokuRowspan = 13;
          $kojoRowspan = 17;
          $taxRowspan = $showTokubetsu ? 10 : 9;
        @endphp

        @php ob_start(); @endphp
      <div>
        <h5 class="card-title mb-3">確定申告書(総合課税)</h5>
        <div class="table-responsive">
          <table class="table table-bordered table-sm align-middle mb-0">
            <thead class="table-light text-center align-middle">
              <tr>
                <th rowspan="2" colspan="4">項目</th>
                <th rowspan="2" colspan="2"></th>
                <th colspan="2">所得税</th>
                <th colspan="2">住民税</th>
              </tr>
              <tr>
                <th>{{ $warekiPrevLabel }}</th>
                <th>{{ $warekiCurrLabel }}</th>
                <th>{{ $warekiPrevLabel }}</th>
                <th>{{ $warekiCurrLabel }}</th>
              </tr>
            </thead>
            <tbody>
              <tr>
                <th scope="rowgroup" rowspan="{{ $syunyuRowspan }}" class="text-center align-middle bg-light">収入金額等</th>
                <th rowspan="2" class="text-center align-middle">事業</th>
                <th colspan="2" class="align-middle">営業等</th>
                <td class="text-center align-middle">
                  <button type="button" class="btn btn-link btn-sm px-0">HELP</button>
                </td>
                <td class="text-center align-middle">
                  <button type="submit" class="btn btn-outline-secondary btn-sm" name="redirect_to" value="jigyo">内訳</button>
                </td>
                {!! $renderInputs('syunyu_jigyo_eigyo') !!}
              </tr>
              <tr>
                <th colspan="2" class="align-middle">農業</th>
                <td class="text-center align-middle">
                  <button type="button" class="btn btn-link btn-sm px-0">HELP</button>
                </td>
                <td class="text-center align-middle"></td>
                {!! $renderInputs('syunyu_jigyo_nogyo') !!}
              </tr>
              <tr>
                <th colspan="3" class="align-middle">不動産</th>
                <td class="text-center align-middle">
                  <button type="button" class="btn btn-link btn-sm px-0">HELP</button>
                </td>
                <td class="text-center align-middle">
                  <button type="submit" class="btn btn-outline-secondary btn-sm" name="redirect_to" value="fudosan">内訳</button>
                </td>
                {!! $renderInputs('syunyu_fudosan') !!}
              </tr>
              <tr>
                <th colspan="3" class="align-middle">配当</th>
                <td class="text-center align-middle">
                  <button type="button" class="btn btn-link btn-sm px-0">HELP</button>
                </td>
                <td class="text-center align-middle"></td>
                {!! $renderInputs('syunyu_haito') !!}
              </tr>
              <tr>
                <th colspan="3" class="align-middle">給与</th>
                <td class="text-center align-middle">
                  <button type="button" class="btn btn-link btn-sm px-0">HELP</button>
                </td>
                <td class="text-center align-middle"></td>
                {!! $renderInputs('syunyu_kyuyo') !!}
              </tr>
              <tr>
                <th rowspan="3" class="text-center align-middle">雑</th>
                <th colspan="2" class="align-middle">公的年金等</th>
                <td class="text-center align-middle">
                  <button type="button" class="btn btn-link btn-sm px-0">HELP</button>
                </td>
                <td class="text-center align-middle"></td>
                {!! $renderInputs('syunyu_zatsu_nenkin') !!}
              </tr>
              <tr>
                <th colspan="2" class="align-middle">業務</th>
                <td class="text-center align-middle">
                  <button type="button" class="btn btn-link btn-sm px-0">HELP</button>
                </td>
                <td class="text-center align-middle"></td>
                {!! $renderInputs('syunyu_zatsu_gyomu') !!}
              </tr>
              <tr>
                <th colspan="2" class="align-middle">その他</th>
                <td class="text-center align-middle">
                  <button type="button" class="btn btn-link btn-sm px-0">HELP</button>
                </td>
                <td class="text-center align-middle"></td>
                {!! $renderInputs('syunyu_zatsu_sonota') !!}
              </tr>
              <tr>
                <th rowspan="2" class="text-center align-middle">譲渡</th>
                <th colspan="2" class="align-middle">短期</th>
                <td class="text-center align-middle">
                  <button type="button" class="btn btn-link btn-sm px-0">HELP</button>
                </td>
                <td class="text-center align-middle"></td>
                {!! $renderInputs('syunyu_joto_tanki') !!}
              </tr>
              <tr>
                <th colspan="2" class="align-middle">長期</th>
                <td class="text-center align-middle">
                  <button type="button" class="btn btn-link btn-sm px-0">HELP</button>
                </td>
                <td class="text-center align-middle"></td>
                {!! $renderInputs('syunyu_joto_choki') !!}
              </tr>
              <tr>
                <th colspan="3" class="align-middle">一時</th>
                <td class="text-center align-middle">
                  <button type="button" class="btn btn-link btn-sm px-0">HELP</button>
                </td>
                <td class="text-center align-middle"></td>
                {!! $renderInputs('syunyu_ichiji') !!}
              </tr>
              <tr>
                <th scope="rowgroup" rowspan="{{ $shotokuRowspan }}" class="text-center align-middle bg-light">所得金額等</th>
                <th rowspan="2" class="text-center align-middle">事業</th>
                <th colspan="2" class="align-middle">営業等</th>
                <td class="text-center align-middle">
                  <button type="button" class="btn btn-link btn-sm px-0">HELP</button>
                </td>
                <td class="text-center align-middle">
                  <button type="submit" class="btn btn-outline-secondary btn-sm" name="redirect_to" value="jigyo">内訳</button>
                </td>
                {!! $renderInputs('shotoku_jigyo_eigyo') !!}
              </tr>
              <tr>
                <th colspan="2" class="align-middle">農業</th>
                <td class="text-center align-middle">
                  <button type="button" class="btn btn-link btn-sm px-0">HELP</button>
                </td>
                <td class="text-center align-middle"></td>
                {!! $renderInputs('shotoku_jigyo_nogyo') !!}
              </tr>
              <tr>
                <th colspan="3" class="align-middle">不動産</th>
                <td class="text-center align-middle">
                  <button type="button" class="btn btn-link btn-sm px-0">HELP</button>
                </td>
                <td class="text-center align-middle">
                  <button type="submit" class="btn btn-outline-secondary btn-sm" name="redirect_to" value="fudosan">内訳</button>
                </td>
                {!! $renderInputs('shotoku_fudosan') !!}
              </tr>
              <tr>
                <th colspan="3" class="align-middle">利子</th>
                <td class="text-center align-middle">
                  <button type="button" class="btn btn-link btn-sm px-0">HELP</button>
                </td>
                <td class="text-center align-middle"></td>
                {!! $renderInputs('shotoku_rishi') !!}
              </tr>
              <tr>
                <th colspan="3" class="align-middle">配当</th>
                <td class="text-center align-middle">
                  <button type="button" class="btn btn-link btn-sm px-0">HELP</button>
                </td>
                <td class="text-center align-middle"></td>
                {!! $renderInputs('shotoku_haito') !!}
              </tr>
              <tr>
                <th colspan="3" class="align-middle">給与</th>
                <td class="text-center align-middle">
                  <button type="button" class="btn btn-link btn-sm px-0">HELP</button>
                </td>
                <td class="text-center align-middle"></td>
                {!! $renderInputs('shotoku_kyuyo') !!}
              </tr>
              <tr>
                <th rowspan="3" class="text-center align-middle">雑</th>
                <th colspan="2" class="align-middle">公的年金等</th>
                <td class="text-center align-middle">
                  <button type="button" class="btn btn-link btn-sm px-0">HELP</button>
                </td>
                <td class="text-center align-middle"></td>
                {!! $renderInputs('shotoku_zatsu_nenkin') !!}
              </tr>
              <tr>
                <th colspan="2" class="align-middle">業務</th>
                <td class="text-center align-middle">
                  <button type="button" class="btn btn-link btn-sm px-0">HELP</button>
                </td>
                <td class="text-center align-middle"></td>
                {!! $renderInputs('shotoku_zatsu_gyomu') !!}
              </tr>
              <tr>
                <th colspan="2" class="align-middle">その他</th>
                <td class="text-center align-middle">
                  <button type="button" class="btn btn-link btn-sm px-0">HELP</button>
                </td>
                <td class="text-center align-middle"></td>
                {!! $renderInputs('shotoku_zatsu_sonota') !!}
              </tr>
              <tr>
                <th rowspan="2" class="text-center align-middle">譲渡</th>
                <th colspan="2" class="align-middle">短期</th>
                <td class="text-center align-middle">
                  <button type="button" class="btn btn-link btn-sm px-0">HELP</button>
                </td>
                <td class="text-center align-middle"></td>
                {!! $renderInputs('shotoku_joto_tanki') !!}
              </tr>
              <tr>
                <th colspan="2" class="align-middle">長期</th>
                <td class="text-center align-middle">
                  <button type="button" class="btn btn-link btn-sm px-0">HELP</button>
                </td>
                <td class="text-center align-middle"></td>
                {!! $renderInputs('shotoku_joto_choki') !!}
              </tr>
              <tr>
                <th colspan="3" class="align-middle">一時</th>
                <td class="text-center align-middle">
                  <button type="button" class="btn btn-link btn-sm px-0">HELP</button>
                </td>
                <td class="text-center align-middle"></td>
                {!! $renderInputs('shotoku_ichiji') !!}
              </tr>
              <tr>
                <th colspan="3" class="align-middle">合計</th>
                <td colspan="2" class="text-center align-middle"></td>
                {!! $renderInputs('shotoku_gokei') !!}
              </tr>
              <tr>
                <th scope="rowgroup" rowspan="{{ $kojoRowspan }}" class="text-center align-middle bg-light">所得から差し引かれる金額</th>
                <th colspan="3" class="align-middle">社会保険料控除</th>
                <td class="text-center align-middle">
                  <button type="button" class="btn btn-link btn-sm px-0">HELP</button>
                </td>
                <td class="text-center align-middle"></td>
                {!! $renderInputs('kojo_shakaihoken') !!}
              </tr>
              <tr>
                <th colspan="3" class="align-middle">小規模企業共済等掛金控除</th>
                <td class="text-center align-middle">
                  <button type="button" class="btn btn-link btn-sm px-0">HELP</button>
                </td>
                <td class="text-center align-middle"></td>
                {!! $renderInputs('kojo_shokibo') !!}
              </tr>
              <tr>
                <th colspan="3" class="align-middle">生命保険料控除</th>
                <td class="text-center align-middle">
                  <button type="button" class="btn btn-link btn-sm px-0">HELP</button>
                </td>
                <td class="text-center align-middle">
                  <button type="button" class="btn btn-outline-secondary btn-sm">内訳</button>
                </td>
                {!! $renderInputs('kojo_seimei') !!}
              </tr>
              <tr>
                <th colspan="3" class="align-middle">地震保険料控除</th>
                <td class="text-center align-middle">
                  <button type="button" class="btn btn-link btn-sm px-0">HELP</button>
                </td>
                <td class="text-center align-middle">
                  <button type="button" class="btn btn-outline-secondary btn-sm">内訳</button>
                </td>
                {!! $renderInputs('kojo_jishin') !!}
              </tr>
              <tr>
                <th colspan="3" class="align-middle">寡婦控除</th>
                <td class="text-center align-middle">
                  <button type="button" class="btn btn-link btn-sm px-0">HELP</button>
                </td>
                <td class="text-center align-middle"></td>
                {!! $renderInputs('kojo_kafu') !!}
              </tr>
              <tr>
                <th colspan="3" class="align-middle">ひとり親控除</th>
                <td class="text-center align-middle">
                  <button type="button" class="btn btn-link btn-sm px-0">HELP</button>
                </td>
                <td class="text-center align-middle"></td>
                {!! $renderInputs('kojo_hitorioya') !!}
              </tr>
              <tr>
                <th colspan="3" class="align-middle">勤労学生控除</th>
                <td class="text-center align-middle">
                  <button type="button" class="btn btn-link btn-sm px-0">HELP</button>
                </td>
                <td class="text-center align-middle"></td>
                {!! $renderInputs('kojo_kinrogakusei') !!}
              </tr>
              <tr>
                <th colspan="3" class="align-middle">障害者控除</th>
                <td class="text-center align-middle">
                  <button type="button" class="btn btn-link btn-sm px-0">HELP</button>
                </td>
                <td class="text-center align-middle">
                  <button type="button" class="btn btn-outline-secondary btn-sm">内訳</button>
                </td>
                {!! $renderInputs('kojo_shogaisha') !!}
              </tr>
              <tr>
                <th colspan="3" class="align-middle">配偶者控除</th>
                <td class="text-center align-middle">
                  <button type="button" class="btn btn-link btn-sm px-0">HELP</button>
                </td>
                <td class="text-center align-middle">
                  <button type="button" class="btn btn-outline-secondary btn-sm">内訳</button>
                </td>
                {!! $renderInputs('kojo_haigusha') !!}
              </tr>
              <tr>
                <th colspan="3" class="align-middle">配偶者特別控除</th>
                <td class="text-center align-middle">
                  <button type="button" class="btn btn-link btn-sm px-0">HELP</button>
                </td>
                <td class="text-center align-middle">
                  <button type="button" class="btn btn-outline-secondary btn-sm">内訳</button>
                </td>
                {!! $renderInputs('kojo_haigusha_tokubetsu') !!}
              </tr>
              <tr>
                <th colspan="3" class="align-middle">扶養控除</th>
                <td class="text-center align-middle">
                  <button type="button" class="btn btn-link btn-sm px-0">HELP</button>
                </td>
                <td class="text-center align-middle">
                  <button type="button" class="btn btn-outline-secondary btn-sm">内訳</button>
                </td>
                {!! $renderInputs('kojo_fuyo') !!}
              </tr>
              <tr>
                <th colspan="3" class="align-middle">基礎控除</th>
                <td class="text-center align-middle">
                  <button type="button" class="btn btn-link btn-sm px-0">HELP</button>
                </td>
                <td class="text-center align-middle"></td>
                {!! $renderInputs('kojo_kiso') !!}
              </tr>
              <tr>
                <th colspan="3" class="align-middle">小計</th>
                <td colspan="2" class="text-center align-middle"></td>
                {!! $renderInputs('kojo_shokei') !!}
              </tr>
              <tr>
                <th colspan="3" class="align-middle">雑損控除</th>
                <td class="text-center align-middle">
                  <button type="button" class="btn btn-link btn-sm px-0">HELP</button>
                </td>
                <td class="text-center align-middle"></td>
                {!! $renderInputs('kojo_zasson') !!}
              </tr>
              <tr>
                <th colspan="3" class="align-middle">医療費控除</th>
                <td class="text-center align-middle">
                  <button type="button" class="btn btn-link btn-sm px-0">HELP</button>
                </td>
                <td class="text-center align-middle"></td>
                {!! $renderInputs('kojo_iryo') !!}
              </tr>
              <tr>
                <th colspan="3" class="align-middle">寄付金控除</th>
                <td class="text-center align-middle">
                  <button type="button" class="btn btn-link btn-sm px-0">HELP</button>
                </td>
                <td class="text-center align-middle">
                  <button type="submit" class="btn btn-outline-secondary btn-sm" name="redirect_to" value="kihukin_details">内訳</button>
                </td>
                {!! $renderInputs('kojo_kifukin') !!}
              </tr>
              <tr>
                <th colspan="3" class="align-middle">合計</th>
                <td class="text-center align-middle">
                  <button type="button" class="btn btn-link btn-sm px-0">HELP</button>
                </td>
                <td class="text-center align-middle"></td>
                {!! $renderInputs('kojo_gokei') !!}
              </tr>
              <tr>
                <th scope="rowgroup" rowspan="{{ $taxRowspan }}" class="text-center align-middle bg-light">税金の金額</th>
                <th colspan="3" class="align-middle">課税所得金額</th>
                <td class="text-center align-middle">
                  <button type="button" class="btn btn-link btn-sm px-0">HELP</button>
                </td>
                <td class="text-center align-middle"></td>
                {!! $renderInputs('tax_kazeishotoku') !!}
              </tr>
              <tr>
                <th colspan="3" class="align-middle">税額</th>
                <td class="text-center align-middle">
                  <button type="button" class="btn btn-link btn-sm px-0">HELP</button>
                </td>
                <td class="text-center align-middle"></td>
                {!! $renderInputs('tax_zeigaku') !!}
              </tr>
              <tr>
                <th colspan="3" class="align-middle">配当控除</th>
                <td class="text-center align-middle">
                  <button type="button" class="btn btn-link btn-sm px-0">HELP</button>
                </td>
                <td class="text-center align-middle"></td>
                {!! $renderInputs('tax_haito') !!}
              </tr>
              <tr>
                <th colspan="3" class="align-middle">住宅借入金等特別控除</th>
                <td class="text-center align-middle">
                  <button type="button" class="btn btn-link btn-sm px-0">HELP</button>
                </td>
                <td class="text-center align-middle"></td>
                {!! $renderInputs('tax_jutaku') !!}
              </tr>
              <tr>
                <th colspan="3" class="align-middle">政党等寄付金等特別控除</th>
                <td class="text-center align-middle">
                  <button type="button" class="btn btn-link btn-sm px-0">HELP</button>
                </td>
                <td class="text-center align-middle"></td>
                {!! $renderInputs('tax_seito') !!}
              </tr>
              <tr>
                <th colspan="3" class="align-middle">差引所得税額</th>
                <td class="text-center align-middle">
                  <button type="button" class="btn btn-link btn-sm px-0">HELP</button>
                </td>
                <td class="text-center align-middle"></td>
                {!! $renderInputs('tax_sashihiki') !!}
              </tr>
              @if ($showTokubetsu)
              <tr>
                <th colspan="3" class="align-middle">令和6年度分特別税額控除</th>
                <td class="text-center align-middle">
                  <button type="button" class="btn btn-link btn-sm px-0">HELP</button>
                </td>
                <td class="text-center align-middle"></td>
                {!! $renderInputs('tax_tokubetsu_R6') !!}
              </tr>
              @endif
              <tr>
                <th colspan="3" class="align-middle">基準所得税額</th>
                <td class="text-center align-middle">
                  <button type="button" class="btn btn-link btn-sm px-0">HELP</button>
                </td>
                <td class="text-center align-middle"></td>
                {!! $renderInputs('tax_kijun') !!}
              </tr>
              <tr>
                <th colspan="3" class="align-middle">復興所得税額</th>
                <td class="text-center align-middle">
                  <button type="button" class="btn btn-link btn-sm px-0">HELP</button>
                </td>
                <td class="text-center align-middle"></td>
                {!! $renderInputs('tax_fukkou') !!}
              </tr>
              <tr>
                <th colspan="3" class="align-middle">合計</th>
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
                <h5 class="card-title mb-3">確定申告書(分離課税)</h5>
                <div class="table-responsive">
                  <table class="table table-bordered table-sm align-middle mb-0">
                    <thead class="table-light text-center align-middle">
                      <tr>
                        <th rowspan="2" colspan="4">項目</th>
                        <th rowspan="2" colspan="2"></th>
                        <th colspan="2">所得税</th>
                        <th colspan="2">住民税</th>
                      </tr>
                      <tr>
                        <th>{{ $warekiPrevLabel }}</th>
                        <th>{{ $warekiCurrLabel }}</th>
                        <th>{{ $warekiPrevLabel }}</th>
                        <th>{{ $warekiCurrLabel }}</th>
                      </tr>
                    </thead>
                    <tbody>
                      <tr>
                        <th scope="rowgroup" rowspan="13" class="text-center align-middle bg-light">所得金額</th>
                        <th scope="rowgroup" rowspan="11" class="text-center align-middle bg-light">分離課税</th>
                        <th scope="rowgroup" rowspan="2" class="text-center align-middle">短期</th>
                        <th scope="row" class="align-middle">一般分</th>
                        <td class="text-center align-middle">
                          <button type="button" class="btn btn-link btn-sm px-0">HELP</button>
                        </td>
                        <td></td>
                        @foreach (['shotoku' => ['prev', 'curr'], 'jumin' => ['prev', 'curr']] as $tax => $periods)
                          @foreach ($periods as $period)
                            @php
                              $name = sprintf('bunri_tanki_ippan_%s_%s', $tax, $period);
                              $value = old($name, $inputs[$name] ?? null);
                            @endphp
                            <td>
                              <input type="number" min="0" step="1" class="form-control form-control-sm text-end" name="{{ $name }}" value="{{ $value }}">
                            </td>
                          @endforeach
                        @endforeach
                      </tr>
                      <tr>
                        <th scope="row" class="align-middle">軽減分</th>
                        <td class="text-center align-middle">
                          <button type="button" class="btn btn-link btn-sm px-0">HELP</button>
                        </td>
                        <td></td>
                        @foreach (['shotoku' => ['prev', 'curr'], 'jumin' => ['prev', 'curr']] as $tax => $periods)
                          @foreach ($periods as $period)
                            @php
                              $name = sprintf('bunri_tanki_keigen_%s_%s', $tax, $period);
                              $value = old($name, $inputs[$name] ?? null);
                            @endphp
                            <td>
                              <input type="number" min="0" step="1" class="form-control form-control-sm text-end" name="{{ $name }}" value="{{ $value }}">
                            </td>
                          @endforeach
                        @endforeach
                      </tr>
                      <tr>
                        <th scope="rowgroup" rowspan="5" class="text-center align-middle">長期</th>
                        <th scope="row" class="align-middle">一般分</th>
                        <td class="text-center align-middle">
                          <button type="button" class="btn btn-link btn-sm px-0">HELP</button>
                        </td>
                        <td></td>
                        @foreach (['shotoku' => ['prev', 'curr'], 'jumin' => ['prev', 'curr']] as $tax => $periods)
                          @foreach ($periods as $period)
                            @php
                              $name = sprintf('bunri_choki_ippan_%s_%s', $tax, $period);
                              $value = old($name, $inputs[$name] ?? null);
                            @endphp
                            <td>
                              <input type="number" min="0" step="1" class="form-control form-control-sm text-end" name="{{ $name }}" value="{{ $value }}">
                            </td>
                          @endforeach
                        @endforeach
                      </tr>
                      <tr>
                        <th scope="rowgroup" rowspan="2" class="align-middle">特定分</th>
                        <td class="text-center align-middle">
                          <button type="button" class="btn btn-link btn-sm px-0">HELP</button>
                        </td>
                        <td></td>
                        @foreach (['shotoku' => ['prev', 'curr'], 'jumin' => ['prev', 'curr']] as $tax => $periods)
                          @foreach ($periods as $period)
                            @php
                              $name = sprintf('bunri_choki_tokutei_%s_%s', $tax, $period);
                              $value = old($name, $inputs[$name] ?? null);
                            @endphp
                            <td>
                              <input type="number" min="0" step="1" class="form-control form-control-sm text-end" name="{{ $name }}" value="{{ $value }}">
                            </td>
                          @endforeach
                        @endforeach
                      </tr>
                      <tr>
                        <td class="text-center align-middle">
                          <button type="button" class="btn btn-link btn-sm px-0">HELP</button>
                        </td>
                        <td></td>
                        @foreach (['shotoku' => ['prev', 'curr'], 'jumin' => ['prev', 'curr']] as $tax => $periods)
                          @foreach ($periods as $period)
                            @php
                              $name = sprintf('bunri_choki_tokutei_%s_%s', $tax, $period);
                              $value = old($name, $inputs[$name] ?? null);
                            @endphp
                            <td>
                              <input type="number" min="0" step="1" class="form-control form-control-sm text-end" name="{{ $name }}" value="{{ $value }}">
                            </td>
                          @endforeach
                        @endforeach
                      </tr>
                      <tr>
                        <th scope="rowgroup" rowspan="2" class="align-middle">軽課分</th>
                        <td class="text-center align-middle">
                          <button type="button" class="btn btn-link btn-sm px-0">HELP</button>
                        </td>
                        <td></td>
                        @foreach (['shotoku' => ['prev', 'curr'], 'jumin' => ['prev', 'curr']] as $tax => $periods)
                          @foreach ($periods as $period)
                            @php
                              $name = sprintf('bunri_choki_keika_%s_%s', $tax, $period);
                              $value = old($name, $inputs[$name] ?? null);
                            @endphp
                            <td>
                              <input type="number" min="0" step="1" class="form-control form-control-sm text-end" name="{{ $name }}" value="{{ $value }}">
                            </td>
                          @endforeach
                        @endforeach
                      </tr>
                      <tr>
                        <td class="text-center align-middle">
                          <button type="button" class="btn btn-link btn-sm px-0">HELP</button>
                        </td>
                        <td></td>
                        @foreach (['shotoku' => ['prev', 'curr'], 'jumin' => ['prev', 'curr']] as $tax => $periods)
                          @foreach ($periods as $period)
                            @php
                              $name = sprintf('bunri_choki_keika_%s_%s', $tax, $period);
                              $value = old($name, $inputs[$name] ?? null);
                            @endphp
                            <td>
                              <input type="number" min="0" step="1" class="form-control form-control-sm text-end" name="{{ $name }}" value="{{ $value }}">
                            </td>
                          @endforeach
                        @endforeach
                      </tr>
                      <tr>
                        <th scope="row" colspan="2" class="align-middle">一般株式等の譲渡</th>
                        <td class="text-center align-middle">
                          <button type="button" class="btn btn-link btn-sm px-0">HELP</button>
                        </td>
                        <td></td>
                        @foreach (['shotoku' => ['prev', 'curr'], 'jumin' => ['prev', 'curr']] as $tax => $periods)
                          @foreach ($periods as $period)
                            @php
                              $name = sprintf('bunri_ippan_kabuteki_joto_%s_%s', $tax, $period);
                              $value = old($name, $inputs[$name] ?? null);
                            @endphp
                            <td>
                              <input type="number" min="0" step="1" class="form-control form-control-sm text-end" name="{{ $name }}" value="{{ $value }}">
                            </td>
                          @endforeach
                        @endforeach
                      </tr>
                      <tr>
                        <th scope="row" colspan="2" class="align-middle">上場株式等の譲渡</th>
                        <td class="text-center align-middle">
                          <button type="button" class="btn btn-link btn-sm px-0">HELP</button>
                        </td>
                        <td></td>
                        @foreach (['shotoku' => ['prev', 'curr'], 'jumin' => ['prev', 'curr']] as $tax => $periods)
                          @foreach ($periods as $period)
                            @php
                              $name = sprintf('bunri_jojo_kabuteki_joto_%s_%s', $tax, $period);
                              $value = old($name, $inputs[$name] ?? null);
                            @endphp
                            <td>
                              <input type="number" min="0" step="1" class="form-control form-control-sm text-end" name="{{ $name }}" value="{{ $value }}">
                            </td>
                          @endforeach
                        @endforeach
                      </tr>
                      <tr>
                        <th scope="row" colspan="2" class="align-middle">上場株式等の配当等</th>
                        <td class="text-center align-middle">
                          <button type="button" class="btn btn-link btn-sm px-0">HELP</button>
                        </td>
                        <td></td>
                        @foreach (['shotoku' => ['prev', 'curr'], 'jumin' => ['prev', 'curr']] as $tax => $periods)
                          @foreach ($periods as $period)
                            @php
                              $name = sprintf('bunri_jojo_kabuteki_haito_%s_%s', $tax, $period);
                              $value = old($name, $inputs[$name] ?? null);
                            @endphp
                            <td>
                              <input type="number" min="0" step="1" class="form-control form-control-sm text-end" name="{{ $name }}" value="{{ $value }}">
                            </td>
                          @endforeach
                        @endforeach
                      </tr>
                      <tr>
                        <th scope="row" colspan="2" class="align-middle">先物取引</th>
                        <td class="text-center align-middle">
                          <button type="button" class="btn btn-link btn-sm px-0">HELP</button>
                        </td>
                        <td></td>
                        @foreach (['shotoku' => ['prev', 'curr'], 'jumin' => ['prev', 'curr']] as $tax => $periods)
                          @foreach ($periods as $period)
                            @php
                              $name = sprintf('bunri_sakimono_%s_%s', $tax, $period);
                              $value = old($name, $inputs[$name] ?? null);
                            @endphp
                            <td>
                              <input type="number" min="0" step="1" class="form-control form-control-sm text-end" name="{{ $name }}" value="{{ $value }}">
                            </td>
                          @endforeach
                        @endforeach
                      </tr>
                      <tr>
                        <th scope="row" colspan="3" class="align-middle">山林</th>
                        <td class="text-center align-middle">
                          <button type="button" class="btn btn-link btn-sm px-0">HELP</button>
                        </td>
                        <td></td>
                        @foreach (['shotoku' => ['prev', 'curr'], 'jumin' => ['prev', 'curr']] as $tax => $periods)
                          @foreach ($periods as $period)
                            @php
                              $name = sprintf('bunri_sanrin_%s_%s', $tax, $period);
                              $value = old($name, $inputs[$name] ?? null);
                            @endphp
                            <td>
                              <input type="number" min="0" step="1" class="form-control form-control-sm text-end" name="{{ $name }}" value="{{ $value }}">
                            </td>
                          @endforeach
                        @endforeach
                      </tr>
                      <tr>
                        <th scope="row" colspan="3" class="align-middle">退職</th>
                        <td class="text-center align-middle">
                          <button type="button" class="btn btn-link btn-sm px-0">HELP</button>
                        </td>
                        <td></td>
                        @foreach (['shotoku' => ['prev', 'curr'], 'jumin' => ['prev', 'curr']] as $tax => $periods)
                          @foreach ($periods as $period)
                            @php
                              $name = sprintf('bunri_taishoku_%s_%s', $tax, $period);
                              $value = old($name, $inputs[$name] ?? null);
                            @endphp
                            <td>
                              <input type="number" min="0" step="1" class="form-control form-control-sm text-end" name="{{ $name }}" value="{{ $value }}">
                            </td>
                          @endforeach
                        @endforeach
                      </tr>
                      <tr>
                        <th scope="rowgroup" rowspan="19" class="text-center align-middle bg-light">税金の計算</th>
                        <th scope="row" colspan="3" class="align-middle">総合課税の合計額</th>
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
                        <th scope="row" colspan="3" class="align-middle">所得から差し引かれる金額</th>
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
                        <th scope="rowgroup" rowspan="8" class="text-center align-middle bg-light">課税所得金額</th>
                        <th scope="row" colspan="2" class="align-middle">総合課税</th>
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
                        <th scope="row" colspan="2" class="align-middle">短期譲渡</th>
                        <td class="text-center align-middle">
                          <button type="button" class="btn btn-link btn-sm px-0">HELP</button>
                        </td>
                        <td></td>
                        @foreach (['shotoku' => ['prev', 'curr'], 'jumin' => ['prev', 'curr']] as $tax => $periods)
                          @foreach ($periods as $period)
                            @php
                              $name = sprintf('bunri_kazeishotoku_tanki_%s_%s', $tax, $period);
                              $value = old($name, $inputs[$name] ?? null);
                            @endphp
                            <td>
                              <input type="number" min="0" step="1" class="form-control form-control-sm text-end" name="{{ $name }}" value="{{ $value }}">
                            </td>
                          @endforeach
                        @endforeach
                      </tr>
                      <tr>
                        <th scope="row" colspan="2" class="align-middle">長期譲渡</th>
                        <td class="text-center align-middle">
                          <button type="button" class="btn btn-link btn-sm px-0">HELP</button>
                        </td>
                        <td></td>
                        @foreach (['shotoku' => ['prev', 'curr'], 'jumin' => ['prev', 'curr']] as $tax => $periods)
                          @foreach ($periods as $period)
                            @php
                              $name = sprintf('bunri_kazeishotoku_choki_%s_%s', $tax, $period);
                              $value = old($name, $inputs[$name] ?? null);
                            @endphp
                            <td>
                              <input type="number" min="0" step="1" class="form-control form-control-sm text-end" name="{{ $name }}" value="{{ $value }}">
                            </td>
                          @endforeach
                        @endforeach
                      </tr>
                      <tr>
                        <th scope="row" colspan="2" class="align-middle">一般・上場株式の譲渡</th>
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
                        <th scope="row" colspan="2" class="align-middle">上場株式の配当等</th>
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
                        <th scope="row" colspan="2" class="align-middle">先物取引</th>
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
                        <th scope="row" colspan="2" class="align-middle">山林</th>
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
                        <th scope="row" colspan="2" class="align-middle">退職</th>
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
                        <th scope="rowgroup" rowspan="8" class="text-center align-middle bg-light">税額</th>
                        <th scope="row" colspan="2" class="align-middle">総合課税</th>
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
                        <th scope="row" colspan="2" class="align-middle">短期譲渡</th>
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
                        <th scope="row" colspan="2" class="align-middle">長期譲渡</th>
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
                        <th scope="row" colspan="2" class="align-middle">一般・上場株式の譲渡</th>
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
                        <th scope="row" colspan="2" class="align-middle">上場株式の配当等</th>
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
                        <th scope="row" colspan="2" class="align-middle">先物取引</th>
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
                        <th scope="row" colspan="2" class="align-middle">山林</th>
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
                        <th scope="row" colspan="2" class="align-middle">退職</th>
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
                        <th scope="row" colspan="3" class="align-middle">税額合計</th>
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
        @include('tax.furusato.tabs.result_details', ['results' => $resultsData])
      </div>
      <div class="tab-pane fade" id="furusato-tab-result-upper" role="tabpanel" aria-labelledby="furusato-tab-result-upper-nav">
        @include('tax.furusato.tabs.result_upper_furusato', ['results' => $resultsData])
      </div>
    </div>
  </form>
</div>
@endsection