<!-- resources/views/admin/users/edit.blade.php -->
@extends('layouts.min')

@section('title', 'ユーザー編集')

@section('content')
@php
    use Illuminate\Support\Collection;
    use Illuminate\Support\Facades\Route;
    use Illuminate\Support\Facades\Schema;

    $currentUser = auth()->user();
    $targetUser = $user ?? null;
    $groups = ($groups ?? collect());
    $groups = $groups instanceof Collection ? $groups : collect($groups);

    $indexRoute = Route::has('admin.users.index') ? route('admin.users.index') : null;
    $updateRoute = ($targetUser && Route::has('admin.users.update')) ? route('admin.users.update', $targetUser) : null;
    $deactivateRoute = ($targetUser && Route::has('admin.users.deactivate')) ? route('admin.users.deactivate', $targetUser) : null;
    $activateRoute = ($targetUser && Route::has('admin.users.activate')) ? route('admin.users.activate', $targetUser) : null;

    $canUpdate = $updateRoute && $currentUser && ($currentUser->isOwner() || $currentUser->isRegistrar() || $currentUser->isGroupAdmin());
    $isOwnerUser = $targetUser && method_exists($targetUser, 'isOwner') ? $targetUser->isOwner() : false;

    if ($currentUser?->isRegistrar() && $isOwnerUser) {
        $canUpdate = false;
    }

    $roleOptions = [
        'member' => 'Member（一般）',
        'group_admin' => 'GroupAdmin（部署管理者）',
        'registrar' => 'Registrar（事務担当）',
    ];

    if ($isOwnerUser) {
        $roleOptions = ['owner' => 'Owner（代表者）'];
    } elseif ($currentUser?->isGroupAdmin()) {
        unset($roleOptions['registrar']);
    }

    $selectedRole = old('role', $targetUser->display_role ?? ($targetUser->role ?? 'member'));
    if ($isOwnerUser) {
        $selectedRole = 'owner';
    }

    $hasIsActive = Schema::hasColumn('users', 'is_active');
    $isActive = $hasIsActive ? (bool) ($targetUser->is_active ?? false) : true;
@endphp

<div class="container px-4 py-4" style="width:800px; background-color:#E8EFF0;">
    <div class="mb-4">
        <hb class="mt-3 ms-2 mb-3">ユーザー編集</hb>
        <br>
         <hs class="ms-3 me-3">ユーザーの基本情報・役割・部署を編集します。
         </hs>
    </div>

    @if ($targetUser)
        <div class="card shadow-sm mb-4">
            <div class="card-body">
                <hb>○ユーザー情報</hb>
                <div class="row g-3">
                    <div class="col-md-6">
                        <hb class="ms-3 mt-2">・氏名</hb>
                        <div class="fw-semibold mt-1 ms-5">{{ $targetUser->name }}</div>
                    </div>
                    <div class="col-md-6">
                        <hb>・メールアドレス</hb>
                        <div class="fw-semibold mt-1 ms-4">{{ $targetUser->email }}</div>
                    </div>
                    <div class="col-md-6">
                        <hb class="ms-3 mt-2">・役割</hb>
                        <div class="fw-semibold text-uppercase mt-1 ms-5">{{ $isOwnerUser ? 'owner' : ($targetUser->display_role ?? $targetUser->role ?? 'member') }}</div>
                    </div>
                    <div class="col-md-6">
                        <hb>・ステータス</hb>
                        <br>
                        @if ($hasIsActive)
                            <span class="badge bg-{{ $isActive ? 'primary' : 'secondary' }}">{{ $isActive ? '有効' : '停止中' }}</span>
                        @else
                            <span class="badge bg-primary mt-1 ms-5">有効</span>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        <div class="card shadow-sm">
            <div class="card-body">
                <hb>○ユーザー設定</hb>

                @if ($currentUser?->isRegistrar() && $isOwnerUser)
                    <div class="alert alert-warning">Registrar は Owner ユーザーを編集できません。</div>
                @elseif ($currentUser?->isGroupAdmin() && in_array($selectedRole, ['owner', 'registrar'], true))
                    <div class="alert alert-warning">GroupAdmin は Owner / Registrar への昇格はできません。</div>
                @endif

                <form method="POST" action="{{ $updateRoute ?? '#' }}">
                    @csrf
                    @method('PUT')

                    <div class="mt-3 mb-3">
                        <label for="edit-name" class="form-label ms-3 me-5">・氏名</label>
                        <input type="text" id="edit-name" name="name" value="{{ old('name', $targetUser->name) }}" class="form-control kana9" {{ $canUpdate ? '' : 'disabled' }}>
                        @error('name')
                            <div class="text-danger small mt-1">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="mb-3">
                        <label for="edit-email" class="form-label ms-3">・メールアドレス<span class="text-danger">*</span></label>
                        <input type="email" id="edit-email" name="email" style="width: 500px;" value="{{ old('email', $targetUser->email) }}" class="form-control text-start" required {{ $canUpdate ? '' : 'disabled' }}>
                        @error('email')
                            <div class="text-danger small mt-1">{{ $message }}</div>
                        @enderror
                    </div>
                    {{-- 役割 --}}
                    <div class="d-flex align-items-center mb-1 ms-3 me-3">
                      <label for="edit-role" class="form-label me-2 mb-0">
                        ・役割
                      </label>
                      <select id="edit-role"
                              name="role"
                              class="form-select"
                              style="width: 200px;"
                              {{ ($canUpdate && ! $isOwnerUser) ? '' : 'disabled' }}>
                        @foreach ($roleOptions as $value => $label)
                          <option value="{{ $value }}" @selected($selectedRole === $value)>{{ $label }}</option>
                        @endforeach
                      </select>
                    </div>
                    
                    <p-small class="ms-5">
                      GroupAdmin が設定できる役割は Member / GroupAdmin のみです。
                    </p-small>
                    <br>
                    <p-small class="ms-5">
                    Registrar を付与する場合は部署を空欄にしてください。
                    </p-small>
                    
                    {{-- 部署 --}}
                    <div class="d-flex align-items-center mt-3 mb-1 ms-3 me-3">
                      <label for="edit-group" class="form-label me-2 mb-0">
                        ・部署
                      </label>
                      <select id="edit-group"
                              name="group_id"
                              class="form-select"
                              style="width: 200px;"
                              {{ $canUpdate ? '' : 'disabled' }}>
                        <option value="">（指定なし）</option>
                        @foreach ($groups as $group)
                          <option value="{{ $group->id }}" @selected((string) old('group_id', $targetUser->group_id) === (string) $group->id)>
                            {{ $group->name }}
                          </option>
                        @endforeach
                      </select>
                    </div>
                    
                    <p-small class="ms-5 mb-3">
                      Registrar を付与する場合は部署を空欄にしてください。
                    </p-small>
                    <hr>
                    <div class="d-flex justify-content-between">
                        @if ($indexRoute)
                            <a href="{{ $indexRoute }}" class="btn-base-blue">一覧に戻る</a>
                        @endif
                        <button type="submit" class="btn-base-blue" {{ $canUpdate ? '' : 'disabled' }}>保存する</button>
                    </div>
                </form>

                @if ($hasIsActive && $canUpdate && ! $isOwnerUser)
                    <div class="mt-4 pt-3 border-top">
                        <hb>アカウント状態</hb>
                        <p class="text-muted small">アカウントの停止・有効化は Owner / Registrar のみ操作できます。</p>
                        <div class="d-flex gap-2">
                            @if ($isActive && $deactivateRoute)
                                <form method="POST" action="{{ $deactivateRoute }}" onsubmit="return confirm('このユーザーを停止しますか？');">
                                    @csrf
                                    @method('PATCH')
                                    <button type="submit" class="btn-base-red">停止する</button>
                                </form>
                            @elseif (! $isActive && $activateRoute)
                                <form method="POST" action="{{ $activateRoute }}" onsubmit="return confirm('このユーザーを有効化しますか？');">
                                    @csrf
                                    @method('PATCH')
                                    <button type="submit" class="btn-base-green">有効化する</button>
                                </form>
                            @endif
                        </div>
                    </div>
                @endif
            </div>
        </div>
    @else
        <div class="alert alert-warning">表示できるユーザー情報がありません。</div>
    @endif
</div>
@endsection