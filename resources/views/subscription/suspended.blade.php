<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">ご利用停止中</h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900 space-y-4">
                    <p>ご契約のお支払い状況により、現在サービスをご利用いただけません。</p>
                    <p>お支払い状況の確認やお手続きは支払い設定画面からお願いします。</p>

                    <a href="{{ route('billing.setup') }}"
                       class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-500">
                        支払い設定へ移動
                    </a>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>