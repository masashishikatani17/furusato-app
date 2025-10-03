@extends('layouts.min')
@section('content')
<div class="container" style="min-width: 960px; max-width: 1080px;">
  <h5 class="mb-3">ふるさと納税：上限簡易試算（Excel vol23 ベース）</h5>

  <form method="POST" action="{{ route('furusato.calc') }}" class="row g-3">
    @csrf
    @isset($dataId)
      <input type="hidden" name="data_id" value="{{ $dataId }}">
    @endisset
    <div class="col-md-3">
      <label class="form-label">W17</label>
      <input type="number" class="form-control" name="w17" value="{{ old('w17', 2000000) }}" required>
    </div>
    <div class="col-md-3">
      <label class="form-label">W18</label>
      <input type="number" class="form-control" name="w18" value="{{ old('w18', 3000000) }}" required>
    </div>
    <div class="col-md-3">
      <label class="form-label">AB56</label>
      <input type="number" class="form-control" name="ab56" value="{{ old('ab56', 10000) }}" required>
    </div>
    <div class="col-md-3">
      <label class="form-label">AB6</label>
      <input type="number" class="form-control" name="ab6" value="{{ old('ab6', 300000) }}" required>
    </div>
    <div class="col-md-4">
      <label class="form-label">V6（モードA）</label>
      <select class="form-select" name="v6">
        @foreach([0, 1, 2] as $option)
          <option value="{{ $option }}" @selected((string)old('v6', 0) === (string)$option)>{{ $option }}</option>
        @endforeach
      </select>
      <div class="form-text">Excel「計算結果!V6」の選択（0/1/2）。</div>
    </div>
    <div class="col-md-4">
      <label class="form-label">W6（モードB）</label>
      <select class="form-select" name="w6">
        @foreach([0, 1, 2] as $option)
          <option value="{{ $option }}" @selected((string)old('w6', 0) === (string)$option)>{{ $option }}</option>
        @endforeach
      </select>
      <div class="form-text">Excel「計算結果!W6」の選択（0/1/2）。</div>
    </div>
    <div class="col-md-4">
      <label class="form-label">X6（モードC）</label>
      <select class="form-select" name="x6">
        @foreach([0, 1, 2] as $option)
          <option value="{{ $option }}" @selected((string)old('x6', 0) === (string)$option)>{{ $option }}</option>
        @endforeach
      </select>
      <div class="form-text">Excel「計算結果!X6」の選択（0/1/2）。</div>
    </div>
    <div class="col-12">
      <button class="btn btn-primary">計算する</button>
    </div>
  </form>

  @isset($out)
  <hr>
  <h6>結果（Excelセル対応・確認用）</h6>
  <table class="table table-sm table-bordered w-auto">
    <tbody>
      <tr><th>B8</th><td>{{ number_format($out['b8']) }}</td></tr>
      <tr><th>B9</th><td>{{ number_format($out['b9']) }}</td></tr>
      <tr><th>B12</th><td>{{ number_format($out['b12']) }}</td></tr>
      <tr><th>B13</th><td>{{ number_format($out['b13']) }}</td></tr>
      <tr><th>B16</th><td>{{ number_format($out['b16']) }}</td></tr>
      <tr><th>B17</th><td>{{ number_format($out['b17']) }}</td></tr>
    </tbody>
  </table>
  <h6>入力モード確認</h6>
  <table class="table table-sm table-bordered w-auto">
    <tbody>
      <tr><th>V6</th><td>{{ $out['flags']['v6'] }}</td></tr>
      <tr><th>W6</th><td>{{ $out['flags']['w6'] }}</td></tr>
      <tr><th>X6</th><td>{{ $out['flags']['x6'] }}</td></tr>
    </tbody>
  </table>  
  @endisset
</div>
@endsection