@extends('layouts.min')

@section('title', '総合譲渡・一時（内訳）')

@section('content')
@php
    $inputs = $out['inputs'] ?? [];
    $warekiPrevLabel = $warekiPrev ?? '前年';
    $warekiCurrLabel = $warekiCurr ?? '当年';
    $originTabRaw = request()->input('origin_tab', 'input');
    $originTab = is_string($originTabRaw) && trim($originTabRaw) === 'input' ? 'input' : '';
    $originAnchor = preg_replace('/[^A-Za-z0-9_-]/', '', (string) request()->input('origin_anchor', ''));
@endphp
<div class="container mt-2" style="width:500px;">
  <div class="card-header d-flex align-items-start">
    <img src="{{ asset('storage/images/kado_lefttop.jpg') }}" alt="…">
    <h0 class="mb-0 mt-2">総合譲渡・一時の内訳</h0>
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
      <form method="POST" action="{{ route('furusato.details.joto_ichiji.save') }}">
        @csrf
        <input type="hidden" name="data_id" value="{{ $dataId }}">
        <input type="hidden" name="origin_tab" value="{{ $originTab }}">
        <input type="hidden" name="origin_anchor" value="{{ $originAnchor }}">
    
        <div class="table-responsive mb-3">
          <table class="table-base table-bordered align-middle">
              <tr>
                <th class="text-center th-ccc" style="width: 100px;height:30px;">項  目</th>
                <th class="th-ccc" style="width: 160px;">{{ $warekiPrevLabel }}</th>
                <th class="th-ccc" style="width: 160px;">{{ $warekiCurrLabel }}</th>
              </tr>
           
            <tbody>
              <tr>
                <th scope="row" class="text-center">収入金額</th>
                <td>
                  <input type="number" min="0" step="1" class="form-control suji11" name="joto_ichiji_shunyu_prev" value="{{ old('joto_ichiji_shunyu_prev', $inputs['joto_ichiji_shunyu_prev'] ?? null) }}">
                </td>
                <td>
                  <input type="number" min="0" step="1" class="form-control suji11" name="joto_ichiji_shunyu_curr" value="{{ old('joto_ichiji_shunyu_curr', $inputs['joto_ichiji_shunyu_curr'] ?? null) }}">
                </td>
              </tr>
              <tr>
                <th scope="row">必要経費</th>
                <td>
                  <input type="number" min="0" step="1" class="form-control suji11" name="joto_ichiji_keihi_prev" value="{{ old('joto_ichiji_keihi_prev', $inputs['joto_ichiji_keihi_prev'] ?? null) }}">
                </td>
                <td>
                  <input type="number" min="0" step="1" class="form-control suji11" name="joto_ichiji_keihi_curr" value="{{ old('joto_ichiji_keihi_curr', $inputs['joto_ichiji_keihi_curr'] ?? null) }}">
                </td>
              </tr>
              <tr>
                <th scope="row" class="th-cream">差引金額</th>
                <td>
                  <input type="number" class="form-control suji11" name="joto_ichiji_sashihiki_prev" value="{{ old('joto_ichiji_sashihiki_prev', $inputs['joto_ichiji_sashihiki_prev'] ?? null) }}" readonly>
                </td>
                <td>
                  <input type="number" class="form-control suji11" name="joto_ichiji_sashihiki_curr" value="{{ old('joto_ichiji_sashihiki_curr', $inputs['joto_ichiji_sashihiki_curr'] ?? null) }}" readonly>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
        <hr>
            <div class="text-end me-2 mb-2">
              <button type="submit" class="btn btn-base-blue me-2">戻 る
              </button>
            </div>
      </form>
    </div>  
  </div>    
</div>
@endsection