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
    ];
@endphp

<div class="container" style="width:600px; background-color:#E8EFF0;">
    <div class="wrapper mt-3 ma-2" style="background-color:#E8EFF0;">
        <div class="d-flex align-items-center justify-content-between mt-2 mb-4">
            <hb class="mb-2">○設定TOP</hb>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="btn btn-base-blue me-2">ログアウト</button>
            </form>
        </div>
    
        <div class="row g-4 g-md-4 mb-3">
            @foreach ($cards as $card)
                @if (in_array($role, $card['roles'], true))
                    <div class="col-12 col-md-6 col-xl-4">
                        <div class="card h-100 shadow-sm text-center">
                            <div class="card-body">
                                <h0 class="mb-1">
                                    <a href="{{ $card['route'] }}" class="text-decoration-none">{{ $card['title'] }}</a>
                                </h0>
                                <p class="text-muted small mb-0">{{ $card['description'] }}</p>
                            </div>
                        </div>
                    </div>
                @endif
            @endforeach
        </div>
    </div>    
</div>
@endsection