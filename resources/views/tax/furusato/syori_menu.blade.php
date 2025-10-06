@extends('layouts.min')

@section('content')
@php
  $settings = $settings ?? [];
  $detailMode = old('detail_mode', (string)($settings['detail_mode'] ?? 1));
  $bunriFlag = old('bunri_flag', (string)($settings['bunri_flag'] ?? 0));
  $oneStopFlag = old('one_stop_flag', (string)($settings['one_stop_flag'] ?? 1));
  $shiteiFlag = old('shitei_toshi_flag', (string)($settings['shitei_toshi_flag'] ?? 0));
  $prefStandard = old('pref_standard_rate', $settings['pref_standard_rate'] ?? 0.04);
  $muniStandard = old('muni_standard_rate', $settings['muni_standard_rate'] ?? 0.06);
  $prefApplied = old('pref_applied_rate', $settings['pref_applied_rate'] ?? $prefStandard);
  $muniApplied = old('muni_applied_rate', $settings['muni_applied_rate'] ?? $muniStandard);
  $prefEqual = old('pref_equal_share', $settings['pref_equal_share'] ?? 1500);
  $muniEqual = old('muni_equal_share', $settings['muni_equal_share'] ?? 3500);
  $otherTaxes = old('other_taxes_amount', $settings['other_taxes_amount'] ?? 0);
@endphp
<div class="container" style="max-width: 840px;">
  <form method="POST" action="{{ route('furusato.syori.save') }}" id="furusato-syori-form" class="card">
    @csrf
    <input type="hidden" name="data_id" value="{{ $dataId }}">
    <input type="hidden" name="redirect_to" value="">
    <div class="card-header d-flex justify-content-between align-items-center">
      <h5 class="mb-0">ふるさと納税：処理メニュー設定</h5>
    </div>
    <div class="card-body">
      @if ($errors->any())
        <div class="alert alert-danger">
          <ul class="mb-0">
            @foreach ($errors->all() as $error)
              <li>{{ $error }}</li>
            @endforeach
          </ul>
        </div>
      @endif
      <div class="mb-4">
        <h6 class="mb-3">処理モード</h6>
        <div class="d-flex flex-wrap gap-3">
          <div>
            <span class="d-block mb-1">処理タイプ</span>
            <div class="form-check">
              <input class="form-check-input" type="radio" name="detail_mode" id="detailModeDetail" value="1" @checked($detailMode === '1')>
              <label class="form-check-label" for="detailModeDetail">詳細版</label>
            </div>
            <div class="form-check">
              <input class="form-check-input" type="radio" name="detail_mode" id="detailModeSimple" value="0" @checked($detailMode === '0')>
              <label class="form-check-label" for="detailModeSimple">簡便版</label>
            </div>
          </div>
          <div>
            <span class="d-block mb-1">分離課税</span>
            <div class="form-check">
              <input class="form-check-input" type="radio" name="bunri_flag" id="bunriOff" value="0" @checked($bunriFlag === '0')>
              <label class="form-check-label" for="bunriOff">なし</label>
            </div>
            <div class="form-check">
              <input class="form-check-input" type="radio" name="bunri_flag" id="bunriOn" value="1" @checked($bunriFlag === '1')>
              <label class="form-check-label" for="bunriOn">あり</label>
            </div>
          </div>
          <div>
            <span class="d-block mb-1">ワンストップ特例</span>
            <div class="form-check">
              <input class="form-check-input" type="radio" name="one_stop_flag" id="oneStopUse" value="1" @checked($oneStopFlag === '1')>
              <label class="form-check-label" for="oneStopUse">利用する</label>
            </div>
            <div class="form-check">
              <input class="form-check-input" type="radio" name="one_stop_flag" id="oneStopNotUse" value="0" @checked($oneStopFlag === '0')>
              <label class="form-check-label" for="oneStopNotUse">利用しない</label>
            </div>
          </div>
          <div>
            <span class="d-block mb-1">指定都市区分</span>
            <div class="form-check">
              <input class="form-check-input" type="radio" name="shitei_toshi_flag" id="shiteiMuni" value="1" @checked($shiteiFlag === '1')>
              <label class="form-check-label" for="shiteiMuni">指定都市</label>
            </div>
            <div class="form-check">
              <input class="form-check-input" type="radio" name="shitei_toshi_flag" id="shiteiOther" value="0" @checked($shiteiFlag === '0')>
              <label class="form-check-label" for="shiteiOther">指定都市以外</label>
            </div>
          </div>
        </div>
      </div>
      <div class="mb-4">
        <h6 class="mb-3">所得割の税率</h6>
        <div class="row g-3 align-items-end">
          <div class="col-md-6">
            <label class="form-label">都道府県（標準）</label>
            <input type="text" class="form-control" id="pref-standard-rate" value="{{ number_format((float) $prefStandard, 2, '.', '') }}" readonly>
          </div>
          <div class="col-md-6">
            <label class="form-label">市区町村（標準）</label>
            <input type="text" class="form-control" id="muni-standard-rate" value="{{ number_format((float) $muniStandard, 2, '.', '') }}" readonly>
          </div>
          <div class="col-md-6">
            <label class="form-label">都道府県（適用）</label>
            <input type="number" class="form-control" name="pref_applied_rate" id="pref-applied-rate" value="{{ $prefApplied }}" min="0" max="1" step="0.001" required>
          </div>
          <div class="col-md-6">
            <label class="form-label">市区町村（適用）</label>
            <input type="number" class="form-control" name="muni_applied_rate" id="muni-applied-rate" value="{{ $muniApplied }}" min="0" max="1" step="0.001" required>
          </div>
        </div>
      </div>
      <div class="mb-4">
        <h6 class="mb-3">均等割・その他税額</h6>
        <div class="row g-3">
          <div class="col-md-4">
            <label class="form-label">都道府県 均等割（円）</label>
            <input type="number" class="form-control" name="pref_equal_share" value="{{ $prefEqual }}" min="0" step="1" required>
          </div>
          <div class="col-md-4">
            <label class="form-label">市区町村 均等割（円）</label>
            <input type="number" class="form-control" name="muni_equal_share" value="{{ $muniEqual }}" min="0" step="1" required>
          </div>
          <div class="col-md-4">
            <label class="form-label">その他の税額（円）</label>
            <input type="number" class="form-control" name="other_taxes_amount" value="{{ $otherTaxes }}" min="0" step="1" required>
          </div>
        </div>
      </div>
      <div class="d-flex justify-content-end gap-2">
        <button type="submit" class="btn btn-success" id="furusato-syori-save" formnovalidate>戻る</button>
        <button type="button" class="btn btn-primary" id="furusato-syori-go-input">入力へ進む</button>
      </div>
    </div>
  </form>
</div>
@endsection

@push('scripts')
<script>
(function() {
  const form = document.getElementById('furusato-syori-form');
  if (!form) {
    return;
  }
  const prefStandardInput = document.getElementById('pref-standard-rate');
  const muniStandardInput = document.getElementById('muni-standard-rate');
  const prefAppliedInput = document.getElementById('pref-applied-rate');
  const muniAppliedInput = document.getElementById('muni-applied-rate');
  const shiteiRadios = form.querySelectorAll('input[name="shitei_toshi_flag"]');
  let prefAppliedDirty = false;
  let muniAppliedDirty = false;
  const valuesEqual = function(a, b) {
    const av = parseFloat(a);
    const bv = parseFloat(b);
    if (Number.isNaN(av) || Number.isNaN(bv)) {
      return false;
    }
    return Math.abs(av - bv) < 1e-9;
  };

  prefAppliedInput?.addEventListener('input', function() {
    prefAppliedDirty = true;
  });
  muniAppliedInput?.addEventListener('input', function() {
    muniAppliedDirty = true;
  });

  shiteiRadios.forEach(function(radio) {
    radio.addEventListener('change', function() {
      const isDesignated = form.querySelector('input[name="shitei_toshi_flag"]:checked')?.value === '1';
      const prefStandard = isDesignated ? 0.02 : 0.04;
      const muniStandard = isDesignated ? 0.08 : 0.06;
      if (prefStandardInput) {
        prefStandardInput.value = prefStandard.toFixed(2);
      }
      if (muniStandardInput) {
        muniStandardInput.value = muniStandard.toFixed(2);
      }
      if (!prefAppliedDirty && prefAppliedInput) {
        prefAppliedInput.value = prefStandard.toFixed(2);
      }
      if (!muniAppliedDirty && muniAppliedInput) {
        muniAppliedInput.value = muniStandard.toFixed(2);
      }
    });
  });

  prefAppliedDirty = !(prefAppliedInput && prefStandardInput && valuesEqual(prefAppliedInput.value, prefStandardInput.value));
  muniAppliedDirty = !(muniAppliedInput && muniStandardInput && valuesEqual(muniAppliedInput.value, muniStandardInput.value));

  document.getElementById('furusato-syori-back')?.addEventListener('click', function() {
    form.redirect_to.value = '';
  });

  document.getElementById('furusato-syori-save')?.addEventListener('click', function() {
    form.redirect_to.value = '';
  });

  document.getElementById('furusato-syori-go-input')?.addEventListener('click', function(event) {
    event.preventDefault();
    form.redirect_to.value = 'input';
    form.submit();
  });
})();
</script>
@endpush