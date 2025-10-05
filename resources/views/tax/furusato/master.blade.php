@extends('layouts.min')

@section('content')
<div class="container" style="min-width: 960px; max-width: 1080px;">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h5 class="mb-0">ふるさと納税：マスター一覧</h5>
    <a href="{{ $dataId ? route('furusato.input', ['data_id' => $dataId], false) : route('furusato.index', [], false) }}" class="btn btn-outline-secondary btn-sm">戻る</a>
  </div>

  <div class="table-responsive">
    <table class="table table-sm table-bordered align-middle">
      <thead class="table-light">
        <tr>
          @foreach ($columns as $key => $label)
            <th scope="col">{{ $label }}</th>
          @endforeach
        </tr>
      </thead>
      <tbody>
        @foreach ($records as $record)
          <tr>
            @foreach ($columns as $key => $label)
              <td>{{ $record[$key] ?? '' }}</td>
            @endforeach
          </tr>
        @endforeach
      </tbody>
    </table>
  </div>
</div>
@endsection