<?php

namespace App\Http\Controllers\Billing;

use App\Http\Controllers\Controller;
use App\Models\Subscription;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class SetupController extends Controller
{
    public function index(Request $request): View
    {
        $companyId = (int) ($request->user()?->company_id ?? 0);

        $subscription = Subscription::query()
            ->where('company_id', $companyId)
            ->first();

        $status = (string) ($subscription?->status ?? '');
        $paymentMethod = (string) ($subscription?->payment_method ?? '');
        $quantity = $subscription ? (int) $subscription->quantity : null;

        $statusLabels = [
            'pending' => '手続き中',
            'active' => '利用中',
            'suspended' => '利用停止中',
            'expired' => '契約終了',
            'canceled' => '解約済み',
        ];

        $paymentMethodLabels = [
            'credit' => 'クレジットカード',
            'debit' => '口座振替',
            'bank_transfer' => '銀行振込',
        ];

        $statusGuidance = [
            'active' => '現在ご利用可能です。契約状況と支払方法をご確認ください。',
            'pending' => '現在お手続き中です。請求・入金状況をご確認ください。',
            'suspended' => 'お支払い状況により現在ご利用を停止しています。契約状況と支払方法をご確認ください。',
            'expired' => '契約期間が終了しています。契約状況をご確認ください。',
            'canceled' => '現在は解約済みの状態です。必要に応じて管理者へお問い合わせください。',
        ];

        return view('billing.setup', [
            'subscription' => $subscription,
            'displayStatus' => $statusLabels[$status] ?? '—',
            'displayPaymentMethod' => $paymentMethodLabels[$paymentMethod] ?? '未設定',
            'displayQuantity' => $quantity ?? '—',
            'availableSeats' => $quantity !== null ? max(0, $quantity * 5) : '—',
            'statusGuidance' => $statusGuidance[$status] ?? '契約情報をご確認ください。',
        ]);
    }
}