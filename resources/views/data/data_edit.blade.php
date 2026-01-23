{{-- resources/views/data/data_edit.blade.php --}}
@extends('layouts.min')

@section('title', 'データ編集')

@section('content')
@php
  $minY = 2025;
  $maxY = 2035;
  $today = now()->format('Y-m-d');
@endphp

<div class="container" style="max-width:720px;">
  <div class="d-flex justify-content-between align-items-center py-3">
    <hb class="mb-0">▶ データ編集</hb>
    <a href="{{ route('data.index', ['guest_id' => $guest?->id]) }}" class="btn btn-base-blue">戻る</a>
  </div>

  <div class="mb-3 p-3 border rounded bg-white">
    <div><strong>お客様名：</strong>{{ $guest?->name }}</div>
    <div><strong>現在の年度：</strong>{{ (int)($data->kihu_year ?? 0) }}年</div>
  </div>

  @if ($errors->any())
    <div class="alert alert-danger">
      <ul class="mb-0">
        @foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach
      </ul>
    </div>
  @endif

  @if (session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
  @endif
  @if (session('info'))
    <div class="alert alert-info">{{ session('info') }}</div>
  @endif

  <form method="POST" action="{{ route('data.update', ['data' => $data->id]) }}" id="data-edit-form">
    @csrf
    @method('PUT')

    <input type="hidden" name="confirm_overwrite" id="confirm_overwrite" value="0">
    <input type="hidden" name="source_data_id" value="{{ $data->id }}">

    <table class="table-base table-bordered align-middle w-100 bg-white">
      <tbody>
        <tr>
          <th class="text-start ps-2" style="width:180px;">データ作成日</th>
          <td class="text-start ps-1">
            <input type="date" class="form-control" value="{{ old('data_created_on', (string)($data->data_created_on ?? $today)) }}" readonly>
          </td>
        </tr>
        <tr>
          <th class="text-start ps-2">提案書日（必須）</th>
          <td class="text-start ps-1">
            <input type="date"
                   name="proposal_date"
                   class="form-control"
                   required
                   value="{{ old('proposal_date', (string)($data->proposal_date ?? $today)) }}">
          </td>
        </tr>

        @if (config('feature.data_privacy'))
        <tr>
          <th class="text-start ps-2">共有設定</th>
          <td class="text-start ps-1">
            @php $vis = old('visibility', (string)($data->visibility ?? 'shared')); @endphp
            <div class="form-check form-check-inline">
              <input class="form-check-input" type="radio" name="visibility" id="vis_shared" value="shared" @checked($vis==='shared')>
              <label class="form-check-label" for="vis_shared">共有する（同部署に共有）</label>
            </div>
            <div class="form-check form-check-inline">
              <input class="form-check-input" type="radio" name="visibility" id="vis_private" value="private" @checked($vis==='private')>
              <label class="form-check-label" for="vis_private">共有しない（自分だけ）</label>
            </div>
          </td>
        </tr>
        @endif

        <tr>
          <th class="text-start ps-2">年度（2025〜2035）</th>
          <td class="text-start ps-1">
            @php $yy = (int)old('kihu_year', (int)($data->kihu_year ?? 0)); @endphp
            <select class="form-select" name="kihu_year" required>
              @foreach($years as $y)
                <option value="{{ $y }}" @selected((int)$yy === (int)$y)>{{ $y }}年</option>
              @endforeach
            </select>
          </td>
        </tr>
      </tbody>
    </table>

    <div class="d-flex justify-content-end gap-2 mt-3">
      <button type="submit" class="btn btn-base-blue">保存</button>
    </div>
  </form>

  @if($canDelete)
    <hr>
    <div class="d-flex justify-content-end">
      <button type="button" class="btn btn-danger" data-bs-toggle="modal" data-bs-target="#deleteModal">
        このデータを削除
      </button>
    </div>
  @endif

  {{-- 上書き確認モーダル --}}
  <div class="modal fade" id="overwriteModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">年度データの上書き確認</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          @php $oc = session('overwrite_conflict'); @endphp
          @if($oc)
            <p class="mb-2">
              変更先の年度（{{ (int)$oc['to_year'] }}年）には既にデータが存在します。
            </p>
            <p class="mb-2">
              上書きすると、元の{{ (int)$oc['to_year'] }}年のデータは削除され、 {{ (int)$oc['from_year'] }}年の入力内容に置き換わります。<br>
            <strong>上書きしてもよろしいでしょうか？</strong>
            </p>
          @else
            <p>既存データへの上書き確認が必要です。</p>
          @endif
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">キャンセル</button>
          <button type="button" class="btn btn-danger" id="btn-do-overwrite">上書きして保存</button>
        </div>
      </div>
    </div>
  </div>

  {{-- 削除確認モーダル --}}
  @if($canDelete)
  <div class="modal fade" id="deleteModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">削除確認</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <p class="mb-0">
            {{ (int)($data->kihu_year ?? 0) }}年のデータを削除します。よろしいですか？
          </p>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">キャンセル</button>
          <form method="POST" action="{{ route('data.destroy', ['data'=>$data->id]) }}">
            @csrf
            @method('DELETE')
            <button type="submit" class="btn btn-danger">削除する</button>
          </form>
        </div>
      </div>
    </div>
  </div>
  @endif
</div>
@endsection

@push('scripts')
<script>
(function(){
  // 上書き確認が必要な場合、モーダルを自動表示
  const hasConflict = {!! session('overwrite_conflict') ? 'true' : 'false' !!};
  if (hasConflict) {
    const modalEl = document.getElementById('overwriteModal');
    if (modalEl) bootstrap.Modal.getOrCreateInstance(modalEl).show();
  }

  // 「上書きして保存」→ hidden を立てて再送
  document.getElementById('btn-do-overwrite')?.addEventListener('click', function(){
    const hidden = document.getElementById('confirm_overwrite');
    if (hidden) hidden.value = '1';
    document.getElementById('data-edit-form')?.submit();
  });
})();
</script>
@endpush
