@extends('layouts.min')

@section('title', '内訳－生命・地震')

@section('content')
@php
    $inputs = $out['inputs'] ?? [];
    $warekiPrevLabel = $warekiPrev ?? '前年';
    $warekiCurrLabel = $warekiCurr ?? '当年';
    $originTab = 'input';
    $originAnchor = preg_replace('/[^A-Za-z0-9_-]/', '', (string) request()->input('origin_anchor', 'kojo_seimei_jishin'));
@endphp
<div class="container my-4" style="max-width: 720px;">
  <h1 class="h5 mb-3">生命保険料・地震保険料の内訳</h1>

  @if ($errors->any())
    <div class="alert alert-danger">
      <ul class="mb-0">
        @foreach ($errors->all() as $error)
          <li>{{ $error }}</li>
        @endforeach
      </ul>
    </div>
  @endif

  <form method="POST" action="{{ route('furusato.details.kojo_seimei_jishin.save') }}">
    @csrf
    <input type="hidden" name="data_id" value="{{ $dataId }}">
    <input type="hidden" name="origin_tab" value="{{ $originTab }}">
    <input type="hidden" name="origin_anchor" value="{{ $originAnchor ?: 'kojo_seimei_jishin' }}">

    <div class="table-responsive mb-4">
      <table class="table table-bordered table-sm align-middle text-center">
        <thead class="table-light">
          <tr>
            <th class="text-start">項目</th>
            <th style="width: 180px;">{{ $warekiPrevLabel }}</th>
            <th style="width: 180px;">{{ $warekiCurrLabel }}</th>
          </tr>
        </thead>
        <tbody>
          <tr>
            <th scope="row" class="text-start">新生命保険料</th>
            <td>
              <input type="number" min="0" step="1" class="form-control suji11 js-seimei" name="kojo_seimei_shin_prev" value="{{ old('kojo_seimei_shin_prev', $inputs['kojo_seimei_shin_prev'] ?? null) }}">
            </td>
            <td>
              <input type="number" min="0" step="1" class="form-control suji11 js-seimei" name="kojo_seimei_shin_curr" value="{{ old('kojo_seimei_shin_curr', $inputs['kojo_seimei_shin_curr'] ?? null) }}">
            </td>
          </tr>
          <tr>
            <th scope="row" class="text-start">旧生命保険料</th>
            <td>
              <input type="number" min="0" step="1" class="form-control suji11 js-seimei" name="kojo_seimei_kyu_prev" value="{{ old('kojo_seimei_kyu_prev', $inputs['kojo_seimei_kyu_prev'] ?? null) }}">
            </td>
            <td>
              <input type="number" min="0" step="1" class="form-control suji11 js-seimei" name="kojo_seimei_kyu_curr" value="{{ old('kojo_seimei_kyu_curr', $inputs['kojo_seimei_kyu_curr'] ?? null) }}">
            </td>
          </tr>
          <tr>
            <th scope="row" class="text-start">新個人年金保険料</th>
            <td>
              <input type="number" min="0" step="1" class="form-control suji11 js-seimei" name="kojo_seimei_nenkin_shin_prev" value="{{ old('kojo_seimei_nenkin_shin_prev', $inputs['kojo_seimei_nenkin_shin_prev'] ?? null) }}">
            </td>
            <td>
              <input type="number" min="0" step="1" class="form-control suji11 js-seimei" name="kojo_seimei_nenkin_shin_curr" value="{{ old('kojo_seimei_nenkin_shin_curr', $inputs['kojo_seimei_nenkin_shin_curr'] ?? null) }}">
            </td>
          </tr>
          <tr>
            <th scope="row" class="text-start">旧個人年金保険料</th>
            <td>
              <input type="number" min="0" step="1" class="form-control suji11 js-seimei" name="kojo_seimei_nenkin_kyu_prev" value="{{ old('kojo_seimei_nenkin_kyu_prev', $inputs['kojo_seimei_nenkin_kyu_prev'] ?? null) }}">
            </td>
            <td>
              <input type="number" min="0" step="1" class="form-control suji11 js-seimei" name="kojo_seimei_nenkin_kyu_curr" value="{{ old('kojo_seimei_nenkin_kyu_curr', $inputs['kojo_seimei_nenkin_kyu_curr'] ?? null) }}">
            </td>
          </tr>
          <tr>
            <th scope="row" class="text-start">介護医療保険料</th>
            <td>
              <input type="number" min="0" step="1" class="form-control suji11 js-seimei" name="kojo_seimei_kaigo_iryo_prev" value="{{ old('kojo_seimei_kaigo_iryo_prev', $inputs['kojo_seimei_kaigo_iryo_prev'] ?? null) }}">
            </td>
            <td>
              <input type="number" min="0" step="1" class="form-control suji11 js-seimei" name="kojo_seimei_kaigo_iryo_curr" value="{{ old('kojo_seimei_kaigo_iryo_curr', $inputs['kojo_seimei_kaigo_iryo_curr'] ?? null) }}">
            </td>
          </tr>
          <tr>
            <th scope="row" class="text-start">合計</th>
            <td>
              <input type="number" class="form-control suji11 bg-light" name="kojo_seimei_gokei_prev" value="{{ old('kojo_seimei_gokei_prev', $inputs['kojo_seimei_gokei_prev'] ?? null) }}" readonly>
            </td>
            <td>
              <input type="number" class="form-control suji11 bg-light" name="kojo_seimei_gokei_curr" value="{{ old('kojo_seimei_gokei_curr', $inputs['kojo_seimei_gokei_curr'] ?? null) }}" readonly>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <div class="table-responsive mb-4">
      <table class="table table-bordered table-sm align-middle text-center">
        <thead class="table-light">
          <tr>
            <th class="text-start">項目</th>
            <th style="width: 180px;">{{ $warekiPrevLabel }}</th>
            <th style="width: 180px;">{{ $warekiCurrLabel }}</th>
          </tr>
        </thead>
        <tbody>
          <tr>
            <th scope="row" class="text-start">地震保険料</th>
            <td>
              <input type="number" min="0" step="1" class="form-control suji11 js-jishin" name="kojo_jishin_prev" value="{{ old('kojo_jishin_prev', $inputs['kojo_jishin_prev'] ?? null) }}">
            </td>
            <td>
              <input type="number" min="0" step="1" class="form-control suji11 js-jishin" name="kojo_jishin_curr" value="{{ old('kojo_jishin_curr', $inputs['kojo_jishin_curr'] ?? null) }}">
            </td>
          </tr>
          <tr>
            <th scope="row" class="text-start">旧長期損害保険料</th>
            <td>
              <input type="number" min="0" step="1" class="form-control suji11 js-jishin" name="kojo_kyuchoki_songai_prev" value="{{ old('kojo_kyuchoki_songai_prev', $inputs['kojo_kyuchoki_songai_prev'] ?? null) }}">
            </td>
            <td>
              <input type="number" min="0" step="1" class="form-control suji11 js-jishin" name="kojo_kyuchoki_songai_curr" value="{{ old('kojo_kyuchoki_songai_curr', $inputs['kojo_kyuchoki_songai_curr'] ?? null) }}">
            </td>
          </tr>
          <tr>
            <th scope="row" class="text-start">合計</th>
            <td>
              <input type="number" class="form-control suji11 bg-light" name="kojo_jishin_gokei_prev" value="{{ old('kojo_jishin_gokei_prev', $inputs['kojo_jishin_gokei_prev'] ?? null) }}" readonly>
            </td>
            <td>
              <input type="number" class="form-control suji11 bg-light" name="kojo_jishin_gokei_curr" value="{{ old('kojo_jishin_gokei_curr', $inputs['kojo_jishin_gokei_curr'] ?? null) }}" readonly>
            </td>
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

@push('scripts')
  <script>
    document.addEventListener('DOMContentLoaded', function () {
      const seimeiKeysPrev = [
        'kojo_seimei_shin_prev',
        'kojo_seimei_kyu_prev',
        'kojo_seimei_nenkin_shin_prev',
        'kojo_seimei_nenkin_kyu_prev',
        'kojo_seimei_kaigo_iryo_prev',
      ];
      const seimeiKeysCurr = [
        'kojo_seimei_shin_curr',
        'kojo_seimei_kyu_curr',
        'kojo_seimei_nenkin_shin_curr',
        'kojo_seimei_nenkin_kyu_curr',
        'kojo_seimei_kaigo_iryo_curr',
      ];
      const jishinKeysPrev = [
        'kojo_jishin_prev',
        'kojo_kyuchoki_songai_prev',
      ];
      const jishinKeysCurr = [
        'kojo_jishin_curr',
        'kojo_kyuchoki_songai_curr',
      ];

      const parseValue = (input) => {
        if (!input) {
          return 0;
        }
        const value = parseInt(input.value, 10);
        return Number.isNaN(value) ? 0 : value;
      };

      const sumByKeys = (keys) => {
        return keys.reduce((total, key) => {
          const input = document.querySelector(`[name="${key}"]`);
          return total + parseValue(input);
        }, 0);
      };

      const updateSeimeiTotals = () => {
        const prevTotal = sumByKeys(seimeiKeysPrev);
        const currTotal = sumByKeys(seimeiKeysCurr);
        const prevTarget = document.querySelector('[name="kojo_seimei_gokei_prev"]');
        const currTarget = document.querySelector('[name="kojo_seimei_gokei_curr"]');
        if (prevTarget) {
          prevTarget.value = prevTotal;
        }
        if (currTarget) {
          currTarget.value = currTotal;
        }
      };

      const updateJishinTotals = () => {
        const prevTotal = sumByKeys(jishinKeysPrev);
        const currTotal = sumByKeys(jishinKeysCurr);
        const prevTarget = document.querySelector('[name="kojo_jishin_gokei_prev"]');
        const currTarget = document.querySelector('[name="kojo_jishin_gokei_curr"]');
        if (prevTarget) {
          prevTarget.value = prevTotal;
        }
        if (currTarget) {
          currTarget.value = currTotal;
        }
      };

      document.querySelectorAll('.js-seimei').forEach((el) => {
        el.addEventListener('blur', updateSeimeiTotals);
      });
      document.querySelectorAll('.js-jishin').forEach((el) => {
        el.addEventListener('blur', updateJishinTotals);
      });

      updateSeimeiTotals();
      updateJishinTotals();
    });
  </script>
@endpush