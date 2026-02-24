@extends('layouts.min')

@section('title', '代表者権限の譲渡')

@section('content')
@php
  $eligibleUsers = $eligibleUsers ?? collect();
  $activeGroups = $activeGroups ?? collect();
@endphp

<div class="container px-4 py-4" style="width:770px; background-color:#E8EFF0;">
  <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-3">
        <div class="mb-2 mb-md-0">
            <hb class="mt-1 ms-2">代表者権限の譲渡</hb>
        </div>
        <div class="d-flex gap-2 text-end">
          <a href="{{ route('admin.settings') }}" class="btn btn-base-blue">設定TOPへ戻る</a>
        </div>
   </div>
  @if(session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
  @endif
  @if ($errors->any())
    <div class="alert alert-danger">
      <div class="fw-bold mb-1">入力内容を確認して下さい。</div>
      <ul class="mb-0">
        @foreach($errors->all() as $e)
          <li>{{ $e }}</li>
        @endforeach
      </ul>
    </div>
  @endif

  <div class="card shadow-sm">
    <div class="card-body">
      <h13>
        ※この操作を実行すると、あなたは <strong>Owner 権限を失います</strong>。取り消しはできません。
      </h13>

      <form method="POST" action="{{ route('admin.ownerTransfer.store') }}">
        @csrf

        <div class="mt-3 mb-3">
          <table class="table table-base align-middle mb-0" style="width:auto;">
            <tbody>
              <tr>
                <th class="text-start ps-2" style="width:220px;">新しい代表者（Owner）</th>
                <td class="text-start ps-2">
                  <select name="new_owner_user_id" class="form-select" required style="width:350px;">
                    <option value="">（選択して下さい）</option>
                    @foreach($eligibleUsers as $u)
                      <option value="{{ (int)$u->id }}" @selected((string)old('new_owner_user_id')===(string)$u->id)>
                        {{ $u->name }}（{{ $u->email }}）
                      </option>
                    @endforeach
                  </select>
                  <div class="form-text">
                    対象：同一会社の有効ユーザー（Client以外）
                  </div>
                </td>
              </tr>
              <tr>
                <th class="text-start ps-2">譲渡後のあなた（旧Owner）の役割</th>
                <td class="text-start ps-2">
                  <select name="old_owner_new_role" id="old_owner_new_role" class="form-select" required style="width:350px;">
                    <option value="registrar" @selected(old('old_owner_new_role')==='registrar')>Registrar</option>
                    <option value="group_admin" @selected(old('old_owner_new_role')==='group_admin')>GroupAdmin</option>
                    <option value="member" @selected(old('old_owner_new_role','member')==='member')>Member</option>
                  </select>
                  <div class="form-text">
                    Registrar を選ぶ場合は部署なし（横断）。GroupAdmin/Member は部署が必須です。
                  </div>
                </td>
              </tr>
              <tr>
                <th class="text-start ps-2">譲渡後の部署（旧Owner）</th>
                <td class="text-start ps-2">
                  <div class="d-none" id="row-old-owner-group">
                    <select name="old_owner_new_group_id" id="old_owner_new_group_id" class="form-select" style="width:350px;">
                      <option value="">（選択して下さい）</option>
                      @foreach($activeGroups as $g)
                        <option value="{{ (int)$g->id }}" @selected((string)old('old_owner_new_group_id')===(string)$g->id)>
                          {{ $g->name }}
                        </option>
                      @endforeach
                    </select>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <div class="form-check ms-4 mb-3">
          <input class="form-check-input" type="checkbox" value="1" id="confirm" name="confirm" {{ old('confirm') ? 'checked' : '' }} required>
          <label class="form-check-label" for="confirm">
            この操作により、代表者（Owner）が変更されることを理解しました（取り消し不可）
          </label>
        </div>
      <hr>
        <div class="d-flex justify-content-end gap-2">
          <button type="submit" class="btn btn-base-red">譲渡を実行する</button>
        </div>
      </form>
    </div>
  </div>
</div>
@endsection

@push('scripts')
<script>
(() => {
  const roleSel = document.getElementById('old_owner_new_role');
  const row = document.getElementById('row-old-owner-group');
  const groupSel = document.getElementById('old_owner_new_group_id');
  if (!roleSel || !row || !groupSel) return;

  const apply = () => {
    const v = String(roleSel.value || '');
    const needGroup = (v === 'group_admin' || v === 'member');
    row.classList.toggle('d-none', !needGroup);
    groupSel.required = needGroup;
    if (!needGroup) groupSel.value = '';
  };
  roleSel.addEventListener('change', apply);
  apply();
})();
</script>
@endpush
