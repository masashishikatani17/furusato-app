<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                設定TOP
            </h2>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="text-sm text-gray-600 hover:text-gray-900">
                    ログアウト
                </button>
            </form>
        </div>
    </x-slot>

    @php
        $role = strtolower(auth()->user()->role ?? '');
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

    <div class="py-12">
        <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="grid gap-6 md:grid-cols-2 xl:grid-cols-3">
                @foreach ($cards as $card)
                    @if (in_array($role, $card['roles'], true))
                        <a href="{{ $card['route'] }}" class="block bg-white shadow rounded-lg p-6 hover:shadow-md transition">
                            <h3 class="text-lg font-semibold text-gray-800">{{ $card['title'] }}</h3>
                            <p class="mt-2 text-sm text-gray-600">{{ $card['description'] }}</p>
                        </a>
                    @endif
                @endforeach
            </div>
        </div>
    </div>
</x-app-layout>