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
<div class="container-blue mt-2" style="max-width: 840px;">
  <div class="card-header d-flex align-items-start">
    <img src="{{ asset('storage/images/kado_lefttop.jpg') }}" alt="…">
    <h0 class="mb-0 mt-2">処理メニュー設定</h0>
  </div>
  <div class="card-body">
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
      <div class="mb-4 mt-3">
        <h1 class="ms-3">○処理モード</h1>
          <table class="table-base" align="center">
            <tr>
              <td style="width:100px;background-color:#f9f8ee">
                  <div>
                    <hb class="d-block mt-2">処理タイプ</hb><hr>
                    <div class="form-check">
                      <input class="form-check-input" type="radio" name="detail_mode" id="detailModeDetail" value="1" @checked($detailMode === '1')>
                      <label class="form-check-label" for="detailModeDetail">詳細版</label>
                    </div>
                    <div class="form-check">
                      <input class="form-check-input" type="radio" name="detail_mode" id="detailModeSimple" value="0" @checked($detailMode === '0')>
                      <label class="form-check-label" for="detailModeSimple">簡便版</label>
                    </div>
                  </div>
              </td>
              <td style="width:20px;">
              </td>
              <td style="width:100px;background-color:#f9f8ee">
                  <div>
                    <hb class="d-block mt-2">分離課税</hb><hr>
                    <div class="form-check">
                      <input class="form-check-input" type="radio" name="bunri_flag" id="bunriOff" value="0" @checked($bunriFlag === '0')>
                      <label class="form-check-label" for="bunriOff">なし</label>
                    </div>
                    <div class="form-check">
                      <input class="form-check-input" type="radio" name="bunri_flag" id="bunriOn" value="1" @checked($bunriFlag === '1')>
                      <label class="form-check-label" for="bunriOn">あり</label>
                    </div>
                  </div>
              </td>
              <td style="width:20px;">
              </td>
              <td style="width:120px;background-color:#f9f8ee;">
                  <div>
                    <hb class="d-block mt-2">ワンストップ特例</hb><hr>
                    <div class="form-check">
                      <input class="form-check-input" type="radio" name="one_stop_flag" id="oneStopUse" value="1" @checked($oneStopFlag === '1')>
                      <label class="form-check-label" for="oneStopUse">利用する</label>
                    </div>
                    <div class="form-check">
                      <input class="form-check-input" type="radio" name="one_stop_flag" id="oneStopNotUse" value="0" @checked($oneStopFlag === '0')>
                      <label class="form-check-label" for="oneStopNotUse">利用しない</label>
                    </div>
                  </div>
              </td>
              <td style="width:20px;">
              </td>
              <td style="width:120px;background-color:#f9f8ee;">
                  <div>
                    <hb class="d-block mt-2">指定都市区分</hb><hr>
                    <div class="form-check">
                      <input class="form-check-input" type="radio" name="shitei_toshi_flag" id="shiteiMuni" value="1" @checked($shiteiFlag === '1')>
                      <label class="form-check-label" for="shiteiMuni">指定都市</label>
                    </div>
                    <div class="form-check">
                      <input class="form-check-input" type="radio" name="shitei_toshi_flag" id="shiteiOther" value="0" @checked($shiteiFlag === '0')>
                      <label class="form-check-label" for="shiteiOther">指定都市以外</label>
                    </div>
                  </div>
              </td>
            </tr>
          </table>
      </div>
      <div class="mb-4">
        <h1 class="ms-3 mb-3">○所得割の税率</h1>
        <div class="row g-3 align-items-end">
          <div class="col-md-6">
            <div class="d-flex align-items-center gap-2 ms-5">
              <label class="form-label">都道府県（標準）</label>
              <input type="text" class="form-control suji4 comma decimal3 floor integer_comma" id="pref-standard-rate" value="{{ number_format((float) $prefStandard, 2, '.', '') }}" readonly>
            </div>  
          </div>
          <div class="col-md-6">
            <div class="d-flex align-items-center gap-2">
              <label class="form-label">市区町村（標準）</label>
              <input type="text" class="form-control suji4 comma decimal3 floor integer_comma" id="muni-standard-rate" value="{{ number_format((float) $muniStandard, 2, '.', '') }}" readonly>
            </div>  
          </div>
          <div class="col-md-6">
            <div class="d-flex align-items-center gap-2 ms-5">
              <label class="form-label">都道府県（適用）</label>
              <input type="number" class="form-control suji7 comma decimal3 floor integer_comma" name="pref_applied_rate" id="pref-applied-rate" value="{{ $prefApplied }}" min="0" max="1" step="0.001" required>
            </div>
          </div>
          <div class="col-md-6">
            <div class="d-flex align-items-center gap-2">
              <label class="form-label">市区町村（適用）</label>
              <input type="number" class="form-control suji7 comma decimal3 floor integer_comma" name="muni_applied_rate" id="muni-applied-rate" value="{{ $muniApplied }}" min="0" max="1" step="0.001" required>
            </div>
          </div>
        </div>
      </div>
      <div class="mb-4">
        <h1 class="ms-3 mb-3">○均等割・その他税額</h1>
        <div class="row g-3">
          <div class="col-md-6">
            <label class="form-label ms-5 me-1">都道府県 均等割</label>
            <input type="number" class="form-control suji7 comma floor integer_comma" name="pref_equal_share" value="{{ $prefEqual }}" min="0" step="1" required>
            円
          </div>
          <div class="col-md-6">
            <label class="form-label me-1">市区町村 均等割</label>
            <input type="number" class="form-control suji7 comma floor integer_comma" name="muni_equal_share" value="{{ $muniEqual }}" min="0" step="1" required>
            円
          </div>
        </div>  
          <div class="col-md-12">
            <label class="form-label ms-5 me-4">その他の税額 </label>
            <input type="number" class="form-control suji7 comma floor integer_comma" name="other_taxes_amount" value="{{ $otherTaxes }}" min="0" step="1" required>
            円
          </div>
        
      </div>
      <div class="btn-footer mt-3">
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
</div>
@endsection
