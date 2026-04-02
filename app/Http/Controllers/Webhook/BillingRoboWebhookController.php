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

        if ($response = $this->validateSignature($payload)) {
            return $response;
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
            $creditErrorCode = trim((string) ($detail['credit_error_code'] ?? ''));

        Log::info('BillingRobo credit status webhook received.', [
            'billing_code' => $billingCode,
            'payment_method_code' => $paymentMethodCode,
            'payment_status' => $paymentStatus,
            'credit_status' => $creditStatus,
            'credit_error_code' => $creditErrorCode,
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
 
        if ($paymentStatus !== null) {
            $setting->credit_register_status = $paymentStatus;
        }

        if ($paymentStatus === 5 || $creditStatus === 2) {
            $setting->credit_registered_at = now('Asia/Tokyo');
            $setting->credit_last_error_code = null;
            $setting->credit_last_error_message = null;
            $setting->save();

            try {
                $invoice = $issuer->issueInitialCreditAfterRegistration((int) $setting->company_id);

                Log::info('BillingRobo credit status webhook prepared initial credit invoice.', [
                    'company_id' => (int) $setting->company_id,
                    'billing_code' => $billingCode,
                    'payment_method_code' => $paymentMethodCode,
                    'invoice_id' => (int) $invoice->id,
                    'bill_number' => (string) ($invoice->bill_number ?? ''),
                    'invoice_status' => (string) $invoice->status,
                ]);
            } catch (Throwable $e) {
                Log::error('BillingRobo credit status webhook failed while preparing initial credit invoice.', [
                    'company_id' => (int) $setting->company_id,
                    'billing_code' => $billingCode,
                    'payment_method_code' => $paymentMethodCode,
                    'exception_message' => $e->getMessage(),
                    'exception' => $e,
                ]);

                return response()->json([
                    'message' => 'failed to prepare initial invoice',
                ], Response::HTTP_INTERNAL_SERVER_ERROR);
            }

            return response()->json([
                'status' => 'ok',
            ], Response::HTTP_OK);
        }

        if ($paymentStatus === 6 || $creditStatus === 3) {
            $setting->credit_last_error_code = $creditErrorCode !== '' ? $creditErrorCode : 'credit_status_issue';
            $setting->credit_last_error_message = 'BillingRobo でクレジットカード登録失敗が通知されました。';
            $setting->save();
        } else {
            $setting->save();
        }

        return response()->json([
            'status' => 'ok',
        ], Response::HTTP_OK);
    }

    public function billIssue(Request $request, IssueInvoiceService $issuer): JsonResponse
    {
        $payload = $request->all();

        if ($response = $this->validateSignature($payload)) {
            return $response;
        }

        if (($payload['event_name'] ?? null) !== 'bill_issue') {
            return response()->json([
                'status' => 'ignored',
            ], Response::HTTP_ACCEPTED);
        }

        $detail = is_array($payload['event_detail'] ?? null)
            ? $payload['event_detail']
            : [];
        $bill = is_array($detail['bill'] ?? null)
            ? $detail['bill']
            : [];

        $billNumber = trim((string) ($bill['billing_number'] ?? ''));
        $paymentMethod = isset($bill['payment_method']) && $bill['payment_method'] !== ''
            ? (int) $bill['payment_method']
            : null;

        Log::info('BillingRobo bill issue webhook received.', [
            'bill_number' => $billNumber,
            'payment_method' => $paymentMethod,
            'raw_payload' => $payload,
        ]);

        if ($billNumber === '' || $paymentMethod !== 1) {
            return response()->json([
                'status' => 'accepted',
            ], Response::HTTP_ACCEPTED);
        }

        try {
            $result = $issuer->syncInitialCreditSettlementByBillNumber($billNumber);

            Log::info('BillingRobo bill issue webhook initial credit sync finished.', [
                'bill_number' => $billNumber,
                'result' => $result,
            ]);
        } catch (Throwable $e) {
            Log::error('BillingRobo bill issue webhook failed while syncing initial credit settlement.', [
                'bill_number' => $billNumber,
                'exception_message' => $e->getMessage(),
                'exception' => $e,
            ]);

            return response()->json([
                'message' => 'failed to sync initial credit settlement',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        return response()->json([
            'status' => 'ok',
        ], Response::HTTP_OK);
    }

    /**
     * @param array<string,mixed> $payload
     */
    private function validateSignature(array $payload): ?JsonResponse
    {
        $expectedSignature = trim((string) config('billing_robo.webhook_signature_key', ''));
        $receivedSignature = trim((string) ($payload['BillingRoboSignaturekey'] ?? ''));

        if ($expectedSignature !== '' && !hash_equals($expectedSignature, $receivedSignature)) {
            Log::warning('BillingRobo webhook signature mismatch.', [
                'event_name' => $payload['event_name'] ?? null,
                'billing_code' => data_get($payload, 'event_detail.billing_code'),
                'payment_code' => data_get($payload, 'event_detail.payment_code'),
                'bill_number' => data_get($payload, 'event_detail.bill.billing_number'),
            ]);

            return response()->json([
                'message' => 'invalid signature',
            ], Response::HTTP_FORBIDDEN);
        }

        return null;
    }
}