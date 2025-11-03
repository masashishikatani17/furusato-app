<!-- views/tax/furusato/details/kifukin_details.blade.php-->
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
        foreach (['pref', 'muni'] as $area) {
            $inputDisabled[$makeField("juminzei_zeigakukojo_{$area}", $category, 'prev')] = true;
            $inputDisabled[$makeField("juminzei_zeigakukojo_{$area}", $category, 'curr')] = true;
        }
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
        foreach (['pref', 'muni'] as $area) {
            $referenceSymbols[$makeField("juminzei_zeigakukojo_{$area}", $category, 'prev')] = '〇';
            $referenceSymbols[$makeField("juminzei_zeigakukojo_{$area}", $category, 'curr')] = '〇';
        }
    }
    foreach (['kyodobokin_nisseki', 'npo', 'koueki', 'sonota'] as $category) {
        $symbol = '〇(※)';
        foreach (['pref', 'muni'] as $area) {
            $referenceSymbols[$makeField("juminzei_zeigakukojo_{$area}", $category, 'prev')] = $symbol;
            $referenceSymbols[$makeField("juminzei_zeigakukojo_{$area}", $category, 'curr')] = $symbol;
        }
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
        <form method="POST" action="{{ route('furusato.details.kifukin.save') }}">
          @csrf
          <input type="hidden" name="data_id" value="{{ $dataId }}">
          <input type="hidden" name="redirect_to" value="input">
          <input type="hidden" name="recalc_all" value="1">
          <input type="hidden" name="origin_tab" value="{{ $originTab }}">
          <input type="hidden" name="origin_anchor" value="{{ $originAnchor }}">
          <input type="hidden" name="stay_on_details" id="stay-on-details-flag" value="0">
      
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
                          <input type="text" inputmode="numeric" autocomplete="off"
                                 data-format="comma-int" data-name="{{ $field }}"
                                 class="form-control suji8 text-end"
                                 value="{{ old($field, $inputs[$field] ?? '') }}">
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
            <button type="submit" class="btn-base-blue" id="btn-back">戻 る</button>
            <button type="submit"
                    class="btn-base-green ms-2"
                    id="btn-recalc"
                    data-disable-on-submit>再計算</button>
            </div>
        </form>
    </div>    
  </div>      
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
  // ===== stay_on_details フラグの確実送信（プログラム submit でも失われないように） =====
  (function ensureStayFlag() {
    const form = document.querySelector('form');
    if (!form) return;
    const stayFlag = form.querySelector('#stay-on-details-flag');
    const btnBack = form.querySelector('#btn-back');
    const btnRecalc = form.querySelector('#btn-recalc');

    if (btnBack) {
      btnBack.addEventListener('click', () => { if (stayFlag) stayFlag.value = '0'; });
    }
    if (btnRecalc) {
      btnRecalc.addEventListener('click', () => { if (stayFlag) stayFlag.value = '1'; });
    }
    // 念のため submit 直前にも最終補正（submitterが失われても安全）
    form.addEventListener('submit', () => {
      if (!stayFlag || (stayFlag.value !== '0' && stayFlag.value !== '1')) {
        stayFlag.value = '0';
      }
    });
  })();
  // ===== 3桁カンマ表示 + hidden数値POST =====
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
    // 初回同期：hidden優先、なければdisplayを採用
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
    // 初期表示は常にカンマ整形
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

  // 送信直前：hiddenへ数値を確実に格納（表示側はnameを持たないのでチラつきなし）
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