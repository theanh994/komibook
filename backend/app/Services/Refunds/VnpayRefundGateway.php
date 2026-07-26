<?php

namespace App\Services\Refunds;

use App\Models\PaymentTransaction;
use App\Models\RefundTransaction;
use Illuminate\Support\Facades\Http;
use LogicException;

class VnpayRefundGateway implements RefundGatewayInterface
{
    public function refund(
        RefundTransaction $refund,
        PaymentTransaction $originalPayment,
        string $actorReference,
        string $clientIp
    ): array {
        $url = config('services.vnpay.refund_url');
        $tmnCode = config('services.vnpay.tmn_code');
        $secret = config('services.vnpay.hash_secret');

        if (! $url || ! $tmnCode || ! $secret) {
            throw new LogicException('VNPAY refund is not configured.');
        }

        if (! $originalPayment->provider_transaction_id || ! $originalPayment->paid_at) {
            throw new LogicException('The original VNPAY payment lacks refund identifiers.');
        }

        $requestId = strtoupper(substr(hash('sha256', $refund->idempotency_key), 0, 32));
        $transactionDate = $originalPayment->request_payload['vnp_CreateDate']
            ?? $originalPayment->paid_at->copy()->setTimezone('Asia/Ho_Chi_Minh')->format('YmdHis');
        $createDate = now()->setTimezone('Asia/Ho_Chi_Minh')->format('YmdHis');
        $validIp = filter_var($clientIp, FILTER_VALIDATE_IP) ? $clientIp : '127.0.0.1';
        $payload = [
            'vnp_RequestId' => $requestId,
            'vnp_Version' => '2.1.0',
            'vnp_Command' => 'refund',
            'vnp_TmnCode' => $tmnCode,
            'vnp_TransactionType' => $refund->amount === $originalPayment->amount ? '02' : '03',
            'vnp_TxnRef' => $originalPayment->provider_reference,
            'vnp_Amount' => $refund->amount * 100,
            'vnp_TransactionNo' => $originalPayment->provider_transaction_id,
            'vnp_TransactionDate' => $transactionDate,
            'vnp_CreateBy' => $actorReference,
            'vnp_CreateDate' => $createDate,
            'vnp_IpAddr' => $validIp,
            'vnp_OrderInfo' => 'Refund '.$refund->returnRequest->code,
        ];

        $signatureInput = implode('|', [
            $payload['vnp_RequestId'],
            $payload['vnp_Version'],
            $payload['vnp_Command'],
            $payload['vnp_TmnCode'],
            $payload['vnp_TransactionType'],
            $payload['vnp_TxnRef'],
            $payload['vnp_Amount'],
            $payload['vnp_TransactionNo'],
            $payload['vnp_TransactionDate'],
            $payload['vnp_CreateBy'],
            $payload['vnp_CreateDate'],
            $payload['vnp_IpAddr'],
            $payload['vnp_OrderInfo'],
        ]);
        $payload['vnp_SecureHash'] = hash_hmac('sha512', $signatureInput, $secret);

        $response = Http::asJson()->timeout(15)->post($url, $payload);
        $body = $response->json();
        $body = is_array($body) ? $body : ['raw' => $response->body()];
        $responseCode = (string) ($body['vnp_ResponseCode'] ?? '');
        $transactionStatus = (string) ($body['vnp_TransactionStatus'] ?? '');
        $signatureValid = $this->responseSignatureIsValid($body, $secret);
        $successful = $response->successful()
            && $signatureValid
            && $responseCode === '00'
            && $transactionStatus === '00';
        $pending = $response->successful()
            && $signatureValid
            && (($responseCode === '00' && in_array($transactionStatus, ['05', '06'], true)) || $responseCode === '94');

        return [
            'successful' => $successful,
            'pending' => $pending,
            'provider_reference' => $body['vnp_TransactionNo'] ?? $body['vnp_ResponseId'] ?? null,
            'request' => array_diff_key($payload, ['vnp_SecureHash' => true]),
            'response' => $body,
            'failure_reason' => $successful || $pending
                ? null
                : (! $signatureValid
                    ? 'VNPAY refund response signature is invalid.'
                    : (string) ($body['vnp_Message'] ?? 'VNPAY refund request failed.')),
        ];
    }

    public function queryRefund(
        RefundTransaction $refund,
        PaymentTransaction $originalPayment,
        string $requestReference,
        string $clientIp
    ): array {
        $url = config('services.vnpay.refund_url');
        $tmnCode = config('services.vnpay.tmn_code');
        $secret = config('services.vnpay.hash_secret');
        if (! $url || ! $tmnCode || ! $secret) {
            throw new LogicException('VNPAY refund is not configured.');
        }
        if (! $originalPayment->paid_at) {
            throw new LogicException('The original VNPAY payment lacks its transaction date.');
        }

        $transactionDate = $originalPayment->request_payload['vnp_CreateDate']
            ?? $originalPayment->paid_at->copy()->setTimezone('Asia/Ho_Chi_Minh')->format('YmdHis');
        $payload = [
            'vnp_RequestId' => strtoupper(substr(hash('sha256', $requestReference), 0, 32)),
            'vnp_Version' => '2.1.0',
            'vnp_Command' => 'querydr',
            'vnp_TmnCode' => $tmnCode,
            'vnp_TxnRef' => $originalPayment->provider_reference,
            'vnp_TransactionNo' => $originalPayment->provider_transaction_id,
            'vnp_TransactionDate' => $transactionDate,
            'vnp_CreateDate' => now()->setTimezone('Asia/Ho_Chi_Minh')->format('YmdHis'),
            'vnp_IpAddr' => filter_var($clientIp, FILTER_VALIDATE_IP) ? $clientIp : '127.0.0.1',
            'vnp_OrderInfo' => 'Query refund '.$refund->returnRequest->code,
        ];
        $signatureInput = implode('|', [
            $payload['vnp_RequestId'],
            $payload['vnp_Version'],
            $payload['vnp_Command'],
            $payload['vnp_TmnCode'],
            $payload['vnp_TxnRef'],
            $payload['vnp_TransactionDate'],
            $payload['vnp_CreateDate'],
            $payload['vnp_IpAddr'],
            $payload['vnp_OrderInfo'],
        ]);
        $payload['vnp_SecureHash'] = hash_hmac('sha512', $signatureInput, $secret);

        $response = Http::asJson()->timeout(15)->post($url, $payload);
        $body = $response->json();
        $body = is_array($body) ? $body : ['raw' => $response->body()];
        $responseCode = (string) ($body['vnp_ResponseCode'] ?? '');
        $transactionStatus = (string) ($body['vnp_TransactionStatus'] ?? '');
        $transactionType = (string) ($body['vnp_TransactionType'] ?? '');
        $signatureValid = $this->queryResponseSignatureIsValid($body, $secret);
        $successful = $response->successful()
            && $signatureValid
            && $responseCode === '00'
            && $transactionStatus === '00'
            && in_array($transactionType, ['02', '03'], true);
        $pending = $response->successful()
            && $signatureValid
            && $responseCode === '00'
            && ($transactionType === '01' || in_array($transactionStatus, ['05', '06'], true));

        return [
            'successful' => $successful,
            'pending' => $pending,
            'provider_reference' => $body['vnp_TransactionNo'] ?? $refund->provider_reference,
            'request' => array_diff_key($payload, ['vnp_SecureHash' => true]),
            'response' => $body,
            'failure_reason' => $successful || $pending
                ? null
                : (! $signatureValid
                    ? 'VNPAY query response signature is invalid.'
                    : (string) ($body['vnp_Message'] ?? 'VNPAY refund status query failed.')),
        ];
    }

    /** @param array<string, mixed> $body */
    private function responseSignatureIsValid(array $body, string $secret): bool
    {
        $incomingHash = (string) ($body['vnp_SecureHash'] ?? '');
        if ($incomingHash === '') {
            return false;
        }

        $signatureInput = implode('|', array_map(
            fn (string $key) => (string) ($body[$key] ?? ''),
            [
                'vnp_ResponseId',
                'vnp_Command',
                'vnp_ResponseCode',
                'vnp_Message',
                'vnp_TmnCode',
                'vnp_TxnRef',
                'vnp_Amount',
                'vnp_BankCode',
                'vnp_PayDate',
                'vnp_TransactionNo',
                'vnp_TransactionType',
                'vnp_TransactionStatus',
                'vnp_OrderInfo',
            ]
        ));

        return hash_equals(hash_hmac('sha512', $signatureInput, $secret), $incomingHash);
    }

    /** @param array<string, mixed> $body */
    private function queryResponseSignatureIsValid(array $body, string $secret): bool
    {
        $incomingHash = (string) ($body['vnp_SecureHash'] ?? '');
        if ($incomingHash === '') {
            return false;
        }

        $signatureInput = implode('|', array_map(
            fn (string $key) => (string) ($body[$key] ?? ''),
            [
                'vnp_ResponseId',
                'vnp_Command',
                'vnp_ResponseCode',
                'vnp_Message',
                'vnp_TmnCode',
                'vnp_TxnRef',
                'vnp_Amount',
                'vnp_BankCode',
                'vnp_PayDate',
                'vnp_TransactionNo',
                'vnp_TransactionType',
                'vnp_TransactionStatus',
                'vnp_OrderInfo',
                'vnp_PromotionCode',
                'vnp_PromotionAmount',
            ]
        ));

        return hash_equals(hash_hmac('sha512', $signatureInput, $secret), $incomingHash);
    }
}
