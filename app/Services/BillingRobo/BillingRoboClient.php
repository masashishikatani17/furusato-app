<?php

namespace App\Services\BillingRobo;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * 請求管理ロボ API クライアント（骨格）
 * - ここには「HTTP呼び出し」だけを書き、業務判断（契約更新等）はService側へ寄せる
 */
class BillingRoboClient
{
    private string $baseUrl;
    private string $userId;
    private string $accessKey;

    public function __construct()
    {
        $this->baseUrl = (string) config('billing_robo.base_url');
        $this->userId = (string) config('billing_robo.user_id');
        $this->accessKey = (string) config('billing_robo.access_key');
        if ($this->baseUrl === '' || $this->userId === '' || $this->accessKey === '') {
            // 設定が無い環境でもアプリ全体を落とさないため、呼び出し時に例外にする
        }
    }
    
    /**
     * billing/bulk_upsert
     * @param array<int,array<string,mixed>> $billings
     * @return array<string,mixed>
     */
    public function billingBulkUpsert(array $billings): array
    {
        $res = $this->postJson('/api/v1.0/billing/bulk_upsert', [
            'billing' => $billings,
        ]);

        $billingRows = $res['billing'] ?? null;
        if (!is_array($billingRows)) {
            throw new RuntimeException('BillingRobo billingBulkUpsert response does not contain billing array.');
        }

        foreach ($billingRows as $idx => $row) {
            if (!is_array($row)) {
                throw new RuntimeException("BillingRobo billingBulkUpsert response billing[{$idx}] is invalid.");
            }

            $errorCode = $row['error_code'] ?? null;
            if ($this->hasApiError($errorCode)) {
                $message = (string)($row['error_message'] ?? 'unknown billing error');
                $code = (string)($row['code'] ?? '');
                throw new RuntimeException("BillingRobo billingBulkUpsert failed: billing.code={$code} error_code={$errorCode} message={$message}");
            }

            $individuals = $row['individual'] ?? null;
            if (is_array($individuals)) {
                foreach ($individuals as $j => $individual) {
                    if (!is_array($individual)) {
                        throw new RuntimeException("BillingRobo billingBulkUpsert response billing[{$idx}].individual[{$j}] is invalid.");
                    }

                    $individualErrorCode = $individual['error_code'] ?? null;
                    if ($this->hasApiError($individualErrorCode)) {
                        $message = (string)($individual['error_message'] ?? 'unknown individual error');
                        $code = (string)($individual['code'] ?? '');
                        throw new RuntimeException("BillingRobo billingBulkUpsert failed: billing.individual.code={$code} error_code={$individualErrorCode} message={$message}");
                    }
                }
            }

            $payments = $row['payment'] ?? null;
            if (is_array($payments)) {
                foreach ($payments as $k => $payment) {
                    if (!is_array($payment)) {
                        throw new RuntimeException("BillingRobo billingBulkUpsert response billing[{$idx}].payment[{$k}] is invalid.");
                    }

                    $paymentErrorCode = $payment['error_code'] ?? null;
                    if ($this->hasApiError($paymentErrorCode)) {
                        $message = (string)($payment['error_message'] ?? 'unknown payment error');
                        $code = (string)($payment['code'] ?? '');
                        throw new RuntimeException("BillingRobo billingBulkUpsert failed: billing.payment.code={$code} error_code={$paymentErrorCode} message={$message}");
                    }
                }
            }
        }

        return $res;
    }

    /**
     * demand/bulk_upsert
     * @param array<int,array<string,mixed>> $demands
     * @return array<string,mixed>
     */
    public function demandBulkUpsert(array $demands): array
    {
        $res = $this->postJson('/api/v1.0/demand/bulk_upsert', [
            'demand' => $demands,
        ]);

        $demandRows = $res['demand'] ?? null;
        if (!is_array($demandRows)) {
            throw new RuntimeException('BillingRobo demandBulkUpsert response does not contain demand array.');
        }

        foreach ($demandRows as $idx => $row) {
            if (!is_array($row)) {
                throw new RuntimeException("BillingRobo demandBulkUpsert response demand[{$idx}] is invalid.");
            }

            $errorCode = $row['error_code'] ?? null;
            if ($this->hasApiError($errorCode)) {
                $message = (string)($row['error_message'] ?? 'unknown demand error');
                $code = (string)($row['code'] ?? '');
                throw new RuntimeException("BillingRobo demandBulkUpsert failed: demand.code={$code} error_code={$errorCode} message={$message}");
            }
        }

        return $res;
    }

    /**
     * demand/bulk_issue_bill_select
     * - demand の code または number を指定して請求書発行
     *
     * @param array<int,string> $demandCodes
     * @return array<string,mixed>
     */
    public function demandBulkIssueBillSelect(array $demandCodes): array
    {
        // 仕様：demand=[{code:...}] の配列（user_id/access_key 必須）
        $demand = [];
        foreach ($demandCodes as $code) {
            $demand[] = ['code' => (string)$code];
        }
        return $this->postJson('/api/v1.0/demand/bulk_issue_bill_select', [
            'demand' => $demand,
        ]);
    }

    /**
     * bill/search（請求書番号で検索）
     * @return array<string,mixed>
     */
    public function billSearchByNumber(string $billNumber): array
    {
        return $this->postJson('/api/v1.0/bill/search', [
            'bill' => [
                'number' => $billNumber,
            ],
        ]);
    }

    /**
     * bill/update（transfer_deadline 等を更新）
     * @param string $billNumber
     * @param array<string,mixed> $updates
     * @return array<string,mixed>
     */
    public function billUpdate(string $billNumber, array $updates): array
    {
        return $this->postJson('/api/v1.0/bill/update', [
            'bill' => array_merge(['number' => $billNumber], $updates),
        ]);
    }

    /**
     * 共通POST（JSON）
     * @param string $path
     * @param array<string,mixed> $payload
     * @return array<string,mixed>
     */
    private function postJson(string $path, array $payload): array
    {
        if ($this->baseUrl === '' || $this->userId === '' || $this->accessKey === '') {
            throw new RuntimeException('BillingRobo API is not configured. Please set BILLING_ROBO_BASE_URL, BILLING_ROBO_USER_ID, BILLING_ROBO_ACCESS_KEY.');
        }

        $url = $this->baseUrl . $path;

        // ロボ仕様：user_id / access_key はリクエストボディに必須
        $payload = array_merge([
            'user_id' => $this->userId,
            'access_key' => $this->accessKey,
        ], $payload);

        // 追跡用
        $rid = (string) Str::uuid();

        $resp = Http::timeout(20)
            ->acceptJson()
            ->asJson()
            ->withHeaders(['X-Request-Id' => $rid])
            ->post($url, $payload);

        if (!$resp->ok()) {
            throw new RuntimeException('BillingRobo API request failed: HTTP ' . $resp->status() . ' body=' . (string)$resp->body());
        }

        $json = $resp->json();
        if (!is_array($json)) {
            throw new RuntimeException('BillingRobo API response is not JSON array.');
        }

        return $json;
    }

    private function hasApiError(mixed $errorCode): bool
    {
        if ($errorCode === null) {
            return false;
        }

        $value = trim((string) $errorCode);
        return $value !== '' && $value !== '0';
    }
}
