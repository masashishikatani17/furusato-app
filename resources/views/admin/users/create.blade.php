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

<div class="container px-4 py-4" style="width:800px; background-color:#E8EFF0;">
    <div class="mb-4">
        <hb class="mt-3 ms-2">ユーザー招待</hb>
        <hs class="ms-3 me-3">ユーザーに招待メールを送り、furusato を利用できるようにします。</hs>
    </div>

    <div class="card shadow-sm mb-4">
        <div class="card-body">
            <hb>○招待のルール</hb>
            <ul class="hs mb-0 ms-3">
                <li>Owner / Registrar / GroupAdmin が招待できます。</li>
                <li>GroupAdmin が付与できる役割は <strong>Member</strong> と <strong>GroupAdmin</strong> のみです。</li>
                <li>Registrar を付与する場合は部署を空欄にします。<br>（部署は固定されません。空欄で保存してください）</li>
                <li>未承諾・未失効の重複招待はできません。</li>
                @if ($hasIsActive)
                    <li>停止中ユーザーは「有効化」を行うことで再カウントされます。</li>
                @endif
            </ul>
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="card-body">
            <hb>○招待フォーム</hb>

            @if (session('status'))
                <div class="alert alert-success">{{ session('status') }}</div>
            @endif

            @if (! $canSubmit)
                <div class="alert alert-warning mb-4">招待機能を利用できる権限がありません。</div>
            @endif

            <form method="POST" action="{{ $storeRoute ?? '#' }}">
                @csrf

                <div class="mt-2 mb-3 ms-3 me-2">
                    <label for="invite-name" class="form-label me-5">・氏名</label>
                    <input type="text" id="invite-name" name="name" value="{{ old('name') }}" class="form-control kana9" {{ $canSubmit ? '' : 'disabled' }}>
                    @error('name')
                        <div class="text-danger small mt-1">{{ $message }}</div>
                    @enderror
                </div>

                <div class="d-flex align-items-center mb-3 ms-3 me-3">
                  <label for="invite-email" class="form-label me-2 mb-0">
                    ・メールアドレス<span class="text-danger">*</span>
                  </label>
                  <input type="email"
                         id="invite-email"
                         name="email"
                         style="width: 500px;"
                         value="{{ old('email') }}"
                         class="form-control"
                         required {{ $canSubmit ? '' : 'disabled' }}>
                  @error('email')
                    <div class="text-danger small ms-2">{{ $message }}</div>
                  @enderror
                </div>
                
                {{-- 付与する役割 --}}
                <div class="d-flex align-items-center mb-1 ms-3 me-3">
                  <label for="invite-role" class="form-label me-2 mb-0">
                    ・付与する役割<span class="text-danger">*</span>
                  </label>
                  <select id="invite-role"
                          name="role"
                          class="form-select"
                          style="width: 200px;"
                          required {{ $canSubmit ? '' : 'disabled' }}>
                    @foreach ($roleOptions as $value => $label)
                      <option value="{{ $value }}" @selected($defaultRole === $value)>{{ $label }}</option>
                    @endforeach
                  </select>
                </div>
                
                {{-- 注意文 --}}
                <p-small class="ms-5 mb-3">
                  Owner の招待はできません。必要な場合は代表者権限の譲渡をご利用ください。
                </p-small>
                <div class="d-flex align-items-center mt-3 mb-1 ms-3 me-3">
                  <label for="invite-group" class="form-label me-5 mb-0">
                    ・部 署
                  </label>
                  <select id="invite-group"
                          name="group_id"
                          class="form-select"
                          style="width: 200px;"
                          {{ $canSubmit ? '' : 'disabled' }}>
                    <option value="">（指定なし）</option>
                    @foreach ($groups as $group)
                      <option value="{{ $group->id }}" @selected((string) old('group_id') === (string) $group->id)>
                        {{ $group->name }}
                      </option>
                    @endforeach
                  </select>
                </div>
                
                <p-small class="ms-5">
                  Registrar を付与する場合は部署を空欄にしてください。
                </p-small>
　　　　　　　　<hr>
                <div class="d-flex justify-content-between">
                    @if ($indexRoute)
                        <a href="{{ $indexRoute }}" class="btn-base-blue">一覧に戻る</a>
                    @endif
                    <button type="submit" class="btn-base-blue" {{ $canSubmit ? '' : 'disabled' }}>招待メールを送信</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection