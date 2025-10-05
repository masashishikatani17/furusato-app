@extends('layouts.min')

@section('content')
<div class="container" style="min-width: 960px; max-width: 1080px;">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h5 class="mb-0">ふるさと納税：マスター一覧</h5>
    <a href="{{ $dataId ? route('furusato.input', ['data_id' => $dataId], false) : route('furusato.index', [], false) }}" class="btn btn-outline-secondary btn-sm">戻る</a>
  </div>

  <div class="table-responsive">
    <table class="table table-sm table-bordered align-middle" style="white-space:nowrap;">
      @foreach ($grid as $rIdx => $row)
        <tr>
          @foreach ($row as $cIdx => $cell)
            @php
              // 1行目を見出し扱い（ExcelのA1〜AA1）。必要に応じて調整可
              $isHeader = ($rIdx === 0);
            @endphp
            @if ($isHeader)
              <th class="table-light">{{ $cell }}</th>
            @else
              <td>{{ $cell }}</td>
            @endif
          @endforeach
        </tr>
      @endforeach
    </table>
  </div>
</div>
@endsection