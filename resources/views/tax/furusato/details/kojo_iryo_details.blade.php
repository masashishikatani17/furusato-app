@extends('layouts.min')

@section('title', '医療費控除（内訳）')

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
  <h1 class="h5 mb-3">医療費控除の内訳</h1>

  @if ($errors->any())
    <div class="alert alert-danger">
      <ul class="mb-0">
        @foreach ($errors->all() as $error)
          <li>{{ $error }}</li>
        @endforeach
      </ul>
    </div>
  @endif

  <form method="POST" action="{{ route('furusato.details.kojo_iryo.save') }}">
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
            <th scope="row" class="text-start">支出額</th>
            <td>
              <input type="number" min="0" step="1" class="form-control form-control-sm text-end" name="kojo_iryo_shishutsu_prev" value="{{ old('kojo_iryo_shishutsu_prev', $inputs['kojo_iryo_shishutsu_prev'] ?? null) }}">
            </td>
            <td>
              <input type="number" min="0" step="1" class="form-control form-control-sm text-end" name="kojo_iryo_shishutsu_curr" value="{{ old('kojo_iryo_shishutsu_curr', $inputs['kojo_iryo_shishutsu_curr'] ?? null) }}">
            </td>
          </tr>
          <tr>
            <th scope="row" class="text-start">保険金等で補填される額</th>
            <td>
              <input type="number" min="0" step="1" class="form-control form-control-sm text-end" name="kojo_iryo_hojokin_prev" value="{{ old('kojo_iryo_hojokin_prev', $inputs['kojo_iryo_hojokin_prev'] ?? null) }}">
            </td>
            <td>
              <input type="number" min="0" step="1" class="form-control form-control-sm text-end" name="kojo_iryo_hojokin_curr" value="{{ old('kojo_iryo_hojokin_curr', $inputs['kojo_iryo_hojokin_curr'] ?? null) }}">
            </td>
          </tr>
          <tr>
            <th scope="row" class="text-start">控除対象額（参考）</th>
            <td>
              <input type="number" class="form-control form-control-sm text-end" name="kojo_iryo_kojogaku_prev" value="{{ old('kojo_iryo_kojogaku_prev', $inputs['kojo_iryo_kojogaku_prev'] ?? null) }}" readonly>
            </td>
            <td>
              <input type="number" class="form-control form-control-sm text-end" name="kojo_iryo_kojogaku_curr" value="{{ old('kojo_iryo_kojogaku_curr', $inputs['kojo_iryo_kojogaku_curr'] ?? null) }}" readonly>
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