@extends('layouts.min')

@section('title', 'ユーザー招待')

@section('content')
@php
    use Illuminate\Support\Collection;
    use Illuminate\Support\Facades\Route;
    use Illuminate\Support\Facades\Schema;

    $currentUser = auth()->user();
    $groups = ($groups ?? collect());
    $groups = $groups instanceof Collection ? $groups : collect($groups);

    $storeRoute = Route::has('admin.users.store') ? route('admin.users.store') : null;
    $indexRoute = Route::has('admin.users.index') ? route('admin.users.index') : null;

    $canSubmit = $storeRoute && $currentUser && ($currentUser->isOwner() || $currentUser->isRegistrar() || $currentUser->isGroupAdmin());

    $roleOptions = [
        'member' => 'Member（一般）',
        'group_admin' => 'GroupAdmin（部署管理者）',
        'registrar' => 'Registrar（事務担当）',
    ];

    if ($currentUser?->isGroupAdmin()) {
        unset($roleOptions['registrar']);
    }

    $defaultRole = old('role', array_key_first($roleOptions));
    $hasIsActive = Schema::hasColumn('users', 'is_active');
@endphp

<div class="container px-4 py-4">
    <div class="mb-4">
        <h1 class="h5 mb-1">ユーザー招待</h1>
        <p-small>ユーザーに招待メールを送り、furusato を利用できるようにします。</p-small>
    </div>

    <div class="card shadow-sm mb-4">
        <div class="card-body">
            <hb>招待のルール</hb>
            <ul class="mb-0 small text-muted ps-3">
                <li>Owner / Registrar / GroupAdmin が招待できます。</li>
                <li>GroupAdmin が付与できる役割は <strong>Member</strong> と <strong>GroupAdmin</strong> のみです。</li>
                <li>Registrar を付与する場合は部署を空欄にします。<br><span class="text-muted">（部署は固定されません。空欄で保存してください）</span></li>
                <li>未承諾・未失効の重複招待はできません。</li>
                @if ($hasIsActive)
                    <li>停止中ユーザーは「有効化」を行うことで再カウントされます。</li>
                @endif
            </ul>
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="card-body">
            <hb>招待フォーム</hb>

            @if (session('status'))
                <div class="alert alert-success">{{ session('status') }}</div>
            @endif

            @if (! $canSubmit)
                <div class="alert alert-warning mb-4">招待機能を利用できる権限がありません。</div>
            @endif

            <form method="POST" action="{{ $storeRoute ?? '#' }}">
                @csrf

                <div class="mb-3">
                    <label for="invite-name" class="form-label">氏名</label>
                    <input type="text" id="invite-name" name="name" value="{{ old('name') }}" class="form-control" {{ $canSubmit ? '' : 'disabled' }}>
                    @error('name')
                        <div class="text-danger small mt-1">{{ $message }}</div>
                    @enderror
                </div>

                <div class="mb-3">
                    <label for="invite-email" class="form-label">メールアドレス<span class="text-danger">*</span></label>
                    <input type="email" id="invite-email" name="email" value="{{ old('email') }}" class="form-control" required {{ $canSubmit ? '' : 'disabled' }}>
                    @error('email')
                        <div class="text-danger small mt-1">{{ $message }}</div>
                    @enderror
                </div>

                <div class="mb-3">
                    <label for="invite-role" class="form-label">付与する役割<span class="text-danger">*</span></label>
                    <select id="invite-role" name="role" class="form-select" required {{ $canSubmit ? '' : 'disabled' }}>
                        @foreach ($roleOptions as $value => $label)
                            <option value="{{ $value }}" @selected($defaultRole === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                    <div class="text-muted small mt-1">Owner の招待はできません。必要な場合は代表者権限の譲渡をご利用ください。</div>
                </div>

                <div class="mb-3">
                    <label for="invite-group" class="form-label">部署</label>
                    <select id="invite-group" name="group_id" class="form-select" {{ $canSubmit ? '' : 'disabled' }}>
                        <option value="">（指定なし）</option>
                        @foreach ($groups as $group)
                            <option value="{{ $group->id }}" @selected((string) old('group_id') === (string) $group->id)>{{ $group->name }}</option>
                        @endforeach
                    </select>
                    <div class="text-muted small mt-1">Registrar を付与する場合は部署を空欄にしてください。</div>
                </div>

                <div class="d-flex justify-content-between mt-4">
                    @if ($indexRoute)
                        <a href="{{ $indexRoute }}" class="btn-base">一覧に戻る</a>
                    @endif
                    <button type="submit" class="btn-base-blue" {{ $canSubmit ? '' : 'disabled' }}>招待メールを送信</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection