@extends('layouts.min')

@section('content')
<div class="container" style="min-width: 960px; max-width: 1080px;">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h5 class="mb-0">ふるさと納税：マスター一覧</h5>
    <div class="d-flex align-items-center gap-2">
      <a href="{{ route('furusato.master.shotoku', ['data_id' => $dataId]) }}" class="btn btn-outline-primary btn-sm">所得税率</a>
      <a href="{{ route('furusato.master.jumin', ['data_id' => $dataId]) }}" class="btn btn-outline-primary btn-sm">住民税率</a>
      <a href="{{ route('furusato.master.tokurei', ['data_id' => $dataId]) }}" class="btn btn-outline-primary btn-sm">特例控除</a>
      <a href="{{ route('furusato.master.shinkokutokurei', ['data_id' => $dataId]) }}" class="btn btn-outline-primary btn-sm">申告特例控除</a>
      <a href="{{ route('furusato.input', ['data_id' => $dataId], false) }}" class="btn btn-outline-secondary btn-sm">戻る</a>
    </div>
  </div>
</div>
@endsection