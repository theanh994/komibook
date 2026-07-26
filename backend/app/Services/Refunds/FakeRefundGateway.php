<?php

namespace App\Services\Refunds;

use App\Models\PaymentTransaction;
use App\Models\RefundTransaction;

class FakeRefundGateway implements RefundGatewayInterface
{
    public function refund(
        RefundTransaction $refund,
        PaymentTransaction $originalPayment,
        string $actorReference,
        string $clientIp
    ): array {
        return [
            'successful' => true,
            'pending' => false,
            'provider_reference' => 'FAKE-REFUND-'.$refund->id,
            'request' => [
                'refund_id' => $refund->id,
                'original_reference' => $originalPayment->provider_reference,
                'amount' => $refund->amount,
            ],
            'response' => ['response_code' => '00'],
            'failure_reason' => null,
        ];
    }

    public function queryRefund(
        RefundTransaction $refund,
        PaymentTransaction $originalPayment,
        string $requestReference,
        string $clientIp
    ): array {
        return [
            'successful' => true,
            'pending' => false,
            'provider_reference' => $refund->provider_reference ?? 'FAKE-REFUND-'.$refund->id,
            'request' => ['command' => 'querydr', 'request_reference' => $requestReference],
            'response' => ['response_code' => '00', 'transaction_status' => '00'],
            'failure_reason' => null,
        ];
    }
}
