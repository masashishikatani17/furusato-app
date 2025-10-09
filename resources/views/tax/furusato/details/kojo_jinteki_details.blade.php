@extends('layouts.min')

@section('title', '生命・地震保険料控除（内訳）')

@section('content')
@php
    $inputs = $out['inputs'] ?? [];
    $warekiPrevLabel = $warekiPrev ?? '前年';
    $warekiCurrLabel = $warekiCurr ?? '当年';
    $originTabRaw = request()->input('origin_tab', 'input');
    $originTab = is_string($originTabRaw) && trim($originTabRaw) === 'input' ? 'input' : '';
    $originAnchor = preg_replace('/[^A-Za-z0-9_-]/', '', (string) request()->input('origin_anchor', ''));
@endphp
<div class="container my-4" style="max-width: 720px;">
  <h1 class="h5 mb-3">生命保険料控除・地震保険料控除の内訳</h1>

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
    <input type="hidden" name="origin_anchor" value="{{ $originAnchor }}">

    <div class="table-responsive mb-3">
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
            <th scope="row" class="text-start">生命保険料控除（一般・介護医療等）</th>
            <td>
              <input type="number" min="0" step="1" class="form-control form-control-sm text-end" name="kojo_seimei_kiso_prev" value="{{ old('kojo_seimei_kiso_prev', $inputs['kojo_seimei_kiso_prev'] ?? null) }}">
            </td>
            <td>
              <input type="number" min="0" step="1" class="form-control form-control-sm text-end" name="kojo_seimei_kiso_curr" value="{{ old('kojo_seimei_kiso_curr', $inputs['kojo_seimei_kiso_curr'] ?? null) }}">
            </td>
          </tr>
          <tr>
            <th scope="row" class="text-start">生命保険料控除（特定）</th>
            <td>
              <input type="number" min="0" step="1" class="form-control form-control-sm text-end" name="kojo_seimei_tyoteki_prev" value="{{ old('kojo_seimei_tyoteki_prev', $inputs['kojo_seimei_tyoteki_prev'] ?? null) }}">
            </td>
            <td>
              <input type="number" min="0" step="1" class="form-control form-control-sm text-end" name="kojo_seimei_tyoteki_curr" value="{{ old('kojo_seimei_tyoteki_curr', $inputs['kojo_seimei_tyoteki_curr'] ?? null) }}">
            </td>
          </tr>
          <tr>
            <th scope="row" class="text-start">地震保険料控除</th>
            <td>
              <input type="number" min="0" step="1" class="form-control form-control-sm text-end" name="kojo_jishin_prev" value="{{ old('kojo_jishin_prev', $inputs['kojo_jishin_prev'] ?? null) }}">
            </td>
            <td>
              <input type="number" min="0" step="1" class="form-control form-control-sm text-end" name="kojo_jishin_curr" value="{{ old('kojo_jishin_curr', $inputs['kojo_jishin_curr'] ?? null) }}">
            </td>
          </tr>
          <tr>
            <th scope="row" class="text-start">合計（参考）</th>
            <td>
              <input type="number" class="form-control form-control-sm text-end" name="kojo_seimei_jishin_gokei_prev" value="{{ old('kojo_seimei_jishin_gokei_prev', $inputs['kojo_seimei_jishin_gokei_prev'] ?? null) }}" readonly>
            </td>
            <td>
              <input type="number" class="form-control form-control-sm text-end" name="kojo_seimei_jishin_gokei_curr" value="{{ old('kojo_seimei_jishin_gokei_curr', $inputs['kojo_seimei_jishin_gokei_curr'] ?? null) }}" readonly>
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