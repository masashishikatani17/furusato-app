@extends('layouts.min')

@section('title', $pageTitle)

@section('content')
@php
    $originTabRaw = request()->input('origin_tab', 'input');
    $originTab = is_string($originTabRaw) && trim($originTabRaw) === 'input' ? 'input' : '';
    $originAnchor = preg_replace('/[^A-Za-z0-9_-]/', '', (string) request()->input('origin_anchor', ''));
@endphp
<div class="container mt-2" style="max-width:500px;">
  <div class="card">
    <div class="card-header d-flex align-items-start">
      <img src="{{ asset('storage/images/kado_lefttop.jpg') }}" alt="" class="me-2">
      <h0 class="mb-0 mt-2">{{ $headerTitle }}</h0>
    </div>
    <div class="card-body">
      <p class="mb-4">{{ $placeholderMessage }}</p>
      <form method="POST" action="{{ route($saveRouteName) }}">
        @csrf
        <input type="hidden" name="data_id" value="{{ $dataId }}">
        <input type="hidden" name="origin_tab" value="{{ $originTab }}">
        <input type="hidden" name="origin_anchor" value="{{ $originAnchor }}">
        <div class="text-end">
          <button type="submit" class="btn btn-base-blue">入力画面へ戻る</button>
        </div>
      </form>
    </div>
  </div>
</div>
@endsection