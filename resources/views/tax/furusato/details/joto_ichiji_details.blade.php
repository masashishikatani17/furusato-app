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
<div class="container my-4" style="max-width: 640px;">
  <h1 class="h5 mb-3">総合譲渡・一時の内訳</h1>

  @if ($errors->any())
    <div class="alert alert-danger">
      <ul class="mb-0">
        @foreach ($errors->all() as $error)
          <li>{{ $error }}</li>
        @endforeach
      </ul>
    </div>
  @endif

  <form method="POST" action="{{ route('furusato.details.joto_ichiji.save') }}">
    @csrf
    <input type="hidden" name="data_id" value="{{ $dataId }}">
    <input type="hidden" name="origin_tab" value="{{ $originTab }}">
    <input type="hidden" name="origin_anchor" value="{{ $originAnchor }}">

    <div class="table-responsive mb-3">
      <table class="table table-bordered table-sm align-middle text-center">
        <thead class="table-light">
          <tr>
            <th class="text-start">項目</th>
            <th style="width: 160px;">{{ $warekiPrevLabel }}</th>
            <th style="width: 160px;">{{ $warekiCurrLabel }}</th>
          </tr>
        </thead>
        <tbody>
          <tr>
            <th scope="row" class="text-start">収入金額</th>
            <td>
              <input type="number" min="0" step="1" class="form-control form-control-sm text-end" name="joto_ichiji_shunyu_prev" value="{{ old('joto_ichiji_shunyu_prev', $inputs['joto_ichiji_shunyu_prev'] ?? null) }}">
            </td>
            <td>
              <input type="number" min="0" step="1" class="form-control form-control-sm text-end" name="joto_ichiji_shunyu_curr" value="{{ old('joto_ichiji_shunyu_curr', $inputs['joto_ichiji_shunyu_curr'] ?? null) }}">
            </td>
          </tr>
          <tr>
            <th scope="row" class="text-start">必要経費</th>
            <td>
              <input type="number" min="0" step="1" class="form-control form-control-sm text-end" name="joto_ichiji_keihi_prev" value="{{ old('joto_ichiji_keihi_prev', $inputs['joto_ichiji_keihi_prev'] ?? null) }}">
            </td>
            <td>
              <input type="number" min="0" step="1" class="form-control form-control-sm text-end" name="joto_ichiji_keihi_curr" value="{{ old('joto_ichiji_keihi_curr', $inputs['joto_ichiji_keihi_curr'] ?? null) }}">
            </td>
          </tr>
          <tr>
            <th scope="row" class="text-start">差引金額</th>
            <td>
              <input type="number" class="form-control form-control-sm text-end" name="joto_ichiji_sashihiki_prev" value="{{ old('joto_ichiji_sashihiki_prev', $inputs['joto_ichiji_sashihiki_prev'] ?? null) }}" readonly>
            </td>
            <td>
              <input type="number" class="form-control form-control-sm text-end" name="joto_ichiji_sashihiki_curr" value="{{ old('joto_ichiji_sashihiki_curr', $inputs['joto_ichiji_sashihiki_curr'] ?? null) }}" readonly>
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