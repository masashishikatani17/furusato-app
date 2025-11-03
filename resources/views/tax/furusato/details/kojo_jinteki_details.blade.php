<!-- views/tax/furusato/details/kojo_jinteki_details.blade.php -->
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
<div class="container-blue mt-2" style="width:1000px;">
  <div class="card-header d-flex align-items-start">
    <img src="{{ asset('storage/images/kado_lefttop.jpg') }}" alt="…">
    <h0 class="mb-0 mt-2">人的控除の詳細</h0>
  </div>
  @if ($errors->any())
    <div class="alert alert-danger" role="alert">
      <ul class="mb-0">
        @foreach ($errors->all() as $error)
          <li>{{ $error }}</li>
        @endforeach
      </ul>
    </div>
  @endif
  <div class="card-body">　
  　<div class="wrapper">
      <form method="POST" action="{{ route('furusato.details.kojo_jinteki.save') }}">
        @csrf
        <input type="hidden" name="data_id" value="{{ $dataId }}">
        <input type="hidden" name="origin_tab" value="{{ $originTab }}">
        <input type="hidden" name="origin_anchor" value="{{ $originAnchor ?: 'kojo_jinteki' }}">
        <input type="hidden" name="redirect_to" value="input">
        <input type="hidden" name="recalc_all" value="1">
    
        <div class="table-responsive mb-3">
          <table class="table-base table-bordered align-middle table-jinteki ">
              <tr style="height:30px;">
                <th colspan="4" class="text-center th-ccc">項  目</th>
                <th class="th-ccc" style="width: 160px;">{{ $warekiPrevLabel }}</th>
                <th class="th-ccc" style="width: 160px;">{{ $warekiCurrLabel }}</th>
                <th class="th-ccc" style="width: 220px;">備  考</th>
              </tr>
            <tbody>
              <tr>
                <th colspan="3" class="text-start ps-1">寡婦控除</th>
                <td rowspan="2" class="text-center align-middle">
                  <button class="btn-vp" style="width: 42px;">
                       HELP
                  </button>
                </td>
                <td>
                  @php($kafuPrev = old('kojo_kafu_applicable_prev', $inputs['kojo_kafu_applicable_prev'] ?? null))
                  <select name="kojo_kafu_applicable_prev" class="form-select kana3" style="height:30px" aria-label="{{ $warekiPrevLabel }}の寡婦控除の適用状況">
                    <option value="〇" @selected($kafuPrev === '〇')>〇</option>
                    <option value="×" @selected($kafuPrev === '×')>×</option>
                  </select>
                </td>
                <td>
                  @php($kafuCurr = old('kojo_kafu_applicable_curr', $inputs['kojo_kafu_applicable_curr'] ?? null))
                  <select name="kojo_kafu_applicable_curr" class="form-select kana3" style="height:30px" aria-label="{{ $warekiCurrLabel }}の寡婦控除の適用状況">
                    <option value="〇" @selected($kafuCurr === '〇')>〇</option>
                    <option value="×" @selected($kafuCurr === '×')>×</option>
                  </select>
                </td>
                <td class="remarks-col">&nbsp;</td>
              </tr>
              <tr>
                <th colspan="3" class="text-start ps-1">ひとり親控除</th>
                <td>
                  @php($hitorioyaPrev = old('kojo_hitorioya_applicable_prev', $inputs['kojo_hitorioya_applicable_prev'] ?? null))
                  <select name="kojo_hitorioya_applicable_prev" class="form-select kana3" style="height:30px" aria-label="{{ $warekiPrevLabel }}のひとり親控除の適用状況">
                    <option value="〇" @selected($hitorioyaPrev === '〇')>〇</option>
                    <option value="×" @selected($hitorioyaPrev === '×')>×</option>
                  </select>
                </td>
                <td>
                  @php($hitorioyaCurr = old('kojo_hitorioya_applicable_curr', $inputs['kojo_hitorioya_applicable_curr'] ?? null))
                  <select name="kojo_hitorioya_applicable_curr" class="form-select kana3" style="height:30px" aria-label="{{ $warekiCurrLabel }}のひとり親控除の適用状況">
                    <option value="〇" @selected($hitorioyaCurr === '〇')>〇</option>
                    <option value="×" @selected($hitorioyaCurr === '×')>×</option>
                  </select>
                </td>
                <td class="remarks-col">&nbsp;</td>
              </tr>
              <tr>
                <th colspan="3" class="text-start ps-1">勤労学生控除</th>
                <td class="text-center align-middle">
                  <button class="btn-vp">
                       HELP
                  </button>
                </td>
                <td>
                  @php($kinroPrev = old('kojo_kinrogakusei_applicable_prev', $inputs['kojo_kinrogakusei_applicable_prev'] ?? null))
                  <select name="kojo_kinrogakusei_applicable_prev" class="form-select kana3" style="height:30px" aria-label="{{ $warekiPrevLabel }}の勤労学生控除の適用状況">
                    <option value="〇" @selected($kinroPrev === '〇')>〇</option>
                    <option value="×" @selected($kinroPrev === '×')>×</option>
                  </select>
                </td>
                <td>
                  @php($kinroCurr = old('kojo_kinrogakusei_applicable_curr', $inputs['kojo_kinrogakusei_applicable_curr'] ?? null))
                  <select name="kojo_kinrogakusei_applicable_curr" class="form-select kana3" style="height:30px" aria-label="{{ $warekiCurrLabel }}の勤労学生控除の適用状況">
                    <option value="〇" @selected($kinroCurr === '〇')>〇</option>
                    <option value="×" @selected($kinroCurr === '×')>×</option>
                  </select>
                </td>
                <td class="remarks-col">&nbsp;</td>
              </tr>
              <tr>
                <th rowspan="3" class="text-start align-middle ps-1">障害者控除</th>
                <th colspan="2" class="text-start ps-1 th-ddd">障害者</th>
                <td class="text-center align-middle">
                  <button class="btn-vp">
                       HELP
                  </button>
                </td>
                <td class="text-center">
                  @php($shogaishaPrev = old('kojo_shogaisha_count_prev', $inputs['kojo_shogaisha_count_prev'] ?? null))
                  
                    <input type="number" min="0" step="1" class="form-control suji3 text-end me-2" name="kojo_shogaisha_count_prev" value="{{ $shogaishaPrev }}" aria-label="{{ $warekiPrevLabel }}の障害者控除（障害者）の人数">
                    人 
                  
                </td>
                <td class="text-center">
                  @php($shogaishaCurr = old('kojo_shogaisha_count_curr', $inputs['kojo_shogaisha_count_curr'] ?? null))
                    <input type="number" min="0" step="1" class="form-control suji3 text-end me-2" name="kojo_shogaisha_count_curr" value="{{ $shogaishaCurr }}" aria-label="{{ $warekiCurrLabel }}の障害者控除（障害者）の人数">
                    人
                </td>
                <td class="remarks-col text-start ps-1">それぞれの人数を入力</td>
              </tr>
              <tr>
                <th colspan="2" class="text-start ps-1 th-ddd">特別障害者</th>
                <td class="text-center align-middle">
                  <button class="btn-vp">
                       HELP
                  </button>
                </td>
                <td class="text-center">
                  @php($tokubetsuPrev = old('kojo_tokubetsu_shogaisha_count_prev', $inputs['kojo_tokubetsu_shogaisha_count_prev'] ?? null))
                    <input type="number" min="0" step="1" class="form-control suji3 text-end me-2" name="kojo_tokubetsu_shogaisha_count_prev" value="{{ $tokubetsuPrev }}" aria-label="{{ $warekiPrevLabel }}の障害者控除（特別障害者）の人数">
                    人
                </td>
                <td class="text-center">
                  @php($tokubetsuCurr = old('kojo_tokubetsu_shogaisha_count_curr', $inputs['kojo_tokubetsu_shogaisha_count_curr'] ?? null))
                    <input type="number" min="0" step="1" class="form-control suji3 text-end me-2" name="kojo_tokubetsu_shogaisha_count_curr" value="{{ $tokubetsuCurr }}" aria-label="{{ $warekiCurrLabel }}の障害者控除（特別障害者）の人数">
                    人
                </td>
                <td class="remarks-col">　〃　〃　</td>
              </tr>
              <tr>
                <th colspan="2" class="text-start ps-1 th-ddd">同居特別障害者</th>
                <td class="text-center align-middle">
                  <button class="btn-vp">
                       HELP
                  </button>
                </td>
                <td class="text-center">
                  @php($doukyoPrev = old('kojo_doukyo_tokubetsu_shogaisha_count_prev', $inputs['kojo_doukyo_tokubetsu_shogaisha_count_prev'] ?? null))
                    <input type="number" min="0" step="1" class="form-control suji3 text-end me-2" name="kojo_doukyo_tokubetsu_shogaisha_count_prev" value="{{ $doukyoPrev }}" aria-label="{{ $warekiPrevLabel }}の障害者控除（同居特別障害者）の人数">
                    人
                </td>
                <td class="text-center">
                  @php($doukyoCurr = old('kojo_doukyo_tokubetsu_shogaisha_count_curr', $inputs['kojo_doukyo_tokubetsu_shogaisha_count_curr'] ?? null))
                    <input type="number" min="0" step="1" class="form-control suji3 text-end me-2" name="kojo_doukyo_tokubetsu_shogaisha_count_curr" value="{{ $doukyoCurr }}" aria-label="{{ $warekiCurrLabel }}の障害者控除（同居特別障害者）の人数">
                    人
                </td>
                <td class="remarks-col">　〃　〃　</td>
              </tr>
              <tr>
                <th colspan="3" class="text-start ps-1">配偶者控除</th>
                <td class="text-center align-middle">
                  <button class="btn-vp">
                       HELP
                  </button>
                </td>
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
                <th colspan="3" class="text-start ps-1">配偶者特別控除</th>
                <td class="text-center align-middle">
                  <button class="btn-vp">
                       HELP
                  </button>
                </td>
                <td nowrap="nowrap">
                  @php($haigushaTokubetsuPrev = old('kojo_haigusha_tokubetsu_gokeishotoku_prev', $inputs['kojo_haigusha_tokubetsu_gokeishotoku_prev'] ?? null))
                    <input type="text" inputmode="numeric" autocomplete="off"
                           data-format="comma-int" data-name="kojo_haigusha_tokubetsu_gokeishotoku_prev"
                           class="form-control suji11 text-end me-1"
                           value="{{ $haigushaTokubetsuPrev }}" aria-label="{{ $warekiPrevLabel }}の配偶者合計所得金額">
                    円
                </td>
                <td nowrap="nowrap">
                  @php($haigushaTokubetsuCurr = old('kojo_haigusha_tokubetsu_gokeishotoku_curr', $inputs['kojo_haigusha_tokubetsu_gokeishotoku_curr'] ?? null))
                    <input type="text" inputmode="numeric" autocomplete="off"
                           data-format="comma-int" data-name="kojo_haigusha_tokubetsu_gokeishotoku_curr"
                           class="form-control suji11 text-end me-1"
                           value="{{ $haigushaTokubetsuCurr }}" aria-label="{{ $warekiCurrLabel }}の配偶者合計所得金額">
                    円
                </td>
                <td class="remarks-col text-start ps-1">配偶者の合計所得金額を入力</td>
              </tr>
              <tr>
                <th rowspan="4" class="text-start align-middle ps-1">扶養控除</th>
                <th colspan="2" class="text-start ps-1 th-ddd">一般</th>
                <td rowspan="4" class="text-center align-middle">
                  <button class="btn-vp">
                       HELP
                  </button>
                </td>
                <td>
                  @php($fuyoIppanPrev = old('kojo_fuyo_ippan_count_prev', $inputs['kojo_fuyo_ippan_count_prev'] ?? null))
                    <input type="number" min="0" step="1" class="form-control suji3 text-end me-2" name="kojo_fuyo_ippan_count_prev" value="{{ $fuyoIppanPrev }}" aria-label="{{ $warekiPrevLabel }}の扶養控除（一般）の人数">
                    人
                </td>
                <td>
                  @php($fuyoIppanCurr = old('kojo_fuyo_ippan_count_curr', $inputs['kojo_fuyo_ippan_count_curr'] ?? null))
                    <input type="number" min="0" step="1" class="form-control suji3 text-end me-2" name="kojo_fuyo_ippan_count_curr" value="{{ $fuyoIppanCurr }}" aria-label="{{ $warekiCurrLabel }}の扶養控除（一般）の人数">
                    人
                </td>
                <td class="remarks-col text-start ps-1">それぞれの人数を入力</td>
              </tr>
              <tr>
                <th colspan="2" class="text-start ps-1 th-ddd">特定扶養親族</th>
                <td>
                  @php($fuyoTokuteiPrev = old('kojo_fuyo_tokutei_count_prev', $inputs['kojo_fuyo_tokutei_count_prev'] ?? null))
                    <input type="number" min="0" step="1" class="form-control suji3 text-end me-2" name="kojo_fuyo_tokutei_count_prev" value="{{ $fuyoTokuteiPrev }}" aria-label="{{ $warekiPrevLabel }}の扶養控除（特定扶養親族）の人数">
                    人
                </td>
                <td>
                  @php($fuyoTokuteiCurr = old('kojo_fuyo_tokutei_count_curr', $inputs['kojo_fuyo_tokutei_count_curr'] ?? null))
                    <input type="number" min="0" step="1" class="form-control suji3 text-end me-2" name="kojo_fuyo_tokutei_count_curr" value="{{ $fuyoTokuteiCurr }}" aria-label="{{ $warekiCurrLabel }}の扶養控除（特定扶養親族）の人数">
                    人
                </td>
                <td class="remarks-col">　〃　〃　</td>
              </tr>
              <tr>
                <th rowspan="2" class="text-start align-middle ps-1 pe-1 th-ddd">老人扶養親族</th>
                <th class="text-start ps-1 pe-1 th-ddd">同居老親等</th>
                <td>
                  @php($fuyoRoujinDoukyoPrev = old('kojo_fuyo_roujin_doukyo_count_prev', $inputs['kojo_fuyo_roujin_doukyo_count_prev'] ?? null))
                    <input type="number" min="0" step="1" class="form-control suji3 text-end me-2" name="kojo_fuyo_roujin_doukyo_count_prev" value="{{ $fuyoRoujinDoukyoPrev }}" aria-label="{{ $warekiPrevLabel }}の扶養控除（老人扶養親族・同居老親等）の人数">
                    人
                </td>
                <td>
                  @php($fuyoRoujinDoukyoCurr = old('kojo_fuyo_roujin_doukyo_count_curr', $inputs['kojo_fuyo_roujin_doukyo_count_curr'] ?? null))
                    <input type="number" min="0" step="1" class="form-control suji3 text-end me-2" name="kojo_fuyo_roujin_doukyo_count_curr" value="{{ $fuyoRoujinDoukyoCurr }}" aria-label="{{ $warekiCurrLabel }}の扶養控除（老人扶養親族・同居老親等）の人数">
                    人
                </td>
                <td class="remarks-col">　〃　〃　</td>
              </tr>
              <tr>
                <th class="text-start ps-1 th-ddd">その他</th>
                <td>
                  @php($fuyoRoujinSonotaPrev = old('kojo_fuyo_roujin_sonota_count_prev', $inputs['kojo_fuyo_roujin_sonota_count_prev'] ?? null))
                    <input type="number" min="0" step="1" class="form-control suji3 text-end me-2" name="kojo_fuyo_roujin_sonota_count_prev" value="{{ $fuyoRoujinSonotaPrev }}" aria-label="{{ $warekiPrevLabel }}の扶養控除（老人扶養親族・その他）の人数">
                   人
                </td>
                <td>
                  @php($fuyoRoujinSonotaCurr = old('kojo_fuyo_roujin_sonota_count_curr', $inputs['kojo_fuyo_roujin_sonota_count_curr'] ?? null))
                    <input type="number" min="0" step="1" class="form-control suji3 text-end me-2" name="kojo_fuyo_roujin_sonota_count_curr" value="{{ $fuyoRoujinSonotaCurr }}" aria-label="{{ $warekiCurrLabel }}の扶養控除（老人扶養親族・その他）の人数">
                    人
                </td>
                <td class="remarks-col">　〃　〃　</td>
              </tr>
              <tr>
                <th rowspan="3" colspan="2" class="text-start align-middle">特定親族特別控除</th>
                <th class="text-start ps-1 th-ddd">1人目</th>
                <td rowspan="3" class="text-center align-middle">
                  <button class="btn-vp">
                       HELP
                  </button>
                </td>
                <td nowrap="nowrap">
                  @php($tokutei1Prev = old('kojo_tokutei_shinzoku_1_shotoku_prev', $inputs['kojo_tokutei_shinzoku_1_shotoku_prev'] ?? null))
                    <input type="text" inputmode="numeric" autocomplete="off"
                           data-format="comma-int" data-name="kojo_tokutei_shinzoku_1_shotoku_prev"
                           class="form-control suji11 text-end me-1"
                           value="{{ $tokutei1Prev }}" aria-label="{{ $warekiPrevLabel }}の特定親族特別控除（1人目）の合計所得金額">
                    円
                </td>
                <td nowrap="nowrap">
                  @php($tokutei1Curr = old('kojo_tokutei_shinzoku_1_shotoku_curr', $inputs['kojo_tokutei_shinzoku_1_shotoku_curr'] ?? null))
                    <input type="text" inputmode="numeric" autocomplete="off"
                           data-format="comma-int" data-name="kojo_tokutei_shinzoku_1_shotoku_curr"
                           class="form-control suji11 text-end me-1"
                           value="{{ $tokutei1Curr }}" aria-label="{{ $warekiCurrLabel }}の特定親族特別控除（1人目）の合計所得金額">
                    円
                </td>
                <td class="remarks-col text-start ps-1">特定親族の合計所得金額を入力</td>
              </tr>
              <tr>
                <th class="text-start ps-1 th-ddd">2人目</th>
                <td nowrap="nowrap">
                  @php($tokutei2Prev = old('kojo_tokutei_shinzoku_2_shotoku_prev', $inputs['kojo_tokutei_shinzoku_2_shotoku_prev'] ?? null))
                    <input type="text" inputmode="numeric" autocomplete="off"
                           data-format="comma-int" data-name="kojo_tokutei_shinzoku_2_shotoku_prev"
                           class="form-control suji11 text-end me-1"
                           value="{{ $tokutei2Prev }}" aria-label="{{ $warekiPrevLabel }}の特定親族特別控除（2人目）の合計所得金額">
                    円
                </td>
                <td nowrap="nowrap">
                  @php($tokutei2Curr = old('kojo_tokutei_shinzoku_2_shotoku_curr', $inputs['kojo_tokutei_shinzoku_2_shotoku_curr'] ?? null))
                    <input type="text" inputmode="numeric" autocomplete="off"
                           data-format="comma-int" data-name="kojo_tokutei_shinzoku_2_shotoku_curr"
                           class="form-control suji11 text-end me-1"
                           value="{{ $tokutei2Curr }}" aria-label="{{ $warekiCurrLabel }}の特定親族特別控除（2人目）の合計所得金額">
                    円
                </td>
                <td class="remarks-col">　〃　〃　</td>
              </tr>
              <tr>
                <th class="text-start ps-1 th-ddd">3人目</th>
                <td nowrap="nowrap">
                  @php($tokutei3Prev = old('kojo_tokutei_shinzoku_3_shotoku_prev', $inputs['kojo_tokutei_shinzoku_3_shotoku_prev'] ?? null))
                    <input type="text" inputmode="numeric" autocomplete="off"
                           data-format="comma-int" data-name="kojo_tokutei_shinzoku_3_shotoku_prev"
                           class="form-control suji11 text-end me-1"
                           value="{{ $tokutei3Prev }}" aria-label="{{ $warekiPrevLabel }}の特定親族特別控除（3人目）の合計所得金額">
                    円
                </td>
                <td nowrap="nowrap">
                  @php($tokutei3Curr = old('kojo_tokutei_shinzoku_3_shotoku_curr', $inputs['kojo_tokutei_shinzoku_3_shotoku_curr'] ?? null))
                    <input type="text" inputmode="numeric" autocomplete="off"
                           data-format="comma-int" data-name="kojo_tokutei_shinzoku_3_shotoku_curr"
                           class="form-control suji11 text-end me-1"
                           value="{{ $tokutei3Curr }}" aria-label="{{ $warekiCurrLabel }}の特定親族特別控除（3人目）の合計所得金額">
                    円
                </td>
                <td class="remarks-col">　〃　〃　</td>
              </tr>
            </tbody>
          </table>
        </div>
        <hr>
            <div class="text-end me-2 mb-2">
              <button type="submit" class="btn-base-blue">戻 る</button>
              <button type="submit"
                      class="btn-base-green ms-2"
                      name="stay_on_details"
                      value="1"
                      data-disable-on-submit>再計算</button>
            </div>
      </form>
    </div>  
  </div>    
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

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
  // ===== 3桁カンマ表示 + hidden数値POST（通貨系フィールドのみ）=====
  const toRawInt = (value) => {
    if (typeof value !== 'string') return '';
    const stripped = value.replace(/,/g, '').trim();
    if (stripped === '' || stripped === '-') return '';
    if (!/^(-)?\d+$/.test(stripped)) return '';
    const n = parseInt(stripped, 10);
    return Number.isNaN(n) ? '' : String(n);
  };
  const fmt = (raw) => {
    if (raw === '') return '';
    const n = parseInt(raw, 10);
    return Number.isNaN(n) ? '' : n.toLocaleString('ja-JP');
  };

  const hiddenCache = new Map();
  const getHidden = (name) => {
    if (hiddenCache.has(name)) return hiddenCache.get(name);
    const h = document.querySelector(`input[type="hidden"][name="${name}"]`);
    if (h) hiddenCache.set(name, h);
    return h || null;
  };
  const ensureHidden = (displayInput) => {
    const name = displayInput?.dataset?.name;
    if (!name) return null;
    let h = getHidden(name);
    if (!h) {
      h = document.createElement('input');
      h.type = 'hidden';
      h.name = name;
      h.dataset.commaMirror = '1';
      (displayInput.parentElement || displayInput.closest('form') || document.body).appendChild(h);
      hiddenCache.set(name, h);
    }
    const hiddenRaw = toRawInt(h.value ?? '');
    const inputRaw  = toRawInt(displayInput.value ?? '');
    const raw = hiddenRaw !== '' ? hiddenRaw : inputRaw;
    h.value = raw;
    displayInput.value = raw === '' ? '' : fmt(raw);
    return h;
  };

  const displays = Array.from(document.querySelectorAll('[data-format="comma-int"][data-name]'));
  displays.forEach((input) => {
    const name = input.dataset.name;
    if (!name) return;
    ensureHidden(input);
    // 初期は常にカンマ整形
    const h = getHidden(name);
    const raw = toRawInt(h?.value ?? input.value ?? '');
    input.value = raw === '' ? '' : fmt(raw);
    if (input.readOnly) return;
    input.addEventListener('focus', () => {
      const hidden = getHidden(name);
      input.value = hidden ? hidden.value : toRawInt(input.value ?? '');
      input.select();
    });
    input.addEventListener('blur', () => {
      const hidden = getHidden(name) || ensureHidden(input);
      const raw2 = toRawInt(input.value ?? hidden?.value ?? '');
      if (hidden) hidden.value = raw2;
      input.value = raw2 === '' ? '' : fmt(raw2);
    });
  });

  // 送信直前：hiddenへ数値を確実に格納（表示側はnameを持たないのでチラつき無し）
  const form = document.querySelector('form');
  if (form) {
    form.addEventListener('submit', () => {
      displays.forEach((input) => {
        const name = input.dataset.name;
        if (!name) return;
        const hidden = getHidden(name) || ensureHidden(input);
        const raw = toRawInt(input.value ?? hidden?.value ?? '');
        if (hidden) hidden.value = raw;
      });
    });
  }
});
</script>
@endpush