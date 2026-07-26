<?php

namespace App\Services\Refunds;

use App\Models\PaymentTransaction;
use App\Models\RefundTransaction;

interface RefundGatewayInterface
{
    /**
     * @return array{successful: bool, pending: bool, provider_reference: ?string, request: array, response: array, failure_reason: ?string}
     */
    public function refund(
        RefundTransaction $refund,
        PaymentTransaction $originalPayment,
        string $actorReference,
        string $clientIp
    ): array;

    /**
     * @return array{successful: bool, pending: bool, provider_reference: ?string, request: array, response: array, failure_reason: ?string}
     */
    public function queryRefund(
        RefundTransaction $refund,
        PaymentTransaction $originalPayment,
        string $requestReference,
        string $clientIp
    ): array;
}
