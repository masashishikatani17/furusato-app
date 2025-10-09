@extends('layouts.min')

@section('title', '生命・地震保険料控除（内訳）')

@section('content')
@php
    $inputs = $out['inputs'] ?? [];
    $warekiPrevLabel = $warekiPrev ?? '前年';
    $warekiCurrLabel = $warekiCurr ?? '当年';
    $originTab = 'input';
    $originAnchor = preg_replace('/[^A-Za-z0-9_-]/', '', (string) request()->input('origin_anchor', 'kojo_jinteki'));
@endphp
<div class="container my-4" style="max-width: 960px;">
  <h1 class="h5 mb-3">人的控除の詳細入力</h1>

  @if ($errors->any())
    <div class="alert alert-danger" role="alert">
      <ul class="mb-0">
        @foreach ($errors->all() as $error)
          <li>{{ $error }}</li>
        @endforeach
      </ul>
    </div>
  @endif

  <form method="POST" action="{{ route('furusato.details.kojo_jinteki.save') }}">
    @csrf
    <input type="hidden" name="data_id" value="{{ $dataId }}">
    <input type="hidden" name="origin_tab" value="{{ $originTab }}">
    <input type="hidden" name="origin_anchor" value="{{ $originAnchor ?: 'kojo_jinteki' }}">

    <div class="table-responsive mb-3">
      <table class="table table-bordered table-sm table-jinteki align-middle text-center">
        <thead class="table-light">
          <tr>
            <th colspan="3" class="text-center">項目</th>
            <th style="width: 110px;"></th>
            <th style="width: 160px;">{{ $warekiPrevLabel }}</th>
            <th style="width: 160px;">{{ $warekiCurrLabel }}</th>
            <th style="width: 220px;">備考</th>
          </tr>
        </thead>
        <tbody>
          <tr>
            <th colspan="3" class="text-start">寡婦控除</th>
            <td rowspan="2" class="align-middle">HELP</td>
            <td>
              @php($kafuPrev = old('kojo_kafu_applicable_prev', $inputs['kojo_kafu_applicable_prev'] ?? null))
              <select name="kojo_kafu_applicable_prev" class="form-select form-select-sm" aria-label="{{ $warekiPrevLabel }}の寡婦控除の適用状況">
                <option value="〇" @selected($kafuPrev === '〇')>〇</option>
                <option value="×" @selected($kafuPrev === '×')>×</option>
              </select>
            </td>
            <td>
              @php($kafuCurr = old('kojo_kafu_applicable_curr', $inputs['kojo_kafu_applicable_curr'] ?? null))
              <select name="kojo_kafu_applicable_curr" class="form-select form-select-sm" aria-label="{{ $warekiCurrLabel }}の寡婦控除の適用状況">
                <option value="〇" @selected($kafuCurr === '〇')>〇</option>
                <option value="×" @selected($kafuCurr === '×')>×</option>
              </select>
            </td>
            <td class="remarks-col">&nbsp;</td>
          </tr>
          <tr>
            <th colspan="3" class="text-start">ひとり親控除</th>
            <td>
              @php($hitorioyaPrev = old('kojo_hitorioya_applicable_prev', $inputs['kojo_hitorioya_applicable_prev'] ?? null))
              <select name="kojo_hitorioya_applicable_prev" class="form-select form-select-sm" aria-label="{{ $warekiPrevLabel }}のひとり親控除の適用状況">
                <option value="〇" @selected($hitorioyaPrev === '〇')>〇</option>
                <option value="×" @selected($hitorioyaPrev === '×')>×</option>
              </select>
            </td>
            <td>
              @php($hitorioyaCurr = old('kojo_hitorioya_applicable_curr', $inputs['kojo_hitorioya_applicable_curr'] ?? null))
              <select name="kojo_hitorioya_applicable_curr" class="form-select form-select-sm" aria-label="{{ $warekiCurrLabel }}のひとり親控除の適用状況">
                <option value="〇" @selected($hitorioyaCurr === '〇')>〇</option>
                <option value="×" @selected($hitorioyaCurr === '×')>×</option>
              </select>
            </td>
            <td class="remarks-col">&nbsp;</td>
          </tr>
          <tr>
            <th colspan="3" class="text-start">勤労学生控除</th>
            <td>HELP</td>
            <td>
              @php($kinroPrev = old('kojo_kinrogakusei_applicable_prev', $inputs['kojo_kinrogakusei_applicable_prev'] ?? null))
              <select name="kojo_kinrogakusei_applicable_prev" class="form-select form-select-sm" aria-label="{{ $warekiPrevLabel }}の勤労学生控除の適用状況">
                <option value="〇" @selected($kinroPrev === '〇')>〇</option>
                <option value="×" @selected($kinroPrev === '×')>×</option>
              </select>
            </td>
            <td>
              @php($kinroCurr = old('kojo_kinrogakusei_applicable_curr', $inputs['kojo_kinrogakusei_applicable_curr'] ?? null))
              <select name="kojo_kinrogakusei_applicable_curr" class="form-select form-select-sm" aria-label="{{ $warekiCurrLabel }}の勤労学生控除の適用状況">
                <option value="〇" @selected($kinroCurr === '〇')>〇</option>
                <option value="×" @selected($kinroCurr === '×')>×</option>
              </select>
            </td>
            <td class="remarks-col">&nbsp;</td>
          </tr>
          <tr>
            <th rowspan="3" class="text-start align-middle">障害者控除</th>
            <th colspan="2" class="text-start">障害者</th>
            <td rowspan="3" class="align-middle">HELP</td>
            <td>
              @php($shogaishaPrev = old('kojo_shogaisha_count_prev', $inputs['kojo_shogaisha_count_prev'] ?? null))
              <div class="input-group input-group-sm">
                <input type="number" min="0" step="1" class="form-control form-control-sm suji11 text-end" name="kojo_shogaisha_count_prev" value="{{ $shogaishaPrev }}" aria-label="{{ $warekiPrevLabel }}の障害者控除（障害者）の人数">
                <span class="input-group-text">人</span>
              </div>
            </td>
            <td>
              @php($shogaishaCurr = old('kojo_shogaisha_count_curr', $inputs['kojo_shogaisha_count_curr'] ?? null))
              <div class="input-group input-group-sm">
                <input type="number" min="0" step="1" class="form-control form-control-sm suji11 text-end" name="kojo_shogaisha_count_curr" value="{{ $shogaishaCurr }}" aria-label="{{ $warekiCurrLabel }}の障害者控除（障害者）の人数">
                <span class="input-group-text">人</span>
              </div>
            </td>
            <td class="remarks-col">それぞれの人数を入力</td>
          </tr>
          <tr>
            <th colspan="2" class="text-start">特別障害者</th>
            <td>
              @php($tokubetsuPrev = old('kojo_tokubetsu_shogaisha_count_prev', $inputs['kojo_tokubetsu_shogaisha_count_prev'] ?? null))
              <div class="input-group input-group-sm">
                <input type="number" min="0" step="1" class="form-control form-control-sm suji11 text-end" name="kojo_tokubetsu_shogaisha_count_prev" value="{{ $tokubetsuPrev }}" aria-label="{{ $warekiPrevLabel }}の障害者控除（特別障害者）の人数">
                <span class="input-group-text">人</span>
              </div>
            </td>
            <td>
              @php($tokubetsuCurr = old('kojo_tokubetsu_shogaisha_count_curr', $inputs['kojo_tokubetsu_shogaisha_count_curr'] ?? null))
              <div class="input-group input-group-sm">
                <input type="number" min="0" step="1" class="form-control form-control-sm suji11 text-end" name="kojo_tokubetsu_shogaisha_count_curr" value="{{ $tokubetsuCurr }}" aria-label="{{ $warekiCurrLabel }}の障害者控除（特別障害者）の人数">
                <span class="input-group-text">人</span>
              </div>
            </td>
            <td class="remarks-col">　〃　〃　</td>
          </tr>
          <tr>
            <th colspan="2" class="text-start">同居特別障害者</th>
            <td>
              @php($doukyoPrev = old('kojo_doukyo_tokubetsu_shogaisha_count_prev', $inputs['kojo_doukyo_tokubetsu_shogaisha_count_prev'] ?? null))
              <div class="input-group input-group-sm">
                <input type="number" min="0" step="1" class="form-control form-control-sm suji11 text-end" name="kojo_doukyo_tokubetsu_shogaisha_count_prev" value="{{ $doukyoPrev }}" aria-label="{{ $warekiPrevLabel }}の障害者控除（同居特別障害者）の人数">
                <span class="input-group-text">人</span>
              </div>
            </td>
            <td>
              @php($doukyoCurr = old('kojo_doukyo_tokubetsu_shogaisha_count_curr', $inputs['kojo_doukyo_tokubetsu_shogaisha_count_curr'] ?? null))
              <div class="input-group input-group-sm">
                <input type="number" min="0" step="1" class="form-control form-control-sm suji11 text-end" name="kojo_doukyo_tokubetsu_shogaisha_count_curr" value="{{ $doukyoCurr }}" aria-label="{{ $warekiCurrLabel }}の障害者控除（同居特別障害者）の人数">
                <span class="input-group-text">人</span>
              </div>
            </td>
            <td class="remarks-col">　〃　〃　</td>
          </tr>
          <tr>
            <th colspan="3" class="text-start">配偶者控除</th>
            <td>HELP</td>
            <td>
              @php($haigushaPrev = old('kojo_haigusha_category_prev', $inputs['kojo_haigusha_category_prev'] ?? null))
              <select name="kojo_haigusha_category_prev" class="form-select form-select-sm" aria-label="{{ $warekiPrevLabel }}の配偶者控除区分">
                <option value="ippan" @selected($haigushaPrev === 'ippan')>一般（70歳未満）</option>
                <option value="roujin" @selected($haigushaPrev === 'roujin')>老人（70歳以上）</option>
                <option value="none" @selected($haigushaPrev === 'none')>なし</option>
              </select>
            </td>
            <td>
              @php($haigushaCurr = old('kojo_haigusha_category_curr', $inputs['kojo_haigusha_category_curr'] ?? null))
              <select name="kojo_haigusha_category_curr" class="form-select form-select-sm" aria-label="{{ $warekiCurrLabel }}の配偶者控除区分">
                <option value="ippan" @selected($haigushaCurr === 'ippan')>一般（70歳未満）</option>
                <option value="roujin" @selected($haigushaCurr === 'roujin')>老人（70歳以上）</option>
                <option value="none" @selected($haigushaCurr === 'none')>なし</option>
              </select>
            </td>
            <td class="remarks-col">&nbsp;</td>
          </tr>
          <tr>
            <th colspan="3" class="text-start">配偶者特別控除</th>
            <td>HELP</td>
            <td>
              @php($haigushaTokubetsuPrev = old('kojo_haigusha_tokubetsu_gokeishotoku_prev', $inputs['kojo_haigusha_tokubetsu_gokeishotoku_prev'] ?? null))
              <div class="input-group input-group-sm">
                <input type="number" min="0" step="1" class="form-control form-control-sm suji11 text-end" name="kojo_haigusha_tokubetsu_gokeishotoku_prev" value="{{ $haigushaTokubetsuPrev }}" aria-label="{{ $warekiPrevLabel }}の配偶者合計所得金額">
                <span class="input-group-text">円</span>
              </div>
            </td>
            <td>
              @php($haigushaTokubetsuCurr = old('kojo_haigusha_tokubetsu_gokeishotoku_curr', $inputs['kojo_haigusha_tokubetsu_gokeishotoku_curr'] ?? null))
              <div class="input-group input-group-sm">
                <input type="number" min="0" step="1" class="form-control form-control-sm suji11 text-end" name="kojo_haigusha_tokubetsu_gokeishotoku_curr" value="{{ $haigushaTokubetsuCurr }}" aria-label="{{ $warekiCurrLabel }}の配偶者合計所得金額">
                <span class="input-group-text">円</span>
              </div>
            </td>
            <td class="remarks-col">配偶者の合計所得金額を入力</td>
          </tr>
          <tr>
            <th rowspan="4" class="text-start align-middle">扶養控除</th>
            <th colspan="2" class="text-start">一般</th>
            <td rowspan="4" class="align-middle">HELP</td>
            <td>
              @php($fuyoIppanPrev = old('kojo_fuyo_ippan_count_prev', $inputs['kojo_fuyo_ippan_count_prev'] ?? null))
              <div class="input-group input-group-sm">
                <input type="number" min="0" step="1" class="form-control form-control-sm suji11 text-end" name="kojo_fuyo_ippan_count_prev" value="{{ $fuyoIppanPrev }}" aria-label="{{ $warekiPrevLabel }}の扶養控除（一般）の人数">
                <span class="input-group-text">人</span>
              </div>
            </td>
            <td>
              @php($fuyoIppanCurr = old('kojo_fuyo_ippan_count_curr', $inputs['kojo_fuyo_ippan_count_curr'] ?? null))
              <div class="input-group input-group-sm">
                <input type="number" min="0" step="1" class="form-control form-control-sm suji11 text-end" name="kojo_fuyo_ippan_count_curr" value="{{ $fuyoIppanCurr }}" aria-label="{{ $warekiCurrLabel }}の扶養控除（一般）の人数">
                <span class="input-group-text">人</span>
              </div>
            </td>
            <td class="remarks-col">それぞれの人数を入力</td>
          </tr>
          <tr>
            <th colspan="2" class="text-start">特定扶養親族</th>
            <td>
              @php($fuyoTokuteiPrev = old('kojo_fuyo_tokutei_count_prev', $inputs['kojo_fuyo_tokutei_count_prev'] ?? null))
              <div class="input-group input-group-sm">
                <input type="number" min="0" step="1" class="form-control form-control-sm suji11 text-end" name="kojo_fuyo_tokutei_count_prev" value="{{ $fuyoTokuteiPrev }}" aria-label="{{ $warekiPrevLabel }}の扶養控除（特定扶養親族）の人数">
                <span class="input-group-text">人</span>
              </div>
            </td>
            <td>
              @php($fuyoTokuteiCurr = old('kojo_fuyo_tokutei_count_curr', $inputs['kojo_fuyo_tokutei_count_curr'] ?? null))
              <div class="input-group input-group-sm">
                <input type="number" min="0" step="1" class="form-control form-control-sm suji11 text-end" name="kojo_fuyo_tokutei_count_curr" value="{{ $fuyoTokuteiCurr }}" aria-label="{{ $warekiCurrLabel }}の扶養控除（特定扶養親族）の人数">
                <span class="input-group-text">人</span>
              </div>
            </td>
            <td class="remarks-col">　〃　〃　</td>
          </tr>
          <tr>
            <th rowspan="2" class="text-start align-middle">老人扶養親族</th>
            <th class="text-start">同居老親等</th>
            <td>
              @php($fuyoRoujinDoukyoPrev = old('kojo_fuyo_roujin_doukyo_count_prev', $inputs['kojo_fuyo_roujin_doukyo_count_prev'] ?? null))
              <div class="input-group input-group-sm">
                <input type="number" min="0" step="1" class="form-control form-control-sm suji11 text-end" name="kojo_fuyo_roujin_doukyo_count_prev" value="{{ $fuyoRoujinDoukyoPrev }}" aria-label="{{ $warekiPrevLabel }}の扶養控除（老人扶養親族・同居老親等）の人数">
                <span class="input-group-text">人</span>
              </div>
            </td>
            <td>
              @php($fuyoRoujinDoukyoCurr = old('kojo_fuyo_roujin_doukyo_count_curr', $inputs['kojo_fuyo_roujin_doukyo_count_curr'] ?? null))
              <div class="input-group input-group-sm">
                <input type="number" min="0" step="1" class="form-control form-control-sm suji11 text-end" name="kojo_fuyo_roujin_doukyo_count_curr" value="{{ $fuyoRoujinDoukyoCurr }}" aria-label="{{ $warekiCurrLabel }}の扶養控除（老人扶養親族・同居老親等）の人数">
                <span class="input-group-text">人</span>
              </div>
            </td>
            <td class="remarks-col">　〃　〃　</td>
          </tr>
          <tr>
            <th class="text-start">その他</th>
            <td>
              @php($fuyoRoujinSonotaPrev = old('kojo_fuyo_roujin_sonota_count_prev', $inputs['kojo_fuyo_roujin_sonota_count_prev'] ?? null))
              <div class="input-group input-group-sm">
                <input type="number" min="0" step="1" class="form-control form-control-sm suji11 text-end" name="kojo_fuyo_roujin_sonota_count_prev" value="{{ $fuyoRoujinSonotaPrev }}" aria-label="{{ $warekiPrevLabel }}の扶養控除（老人扶養親族・その他）の人数">
                <span class="input-group-text">人</span>
              </div>
            </td>
            <td>
              @php($fuyoRoujinSonotaCurr = old('kojo_fuyo_roujin_sonota_count_curr', $inputs['kojo_fuyo_roujin_sonota_count_curr'] ?? null))
              <div class="input-group input-group-sm">
                <input type="number" min="0" step="1" class="form-control form-control-sm suji11 text-end" name="kojo_fuyo_roujin_sonota_count_curr" value="{{ $fuyoRoujinSonotaCurr }}" aria-label="{{ $warekiCurrLabel }}の扶養控除（老人扶養親族・その他）の人数">
                <span class="input-group-text">人</span>
              </div>
            </td>
            <td class="remarks-col">　〃　〃　</td>
          </tr>
          <tr>
            <th rowspan="3" colspan="2" class="text-start align-middle">特定親族特別控除</th>
            <th class="text-start">1人目</th>
            <td rowspan="3" class="align-middle">HELP</td>
            <td>
              @php($tokutei1Prev = old('kojo_tokutei_shinzoku_1_shotoku_prev', $inputs['kojo_tokutei_shinzoku_1_shotoku_prev'] ?? null))
              <div class="input-group input-group-sm">
                <input type="number" min="0" step="1" class="form-control form-control-sm suji11 text-end" name="kojo_tokutei_shinzoku_1_shotoku_prev" value="{{ $tokutei1Prev }}" aria-label="{{ $warekiPrevLabel }}の特定親族特別控除（1人目）の合計所得金額">
                <span class="input-group-text">円</span>
              </div>
            </td>
            <td>
              @php($tokutei1Curr = old('kojo_tokutei_shinzoku_1_shotoku_curr', $inputs['kojo_tokutei_shinzoku_1_shotoku_curr'] ?? null))
              <div class="input-group input-group-sm">
                <input type="number" min="0" step="1" class="form-control form-control-sm suji11 text-end" name="kojo_tokutei_shinzoku_1_shotoku_curr" value="{{ $tokutei1Curr }}" aria-label="{{ $warekiCurrLabel }}の特定親族特別控除（1人目）の合計所得金額">
                <span class="input-group-text">円</span>
              </div>
            </td>
            <td class="remarks-col">特定親族の合計所得金額を入力</td>
          </tr>
          <tr>
            <th class="text-start">2人目</th>
            <td>
              @php($tokutei2Prev = old('kojo_tokutei_shinzoku_2_shotoku_prev', $inputs['kojo_tokutei_shinzoku_2_shotoku_prev'] ?? null))
              <div class="input-group input-group-sm">
                <input type="number" min="0" step="1" class="form-control form-control-sm suji11 text-end" name="kojo_tokutei_shinzoku_2_shotoku_prev" value="{{ $tokutei2Prev }}" aria-label="{{ $warekiPrevLabel }}の特定親族特別控除（2人目）の合計所得金額">
                <span class="input-group-text">円</span>
              </div>
            </td>
            <td>
              @php($tokutei2Curr = old('kojo_tokutei_shinzoku_2_shotoku_curr', $inputs['kojo_tokutei_shinzoku_2_shotoku_curr'] ?? null))
              <div class="input-group input-group-sm">
                <input type="number" min="0" step="1" class="form-control form-control-sm suji11 text-end" name="kojo_tokutei_shinzoku_2_shotoku_curr" value="{{ $tokutei2Curr }}" aria-label="{{ $warekiCurrLabel }}の特定親族特別控除（2人目）の合計所得金額">
                <span class="input-group-text">円</span>
              </div>
            </td>
            <td class="remarks-col">　〃　〃　</td>
          </tr>
          <tr>
            <th class="text-start">3人目</th>
            <td>
              @php($tokutei3Prev = old('kojo_tokutei_shinzoku_3_shotoku_prev', $inputs['kojo_tokutei_shinzoku_3_shotoku_prev'] ?? null))
              <div class="input-group input-group-sm">
                <input type="number" min="0" step="1" class="form-control form-control-sm suji11 text-end" name="kojo_tokutei_shinzoku_3_shotoku_prev" value="{{ $tokutei3Prev }}" aria-label="{{ $warekiPrevLabel }}の特定親族特別控除（3人目）の合計所得金額">
                <span class="input-group-text">円</span>
              </div>
            </td>
            <td>
              @php($tokutei3Curr = old('kojo_tokutei_shinzoku_3_shotoku_curr', $inputs['kojo_tokutei_shinzoku_3_shotoku_curr'] ?? null))
              <div class="input-group input-group-sm">
                <input type="number" min="0" step="1" class="form-control form-control-sm suji11 text-end" name="kojo_tokutei_shinzoku_3_shotoku_curr" value="{{ $tokutei3Curr }}" aria-label="{{ $warekiCurrLabel }}の特定親族特別控除（3人目）の合計所得金額">
                <span class="input-group-text">円</span>
              </div>
            </td>
            <td class="remarks-col">　〃　〃　</td>
          </tr>
        </tbody>
      </table>
    </div>

    <div class="text-end">
      <button type="submit" class="btn btn-primary btn-sm">戻る</button>
    </div>
  </form>
</div>
@endsection

@push('styles')
  <style>
    .table-jinteki td.remarks-col {
      border-top: 0 !important;
      border-bottom: 0 !important;
      vertical-align: middle;
      white-space: nowrap;
      font-size: 0.9rem;
    }
  </style>
@endpush