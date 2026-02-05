@extends('layouts.min')

@section('title', '設定TOP')

@section('content')
@php
    $me = auth()->user();
    $normalizedRoles = ['owner', 'registrar', 'groupadmin', 'member', 'client'];
    $role = 'member';
    $isOwner = false;

    if ($me) {
        if (method_exists($me, 'isOwner')) {
            $isOwner = $me->isOwner();
        }

        if (! $isOwner) {
            $companyOwnerId = optional($me->company ?? null)->owner_user_id;

            if (! $companyOwnerId && method_exists($me, 'companies')) {
                $companies = $me->companies;

                if ($companies instanceof \Illuminate\Support\Collection) {
                    $companyOwnerId = optional($companies->first())->owner_user_id;
                } elseif (is_iterable($companies)) {
                    $companyOwnerId = optional(collect($companies)->first())->owner_user_id ?? null;
                }
            }

            $isOwner = (int) $companyOwnerId === (int) optional($me)->id;
        }

        $rawRole = strtolower((string) ($me->role ?? 'member'));
        $role = in_array($rawRole, $normalizedRoles, true) ? $rawRole : 'member';

        if ($isOwner) {
            $role = 'owner';
        }
    }

    $cards = [
        [
            'title' => 'ユーザー管理',
            'description' => 'ユーザーの閲覧・管理',
            'route' => route('admin.users.index'),
            'roles' => ['owner', 'registrar', 'groupadmin', 'member'],
        ],
        [
            'title' => '部署一覧',
            'description' => '部署情報を閲覧できます',
            'route' => route('admin.groups.index'),
            'roles' => ['owner', 'registrar', 'groupadmin', 'member'],
        ],
        [
            'title' => 'カード設定',
            'description' => 'カードに関する設定を行います',
            'route' => route('billing.setup'),
            'roles' => ['owner', 'registrar'],
        ],
        [
            'title' => '領収書一覧',
            'description' => '領収書を確認できます',
            'route' => route('admin.billing.receipts.index'),
            'roles' => ['owner', 'registrar', 'groupadmin', 'member'],
        ],
        [
            'title' => '代表者権限の譲渡',
            'description' => '代表者権限の移譲手続きを行います',
            'route' => route('admin.ownerTransfer.form'),
            'roles' => ['owner'],
        ],
        [
            'title' => 'データダウンロード',
            'description' => '各種データをダウンロードできます',
            'route' => route('admin.data_download.index'),
            'roles' => ['owner', 'registrar', 'groupadmin', 'member'],
        ],
        [
            'title' => '操作履歴',
            'description' => '新規作成/コピー/上書き/削除の履歴を確認できます',
            'route' => route('admin.audit_logs.index'),
            'roles' => ['owner', 'registrar'],
        ],
    ];
@endphp

<div class="container bg-cream" style="width:630px;">
    <div class="wrapper mt-3 ms-3 bg-cream">
        <div class="d-flex align-items-center justify-content-between mt-2 mb-4 bg-cream">
            <hb class="mb-2">○設定TOP</hb>
            <div class="d-flex align-items-center gap-2">
                <a href="{{ route('data.index') }}" class="btn btn-base-blue">戻 る</a>
                <form method="POST" action="{{ route('logout') }}" class="m-0">
                    @csrf
                    <button type="submit" class="btn btn-base-blue">ログアウト</button>
                </form>
            </div>
        </div>
    
        <div class="row g-3 g-md-3 mb-3">
            @foreach ($cards as $card)
                @if (in_array($role, $card['roles'], true))
                    <div class="col-12 col-md-6 col-xl-4">
                        <div class="card h-100 shadow-sm text-center bg-pale">
                            <div class="card-body">
                                <h0 class="text-center mb-1">
                                    <a href="{{ $card['route'] }}" class="text-decoration-none title-link">{{ $card['title'] }}</a>
                                </h0>
                                <p class="text-muted small mb-0">{!! $card['description'] !!}</p>
                            </div>
                        </div>
                    </div>
                @endif
            @endforeach
        </div>
    </div>    
</div>
@endsection