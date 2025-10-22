{{-- resources/views/data/data_copy.blade.php --}}
@extends('layouts.min')

@section('content')
<div class="container">
  <div class="d-flex justify-content-between align-items-center py-3">
    <hb class="ms-3">▶ 既存データのコピー（年度指定）</hb>
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
  <div class="wrapper">
    
      <h1 class="ms-2 mt-1 mb-3">○コピー元</h1>
      <table class="table-base table-bordered align-middle w-auto mx-auto">
        <tbody>
          <tr>
            <th class="text-center" style="width:100px;">お客様名</th>
            <td class="px-2 text-start ps-1" style="min-width:320px;">{{ $source->guest?->name }}</td>
          </tr>
          <tr>
            <th class="text-center">元の年度</th>
            <td class="px-2 text-start ps-1">{{ $source->kihu_year ? $source->kihu_year.'年' : '—' }}</td>
          </tr>
        </tbody>
      </table>
  <form action="{{ route('data.copy') }}" method="POST" id="data-copy-form">
    @csrf
    <input type="hidden" name="selected_data_id" value="{{ $source->id }}">
    <br>
    <h1 class="ms-2 mb-2">○コピー先の指定</h1>
      <div class="card-body">
        {{-- 1) コピー先お客様の指定 --}}
        <div class="mb-3">
          <label class="form-label d-block">
            <hb class="ms-4">・コピー先お客様</hb>
            </label>
            <table align="center" class="table-beige gap-3" style="width: 420px; height:40px;">
              <tr>
                <td>
                  <div class="form-check form-check-inline ms-2 mt-1">
                    <input class="form-check-input" type="radio" name="copy_mode" id="mode_same" value="same" checked>
                    <label class="form-check-label" for="mode_same">同じお客様</label>
                  </div>
                  <div class="form-check form-check-inline">
                    <input class="form-check-input" type="radio" name="copy_mode" id="mode_existing" value="existing">
                    <label class="form-check-label" for="mode_existing">登録済から選択</label>
                  </div>
                  <div class="form-check form-check-inline">
                    <input class="form-check-input" type="radio" name="copy_mode" id="mode_new" value="new">
                    <label class="form-check-label" for="mode_new">新規のお客様</label>
                  </div>
                </td> 
              </tr>   
            </table>
        </div>

        {{-- 2) お客様名（existingは読み取り・sameは元の名前、newのみ入力必須） --}}
        <div class="mt-4">
          <label for="target_guest_name" class="form-label">
            <hb class="ms-4 me-5">・お客様名</hb></label>
              <input type="text" name="target_guest_name" id="target_guest_name" class="form-control kana10"
                     maxlength="25" placeholder="（新規のお客様の場合は入力）"
                     value="{{ old('target_guest_name') }}">
              <input type="hidden" name="target_guest_id" id="target_guest_id" value="{{ old('target_guest_id') }}">
        </div>

        <div class="mt-3">
          <label for="copy_birth_date" class="form-label">
            <hb class="ms-4 me-3">・生年月日（西暦）</hb>
          </label>
          <input type="date"
                 name="birth_date"
                 id="copy_birth_date"
                 class="form-control"
                 style="width:180px;"
                 placeholder="YYYY-MM-DD"
                 value="{{ old('birth_date', $defaultBirthDate) }}">
        </div
        {{-- 3) 年度（複数選択：今年±10年） --}}
        <div class="mt-3">
          <label class="form-label d-block">
          <hb class="ms-4">・年度（複数可）</hb></label>
          @php
            $now = (int)date('Y');
            $minY = $now - 10; $maxY = $now + 10;
            $defaultY = $source->kihu_year ?: $now;
            $years = [];
            for($y=$maxY; $y>=$minY; $y--){ $years[] = $y; }
            $oldYears = collect(old('years', [$defaultY]))->unique()->values()->all();
          @endphp
          <div class="d-flex flex-wrap gap-2 ms-5" id="year-checkboxes">
            @foreach($years as $y)
              <label class="form-check form-check-inline" style="min-width: 120px;">
                <input class="form-check-input" type="checkbox" name="years[]" value="{{ $y }}"
                       @checked(in_array($y, $oldYears, true))>
                <span class="ms-1">{{ $y }}年</span>
              </label>
            @endforeach
          </div>
          <div class="ms-5 mb-2">
            <button type="button" class="btn btn-sm btn-link px-0" id="btn-select-all">すべて選択</button>
            <button type="button" class="btn btn-sm btn-link px-2" id="btn-clear-all">すべて解除</button>
          </div>
        </div>

        {{-- 4) 共有設定（feature.data_privacy=true のときのみ表示） --}}
        @if (config('feature.data_privacy'))
        <div>
          <label class="form-label d-block">
            <hb class="ms-4">・共有設定</hb>
            </label>
            <table align="center" class="table-beige gap-3" style="width: 420px; height:40px;">
              <tr>
                <td>
                  <div class="form-check form-check-inline ms-2">
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
            </table>
        </div>
        @endif
    <hr>
    <div class="d-flex justify-content-end gap-2 mb-3">
      <button type="submit" class="btn btn-base-blue">コピー</button>
      <a href="{{ route('data.index', ['guest_id' => $source->guest_id]) }}" class="btn btn-base-blue">キャンセル</a>
    </div>
  </form>
</div>

{{-- 既存お客様選択モーダル --}}
<div class="modal fade" id="guestModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">お客様を選択</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body p-0">
        <table class="table table-hover mb-0">
          <tbody id="guestListBody">
            @forelse ($guests as $g)
              <tr class="selectable-guest"
                  data-id="{{ $g->id }}"
                  data-name="{{ $g->name }}"
                  data-birth-date="{{ optional($g->birth_date)->format('Y-m-d') }}"
                  style="cursor:pointer;">
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

{{-- 年度重複エラー（全件重複で1件も作成されなかった場合のみモーダル表示） --}}
<div class="modal fade" id="duplicateYearsModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">登録できません</h5>
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
        <button type="button" class="btn btn-primary" data-bs-dismiss="modal">OK</button>
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
  const birthInput = document.getElementById('copy_birth_date');
  const defaultBirthDate = @js($defaultBirthDate);

  const setBirthDate = (value) => {
    if (!birthInput) {
      return;
    }
    birthInput.value = value || '';
  };

  // 初期：same → 元のお客様名（読み取り）
  function setSame(force = false) {
    targetId.value = '{{ $source->guest_id }}';
    targetName.value = '{{ $source->guest?->name }}';
    targetName.readOnly = true;
    if (selectedLbl) selectedLbl.textContent = '';
    if (force || !birthInput || birthInput.value === '') {
      setBirthDate(defaultBirthDate);
    }
  }
  function setExisting() {
    targetName.readOnly = true;
    if (!guestList || guestList.querySelectorAll('tr.selectable-guest').length === 0) {
      alert('登録済のお客様がいません。新規で登録してください。');
      modeNew.checked = true;
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
    targetId.value = '';
    targetName.value = '';
    targetName.readOnly = false;
    targetName.focus();
    if (selectedLbl) selectedLbl.textContent = '';
    setBirthDate('');
  }

  modeSame?.addEventListener('change', () => { if (modeSame.checked) setSame(true); });
  modeExisting?.addEventListener('change', () => { if (modeExisting.checked) setExisting(); });
  modeNew?.addEventListener('change', () => { if (modeNew.checked) setNew(); });

  guestList?.addEventListener('click', (e) => {
    const row = e.target.closest('tr.selectable-guest');
    if (!row) return;
    targetId.value = row.dataset.id || '';
    targetName.value = row.dataset.name || '';
    if (selectedLbl) selectedLbl.textContent = `選択中: ${targetName.value}`;
    targetName.readOnly = true;
    setBirthDate(row.dataset.birthDate || '');
    bootstrap.Modal.getInstance(document.getElementById('guestModal'))?.hide();
  });

  // 年度 チェックボックス一括操作
  const yearBoxWrap = document.getElementById('year-checkboxes');
  document.getElementById('btn-select-all')?.addEventListener('click', () => {
    yearBoxWrap?.querySelectorAll('input[type="checkbox"]').forEach(cb => cb.checked = true);
  });
  document.getElementById('btn-clear-all')?.addEventListener('click', () => {
    yearBoxWrap?.querySelectorAll('input[type="checkbox"]').forEach(cb => cb.checked = false);
  });

  // 初期状態
  setSame(false);

  // サーバ側：全件重複時はモーダルで通知
  @if (session('modal_error.duplicate_years'))
    bootstrap.Modal.getOrCreateInstance(document.getElementById('duplicateYearsModal')).show();
  @endif
})();
</script>
@endpush