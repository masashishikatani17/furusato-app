<?php

namespace App\Http\Controllers\Webhook;

use App\Http\Controllers\Controller;
use App\Models\CompanyBillingSetting;
use App\Services\Billing\IssueInvoiceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class BillingRoboWebhookController extends Controller
{
    public function creditStatus(Request $request, IssueInvoiceService $issuer): JsonResponse
    {
        $payload = $request->all();

        $expectedSignature = trim((string) config('billing_robo.webhook_signature_key', ''));
        $receivedSignature = trim((string) ($payload['BillingRoboSignaturekey'] ?? ''));

        if ($expectedSignature !== '' && !hash_equals($expectedSignature, $receivedSignature)) {
            Log::warning('BillingRobo credit status webhook signature mismatch.', [
                'event_name' => $payload['event_name'] ?? null,
                'billing_code' => data_get($payload, 'event_detail.billing_code'),
                'payment_code' => data_get($payload, 'event_detail.payment_code'),
            ]);

            return response()->json([
                'message' => 'invalid signature',
            ], Response::HTTP_FORBIDDEN);
        }

        if (($payload['event_name'] ?? null) !== 'credit_status_issue') {
            return response()->json([
                'status' => 'ignored',
            ], Response::HTTP_ACCEPTED);
        }

        $detail = is_array($payload['event_detail'] ?? null)
            ? $payload['event_detail']
            : [];

        $billingCode = trim((string) ($detail['billing_code'] ?? ''));
        $paymentMethodCode = trim((string) ($detail['payment_code'] ?? ''));
        $paymentStatus = isset($detail['payment_status']) && $detail['payment_status'] !== ''
            ? (int) $detail['payment_status']
            : null;
        $creditStatus = isset($detail['credit_status']) && $detail['credit_status'] !== ''
            ? (int) $detail['credit_status']
            : null;

        Log::info('BillingRobo credit status webhook received.', [
            'billing_code' => $billingCode,
            'payment_method_code' => $paymentMethodCode,
            'payment_status' => $paymentStatus,
            'credit_status' => $creditStatus,
            'raw_payload' => $payload,
        ]);

        if ($billingCode === '' || $paymentMethodCode === '') {
            return response()->json([
                'status' => 'accepted',
            ], Response::HTTP_ACCEPTED);
        }

        $setting = CompanyBillingSetting::query()
            ->where('billing_code', $billingCode)
            ->where('payment_method_code', $paymentMethodCode)
            ->first();

        if (!$setting) {
            Log::warning('BillingRobo credit status webhook skipped because local billing setting was not found.', [
                'billing_code' => $billingCode,
                'payment_method_code' => $paymentMethodCode,
            ]);

            return response()->json([
                'status' => 'accepted',
            ], Response::HTTP_ACCEPTED);
        }

        if ($paymentStatus === 5 || $creditStatus === 2) {
            $results = $issuer->syncPendingInitialCreditSettlements((int) $setting->company_id);

            Log::info('BillingRobo credit status webhook sync finished.', [
                'company_id' => (int) $setting->company_id,
                'billing_code' => $billingCode,
                'payment_method_code' => $paymentMethodCode,
                'results' => $results,
            ]);
        }

        return response()->json([
            'status' => 'ok',
        ], Response::HTTP_OK);
    }
}