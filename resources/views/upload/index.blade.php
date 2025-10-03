<x-app-layout>
<div class="container py-4" style="max-width: 780px;">
  <x-slot name="header">
    <h2 class="font-semibold text-xl text-gray-800 leading-tight">
      画像アップロード（{{ strtoupper(env('FILE_UPLOAD_DISK','public')) }}）
    </h2>
  </x-slot>

  @if (session('success'))
    <div class="alert alert-success my-3">{{ session('success') }}</div>
  @endif
  @if (session('error'))
    <div class="alert alert-danger my-3">{{ session('error') }}</div>
  @endif
  @if ($errors->any())
    <div class="alert alert-danger my-3">
      <ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
    </div>
  @endif

  <form class="mb-4" action="{{ route('upload.store') }}" method="POST" enctype="multipart/form-data">
    @csrf
    <div class="d-flex gap-2">
      <input type="file" name="file" accept="image/*" class="form-control" required>
      <button class="btn btn-primary">アップロード</button>
    </div>
    <div class="form-text">対応: jpeg/png/webp/gif, 5MBまで</div>
  </form>

  <table class="table table-bordered align-middle">
    <thead><tr><th style="width:30%">ファイル名</th><th style="width:50%">プレビュー / URL</th><th>操作</th></tr></thead>
    <tbody>
      @forelse($files as $f)
        <tr>
          <td><code>{{ $f['name'] }}</code></td>
          <td>
            <img src="{{ $f['url'] }}" alt="" style="max-height:120px;max-width:100%">
            <div class="small mt-1"><a href="{{ $f['url'] }}" target="_blank">開く</a></div>
          </td>
          <td>
            <form method="POST" action="{{ route('upload.destroy') }}">
              @csrf @method('DELETE')
              <input type="hidden" name="path" value="{{ $f['path'] }}">
              <button class="btn btn-sm btn-outline-danger" onclick="return confirm('削除しますか?')">削除</button>
            </form>
          </td>
        </tr>
      @empty
        <tr><td colspan="3">ファイルがありません。</td></tr>
      @endforelse
    </tbody>
  </table>
</div>
</x-app-layout>