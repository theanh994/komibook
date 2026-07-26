<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\PaymentTransaction;
use App\Models\RefundTransaction;
use App\Models\ReturnRequest;
use App\Services\Refunds\VnpayRefundGateway;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class VnpayRefundGatewayTest extends TestCase
{
    public function test_partial_refund_uses_official_fields_and_accepts_only_a_valid_signed_final_response(): void
    {
        config()->set('services.vnpay.refund_url', 'https://sandbox.example/refund');
        config()->set('services.vnpay.tmn_code', 'TESTCODE');
        config()->set('services.vnpay.hash_secret', 'test-secret');

        $responseBody = [
            'vnp_ResponseId' => 'RESPONSE001',
            'vnp_Command' => 'refund',
            'vnp_ResponseCode' => '00',
            'vnp_Message' => 'Success',
            'vnp_TmnCode' => 'TESTCODE',
            'vnp_TxnRef' => 'ORDER001',
            'vnp_Amount' => '5000000',
            'vnp_BankCode' => 'NCB',
            'vnp_PayDate' => '20260726160000',
            'vnp_TransactionNo' => '123456789',
            'vnp_TransactionType' => '03',
            'vnp_TransactionStatus' => '00',
            'vnp_OrderInfo' => 'Refund return-001',
        ];
        $responseBody['vnp_SecureHash'] = $this->sign($responseBody, [
            'vnp_ResponseId', 'vnp_Command', 'vnp_ResponseCode', 'vnp_Message', 'vnp_TmnCode',
            'vnp_TxnRef', 'vnp_Amount', 'vnp_BankCode', 'vnp_PayDate', 'vnp_TransactionNo',
            'vnp_TransactionType', 'vnp_TransactionStatus', 'vnp_OrderInfo',
        ]);
        Http::fake(['https://sandbox.example/refund' => Http::response($responseBody)]);

        $return = new ReturnRequest(['code' => 'return-001']);
        $refund = new RefundTransaction([
            'idempotency_key' => 'refund:return-001',
            'amount' => 50000,
        ]);
        $refund->setRelation('returnRequest', $return);
        $payment = new PaymentTransaction([
            'provider_reference' => 'ORDER001',
            'provider_transaction_id' => '123456789',
            'amount' => 100000,
            'request_payload' => ['vnp_CreateDate' => '20260725150000'],
            'paid_at' => now(),
        ]);

        $result = app(VnpayRefundGateway::class)->refund($refund, $payment, '42', '127.0.0.1');

        $this->assertTrue($result['successful']);
        $this->assertFalse($result['pending']);
        $this->assertSame('03', $result['request']['vnp_TransactionType']);
        $this->assertSame('20260725150000', $result['request']['vnp_TransactionDate']);
        $this->assertMatchesRegularExpression('/^[A-F0-9]{32}$/', $result['request']['vnp_RequestId']);
        $this->assertArrayNotHasKey('vnp_SecureHash', $result['request']);
        Http::assertSent(function (Request $request) {
            $payload = $request->data();

            return $request->url() === 'https://sandbox.example/refund'
                && $payload['vnp_Amount'] === 5000000
                && strlen($payload['vnp_SecureHash']) === 128;
        });
    }

    public function test_invalid_response_signature_never_finalizes_refund(): void
    {
        config()->set('services.vnpay.refund_url', 'https://sandbox.example/refund');
        config()->set('services.vnpay.tmn_code', 'TESTCODE');
        config()->set('services.vnpay.hash_secret', 'test-secret');
        Http::fake(['https://sandbox.example/refund' => Http::response([
            'vnp_ResponseCode' => '00',
            'vnp_TransactionStatus' => '00',
            'vnp_SecureHash' => str_repeat('0', 128),
        ])]);

        $refund = new RefundTransaction(['idempotency_key' => 'refund:return-002', 'amount' => 100000]);
        $refund->setRelation('returnRequest', new ReturnRequest(['code' => 'return-002']));
        $payment = new PaymentTransaction([
            'provider_reference' => 'ORDER002',
            'provider_transaction_id' => '987654321',
            'amount' => 100000,
            'request_payload' => ['vnp_CreateDate' => '20260725150000'],
            'paid_at' => now(),
        ]);

        $result = app(VnpayRefundGateway::class)->refund($refund, $payment, '42', '127.0.0.1');

        $this->assertFalse($result['successful']);
        $this->assertFalse($result['pending']);
        $this->assertSame('VNPAY refund response signature is invalid.', $result['failure_reason']);
    }

    /** @param array<string, mixed> $payload @param array<int, string> $fields */
    private function sign(array $payload, array $fields): string
    {
        $data = implode('|', array_map(fn (string $field) => (string) ($payload[$field] ?? ''), $fields));

        return hash_hmac('sha512', $data, 'test-secret');
    }
}
