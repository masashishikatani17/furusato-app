@extends('layouts.min')

@section('content')
<div class="container" style="min-width: 960px; max-width: 1080px;">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h5 class="mb-0">ふるさと納税：インプット表（v0.4）</h5>
    <div class="d-flex gap-2">
      <button type="button" class="btn btn-outline-secondary btn-sm" form="furusato-input-form" id="furusato-back-to-syori" formnovalidate>戻る</button>
      <a href="{{ route('furusato.master', $dataId ? ['data_id' => $dataId] : [], false) }}" class="btn btn-outline-secondary btn-sm">マスター</a>
    </div>
  </div>

  @if (session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
  @endif

  @if ($errors->any())
    <div class="alert alert-danger">
      <ul class="mb-0">
        @foreach ($errors->all() as $error)
          <li>{{ $error }}</li>
        @endforeach
      </ul>
    </div>
  @endif

  @php
    $sogoContent = <<<'HTML'
        <h5 class="card-title mb-3">確定申告書(総合課税)</h5>
        <div class="text-muted small">ここに確定申告書(総合課税)の入力・帳票UIを実装（次フェーズ）</div>
    HTML;

    $bunriContent = <<<'HTML'
        <h5 class="card-title mb-3">確定申告書(分離課税)</h5>
        <div class="text-muted small">ここに確定申告書(分離課税)の入力・帳票UIを実装（次フェーズ）</div>
    HTML;
  @endphp

  <form method="POST" action="{{ route('furusato.save') }}" class="row g-3" id="furusato-input-form">
    @csrf
    <input type="hidden" name="data_id" value="{{ $dataId ?? '' }}">
    <input type="hidden" name="redirect_to" value="">

    <div class="col-12">
      @if ((int) ($bunriFlag ?? 0) === 1)
        <div class="card mb-4">
          <div class="card-header pb-0">
            <ul class="nav nav-tabs card-header-tabs" id="furusato-input-tabs" role="tablist">
              <li class="nav-item" role="presentation">
                <button class="nav-link active" id="tab-sogo" data-bs-toggle="tab" data-bs-target="#pane-sogo" type="button" role="tab" aria-controls="pane-sogo" aria-selected="true">確定申告書(総合課税)</button>
              </li>
              <li class="nav-item" role="presentation">
                <button class="nav-link" id="tab-bunri" data-bs-toggle="tab" data-bs-target="#pane-bunri" type="button" role="tab" aria-controls="pane-bunri" aria-selected="false">確定申告書(分離課税)</button>
              </li>
            </ul>
          </div>
          <div class="card-body">
            <div class="tab-content" id="furusato-input-tab-content">
              <div class="tab-pane fade show active" id="pane-sogo" role="tabpanel" aria-labelledby="tab-sogo">
                <div class="card">
                  <div class="card-body">
                    {!! $sogoContent !!}
                  </div>
                </div>
              </div>
              <div class="tab-pane fade" id="pane-bunri" role="tabpanel" aria-labelledby="tab-bunri">
                <div class="card">
                  <div class="card-body">
                    {!! $bunriContent !!}
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      @else
        <div class="card mb-4">
          <div class="card-body">
            {!! $sogoContent !!}
          </div>
        </div>
      @endif
    </div>

    <div class="col-12 d-flex flex-column flex-md-row justify-content-end gap-2">
      <button type="submit" class="btn btn-success" formnovalidate onclick="this.form.redirect_to.value='';">保存</button>
      <button type="submit" class="btn btn-primary" formaction="{{ route('furusato.calc') }}" onclick="this.form.redirect_to.value='';">送信</button>
    </div>
  </form>
</div>
@endsection

@push('scripts')
<script>
document.getElementById('furusato-back-to-syori')?.addEventListener('click', function (event) {
  event.preventDefault();
  const form = document.getElementById('furusato-input-form');
  if (!form) {
    return;
  }
  form.redirect_to.value = 'syori';
  form.submit();
});
</script>
@endpush