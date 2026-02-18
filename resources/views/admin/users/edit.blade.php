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

<div class="container px-4 py-3" style="width:740px; background-color:#E8EFF0;">
    <div class="d-flex align-items-start gap-2 ms-2 mb-3">
        <hb>ユーザー編集</hb>
        <hs>ユーザーの基本情報・役割・部署を編集します。
         </hs>
    </div>

    @if ($targetUser)
        <div class="card shadow-sm mb-4">
            <div class="card-body">
                <hb>○ユーザー情報</hb>
                <table class="table table-input align-middle mt-2" style="width: 600px;">
                    <tbody>
                        <tr>
                            <th class="text-start ps-2" style="height:30px;width: 100px;">氏名</th>
                            <td class="text-start ps-2 fw-semibold" style="width: 500px;">{{ $targetUser->name }}</td>
                        </tr>
                        <tr>
                            <th class="text-start ps-2" style="height:30px;">メールアドレス</th>
                            <td class="text-start ps-2 fw-semibold">{{ $targetUser->email }}</td>
                        </tr>
                        <tr>
                            <th class="text-start ps-2" style="height:30px;">役割</th>
                            <td class="text-start ps-2 fw-semibold text-uppercase">
                                {{ $isOwnerUser ? 'owner' : ($targetUser->display_role ?? $targetUser->role ?? 'member') }}
                            </td>
                        </tr>
                        <tr>
                            <th class="text-start ps-2" style="height:30px;">ステータス</th>
                            <td class="text-start ps-2">
                                @if ($hasIsActive)
                                    <span class="badge bg-{{ $isActive ? 'primary' : 'secondary' }}">
                                        {{ $isActive ? '有効' : '停止中' }}
                                    </span>
                                @else
                                    <span class="badge bg-primary">有効</span>
                                @endif
                            </td>
                        </tr>
                    </tbody>
                </table>
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

                    <table class="table table-input align-middle mt-2" style="width: 600px;">
                        <tbody>
                            <tr>
                                <th class="text-start ps-2" style="height:30px;width: 100px;">氏名</th>
                                <td colspan="2" class="text-start" style="width: 500px;">
                                    <input type="text"
                                           id="edit-name"
                                           name="name"
                                           value="{{ old('name', $targetUser->name) }}"
                                           class="form-control kana20"
                                           {{ $canUpdate ? '' : 'disabled' }}
                                           style="height:30px;">
                                    @error('name')
                                        <div class="text-danger small mt-1">{{ $message }}</div>
                                    @enderror
                                </td>
                            </tr>

                            <tr>
                                <th class="text-start ps-2" style="height:30px;">メールアドレス</th>
                                <td colspan="2" class="text-start">
                                    <input type="email"
                                           id="edit-email"
                                           name="email"
                                           style="width: 500px;"
                                           value="{{ old('email', $targetUser->email) }}"
                                           class="form-control text-start"
                                           required
                                           {{ $canUpdate ? '' : 'disabled' }}>
                                    @error('email')
                                        <div class="text-danger small mt-1">{{ $message }}</div>
                                    @enderror
                                </td>
                            </tr>

                            <tr>
                                <th class="text-start ps-2">役割</th>
                                <td class="text-start b-r-no">
                                    <select id="edit-role"
                                            name="role"
                                            class="form-select"
                                            style="height:30px; width: 200px;"
                                            {{ ($canUpdate && ! $isOwnerUser) ? '' : 'disabled' }}>
                                        @foreach ($roleOptions as $value => $label)
                                            <option value="{{ $value }}" @selected($selectedRole === $value)>{{ $label }}</option>
                                        @endforeach
                                    </select>
                                </td>
                                <td class="text-start b-l-no">
                                    <p-small class="ms-1">
                                        GroupAdmin が設定できる役割は Member / GroupAdmin のみです。
                                    </p-small>
                                    <p-small class="ms-1">
                                        Registrar を付与する場合は部署を空欄にしてください。
                                    </p-small>
                                </td>
                            </tr>
                            <tr>
                                <th class="text-start ps-2">部署</th>
                                <td class="text-start b-r-no">
                                    <select id="edit-group"
                                            name="group_id"
                                            class="form-select"
                                            style="height:30px; width: 200px;"
                                            {{ $canUpdate ? '' : 'disabled' }}>
                                        <option value="">（指定なし）</option>
                                        @foreach ($groups as $group)
                                            <option value="{{ $group->id }}" @selected((string) old('group_id', $targetUser->group_id) === (string) $group->id)>
                                                {{ $group->name }}
                                            </option>
                                        @endforeach
                                    </select>
                                </td>
                                <td class="text-start b-l-no">
                                    <p-small class="ms-1 mb-3">
                                        Registrar を付与する場合は部署を空欄にしてください。
                                    </p-small>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                    <div class="d-flex justify-content-between m-2">
                        <button type="submit" class="btn-base-green" {{ $canUpdate ? '' : 'disabled' }}>保存する</button>
                        @if ($indexRoute)
                            <a href="{{ $indexRoute }}" class="btn-base-blue">一覧に戻る</a>
                        @endif
                        
                    </div>
                </form>

                @if ($hasIsActive && $canUpdate && ! $isOwnerUser)
                    <div class="mt-4 pt-3 border-top">
                        <hb>○アカウント状態</hb>
                        <p class="text-muted small ms-5">アカウントの停止・有効化は Owner / Registrar のみ操作できます。</p>
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