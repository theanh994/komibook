<?php

namespace App\Services\Payments;

use App\Models\PaymentTransaction;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Http;
use Throwable;

class VnpayTransactionQueryService
{
    public function __construct(private readonly VnpayCallbackService $callbackService) {}

    /** @return array{RspCode: string, Message: string} */
    public function reconcile(string $providerReference, string $clientIp): array
    {
        $transaction = PaymentTransaction::query()
            ->where('provider', 'vnpay')
            ->where('provider_reference', $providerReference)
            ->first();

        if (! $transaction || ! is_array($transaction->request_payload)) {
            return ['RspCode' => '01', 'Message' => 'Order Not Found'];
        }

        $tmnCode = trim((string) config('services.vnpay.tmn_code'));
        $secret = trim((string) config('services.vnpay.hash_secret'));
        $url = trim((string) config('services.vnpay.refund_url'));
        $transactionDate = (string) ($transaction->request_payload['vnp_CreateDate'] ?? '');
        if ($tmnCode === '' || $secret === '' || $url === '' || ! preg_match('/^\d{14}$/', $transactionDate)) {
            return ['RspCode' => '99', 'Message' => 'Query configuration is invalid'];
        }

        $now = now()->setTimezone('Asia/Ho_Chi_Minh');
        $payload = [
            'vnp_RequestId' => strtoupper(bin2hex(random_bytes(16))),
            'vnp_Version' => '2.1.0',
            'vnp_Command' => 'querydr',
            'vnp_TmnCode' => $tmnCode,
            'vnp_TxnRef' => $providerReference,
            'vnp_TransactionDate' => $transactionDate,
            'vnp_CreateDate' => $now->format('YmdHis'),
            'vnp_IpAddr' => filter_var($clientIp, FILTER_VALIDATE_IP) ? $clientIp : '127.0.0.1',
            'vnp_OrderInfo' => 'Query payment '.$providerReference,
        ];
        $payload['vnp_SecureHash'] = hash_hmac('sha512', implode('|', array_values($payload)), $secret);

        try {
            $response = Http::asJson()->timeout(20)->post($url, $payload);
            $body = $response->json();
        } catch (Throwable) {
            return ['RspCode' => '99', 'Message' => 'Query request failed'];
        }

        if (! $response->successful() || ! is_array($body) || ! $this->responseSignatureIsValid($body, $secret)) {
            return ['RspCode' => '97', 'Message' => 'Invalid Query Checksum'];
        }

        $rawAmount = $body['vnp_Amount'] ?? null;
        if (! is_numeric($rawAmount) || (int) $rawAmount <= 0 || ((int) $rawAmount % 100) !== 0) {
            return ['RspCode' => '04', 'Message' => 'Invalid Amount'];
        }
        if ((string) ($body['vnp_TmnCode'] ?? '') !== $tmnCode
            || (string) ($body['vnp_TxnRef'] ?? '') !== $providerReference
            || (string) ($body['vnp_TransactionType'] ?? '') !== '01') {
            return ['RspCode' => '02', 'Message' => 'Invalid transaction identity'];
        }

        $occurredAt = null;
        $payDate = (string) ($body['vnp_PayDate'] ?? '');
        if (preg_match('/^\d{14}$/', $payDate)) {
            try {
                $occurredAt = CarbonImmutable::createFromFormat('YmdHis', $payDate, 'Asia/Ho_Chi_Minh') ?: null;
            } catch (Throwable) {
                $occurredAt = null;
            }
        }

        $safePayload = $body;
        unset($safePayload['vnp_SecureHash']);

        $result = $this->callbackService->handleVerifiedResult([
            'provider_reference' => $providerReference,
            'provider_transaction_id' => isset($body['vnp_TransactionNo']) ? (string) $body['vnp_TransactionNo'] : null,
            'amount' => (int) $rawAmount / 100,
            'currency' => 'VND',
            'response_code' => (string) ($body['vnp_ResponseCode'] ?? ''),
            'transaction_status' => (string) ($body['vnp_TransactionStatus'] ?? ''),
            'provider_occurred_at' => $occurredAt,
            'payload' => $safePayload,
        ]);

        if (($result['RspCode'] ?? null) === '00') {
            $result['PaymentStatus'] = ((string) ($body['vnp_ResponseCode'] ?? '') === '00'
                && (string) ($body['vnp_TransactionStatus'] ?? '') === '00')
                ? 'success'
                : 'failed';
        }

        return $result;
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
                'vnp_ResponseId', 'vnp_Command', 'vnp_ResponseCode', 'vnp_Message',
                'vnp_TmnCode', 'vnp_TxnRef', 'vnp_Amount', 'vnp_BankCode',
                'vnp_PayDate', 'vnp_TransactionNo', 'vnp_TransactionType',
                'vnp_TransactionStatus', 'vnp_OrderInfo', 'vnp_PromotionCode',
                'vnp_PromotionAmount',
            ]
        ));

        return hash_equals(hash_hmac('sha512', $signatureInput, $secret), $incomingHash);
    }
}
