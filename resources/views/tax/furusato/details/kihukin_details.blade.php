@extends('layouts.min')

@section('title', '寄付金控除の内訳')

@section('content')
@php
    $inputs = $out['inputs'] ?? [];
    $warekiPrevLabel = $warekiPrev ?? '前年';
    $warekiCurrLabel = $warekiCurr ?? '当年';
    $categories = [
        'furusato' => '都道府県・市区町村（ふるさと納税）',
        'kyodobokin_nisseki' => '住所地の共同募金、日赤',
        'seito' => '政党等',
        'npo' => 'NPO法人等',
        'koueki' => '公益社団法人等',
        'kuni' => '国',
        'sonota' => 'その他',
    ];
    $columns = [
        ['base' => 'shotokuzei_shotokukojo', 'period' => 'prev'],
        ['base' => 'shotokuzei_zeigakukojo', 'period' => 'prev'],
        ['base' => 'juminzei_zeigakukojo_pref', 'period' => 'prev'],
        ['base' => 'juminzei_zeigakukojo_muni', 'period' => 'prev'],
        ['base' => 'shotokuzei_shotokukojo', 'period' => 'curr'],
        ['base' => 'shotokuzei_zeigakukojo', 'period' => 'curr'],
        ['base' => 'juminzei_zeigakukojo_pref', 'period' => 'curr'],
        ['base' => 'juminzei_zeigakukojo_muni', 'period' => 'curr'],
    ];
    $makeField = static fn(string $base, string $category, string $period): string => sprintf('%s_%s_%s', $base, $category, $period);

    $inputDisabled = [];
    foreach (['furusato', 'kyodobokin_nisseki', 'kuni', 'sonota'] as $category) {
        $inputDisabled[$makeField('shotokuzei_zeigakukojo', $category, 'prev')] = true;
        $inputDisabled[$makeField('shotokuzei_zeigakukojo', $category, 'curr')] = true;
    }
    foreach (['seito', 'kuni'] as $category) {
        $inputDisabled[$makeField('juminzei_zeigakukojo_pref', $category, 'prev')] = true;
        $inputDisabled[$makeField('juminzei_zeigakukojo_muni', $category, 'prev')] = true;
        $inputDisabled[$makeField('juminzei_zeigakukojo_pref', $category, 'curr')] = true;
        $inputDisabled[$makeField('juminzei_zeigakukojo_muni', $category, 'curr')] = true;
    }

    $referenceSymbols = [];
    foreach (array_keys($inputDisabled) as $field) {
        $referenceSymbols[$field] = '－';
    }
    foreach (array_keys($categories) as $category) {
        $referenceSymbols[$makeField('shotokuzei_shotokukojo', $category, 'prev')] = '〇';
        $referenceSymbols[$makeField('shotokuzei_shotokukojo', $category, 'curr')] = '〇';
    }
    foreach (['seito', 'npo', 'koueki'] as $category) {
        $referenceSymbols[$makeField('shotokuzei_zeigakukojo', $category, 'prev')] = '〇';
        $referenceSymbols[$makeField('shotokuzei_zeigakukojo', $category, 'curr')] = '〇';
    }
    foreach (['furusato'] as $category) {
        $referenceSymbols[$makeField('juminzei_zeigakukojo_pref', $category, 'prev')] = '〇';
        $referenceSymbols[$makeField('juminzei_zeigakukojo_muni', $category, 'prev')] = '〇';
        $referenceSymbols[$makeField('juminzei_zeigakukojo_pref', $category, 'curr')] = '〇';
        $referenceSymbols[$makeField('juminzei_zeigakukojo_muni', $category, 'curr')] = '〇';
    }
    foreach (['kyodobokin_nisseki', 'npo', 'koueki', 'sonota'] as $category) {
        $symbol = '〇(※)';
        $referenceSymbols[$makeField('juminzei_zeigakukojo_pref', $category, 'prev')] = $symbol;
        $referenceSymbols[$makeField('juminzei_zeigakukojo_muni', $category, 'prev')] = $symbol;
        $referenceSymbols[$makeField('juminzei_zeigakukojo_pref', $category, 'curr')] = $symbol;
        $referenceSymbols[$makeField('juminzei_zeigakukojo_muni', $category, 'curr')] = $symbol;
    }

    $originTabRaw = request()->input('origin_tab', 'input');
    $originTab = is_string($originTabRaw) && trim($originTabRaw) === 'input' ? 'input' : '';
    $originAnchor = preg_replace('/[^A-Za-z0-9_-]/', '', (string) request()->input('origin_anchor', ''));
    $inputRouteParams = ['data_id' => $dataId];
    if ($originTab === 'input') {
        $inputRouteParams['tab'] = 'input';
    }
    $returnUrl = route('furusato.input', $inputRouteParams);
    if ($originAnchor !== '') {
        $returnUrl .= '#' . $originAnchor;
    }
@endphp
<div class="container-blue mt-2" style="width:1100px;">
  <div class="card-header d-flex align-items-start">
    <img src="{{ asset('storage/images/kado_lefttop.jpg') }}" alt="…">
    <h0 class="mb-0 mt-2">寄付金控除の内訳</h0>
    <a href="{{ $returnUrl }}" class="btn btn-link btn-sm">入力へ戻る</a>
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
  <div class="card-body">　
  　<div class="wrapper">
        <form method="POST" action="{{ route('furusato.details.kihukin.save') }}">
          @csrf
          <input type="hidden" name="data_id" value="{{ $dataId }}">
          <input type="hidden" name="redirect_to" value="input">
          <input type="hidden" name="origin_tab" value="{{ $originTab }}">
          <input type="hidden" name="origin_anchor" value="{{ $originAnchor }}">
      
          <div class="table-responsive mb-4">
            <table class="table-base table-bordered align-middle text-start ms-2">
                <tr>
                  <th rowspan="4" class="align-middle th-ccc" style="width:120px;height:30px;">寄付対象</th>
                  <th colspan="4" class="th-ccc">{{ $warekiPrevLabel }}</th>
                  <th colspan="4" class="th-ccc">{{ $warekiCurrLabel }}</th>
                </tr>
                <tr>
                  <th colspan="2" rowspan="2">所得税</th>
                  <th colspan="2">住民税</th>
                  <th colspan="2" rowspan="2">所得税</th>
                  <th colspan="2">住民税</th>
                </tr>
                <tr>
                  <th>都道府県</th>
                  <th>市区町村</th>
                  <th>都道府県</th>
                  <th>市区町村</th>
                </tr>
                <tr>
                  <th class="th-ddd">所得控除</th>
                  <th class="th-ddd">税額控除</th>
                  <th class="th-ddd">税額控除</th>
                  <th class="th-ddd">税額控除</th>
                  <th class="th-ddd">所得控除</th>
                  <th class="th-ddd">税額控除</th>
                  <th class="th-ddd">税額控除</th>
                  <th class="th-ddd">税額控除</th>
                </tr>
              <tbody>
                @foreach ($categories as $key => $label)
                  <tr>
                    <th scope="row" class="text-start">{{ $label }}</th>
                    @foreach ($columns as $column)
                      @php($field = $makeField($column['base'], $key, $column['period']))
                      @if (isset($inputDisabled[$field]))
                        <td class="text-center align-middle">－</td>
                      @else
                        <td>
                          <input type="number" min="0" step="1" class="form-control suji8" name="{{ $field }}" value="{{ old($field, $inputs[$field] ?? '') }}">
                        </td>
                      @endif
                    @endforeach
                  </tr>
                @endforeach
              </tbody>
            </table>
          </div>
      
          <div class="table-responsive">
            <table class="table-base table-bordered align-middle text-start ms-2">
                <tr>
                  <th rowspan="4" class="align-middle th-ccc" style="width:120px;height:30px;">寄付対象</th>
                  <th colspan="4" class="th-ccc">{{ $warekiPrevLabel }}</th>
                  <th colspan="4" class="th-ccc">{{ $warekiCurrLabel }}</th>
                </tr>
                <tr>
                  <th colspan="2" rowspan="2">所得税</th>
                  <th colspan="2">住民税</th>
                  <th colspan="2" rowspan="2">所得税</th>
                  <th colspan="2">住民税</th>
                </tr>
                <tr>
                  <th>都道府県</th>
                  <th>市区町村</th>
                  <th>都道府県</th>
                  <th>市区町村</th>
                </tr>
                <tr>
                  <th class="th-ddd">所得控除</th>
                  <th class="th-ddd">税額控除</th>
                  <th class="th-ddd">税額控除</th>
                  <th class="th-ddd">税額控除</th>
                  <th class="th-ddd">所得控除</th>
                  <th class="th-ddd">税額控除</th>
                  <th class="th-ddd">税額控除</th>
                  <th class="th-ddd">税額控除</th>
                </tr>
              <tbody>
                @foreach ($categories as $key => $label)
                  <tr>
                    <th scope="row" class="text-start">{{ $label }}</th>
                    @foreach ($columns as $column)
                      @php($field = $makeField($column['base'], $key, $column['period']))
                      <td class="text-center align-middle">{{ $referenceSymbols[$field] ?? '' }}</td>
                    @endforeach
                  </tr>
                @endforeach
              </tbody>
            </table>
          </div>
      <img src="{{ asset('storage/images/kifu.jpg') }}" alt="…">
          <p class="p-small">(※) 都道府県、市区町村が条例で指定したものに限る。</p>
          <hr>
            <div class="text-end me-2 mb-2">
            <button type="submit" class="btn btn-base-blue me-2">戻 る</button>
            <button type="submit"
                    class="btn btn-base-green ms-2"
                    name="recalc_all"
                    value="1"
                    data-disable-on-submit
                    data-redirect-to="kihukin_details">再計算</button>
            </div>
        </form>
    </div>    
  </div>      
</div>
@endsection