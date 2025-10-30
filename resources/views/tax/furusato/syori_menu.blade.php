@extends('layouts.min')

@section('content')
@php
  $settings = $settings ?? [];

  $detailModePrev = old('detail_mode_prev');
  if ($detailModePrev === null) {
      $detailModePrev = (string)($settings['detail_mode_prev'] ?? $settings['detail_mode'] ?? 1);
  }

  $detailModeCurr = old('detail_mode_curr');
  if ($detailModeCurr === null) {
      $detailModeCurr = (string)($settings['detail_mode_curr'] ?? $settings['detail_mode_prev'] ?? $settings['detail_mode'] ?? $detailModePrev);
  }

  $bunriFlagPrev = old('bunri_flag_prev');
  if ($bunriFlagPrev === null) {
      $bunriFlagPrev = (string)($settings['bunri_flag_prev'] ?? $settings['bunri_flag'] ?? 0);
  }

  $bunriFlagCurr = old('bunri_flag_curr');
  if ($bunriFlagCurr === null) {
      $bunriFlagCurr = (string)($settings['bunri_flag_curr'] ?? $settings['bunri_flag_prev'] ?? $settings['bunri_flag'] ?? $bunriFlagPrev);
  }

  $oneStopFlagPrev = old('one_stop_flag_prev');
  if ($oneStopFlagPrev === null) {
      $oneStopFlagPrev = (string)($settings['one_stop_flag_prev'] ?? $settings['one_stop_flag'] ?? 1);
  }

  $oneStopFlagCurr = old('one_stop_flag_curr');
  if ($oneStopFlagCurr === null) {
      $oneStopFlagCurr = (string)($settings['one_stop_flag_curr'] ?? $settings['one_stop_flag_prev'] ?? $settings['one_stop_flag'] ?? $oneStopFlagPrev);
  }

  $shiteiFlagPrev = old('shitei_toshi_flag_prev');
  if ($shiteiFlagPrev === null) {
      $shiteiFlagPrev = (string)($settings['shitei_toshi_flag_prev'] ?? $settings['shitei_toshi_flag'] ?? 0);
  }

  $shiteiFlagCurr = old('shitei_toshi_flag_curr');
  if ($shiteiFlagCurr === null) {
      $shiteiFlagCurr = (string)($settings['shitei_toshi_flag_curr'] ?? $settings['shitei_toshi_flag_prev'] ?? $settings['shitei_toshi_flag'] ?? $shiteiFlagPrev);
  }

  $prefStandard = old('pref_standard_rate', $settings['pref_standard_rate'] ?? 0.04);
  $muniStandard = old('muni_standard_rate', $settings['muni_standard_rate'] ?? 0.06);

  $prefAppliedPrev = old('pref_applied_rate_prev');
  if ($prefAppliedPrev === null) {
      $prefAppliedPrev = $settings['pref_applied_rate_prev'] ?? $settings['pref_applied_rate'] ?? $prefStandard;
  }

  $prefAppliedCurr = old('pref_applied_rate_curr');
  if ($prefAppliedCurr === null) {
      $prefAppliedCurr = $settings['pref_applied_rate_curr'] ?? $settings['pref_applied_rate_prev'] ?? $settings['pref_applied_rate'] ?? $prefAppliedPrev;
  }

  $muniAppliedPrev = old('muni_applied_rate_prev');
  if ($muniAppliedPrev === null) {
      $muniAppliedPrev = $settings['muni_applied_rate_prev'] ?? $settings['muni_applied_rate'] ?? $muniStandard;
  }

  $muniAppliedCurr = old('muni_applied_rate_curr');
  if ($muniAppliedCurr === null) {
      $muniAppliedCurr = $settings['muni_applied_rate_curr'] ?? $settings['muni_applied_rate_prev'] ?? $settings['muni_applied_rate'] ?? $muniAppliedPrev;
  }

  $prefEqualPrev = old('pref_equal_share_prev');
  if ($prefEqualPrev === null) {
      $prefEqualPrev = $settings['pref_equal_share_prev'] ?? $settings['pref_equal_share'] ?? 1500;
  }

  $prefEqualCurr = old('pref_equal_share_curr');
  if ($prefEqualCurr === null) {
      $prefEqualCurr = $settings['pref_equal_share_curr'] ?? $settings['pref_equal_share_prev'] ?? $settings['pref_equal_share'] ?? $prefEqualPrev;
  }

  $muniEqualPrev = old('muni_equal_share_prev');
  if ($muniEqualPrev === null) {
      $muniEqualPrev = $settings['muni_equal_share_prev'] ?? $settings['muni_equal_share'] ?? 3500;
  }

  $muniEqualCurr = old('muni_equal_share_curr');
  if ($muniEqualCurr === null) {
      $muniEqualCurr = $settings['muni_equal_share_curr'] ?? $settings['muni_equal_share_prev'] ?? $settings['muni_equal_share'] ?? $muniEqualPrev;
  }

  $otherTaxesPrev = old('other_taxes_amount_prev');
  if ($otherTaxesPrev === null) {
      $otherTaxesPrev = $settings['other_taxes_amount_prev'] ?? $settings['other_taxes_amount'] ?? 0;
  }

  $otherTaxesCurr = old('other_taxes_amount_curr');
  if ($otherTaxesCurr === null) {
      $otherTaxesCurr = $settings['other_taxes_amount_curr'] ?? $settings['other_taxes_amount_prev'] ?? $settings['other_taxes_amount'] ?? $otherTaxesPrev;
  }

  $periods = [
      'prev' => [
          'title' => '前期',
          'detail_mode' => $detailModePrev,
          'bunri_flag' => $bunriFlagPrev,
          'one_stop_flag' => $oneStopFlagPrev,
          'shitei_toshi_flag' => $shiteiFlagPrev,
          'pref_applied_rate' => $prefAppliedPrev,
          'muni_applied_rate' => $muniAppliedPrev,
          'pref_equal_share' => $prefEqualPrev,
          'muni_equal_share' => $muniEqualPrev,
          'other_taxes_amount' => $otherTaxesPrev,
      ],
      'curr' => [
          'title' => '当期',
          'detail_mode' => $detailModeCurr,
          'bunri_flag' => $bunriFlagCurr,
          'one_stop_flag' => $oneStopFlagCurr,
          'shitei_toshi_flag' => $shiteiFlagCurr,
          'pref_applied_rate' => $prefAppliedCurr,
          'muni_applied_rate' => $muniAppliedCurr,
          'pref_equal_share' => $prefEqualCurr,
          'muni_equal_share' => $muniEqualCurr,
          'other_taxes_amount' => $otherTaxesCurr,
      ],
  ];

  $detailOptions = [
      '1' => '詳細版',
      '0' => '簡便版',
  ];

  $bunriOptions = [
      '0' => 'なし',
      '1' => 'あり',
  ];

  $oneStopOptions = [
      '1' => '利用する',
      '0' => '利用しない',
  ];

  $shiteiOptions = [
      '1' => '指定都市',
      '0' => '指定都市以外',
  ];
@endphp
<div class="container-blue mt-2" style="max-width: 700px;">
  <div class="card-header d-flex align-items-start">
    <img src="{{ asset('storage/images/kado_lefttop.jpg') }}" alt="…">
    <h0 class="mb-0 mt-2">処理メニュー設定</h0>
  </div>
    <form method="POST" action="{{ route('furusato.syori.save') }}" id="furusato-syori-form" class="card">
    @csrf
    <input type="hidden" name="data_id" value="{{ $dataId }}">
    <input type="hidden" name="redirect_to" value="">
      @if ($errors->any())
        <div class="alert alert-danger">
          <ul class="mb-0">
            @foreach ($errors->all() as $error)
              <li>{{ $error }}</li>
            @endforeach
          </ul>
        </div>
      @endif
      <div class="row g-4 ms-2 me-2">
        @foreach ($periods as $key => $period)
          <div class="col-md-6">
            <div class="card h-100 tax-period-card" data-period="{{ $key }}">
              <div class="card-header text-center fw-bold">{{ $period['title'] }}</div>
              <div class="card-body">
                <div class="mb-4">
                  <h1>○処理モード</h1>
                  <div class="row g-3">
                    <div class="col-12">
                      <div class="p-2 bg-cream mt-1">
                        <hb class="d-block text-center">処理タイプ</hb>
                        <hr class="my-2">
                      <div class="d-flex ms-5 gap-3 flex-wrap">
                        @foreach ($detailOptions as $value => $label)
                          @php $id = sprintf('detail-mode-%s-%s', $key, $value); @endphp
                          <div class="form-check form-check-inline">
                            <input class="form-check-input"
                                   type="radio"
                                   name="detail_mode_{{ $key }}"
                                   id="{{ $id }}"
                                   value="{{ $value }}"
                                   @checked($period['detail_mode'] === (string) $value)
                                   required>
                            <label class="form-check-label" for="{{ $id }}">{{ $label }}</label>
                          </div>
                        @endforeach
</div>

                      </div>
                    </div>
                    <div class="col-12">
                      <div class="p-2 bg-cream mt-1">
                        <hb class="d-block text-center">分離課税</hb>
                        <hr class="my-2">
                        <div class="d-flex ms-5 gap-3 flex-wrap">
                          @foreach ($bunriOptions as $value => $label)
                            @php $id = sprintf('bunri-flag-%s-%s', $key, $value); @endphp
                            <div class="form-check form-check-inline">
                              <input class="form-check-input"
                                     type="radio"
                                     name="bunri_flag_{{ $key }}"
                                     id="{{ $id }}"
                                     value="{{ $value }}"
                                     @checked($period['bunri_flag'] === (string) $value)
                                     required>
                              <label class="form-check-label" for="{{ $id }}">{{ $label }}</label>
                            </div>
                          @endforeach
                        </div>
                      </div>
                    </div>
                    <div class="col-12">
                      <div class="p-2 bg-cream mt-1">
                        <hb class="d-block text-center">ワンストップ特例</hb>
                        <hr class="my-2">
                        <div class="d-flex ms-3 gap-3 flex-wrap">
                          @foreach ($oneStopOptions as $value => $label)
                            @php $id = sprintf('one-stop-flag-%s-%s', $key, $value); @endphp
                            <div class="form-check form-check-inline">
                              <input class="form-check-input"
                                     type="radio"
                                     name="one_stop_flag_{{ $key }}"
                                     id="{{ $id }}"
                                     value="{{ $value }}"
                                     @checked($period['one_stop_flag'] === (string) $value)
                                     required>
                              <label class="form-check-label" for="{{ $id }}">{{ $label }}</label>
                            </div>
                          @endforeach
                        </div>
                      </div>
                    </div>
                    <div class="col-12">
                      <div class="p-2 bg-cream mt-1">
                        <hb class="d-block text-center">指定都市区分</hb>
                        <hr class="my-2">
                        <div class="d-flex ms-3 gap-1 flex-nowrap">
                          @foreach ($shiteiOptions as $value => $label)
                            @php $id = sprintf('shitei-flag-%s-%s', $key, $value); @endphp
                            <div class="form-check form-check-inline" style="white-space: nowrap;">
                              <input class="form-check-input"
                                     type="radio"
                                     name="shitei_toshi_flag_{{ $key }}"
                                     id="{{ $id }}"
                                     value="{{ $value }}"
                                     @checked($period['shitei_toshi_flag'] === (string) $value)
                                     required>
                              <label class="form-check-label" for="{{ $id }}">{{ $label }}</label>
                            </div>
                          @endforeach
                        </div>
                      </div>
                    </div>
                  </div>
                </div>
                <div class="mb-4">
                  <h1>○所得割の税率</h1>
                  <div class="mb-1 ms-3">
                    <label class="form-label">都道府県（標準）</label>
                    <input type="text"
                           class="form-control suji4 comma decimal3 floor integer_comma pref-standard-rate"
                           value="{{ number_format((float) $prefStandard * 100, 2, '.', '') }}%"
                           readonly>
                  </div>
                  <div class="mb-1 ms-3">
                    <label class="form-label">市区町村（標準）</label>
                    <input type="text"
                           class="form-control suji4 comma decimal3 floor integer_comma muni-standard-rate"
                           value="{{ number_format((float) $muniStandard * 100, 2, '.', '') }}%"
                           readonly>
                  </div>
                  <div class="mb-1 ms-3">
                      <label class="form-label">都道府県（適用）</label>
                      <input type="number"
                             class="form-control suji7 comma decimal3 floor integer_comma pref-applied-rate"
                             name="pref_applied_rate_{{ $key }}"
                             value="{{ $period['pref_applied_rate'] * 100 }}"
                             min="0"
                             max="100"
                             step="0.001"
                             required>
                  </div>
                  
                  <div class="mb-1 ms-3">
                      <label class="form-label">市区町村（適用）</label>
                      <input type="number"
                             class="form-control suji7 comma decimal3 floor integer_comma muni-applied-rate"
                             name="muni_applied_rate_{{ $key }}"
                             value="{{ $period['muni_applied_rate'] * 100 }}"
                             min="0"
                             max="100"
                             step="0.001"
                             required>
                  </div>
                </div>
                <div>
                  <h1>○均等割・その他税額</h1>
                  <div class="mt-1 mb-1 ms-3">
                    <label class="form-label">都道府県 均等割</label>
                    <input type="number"
                           class="form-control suji7 comma floor integer_comma"
                           name="pref_equal_share_{{ $key }}" 
                           value="{{ $period['pref_equal_share'] }}" 
                           min="0"
                           step="1" 
                           required>
                           円
                  </div>
                  <div class="mb-1 ms-3">
                    <label class="form-label">市区町村 均等割</label>
                    <input type="number" 
                           class="form-control suji7 comma floor integer_comma" 
                           name="muni_equal_share_{{ $key }}" 
                           value="{{ $period['muni_equal_share'] }}"
                           min="0" 
                           step="1"
                           required>
                           円
                  </div>
                  <div class="mb-1 ms-3">
                    <label class="form-label">その他の税額</label>
                    <input type="number" 
                           class="form-control suji7 comma floor integer_comma"
                           name="other_taxes_amount_{{ $key }}"
                           value="{{ $period['other_taxes_amount'] }}"
                           min="0"
                           step="1" 
                           required>
                           円
                  </div>
                </div>
              </div>
            </div>
          </div>
        @endforeach
      </div>
      <hr>
      <div class="btn-footer">
        <div class="d-flex justify-content-end gap-2 me-3 mb-3">
          <button type="submit" class="btn-base-green" formnovalidate>保 存</button>
          <button type="submit"
                  class="btn-base-blue"
                  formnovalidate
                  name="redirect_to"
                  value="input">入力へ進む</button>
          <button type="submit"
                  class="btn-base-blue"
                  formnovalidate
                  name="redirect_to"
                  value="data_master">戻 る</button>
          <button type="submit"
                  class="btn-base-blue"
                  formnovalidate
                  name="redirect_to"
                  value="master">マスター</button>
        </div>
      </div>
    </form>
</div>
<script>
document.addEventListener('DOMContentLoaded', () => {
    const formatValue = (value, digits) => {
        return (value * 100).toFixed(digits);  // Display percentage by multiplying by 100
    };

    const updateCardRates = (card) => {
        const period = card.dataset.period;
        if (!period) {
            return;
        }

        const selected = card.querySelector(`input[name="shitei_toshi_flag_${period}"]:checked`);
        if (!selected) {
            return;
        }

        const isDesignated = selected.value === '1';
        const prefRate = isDesignated ? 0.08 : 0.06;
        const muniRate = isDesignated ? 0.02 : 0.04;

        const prefStandardInput = card.querySelector('.pref-standard-rate');
        if (prefStandardInput) {
            prefStandardInput.value = formatValue(prefRate, 2); // Display percentage
        }

        const muniStandardInput = card.querySelector('.muni-standard-rate');
        if (muniStandardInput) {
            muniStandardInput.value = formatValue(muniRate, 2); // Display percentage
        }

        const prefAppliedInput = card.querySelector('.pref-applied-rate');
        if (prefAppliedInput) {
            prefAppliedInput.value = formatValue(prefRate, 3); // Display percentage
            prefAppliedInput.dispatchEvent(new Event('input', { bubbles: true }));
        }

        const muniAppliedInput = card.querySelector('.muni-applied-rate');
        if (muniAppliedInput) {
            muniAppliedInput.value = formatValue(muniRate, 3); // Display percentage
            muniAppliedInput.dispatchEvent(new Event('input', { bubbles: true }));
        }
    };

    const cards = document.querySelectorAll('.tax-period-card');
    cards.forEach((card) => {
        const period = card.dataset.period;
        const radios = card.querySelectorAll(`input[name="shitei_toshi_flag_${period}"]`);
        radios.forEach((radio) => {
            radio.addEventListener('change', () => updateCardRates(card));
        });

        updateCardRates(card);  // Initially update rates on page load
    });
});
</script>
@endsection
