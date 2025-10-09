@extends('layouts.min')

@section('title', '寄付金控除の内訳')

@section('content')
@php
    $inputs = $out['inputs'] ?? [];
    $warekiPrevLabel = $warekiPrev ?? '前年';
    $warekiCurrLabel = $warekiCurr ?? '当年';
    $categories = [
        'furusato' => '都道府県・市区町村に対する寄付金（ふるさと納税）',
        'kyodobokin_nisseki' => '住所地の共同募金、日赤その他に対する寄付金',
        'seito' => '政党等に対する寄付金',
        'npo' => 'NPO法人等に対する寄付金',
        'koueki' => '公益社団法人等に対する寄付金',
        'kuni' => '国に対する寄付金',
        'sonota' => 'その他の寄付金',
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
<div class="container my-4">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h5 mb-0">寄付金控除の内訳</h1>
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

  <form method="POST" action="{{ route('furusato.details.kihukin.save') }}">
    @csrf
    <input type="hidden" name="data_id" value="{{ $dataId }}">
    <input type="hidden" name="redirect_to" value="input">
    <input type="hidden" name="origin_tab" value="{{ $originTab }}">
    <input type="hidden" name="origin_anchor" value="{{ $originAnchor }}">

    <div class="table-responsive mb-4">
      <table class="table table-bordered table-sm align-middle text-center">
        <thead class="table-light">
          <tr>
            <th rowspan="4" class="align-middle">区分</th>
            <th colspan="4">{{ $warekiPrevLabel }}</th>
            <th colspan="4">{{ $warekiCurrLabel }}</th>
          </tr>
          <tr>
            <th colspan="2">所得税</th>
            <th colspan="2">住民税</th>
            <th colspan="2">所得税</th>
            <th colspan="2">住民税</th>
          </tr>
          <tr>
            <th>所得控除</th>
            <th>税額控除</th>
            <th>都道府県</th>
            <th>市区町村</th>
            <th>所得控除</th>
            <th>税額控除</th>
            <th>都道府県</th>
            <th>市区町村</th>
          </tr>
          <tr>
            <th>所得控除</th>
            <th>税額控除</th>
            <th>税額控除</th>
            <th>税額控除</th>
            <th>所得控除</th>
            <th>税額控除</th>
            <th>税額控除</th>
            <th>税額控除</th>
          </tr>
        </thead>
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
                    <input type="number" min="0" step="1" class="form-control form-control-sm text-end" name="{{ $field }}" value="{{ old($field, $inputs[$field] ?? '') }}">
                  </td>
                @endif
              @endforeach
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>

    <div class="table-responsive mb-2">
      <table class="table table-bordered table-sm align-middle text-center">
        <thead class="table-light">
          <tr>
            <th rowspan="4" class="align-middle">区分</th>
            <th colspan="4">{{ $warekiPrevLabel }}</th>
            <th colspan="4">{{ $warekiCurrLabel }}</th>
          </tr>
          <tr>
            <th colspan="2">所得税</th>
            <th colspan="2">住民税</th>
            <th colspan="2">所得税</th>
            <th colspan="2">住民税</th>
          </tr>
          <tr>
            <th>所得控除</th>
            <th>税額控除</th>
            <th>都道府県</th>
            <th>市区町村</th>
            <th>所得控除</th>
            <th>税額控除</th>
            <th>都道府県</th>
            <th>市区町村</th>
          </tr>
          <tr>
            <th>所得控除</th>
            <th>税額控除</th>
            <th>税額控除</th>
            <th>税額控除</th>
            <th>所得控除</th>
            <th>税額控除</th>
            <th>税額控除</th>
            <th>税額控除</th>
          </tr>
        </thead>
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

    <p class="text-muted small mb-4">(※) 都道府県、市区町村が条例で指定したものに限る。</p>

    <div class="d-flex justify-content-end gap-2">
      <button type="submit" class="btn btn-primary">戻る</button>
      <a href="{{ route('furusato.input', ['data_id' => $dataId]) }}" class="btn btn-outline-secondary">キャンセル</a>
    </div>
  </form>
</div>
@endsection