{{-- resources/views/data/data_create.blade.php --}}
@extends('layouts.min')

@section('content')
<div class="container px-2" style="width: 600px;">
  <div class="d-flex justify-content-between align-items-center py-3">
    <hb class="ms-3 mb-0">▶ 新規データ作成</hb>
  </div>

  {{-- ▼ バリデーションエラー（通常の入力エラーのみ。重複年度はここに出さない） --}}
  @if ($errors->any())
    <div class="alert alert-danger">
      <ul class="mb-0">
        @foreach($errors->all() as $e)
          <li>{{ $e }}</li>
        @endforeach
      </ul>
    </div>
  @endif

  <form action="{{ route('data.store') }}" method="POST" id="data-create-form" class="mt-3">
    @csrf

    <table class="table-base table-bordered align-middle w-auto mx-auto">
      <tbody>
      {{-- 1) お客様の指定 --}}
      <tr>
        <th class="text-start ps-2" style="width:100px;">お客様の指定</th>
        <td class="th-cream text-start ps-1 pb-0">
          <div class="form-check form-check-inline pt-1">
            <input class="form-check-input" type="radio" name="guest_mode" id="gm_new" value="new"
                   {{ old('guest_mode','new') === 'new' ? 'checked' : '' }}>
            <label class="form-check-label" for="gm_new">新規で登録</label>
          </div>
          <div class="form-check form-check-inline">
            <input class="form-check-input" type="radio" name="guest_mode" id="gm_existing" value="existing"
                   {{ old('guest_mode') === 'existing' ? 'checked' : '' }}>
            <label class="form-check-label" for="gm_existing">登録済から選択</label>
          </div>
        </td>
      </tr>

      {{-- 2) お客様名（existing選択時は読み取り専用） --}}
      <tr>
        <th class="text-start ps-2">お客様名</th>
        <td class="text-start ps-1">
          <input type="text" name="guest_name" id="guest_name"
                 class="form-control kana10"
                 value="{{ old('guest_name') }}"
                 maxlength="25"
                 placeholder="（新規登録時は入力）">
          <input type="hidden" name="guest_id" id="guest_id" value="{{ old('guest_id') }}">
        </td>
      </tr>

      {{-- 3) 共有設定（feature.data_privacy=true の時のみ表示） --}}
      @if (config('feature.data_privacy'))
      <tr>
        <th class="text-start ps-2">共有設定</th>
        <td class="th-cream text-start ps-1 pt-2 pb-0">
          <div class="form-check form-check-inline">
            <input class="form-check-input" type="radio" name="visibility" id="vis_shared" value="shared"
                   {{ old('visibility','shared') === 'shared' ? 'checked' : '' }}>
            <label class="form-check-label" for="vis_shared">共有する（同部署に共有）</label>
          </div>
          <div class="form-check form-check-inline">
            <input class="form-check-input" type="radio" name="visibility" id="vis_private" value="private"
                   {{ old('visibility') === 'private' ? 'checked' : '' }}>
            <label class="form-check-label" for="vis_private">共有しない（自分だけ）</label>
          </div>
        </td>
      </tr>
      @endif

      {{-- 4) 年度（寄付年） --}}
      <tr>
        <th class="text-start ps-2">年 度</th>
        <td>
          @php
            $now = (int)date('Y');
            $minY = $now - 10; $maxY = $now + 10;
            $oldYear = old('kihu_year', $now);
          @endphp
          <select name="kihu_year" id="kihu_year" class="form-select" style="max-width:200px;">
            @for ($y = $maxY; $y >= $minY; $y--)
              <option value="{{ $y }}" @selected((int)$oldYear === (int)$y)>{{ $y }}年</option>
            @endfor
          </select>
        </td>
      </tr>
      </tbody>
    </table>
    <hr>
    <div class="d-flex justify-content-end gap-2 mb-3">
      <button type="submit" class="btn-base-blue">作 成</button>
      <a href="{{ route('data.index') }}" class="btn-base-blue">キャンセル</a>
    </div>
  </form>
</div>

{{-- ▼ 既存ゲスト選択モーダル --}}
<div class="modal fade" id="guestModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">お客様を選択</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body p-0">
        <table class="table table-hover table-bordered mb-0">
          <tbody id="guestListBody">
          @forelse ($guests as $g)
            <tr class="selectable-guest" data-id="{{ $g->id }}" data-name="{{ $g->name }}" style="cursor:pointer;">
              <td class="py-2 px-2">{{ $g->name }}</td>
            </tr>
          @empty
            <tr><td class="text-muted py-3 px-2">（登録済のお客様がありません）</td></tr>
          @endforelse
          </tbody>
        </table>
      </div>
    </div>
  </div>
  </div>

{{-- ▼ 年度重複エラー用の専用モーダル（ページ上部のエラーとは別扱い） --}}
<div class="modal fade" id="duplicateYearModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">登録できません</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <p class="mb-0">同じお客様について <strong>同一の年度</strong> のデータは登録できません。</p>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-primary" data-bs-dismiss="modal">OK</button>
      </div>
    </div>
  </div>
</div>
@endsection

@push('scripts')
<script>
(function(){
  const form      = document.getElementById('data-create-form');
  const gmNew     = document.getElementById('gm_new');
  const gmExist   = document.getElementById('gm_existing');
  const gName     = document.getElementById('guest_name');
  const gId       = document.getElementById('guest_id');
  const btnOpen   = document.getElementById('btn-open-guest-modal');
  const guestList = document.getElementById('guestListBody');

  const openGuestModal = () => {
    const hasAny = guestList && guestList.querySelectorAll('tr.selectable-guest').length > 0;
    if (!hasAny) {
      // A) トースト → 今は alert で代替（共通トースト未実装のため）
      alert('登録済のお客様がいません。新規で登録してください。');
      // B) モードを new に戻す
      if (gmNew) gmNew.checked = true;
      if (gmExist) gmExist.checked = false;
      gId.value = '';
      gName.readOnly = false;
      gName.focus();
      return;
    }
    const modal = bootstrap.Modal.getOrCreateInstance(document.getElementById('guestModal'));
    modal.show();
  };

  // ラジオ切替
  gmNew?.addEventListener('change', () => {
    if (gmNew.checked) {
      gId.value = '';
      gName.readOnly = false;
      gName.focus();
    }
  });
  gmExist?.addEventListener('change', () => {
    if (gmExist.checked) {
      gName.readOnly = true;
      openGuestModal();
    }
  });

  // 「登録済から選ぶ」ボタン
  btnOpen?.addEventListener('click', (e) => {
    e.preventDefault();
    if (gmExist) gmExist.checked = true;
    if (gmNew) gmNew.checked = false;
    gName.readOnly = true;
    openGuestModal();
  });

  // ゲスト選択
  guestList?.addEventListener('click', (e) => {
    const row = e.target.closest('tr.selectable-guest');
    if (!row) return;
    gId.value = row.dataset.id || '';
    gName.value = row.dataset.name || '';
    gName.readOnly = true;
    bootstrap.Modal.getInstance(document.getElementById('guestModal'))?.hide();
  });

  // サーバ側「年度重複」検知 → モーダルのみ表示（上部のエラーは出さない）
  @if (session('modal_error.duplicate_year'))
    bootstrap.Modal.getOrCreateInstance(document.getElementById('duplicateYearModal')).show();
  @endif
})();
</script>
@endpush