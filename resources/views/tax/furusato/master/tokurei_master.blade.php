@extends('layouts.min')

@section('content')
<div class="container" style="min-width: 960px; max-width: 1080px;">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h5 class="mb-0">ふるさと納税：特例控除マスター</h5>
    <a href="{{ route('furusato.master', ['data_id' => $dataId]) }}" class="btn btn-outline-secondary btn-sm">戻る</a>
  </div>
</div>
@endsection