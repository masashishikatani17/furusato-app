<!-- resources/views/admin/users/index.blade.php -->
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

    $seatUsageData = is_array($seatUsage ?? null) ? $seatUsage : [];

    $activeSeats = $seatUsageData['active_seats'] ?? null;

    if ($activeSeats !== null && ! is_numeric($activeSeats)) {
        $activeSeats = null;
    }

    $activeSeats = isset($activeSeats) ? (int) $activeSeats : null;

    if ($activeSeats !== null && $activeSeats < 0) {
        $activeSeats = null;
    }

    $activeUsers = (int) ($seatUsageData['active_users'] ?? 0);
    $pendingInvites = (int) ($seatUsageData['pending_invites'] ?? 0);

    $remaining = $seatUsageData['remaining'] ?? null;

    if ($remaining !== null) {
        $remaining = (int) $remaining;
    }

    $seatUsage = [
        'active_seats' => $activeSeats,
        'active_users' => $activeUsers,
        'pending_invites' => $pendingInvites,
        'remaining' => $remaining,
    ];

    $hasIsActive = $hasIsActive ?? Schema::hasColumn('users', 'is_active');

    $createRoute = Route::has('admin.users.create') ? route('admin.users.create') : null;
    $editRouteName = Route::has('admin.users.edit') ? 'admin.users.edit' : null;
    $deactivateRouteName = Route::has('admin.users.deactivate') ? 'admin.users.deactivate' : null;
    $activateRouteName = Route::has('admin.users.activate') ? 'admin.users.activate' : null;

    $canInviteUsers = $currentUser && ($currentUser->isOwner() || $currentUser->isRegistrar() || $currentUser->isGroupAdmin()) && $createRoute;
    $canManageSeats = $currentUser && ($currentUser->isOwner() || $currentUser->isRegistrar());

    $remainingSeats = $seatUsage['remaining'];
@endphp

<div class="container px-4 py-4" style="width:870px; background-color:#E8EFF0;">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-3">
        <div class="mb-2 mb-md-0">
            <hb class="mt-1 ms-2">ユーザー管理</hb>
        </div>
        <div class="d-flex gap-2 align-items-center">
            {{-- 招待一覧 --}}
            @if (\Illuminate\Support\Facades\Route::has('admin.invitations.index'))
                <a href="{{ route('admin.invitations.index') }}" class="btn-base-blue">招待一覧</a>
            @endif

            {{-- ユーザー招待 --}}
            @if ($canInviteUsers)
                <a href="{{ $createRoute }}" class="btn-base-blue">ユーザーを招待</a>
            @endif
             {{-- 設定TOPへ --}}
            @if (\Illuminate\Support\Facades\Route::has('admin.settings'))
                <a href="{{ route('admin.settings') }}" class="btn-base-blue">設定TOPへ戻る</a>
            @endif
        </div>
    </div>
        <hs class="ms-3 me-3">
            在籍ユーザーや招待状況を確認し、新しいユーザーの招待・編集を行います。
        </hs>
    <div class="card shadow-sm mt-3 mb-4">
        <div class="card-body">
            <h13><strong>○席数の利用状況</strong>　　※ Client ロールと停止中ユーザーは席数に含まれません。</h13>
            <div class="table-responsive table-m-top mt-1">
                <table class="table-base table-bordered align-middle w-auto" style="width: 320px;">
                    <tbody>
                        <tr>
                            <th class="text-start ps-1" style="width: 100px;">上限席数</th>
                            <td style="width: 60px">
                                @if (is_int($seatUsage['active_seats']))
                                    {{ number_format($seatUsage['active_seats']) }} 席
                                @else
                                    ―
                                @endif
                            </td>
                            <th class="text-start ps-1" style="width: 100px;">在籍（有効）</th>
                            <td style="width: 60px;">{{ number_format($seatUsage['active_users']) }} 名</td>
                        </tr>
                        <tr>
                            <th class="text-start ps-1">予約（招待中）</th>
                            <td>{{ number_format($seatUsage['pending_invites']) }} 名</td>
                            <th class="text-start ps-1">残り席数</th>
                            <td>
                                @if (is_int($remainingSeats))
                                    {{ number_format($remainingSeats) }} 席
                                @else
                                    ―
                                @endif
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="card-body">
            <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-2">
                <hb>○ユーザー 一覧</hb>
                <hs class="me-3">全 {{ number_format($usersPaginator->total()) }} 名</hs>
            </div>
            <div class="table-responsive table-m-top">
                <table class="table-input align-middle w-auto p-2">
                    <thead>
                        <tr style="height: 30px;">
                            <th scope="col" class="text-center" style="width: 200px;">氏 名</th>
                            <th scope="col" class="text-center" style="width: 220px;">メールアドレス</th>
                            <th scope="col" class="text-center" style="width: 100px;">部 署</th>
                            <th scope="col" class="text-center" style="width: 60px;">役 割</th>
                            @if ($hasIsActive)
                                <th scope="col" class="text-center" style="width: 40px;">状態</th>
                            @endif
                            <th scope="col" class="text-center" style="width: 120px;">操 作</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($usersPaginator as $user)
                            @php
                                $isOwnerRow = method_exists($user, 'isOwner') ? $user->isOwner() : false;
                                $displayRole = $user->display_role ?? ($isOwnerRow ? 'owner' : ($user->role ?? 'member'));
                                $groupName = $user->group_name ?? ($user->group->name ?? '—');
                                $isActive = $hasIsActive ? (bool) ($user->is_active ?? false) : true;
                                $canEdit = $editRouteName && $currentUser && ($currentUser->isOwner() || $currentUser->isRegistrar() || $currentUser->isGroupAdmin()) && (! $currentUser->isRegistrar() || ! $isOwnerRow);
                                $canToggle = $hasIsActive && $canManageSeats && ! $isOwnerRow && ($activateRouteName && $deactivateRouteName);
                            @endphp
                            <tr @class(['table-secondary' => ! $isActive && $hasIsActive])>
                                <td class="text-start ps-1">
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
                            @if ($usersPaginator->total() === 0)
                                <tr>
                                    <td colspan="{{ $hasIsActive ? 6 : 5 }}" class="text-center text-muted py-4">ユーザーが登録されていません。</td>
                                </tr>
                            @endif
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