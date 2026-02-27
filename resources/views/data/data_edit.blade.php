{{-- resources/views/data/data_edit.blade.php --}}
@extends('layouts.min')

@section('title', 'データ編集')

@section('content')
@php
  $minY = 2025;
  $maxY = 2035;
  $today = now()->format('Y-m-d');
  // wareki-date へは必ず Y-m-d を渡す（Carbon→文字列化で「—」にならないため）
  $createdOnIso =
      old('data_created_on')
      ?: (optional($data->data_created_on)->format('Y-m-d')
          ?: optional($data->created_at)->timezone(config('app.timezone'))->format('Y-m-d')
          ?: $today);
  $proposalIso =
      old('proposal_date')
      ?: (optional($data->proposal_date)->format('Y-m-d') ?: $today);
@endphp

<div class="container" style="max-width:650px;">
  <div class="d-flex justify-content-between align-items-center m-3">
    <hb>▶ データ編集</hb>
    
  </div>
  <div class="align-middle mb-3 p-3 border rounded bg-pale" style="width:570px;">
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
    <div class="m-3">
        <input type="hidden" name="confirm_overwrite" id="confirm_overwrite" value="0">
        <input type="hidden" name="source_data_id" value="{{ $data->id }}">
    
        <table class="table-input align-middle" style="width:570px; table-layout: fixed;">
          <tbody>
            <tr>
              <th class="text-start ps-2" style="height:33px; width:150px;">データ作成日</th>
              <td class="text-start ps-2" style="width:420px;">
                <x-furusato.wareki-date
                  :name="null"
                  id="edit_data_created_on_view"
                  :readonly="true"
                  :value="$createdOnIso"
                />
              </td>
            </tr>
            <tr>
              <th class="text-start ps-2" style="height:33px;">提案書日</th>
              <td class="text-start">
                <x-furusato.wareki-date
                  name="proposal_date"
                  id="edit_proposal_date"
                  :required="true"
                  :readonly="false"
                  :value="$proposalIso"
                />
              </td>
            </tr>
    
            @if (config('feature.data_privacy') && ($canEditVisibility ?? false))
            <tr>
              <th class="text-start ps-2" style="height:33px;">共有設定</th>
              <td class="bg-cream" style="height:35px;">
                @php $vis = old('visibility', (string)($data->visibility ?? 'shared')); @endphp
                <div class="form-check form-check-inline mt-2 mb-0">
                  <input class="form-check-input" type="radio" name="visibility" id="vis_shared" value="shared" @checked($vis==='shared')>
                  <label class="form-check-label" for="vis_shared">共有する（同部署に共有）</label>
                </div>
                <div class="form-check form-check-inline mt-2 mb-0">
                  <input class="form-check-input" type="radio" name="visibility" id="vis_private" value="private" @checked($vis==='private')>
                  <label class="form-check-label" for="vis_private">共有しない（自分だけ）</label>
                </div>
              </td>
            </tr>
            @endif
    
            <tr>
              <th class="text-start ps-2" style="height:33px;">年度</th>
              <td class="text-start">
                @php $yy = (int)old('kihu_year', (int)($data->kihu_year ?? 0)); @endphp
                <select class="form-select text-start" style="height:32px;width:150px;" name="kihu_year" required>
                  @foreach($years as $y)
                    <option value="{{ $y }}" @selected((int)$yy === (int)$y)>{{ \App\Support\WarekiDate::formatYear((int)$y) }}</option>
                  @endforeach
                </select>
              </td>
            </tr>
            <tr>
              <th class="text-start ps-2" style="height:33px;">データ名</th>
              <td class="text-start">
                <input type="text"
                       name="data_name"
                       class="form-control kana20"
                       style="height:32px; width:260px;"
                       maxlength="25"
                       value="{{ old('data_name', (string)($data->data_name ?? 'default')) }}"
                       required>
                <div class="text-muted ms-1 mt-1 mb-1" style="font-size:12px;">
                  ※改行・タブ・制御文字・\ / : * ? " &lt; &gt; | は使用できません。
                </div>
              </td>
            </tr>
          </tbody>
        </table>
    <hr class="mb-2">
        <div class="d-flex justify-content-between mx-2 mb-2">
          <div>
              <button type="submit" class="btn btn-base-green">保 存</button>
              <button type="button" class="btn-base-red" data-bs-toggle="modal" data-bs-target="#deleteModal">
            このデータを削除
              </button>
             
          </div>
          <div class="d-flex">    
              <a href="{{ route('data.index', ['guest_id' => $guest?->id]) }}" class="btn btn-base-blue">キャンセル</a>
          </div>
        </div>
    </div>
  </form>

  @if($canDelete)
    
    <div class="d-flex justify-content-end">
      
    </div>
  @endif

  {{-- 上書き確認モーダル --}}
  <div class="modal fade" id="overwriteModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
      <div class="modal-content">
        <div class="modal-header">
          <h15 class="modal-title">年度データの上書き確認</h15>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          @php $oc = session('overwrite_conflict'); @endphp
          @if($oc)
            <p class="mb-2">
              変更先（{{ (int)$oc['to_year'] }}年 / {{ (string)($oc['to_name'] ?? '') }}）には既にデータが存在します。
            </p>
            <p class="mb-2">
              既に（{{ (int)$oc['to_year'] }}年 / {{ (string)($oc['to_name'] ?? '') }}）というデータは存在します。上書きしますか？<br>
            <strong>上書きしてもよろしいでしょうか？</strong>
            </p>
          @else
            <p>既存データへの上書き確認が必要です。</p>
          @endif
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-base-blue" data-bs-dismiss="modal">キャンセル</button>
          <button type="button" class="btn btn-base-green" id="btn-do-overwrite">上書きして保存</button>
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
          <h15 class="modal-title">削除確認</h15>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <p class="mb-0">
            {{ (int)($data->kihu_year ?? 0) }}年のデータを削除します。よろしいですか？
          </p>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-base-blue" data-bs-dismiss="modal">キャンセル</button>
          <form method="POST" action="{{ route('data.destroy', ['data'=>$data->id]) }}">
            @csrf
            @method('DELETE')
            <button type="submit" class="btn btn-base-red">削除する</button>
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
