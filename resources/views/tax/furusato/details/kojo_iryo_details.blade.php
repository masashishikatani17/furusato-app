@extends('layouts.min')

@section('title', '内訳－医療費控除')

@section('content')
@php
    $inputs = $out['inputs'] ?? [];
    $warekiPrevLabel = $warekiPrev ?? '前年';
    $warekiCurrLabel = $warekiCurr ?? '当年';
    $originTab = 'input';
    $originAnchor = preg_replace('/[^A-Za-z0-9_-]/', '', (string) request()->input('origin_anchor', 'kojo_iryo'));
@endphp
<div class="container-blue mt-2" style="width:800px;">
  <div class="card-header d-flex align-items-start">
    <img src="{{ asset('storage/images/kado_lefttop.jpg') }}" alt="…">
    <h0 class="mb-0 mt-2">医療費控除の内訳</h0>
  </div>

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
      <form method="POST" action="{{ route('furusato.details.kojo_iryo.save') }}">
        @csrf
        <input type="hidden" name="data_id" value="{{ $dataId }}">
        <input type="hidden" name="origin_tab" value="{{ $originTab }}">
        <input type="hidden" name="origin_anchor" value="{{ $originAnchor ?: 'kojo_iryo' }}">
    
        <div class="table-responsive">
          <table class="table-base table-bordered align-middle">
              <tr>
                <th scope="col" class="text-center" style="width: 260px;">項  目</th>
                <th scope="col" style="width: 150px;">{{ $warekiPrevLabel }}</th>
                <th scope="col" style="width: 150px;">{{ $warekiCurrLabel }}</th>
                <th scope="col" style="width: 150px;"></th>
              </tr>
            <tbody>
              <tr>
                <th scope="row" class="text-start">支払った医療費（Ⓐ）</th>
                <td>
                  <input type="number" min="0" step="1" class="form-control suji11 js-iryo" name="kojo_iryo_shiharai_prev" value="{{ old('kojo_iryo_shiharai_prev', $inputs['kojo_iryo_shiharai_prev'] ?? null) }}">
                </td>
                <td>
                  <input type="number" min="0" step="1" class="form-control suji11 js-iryo" name="kojo_iryo_shiharai_curr" value="{{ old('kojo_iryo_shiharai_curr', $inputs['kojo_iryo_shiharai_curr'] ?? null) }}">
                </td>
                <td class="bg-light"></td>
              </tr>
              <tr>
                <th scope="row" class="text-start">保険金などで補填される金額（Ⓑ）</th>
                <td>
                  <input type="number" min="0" step="1" class="form-control suji11 js-iryo" name="kojo_iryo_hotengaku_prev" value="{{ old('kojo_iryo_hotengaku_prev', $inputs['kojo_iryo_hotengaku_prev'] ?? null) }}">
                </td>
                <td>
                  <input type="number" min="0" step="1" class="form-control suji11 js-iryo" name="kojo_iryo_hotengaku_curr" value="{{ old('kojo_iryo_hotengaku_curr', $inputs['kojo_iryo_hotengaku_curr'] ?? null) }}">
                </td>
                <td class="bg-light"></td>
              </tr>
              <tr>
                <th scope="row" class="text-start">差引金額（Ⓒ＝Ⓐ－Ⓑ）</th>
                <td>
                  <input type="number" class="form-control suji11 bg-light" name="kojo_iryo_sashihiki_prev" value="{{ $inputs['kojo_iryo_sashihiki_prev'] ?? 0 }}" readonly>
                </td>
                <td>
                  <input type="number" class="form-control suji11 bg-light" name="kojo_iryo_sashihiki_curr" value="{{ $inputs['kojo_iryo_sashihiki_curr'] ?? 0 }}" readonly>
                </td>
                <td class="bg-light"></td>
              </tr>
              <tr>
                <th scope="row" class="text-start">所得金額の合計額（Ⓓ）</th>
                <td>
                  <input type="number" class="form-control suji11 bg-light" name="kojo_iryo_shotoku_gokei_prev" value="{{ $inputs['kojo_iryo_shotoku_gokei_prev'] ?? 0 }}" readonly>
                </td>
                <td>
                  <input type="number" class="form-control suji11 bg-light" name="kojo_iryo_shotoku_gokei_curr" value="{{ $inputs['kojo_iryo_shotoku_gokei_curr'] ?? 0 }}" readonly>
                </td>
                <td class="bg-light"></td>
              </tr>
              <tr>
                <th scope="row" class="text-start">Ⓓ×0.05（Ⓔ）</th>
                <td>
                  <input type="number" class="form-control suji11 bg-light" name="kojo_iryo_shotoku_5pct_prev" value="{{ $inputs['kojo_iryo_shotoku_5pct_prev'] ?? 0 }}" readonly>
                </td>
                <td>
                  <input type="number" class="form-control suji11 bg-light" name="kojo_iryo_shotoku_5pct_curr" value="{{ $inputs['kojo_iryo_shotoku_5pct_curr'] ?? 0 }}" readonly>
                </td>
                <td class="bg-light"></td>
              </tr>
              <tr>
                <th scope="row" class="text-start">Ⓔと10万円のいずれか少ない方の金額（Ⓕ）</th>
                <td>
                  <input type="number" class="form-control suji11 bg-light" name="kojo_iryo_min_threshold_prev" value="{{ $inputs['kojo_iryo_min_threshold_prev'] ?? 0 }}" readonly>
                </td>
                <td>
                  <input type="number" class="form-control suji11 bg-light" name="kojo_iryo_min_threshold_curr" value="{{ $inputs['kojo_iryo_min_threshold_curr'] ?? 0 }}" readonly>
                </td>
                <td class="bg-light"></td>
              </tr>
              <tr>
                <th scope="row" class="text-start th-cream">医療費控除額（Ⓖ＝Ⓒ－Ⓕ）</th>
                <td>
                  <input type="number" class="form-control suji11 bg-light" name="kojo_iryo_kojogaku_prev" value="{{ $inputs['kojo_iryo_kojogaku_prev'] ?? 0 }}" readonly>
                </td>
                <td>
                  <input type="number" class="form-control suji11 bg-light" name="kojo_iryo_kojogaku_curr" value="{{ $inputs['kojo_iryo_kojogaku_curr'] ?? 0 }}" readonly>
                </td>
                <td class="bg-light"></td>
              </tr>
            </tbody>
          </table>
        </div>
        <hr>
            <div class="text-end me-2 mb-2">
              <button type="submit" class="btn-base-blue">戻 る</button>
            </div>
      </form>
    </div>  
  </div>    
</div>
@endsection

@push('scripts')
  <script>
    document.addEventListener('DOMContentLoaded', function () {
      const getInt = (value) => {
        if (value === null || value === undefined || value === '') {
          return 0;
        }
        const parsed = parseInt(value, 10);
        return Number.isNaN(parsed) ? 0 : parsed;
      };

      const recalcCol = (period) => {
        const shiharai = document.querySelector(`[name="kojo_iryo_shiharai_${period}"]`);
        const hoten = document.querySelector(`[name="kojo_iryo_hotengaku_${period}"]`);
        const sashihiki = document.querySelector(`[name="kojo_iryo_sashihiki_${period}"]`);
        const shotokuGokei = document.querySelector(`[name="kojo_iryo_shotoku_gokei_${period}"]`);
        const shotoku5pct = document.querySelector(`[name="kojo_iryo_shotoku_5pct_${period}"]`);
        const minThreshold = document.querySelector(`[name="kojo_iryo_min_threshold_${period}"]`);
        const kojogaku = document.querySelector(`[name="kojo_iryo_kojogaku_${period}"]`);

        const a = shiharai ? getInt(shiharai.value) : 0;
        const b = hoten ? getInt(hoten.value) : 0;
        const d = shotokuGokei ? getInt(shotokuGokei.value) : 0;

        const c = a - b;
        const e = Math.floor(d * 0.05);
        const f = Math.min(e, 100000);
        const g = Math.max(c - f, 0);

        if (sashihiki) {
          sashihiki.value = c;
        }
        if (shotoku5pct) {
          shotoku5pct.value = e;
        }
        if (minThreshold) {
          minThreshold.value = f;
        }
        if (kojogaku) {
          kojogaku.value = g;
        }
      };

      document.querySelectorAll('.js-iryo').forEach((input) => {
        input.addEventListener('blur', () => {
          const name = input.getAttribute('name') || '';
          if (name.endsWith('_prev')) {
            recalcCol('prev');
          } else if (name.endsWith('_curr')) {
            recalcCol('curr');
          }
        });
      });

      recalcCol('prev');
      recalcCol('curr');
    });
  </script>
@endpush