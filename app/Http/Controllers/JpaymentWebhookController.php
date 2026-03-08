<?php

namespace App\Http\Controllers;

use App\Models\JpaymentWebhookEvent;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class JpaymentWebhookController extends Controller
{
    /**
     * 決済結果通知URL（通常決済）
     * - まずは payload を丸ごとDBに保存する（段階1）
     */
    public function payment(Request $request)
    {
        $this->storeEvent('payment', $request);
        return response('OK', 200);
    }

    /**
     * 自動課金結果通知URL（年次自動課金）
     * - まずは payload を丸ごとDBに保存する（段階1）
     */
    public function autoCharge(Request $request)
    {
        $this->storeEvent('auto_charge', $request);
        return response('OK', 200);
    }

    private function storeEvent(string $type, Request $request): void
    {
        // 生データ（フォームPOST or x-www-form-urlencoded を想定）
        $payload = $request->all();

        // headers は配列がネストするので json 化して保存
        $headers = $request->headers->all();

        // B-1：iid2 に INV-<invoice_id> を入れる想定
        $iid2 = isset($payload['iid2']) ? (string) $payload['iid2'] : null;
        $invoiceIdHint = null;
        if ($iid2 && preg_match('/^INV-(\d+)$/', $iid2, $m)) {
            $invoiceIdHint = (int) $m[1];
        }

        // 取引ID/結果コードは、実際のキックバックを見てから確定するため、
        // よくありそうなキーを“候補”として拾っておく（無ければnullのまま）
        $jpTid = null;
        foreach (['tid', 'trid', 'transaction_id', 'order_id'] as $k) {
            if (isset($payload[$k]) && $payload[$k] !== '') {
                $jpTid = (string) $payload[$k];
                break;
            }
        }
        $resultCode = null;
        foreach (['result', 'status', 'res', 'rst', 'r'] as $k) {
            if (isset($payload[$k]) && $payload[$k] !== '') {
                $resultCode = (string) $payload[$k];
                break;
            }
        }

        try {
            JpaymentWebhookEvent::query()->create([
                'type' => $type,
                'raw_payload' => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'headers' => json_encode($headers, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'remote_ip' => $request->ip(),
                'user_agent' => substr((string) $request->userAgent(), 0, 255),
                'iid2' => $iid2,
                'invoice_id_hint' => $invoiceIdHint ?: null,
                'jp_tid' => $jpTid,
                'result_code' => $resultCode,
                'received_at' => now(),
            ]);
        } catch (\Throwable $e) {
            // 失敗しても決済側へは 200 を返したいので、ここでは握りつぶしてログに落とす
            Log::error('Jpayment webhook store failed: ' . $e->getMessage(), [
                'type' => $type,
                'ip' => $request->ip(),
            ]);
        }
    }
}