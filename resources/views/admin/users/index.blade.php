@extends('layouts.min')

@section('title', 'ユーザー管理')

@section('content')
@php
    use Illuminate\Contracts\Pagination\LengthAwarePaginator;
    use Illuminate\Pagination\LengthAwarePaginator as Paginator;
    use Illuminate\Support\Collection;
    use Illuminate\Support\Facades\Schema;
    use Illuminate\Support\Facades\Route;

    $currentUser = auth()->user();
    $usersPaginator = $users ?? null;

    if (! $usersPaginator instanceof LengthAwarePaginator) {
        $usersCollection = $usersPaginator instanceof Collection ? $usersPaginator : collect();
        $usersPaginator = new Paginator(
            $usersCollection,
            $usersCollection->count(),
            max(1, $usersCollection->count()),
            1,
            ['path' => request()->url(), 'query' => request()->query()]
        );
    }

    $seatSummary = $seatSummary ?? ['active' => 0, 'reserved' => 0, 'total' => 0];
    $seatLimit = $seatLimit ?? null;
    $hasIsActive = $hasIsActive ?? Schema::hasColumn('users', 'is_active');

    $createRoute = Route::has('admin.users.create') ? route('admin.users.create') : null;
    $editRouteName = Route::has('admin.users.edit') ? 'admin.users.edit' : null;
    $deactivateRouteName = Route::has('admin.users.deactivate') ? 'admin.users.deactivate' : null;
    $activateRouteName = Route::has('admin.users.activate') ? 'admin.users.activate' : null;

    $canInviteUsers = $currentUser && ($currentUser->isOwner() || $currentUser->isRegistrar() || $currentUser->isGroupAdmin()) && $createRoute;
    $canManageSeats = $currentUser && ($currentUser->isOwner() || $currentUser->isRegistrar());

    $remainingSeats = is_int($seatLimit)
        ? max(0, $seatLimit - (int) ($seatSummary['total'] ?? 0))
        : null;
@endphp

<div class="container px-4 py-4">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4">
        <div class="mb-3 mb-md-0">
            <h1 class="h5 mb-1">ユーザー管理</h1>
            <p-small>在籍ユーザーや招待状況を確認し、新しいユーザーの招待・編集を行います。</p-small>
        </div>
        @if ($canInviteUsers)
            <a href="{{ $createRoute }}" class="btn-base-blue">ユーザーを招待</a>
        @endif
    </div>

    <div class="card shadow-sm mb-4">
        <div class="card-body">
            <hb>席数の利用状況</hb>
            <div class="table-responsive table-m-top">
                <table class="table table-sm mb-0 align-middle">
                    <tbody>
                        <tr>
                            <th class="table-light" style="width: 20%">上限席数</th>
                            <td style="width: 30%">{{ isset($seatLimit) ? number_format($seatLimit) . ' 席' : '―' }}</td>
                            <th class="table-light" style="width: 20%">在籍（有効）</th>
                            <td style="width: 30%">{{ number_format((int) ($seatSummary['active'] ?? 0)) }} 名</td>
                        </tr>
                        <tr>
                            <th class="table-light">予約（招待中）</th>
                            <td>{{ number_format((int) ($seatSummary['reserved'] ?? 0)) }} 名</td>
                            <th class="table-light">残り席数</th>
                            <td>
                                @if ($remainingSeats !== null)
                                    {{ number_format($remainingSeats) }} 席
                                @else
                                    ―
                                @endif
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <p class="text-muted small mb-0 mt-2">※ Client ロールと停止中ユーザーは席数に含まれません。</p>
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="card-body">
            <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-3">
                <hb>ユーザー一覧</hb>
                <div class="text-muted small">全 {{ number_format($usersPaginator->total()) }} 名</div>
            </div>
            <div class="table-responsive table-m-top">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th scope="col">氏名</th>
                            <th scope="col">メールアドレス</th>
                            <th scope="col" class="text-center">部署</th>
                            <th scope="col" class="text-center">役割</th>
                            @if ($hasIsActive)
                                <th scope="col" class="text-center">状態</th>
                            @endif
                            <th scope="col" class="text-center" style="width: 170px;">操作</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($usersPaginator as $user)
                            @php
                                $isOwnerRow = method_exists($user, 'isOwner') ? $user->isOwner() : false;
                                $displayRole = $user->display_role ?? ($isOwnerRow ? 'owner' : ($user->role ?? 'member'));
                                $groupName = $user->group->name ?? '—';
                                $isActive = $hasIsActive ? (bool) ($user->is_active ?? false) : true;
                                $canEdit = $editRouteName && $currentUser && ($currentUser->isOwner() || $currentUser->isRegistrar() || $currentUser->isGroupAdmin()) && (! $currentUser->isRegistrar() || ! $isOwnerRow);
                                $canToggle = $hasIsActive && $canManageSeats && ! $isOwnerRow && ($activateRouteName && $deactivateRouteName);
                            @endphp
                            <tr @class(['table-secondary' => ! $isActive && $hasIsActive])>
                                <td>
                                    <div class="fw-semibold">{{ $user->name }}</div>
                                    <div class="text-muted small">ID: {{ $user->id }}</div>
                                </td>
                                <td>{{ $user->email }}</td>
                                <td class="text-center">{{ $groupName }}</td>
                                <td class="text-center text-uppercase">{{ $isOwnerRow ? 'owner' : $displayRole }}</td>
                                @if ($hasIsActive)
                                    <td class="text-center">
                                        <span class="badge bg-{{ $isActive ? 'primary' : 'secondary' }}">{{ $isActive ? '有効' : '停止中' }}</span>
                                    </td>
                                @endif
                                <td class="text-center">
                                    <div class="d-flex justify-content-center gap-2">
                                        @if ($canEdit)
                                            <a href="{{ route($editRouteName, $user) }}" class="btn-base-blue">編集</a>
                                        @else
                                            <span class="text-muted small">権限なし</span>
                                        @endif

                                        @if ($canToggle)
                                            @if ($isActive)
                                                <form method="POST" action="{{ route($deactivateRouteName, $user) }}" onsubmit="return confirm('このユーザーを停止しますか？');">
                                                    @csrf
                                                    @method('PATCH')
                                                    <button type="submit" class="btn-base-red">停止</button>
                                                </form>
                                            @else
                                                <form method="POST" action="{{ route($activateRouteName, $user) }}" onsubmit="return confirm('このユーザーを有効化しますか？');">
                                                    @csrf
                                                    @method('PATCH')
                                                    <button type="submit" class="btn-base-green">有効化</button>
                                                </form>
                                            @endif
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="{{ $hasIsActive ? 6 : 5 }}" class="text-center text-muted py-4">ユーザーが登録されていません。</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-3">
                {{ $usersPaginator->links() }}
            </div>
        </div>
    </div>
</div>
@endsection