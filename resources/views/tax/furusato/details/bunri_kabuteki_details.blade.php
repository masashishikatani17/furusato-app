@extends('layouts.min')

@section('title', '株式等の譲渡所得等 内訳')

@section('content')
@php
    $inputs = $out['inputs'] ?? [];
    $originTabRaw = request()->input('origin_tab', 'input');
    $originTab = is_string($originTabRaw) && trim($originTabRaw) === 'input' ? 'input' : '';
    $originAnchor = preg_replace('/[^A-Za-z0-9_-]/', '', (string) request()->input('origin_anchor', ''));
    $message = $placeholderMessage ?? '';
@endphp
<div class="container-blue mt-2" style="width:600px;">
  <div class="card-header d-flex align-items-start">
    <img src="{{ asset('storage/images/kado_lefttop.jpg') }}" alt="…">
    <h0 class="mb-0 mt-2">内訳－株式等の譲渡所得等</h0>
  </div>
  <div class="card-body">
    <form method="POST" action="{{ route('furusato.details.bunri_kabuteki.save') }}">
      @csrf
      <input type="hidden" name="data_id" value="{{ $dataId }}">
      <input type="hidden" name="origin_tab" value="{{ $originTab }}">
      <input type="hidden" name="origin_anchor" value="{{ $originAnchor }}">
      <div class="alert alert-info" role="alert">
        {{ $message }}
      </div>
      <div class="text-end">
        <button type="submit" class="btn btn-base-blue">入力画面へ戻る</button>
      </div>
    </form>
  </div>
</div>
@endsection