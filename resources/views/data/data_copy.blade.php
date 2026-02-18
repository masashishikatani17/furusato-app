{{-- resources/views/data/data_copy.blade.php --}}
@extends('layouts.min')

@section('content')
@php
  $me = auth()->user();
  $isClient = strtolower((string)($me->role ?? '')) === 'client';
  $clientGuest = $clientGuest ?? null;
@endphp

<div class="container" style="width: 660px;">
  <div class="d-flex justify-content-between align-items-center m-3">
    <hb>▶ 既存データのコピー（年度指定）</hb>
  </div>

  {{-- 通常の入力エラー（重複年度はモーダルで扱う） --}}
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

  {{-- コピー元の参照（読み取り専用） --}}
  <div class="m-3">
    <h1 class="ms-2 mt-1 mb-3">○コピー元</h1>
    <table class="table-base table-bordered align-middle w-auto mx-auto">
      <tbody>
        <tr>
          <th class="text-center" style="width:80px;">お客様名</th>
          <td class="px-2 text-start ps-1" style="min-width:300px;">{{ $source->guest?->name }}</td>
        </tr>
        <tr>
          <th class="text-center">元の年度</th>
          <td class="px-2 text-start ps-1">{{ $source->kihu_year ? $source->kihu_year.'年' : '—' }}</td>
        </tr>
      </tbody>
    </table>
    <hr>

    <form action="{{ route('data.copy') }}" method="POST" id="data-copy-form">
      @csrf
      <input type="hidden" name="selected_data_id" value="{{ $source->id }}">
      {{-- JSが参照するので、clientでも #target_guest_id は常に置く（初期はコピー元guest） --}}
      <input type="hidden" name="target_guest_id" id="target_guest_id"
             value="{{ old('target_guest_id', (int)$source->guest_id) }}">

      @php
        $today = now()->format('Y-m-d');
        $proposalDefault = old('proposal_date', $today);
      @endphp

      <h1 class="ms-2 mb-3">○コピー先の指定</h1>

      {{-- ▼ ここからテーブル化（見た目のみ変更 / name・id・JS参照は維持） --}}
      <table class="table-input align-middle mx-auto" style="max-width:570px;">
        <tbody>
          {{-- 1) コピー先お客様の指定 --}}
          <tr>
            <th class="text-start ps-1" style="height:33px; white-space:nowrap;">
              コピー先お客様
            </th>
            <td class="text-start bg-cream">
                    <div class="form-check form-check-inline ms-2 mt-2 mb-0">
                      <input class="form-check-input" type="radio" name="copy_mode" id="mode_same" value="same" checked>
                      <label class="form-check-label" for="mode_same">同じお客様</label>
                    </div>

                    @if (! $isClient)
                      <div class="form-check form-check-inline">
                        <input class="form-check-input" type="radio" name="copy_mode" id="mode_existing" value="existing">
                        <label class="form-check-label" for="mode_existing">登録済から選択</label>
                      </div>
                      <div class="form-check form-check-inline">
                        <input class="form-check-input" type="radio" name="copy_mode" id="mode_new" value="new">
                        <label class="form-check-label" for="mode_new">新規のお客様</label>
                      </div>
                    @endif
            </td>
          </tr>

          {{-- 2) お客様名 --}}
          <tr>
            <th class="text-start ps-1" style="height:33px; white-space:nowrap;">
              お客様名
            </th>
            <td class="text-start">
              <input type="text"
                     name="target_guest_name"
                     id="target_guest_name"
                     class="form-control kana20"
                     style="height:32px; max-width: 420px;"
                     maxlength="25"
                     placeholder="新規のお客様名を入力"
                     value="{{ $isClient && $clientGuest ? $clientGuest->name : old('target_guest_name') }}"
                     {{ $isClient ? 'readonly' : '' }}>
              {{-- ※ existing/same/new の表示状態は既存JSが制御（name/id維持） --}}
            </td>
          </tr>

          {{-- 3) 生年月日（西暦） --}}
          <tr>
            <th class="text-start ps-1" style="height:33px; white-space:nowrap;">
              生年月日（西暦）
            </th>
            <td class="text-start">
              <x-furusato.wareki-date
                name="birth_date"
                id="copy_birth_date"
                :required="false"
                :readonly="$isClient"
                :value="$isClient && $clientGuest ? optional($clientGuest->birth_date)->format('Y-m-d') : old('birth_date', $defaultBirthDate)"
              />
            </td>
          </tr>

          {{-- 4) 年度（単数） --}}
          <tr>
            <th class="text-start ps-1" style="min-width:120px; white-space:nowrap;">
              年度
            </th>
            <td class="text-start">
              @php
                // 一旦：2025〜2035 に固定
                $minY = 2025; $maxY = 2035;
                $now = (int)date('Y');
                $default = min(max($now, $minY), $maxY);
                $defaultY = (int)($source->kihu_year ?: $default);
                if ($defaultY < $minY) $defaultY = $minY;
                if ($defaultY > $maxY) $defaultY = $maxY;
                $oldYear = (int) old('kihu_year', $defaultY);
                if ($oldYear < $minY) $oldYear = $minY;
                if ($oldYear > $maxY) $oldYear = $maxY;
              @endphp

              <select name="kihu_year" class="form-select" style="height:32px; max-width:120px;">
                @for ($y = $maxY; $y >= $minY; $y--)
                  <option value="{{ $y }}" @selected((int)$oldYear === (int)$y)>{{ \App\Support\WarekiDate::formatYear((int)$y) }}</option>
                @endfor
              </select>
            </td>
          </tr>

          {{-- データ名 --}}
          <tr>
            <th class="text-start ps-1" style="height:33px; white-space:nowrap;">
              データ名
            </th>
            <td class="text-start">
              <input type="text"
                     name="data_name"
                     class="form-control kana20"
                     style="height:32px; max-width: 320px;"
                     maxlength="25"
                     value="{{ old('data_name', $suggestedCopyName ?? (($source->data_name ?? 'default').'_コピー')) }}"
                     required>
              <div class="text-muted mt-1" style="font-size:12px;">
                ※改行・タブ・制御文字・\ / : * ? " &lt; &gt; | は使用できません。
              </div>
            </td>
          </tr>

          {{-- 5) 共有設定（feature.data_privacy=true のときのみ表示） --}}
          @if (config('feature.data_privacy'))
          <tr>
            <th class="text-start ps-1" style="height:33px; white-space:nowrap;">
              共有設定
            </th>
            <td class="text-start bg-cream">
                    <div class="form-check form-check-inline ms-2 mt-2 mb-0">
                      <input class="form-check-input" type="radio" name="visibility" id="vis_shared" value="shared"
                             {{ old('visibility', $source->visibility ?? 'shared') === 'shared' ? 'checked' : '' }}>
                      <label class="form-check-label" for="vis_shared">共有する（同部署に共有）</label>
                    </div>
                    <div class="form-check form-check-inline">
                      <input class="form-check-input" type="radio" name="visibility" id="vis_private" value="private"
                             {{ old('visibility', $source->visibility ?? 'shared') === 'private' ? 'checked' : '' }}>
                      <label class="form-check-label" for="vis_private">共有しない（自分だけ）</label>
                    </div>
            </td>
          </tr>
          @endif

          {{-- 6) データ作成日（編集不可） --}}
          <tr>
            <th class="text-start ps-1" style="height:33px;">
              データ作成日
            </th>
            <td class="text-start ps-1">
              <x-furusato.wareki-date
                :name="null"
                id="copy_data_created_on_view"
                :readonly="true"
                :value="$today"
              />
            </td>
          </tr>

          {{-- 7) 提案書日（編集可） --}}
          <tr>
            <th class="text-start ps-1" style="height:33px;">
              提案書日
            </th>
            <td class="text-start">
              <x-furusato.wareki-date
                name="proposal_date"
                id="copy_proposal_date"
                :required="true"
                :readonly="false"
                :value="$proposalDefault"
              />
            </td>
          </tr>

        </tbody>
      </table>
      {{-- ▲ テーブル化ここまで --}}

      <hr class="mb-2">
      <div class="d-flex justify-content-end gap-2">
        <button type="submit" class="btn btn-base-blue">コピー</button>
        <a href="{{ route('data.index', ['guest_id' => $source->guest_id]) }}" class="btn btn-base-blue">キャンセル</a>
      </div>
    </form>
  </div>

  {{-- 既存お客様選択モーダル --}}
  @if (! $isClient)
  <div class="modal fade" id="guestModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-scrollable" style="max-width:340px;">
      <div class="modal-content">
        <div class="modal-header">
          <h14 class="modal-title">お客様を選択</h14>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body p-3">
          <table class="table table-hover mb-0">
            <tbody id="guestListBody">
              @forelse ($guests as $g)
                <tr class="selectable-guest"
                    data-id="{{ $g->id }}"
                    data-name="{{ $g->name }}"
                    data-birth-date="{{ optional($g->birth_date)->format('Y-m-d') }}"
                    style="cursor:pointer;">
                  <td class="py-1 px-1">{{ $g->name }}</td>
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
  @endif

  {{-- 年度重複エラー（全件重複で1件も作成されなかった場合のみモーダル表示） --}}
  <div class="modal fade" id="duplicateYearsModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
      <div class="modal-content">
        <div class="modal-header">
          <h15 class="modal-title">登録できません</h15>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <p>同じお客様について <strong>同一の年度</strong> のデータは登録できません。</p>
          @if (session('modal_error.duplicate_years'))
            @php $dups = (array)session('modal_error.duplicate_years'); @endphp
            <ul class="mb-0">
              @foreach($dups as $yy)
                <li>{{ $yy }}年</li>
              @endforeach
            </ul>
          @endif
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-primary" data-bs-dismiss="modal">O K</button>
        </div>
      </div>
    </div>
  </div>
</div>
@endsection

@push('scripts')
<script>
(function(){
  const form = document.getElementById('data-copy-form');
  const modeSame = document.getElementById('mode_same');
  const modeExisting = document.getElementById('mode_existing');
  const modeNew = document.getElementById('mode_new');

  const targetName = document.getElementById('target_guest_name');
  const targetId   = document.getElementById('target_guest_id');
  const guestList  = document.getElementById('guestListBody');
  const selectedLbl= document.getElementById('selected-guest-label');
  const defaultBirthDate = @js($defaultBirthDate);

  const setBirthDate = (value) => {
    if (window.WarekiDatePicker && typeof window.WarekiDatePicker.setIsoByName === 'function') {
      window.WarekiDatePicker.setIsoByName('birth_date', value || '');
    }
  };

  // 初期：same → 元のお客様名（読み取り）
  function setSame(force = false) {
    if (targetId) targetId.value = '{{ (int)$source->guest_id }}';
    if (targetName) {
      targetName.value = '{{ $source->guest?->name }}';
      targetName.readOnly = true;
    }
    if (selectedLbl) selectedLbl.textContent = '';
    if (force) {
      setBirthDate(defaultBirthDate);
    }
  }
  function setExisting() {
    if (targetName) targetName.readOnly = true;
    if (!guestList || guestList.querySelectorAll('tr.selectable-guest').length === 0) {
      alert('登録済のお客様がいません。新規で登録してください。');
      if (modeNew) modeNew.checked = true;
      setNew();
      return;
    }
    setBirthDate('');
    // 既に開いている場合は重複showを避ける
    const modalEl = document.getElementById('guestModal');
    if (!modalEl) return;
    const alreadyShown = modalEl.classList.contains('show');
    // フォーカスが閉じる要素上に残っていると aria-hidden 警告が出るので事前にblur
    if (document.activeElement && typeof document.activeElement.blur === 'function') {
      document.activeElement.blur();
    }
    if (!alreadyShown) {
      bootstrap.Modal.getOrCreateInstance(modalEl).show();
    }
  }
  function setNew() {
    if (targetId) targetId.value = '';
    if (targetName) {
      targetName.value = '';
      targetName.readOnly = false;
      targetName.focus();
    }
    if (selectedLbl) selectedLbl.textContent = '';
    setBirthDate('');
  }

  modeSame?.addEventListener('change', () => { if (modeSame.checked) setSame(true); });
  modeExisting?.addEventListener('change', () => { if (modeExisting && modeExisting.checked) setExisting(); });
  modeNew?.addEventListener('change', () => { if (modeNew && modeNew.checked) setNew(); });

  guestList?.addEventListener('click', (e) => {
    const row = e.target.closest('tr.selectable-guest');
    if (!row) return;
    if (targetId) targetId.value = row.dataset.id || '';
    if (targetName) targetName.value = row.dataset.name || '';
    if (selectedLbl) selectedLbl.textContent = `選択中: ${targetName.value}`;
    if (targetName) targetName.readOnly = true;
    setBirthDate(row.dataset.birthDate || '');
    bootstrap.Modal.getInstance(document.getElementById('guestModal'))?.hide();
  });

  // 年度 チェックボックス一括操作
  const yearBoxWrap = document.getElementById('year-checkboxes');
// 年度は単数プルダウンに変更（旧チェックボックス操作は廃止）

  // 初期状態
  setSame(false);

  // サーバ側：全件重複時はモーダルで通知
  @if (session('modal_error.duplicate_years'))
    bootstrap.Modal.getOrCreateInstance(document.getElementById('duplicateYearsModal')).show();
  @endif
})();
</script>
@endpush