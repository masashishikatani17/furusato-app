@extends('layouts.min')

@section('title', '契約・支払確認')

@section('content')
<div class="container">
    <div class="row justify-content-center">
        <div class="col-lg-10 col-xl-9">
            <div class="card shadow-sm mb-3">
                <div class="card-body">
                    <h5 class="mb-1">契約・支払確認</h5>
                    <p class="text-muted small mb-0">契約状況・支払情報・請求管理ロボ連携情報を確認できます。</p>
                </div>
            </div>

            <div class="card shadow-sm mb-3">
                <div class="card-header bg-white"><strong>契約状況</strong></div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <div class="text-muted small">契約状態</div>
                            <div>{{ $displayStatus }}</div>
                        </div>
                        <div class="col-md-6">
                            <div class="text-muted small">契約本数</div>
                            <div>{{ $displayQuantity }}</div>
                        </div>
                        <div class="col-md-6">
                            <div class="text-muted small">利用可能人数</div>
                            <div>{{ is_numeric($availableSeats) ? $availableSeats . ' 人' : $availableSeats }}</div>
                        </div>
                        <div class="col-md-6">
                            <div class="text-muted small">契約開始日</div>
                            <div>{{ optional($subscription?->term_start)->format('Y-m-d') ?? '—' }}</div>
                        </div>
                        <div class="col-md-6">
                            <div class="text-muted small">契約満了日</div>
                            <div>{{ optional($subscription?->term_end)->format('Y-m-d') ?? '—' }}</div>
                        </div>
                        <div class="col-md-6">
                            <div class="text-muted small">支払済み期限</div>
                            <div>{{ optional($subscription?->paid_through)->format('Y-m-d') ?? '—' }}</div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card shadow-sm mb-3">
                <div class="card-header bg-white"><strong>支払情報</strong></div>
                <div class="card-body">
                    <div class="text-muted small">支払方法</div>
                    <div>{{ $displayPaymentMethod }}</div>
                </div>
            </div>

            <div class="card shadow-sm mb-3">
                <div class="card-header bg-white"><strong>ロボ連携情報</strong></div>
                <div class="card-body">
                    <div class="text-muted small">請求管理ロボ連携コード</div>
                    <div>{{ $subscription?->billing_code ?: '未登録' }}</div>
                    <p class="text-muted small mt-2 mb-0">請求管理ロボへの請求先登録に使用するコードです。</p>
                </div>
            </div>

            <div class="card shadow-sm mb-3">
                <div class="card-header bg-white"><strong>ご案内</strong></div>
                <div class="card-body">
                    <p class="mb-0">{{ $statusGuidance }}</p>
                </div>
            </div>

            <div class="card shadow-sm mb-3">
                <div class="card-header bg-white"><strong>今後追加予定の機能</strong></div>
                <div class="card-body d-flex flex-wrap gap-2 align-items-center">
                    <span class="btn btn-outline-secondary disabled" aria-disabled="true">支払方法変更（準備中）</span>
                    <span class="btn btn-outline-secondary disabled" aria-disabled="true">請求履歴確認（準備中）</span>
                </div>
            </div>

            <div class="mb-2">
                <a href="{{ route('admin.settings') }}" class="btn btn-link ps-0">← 戻る</a>
            </div>
        </div>
    </div>
</div>
@endsection