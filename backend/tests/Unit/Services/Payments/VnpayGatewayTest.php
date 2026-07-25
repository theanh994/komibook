<?php

namespace Tests\Unit\Services\Payments;

use App\Services\Payments\VnpayGateway;
use Carbon\CarbonImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class VnpayGatewayTest extends TestCase
{
    private const TEST_TMN_CODE = 'KOMITEST';

    private const TEST_HASH_SECRET = 'SECRETKEY1234567890ABCDEF123456';

    private const TEST_URL = 'https://sandbox.vnpayment.vn/paymentv2/vpcpay.html';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.vnpay.tmn_code' => self::TEST_TMN_CODE,
            'services.vnpay.hash_secret' => self::TEST_HASH_SECRET,
            'services.vnpay.url' => self::TEST_URL,
        ]);
    }

    /**
     * 1. Test deterministic create payment request.
     */
    public function test_creates_deterministic_payment_request_and_does_not_mutate_global_timezone(): void
    {
        $gateway = new VnpayGateway;

        $initialTimezone = date_default_timezone_get();
        $occurredAt = CarbonImmutable::create(2026, 7, 25, 19, 0, 0, 'Asia/Ho_Chi_Minh');

        $result = $gateway->createPaymentUrl(
            'REF-123456',
            150000,
            'Thanh toan don hang REF-123456',
            'https://komibook.id.vn/vnpay-return',
            '192.168.1.1',
            $occurredAt
        );

        // Global timezone must be unchanged
        $this->assertEquals($initialTimezone, date_default_timezone_get());

        $this->assertArrayHasKey('url', $result);
        $this->assertArrayHasKey('request_payload', $result);

        $payload = $result['request_payload'];
        $this->assertEquals('2.1.0', $payload['vnp_Version']);
        $this->assertEquals('pay', $payload['vnp_Command']);
        $this->assertEquals(self::TEST_TMN_CODE, $payload['vnp_TmnCode']);
        $this->assertEquals('15000000', $payload['vnp_Amount']);
        $this->assertEquals('VND', $payload['vnp_CurrCode']);
        $this->assertEquals('REF-123456', $payload['vnp_TxnRef']);
        $this->assertEquals('Thanh toan don hang REF-123456', $payload['vnp_OrderInfo']);
        $this->assertEquals('billpayment', $payload['vnp_OrderType']);
        $this->assertEquals('vn', $payload['vnp_Locale']);
        $this->assertEquals('https://komibook.id.vn/vnpay-return', $payload['vnp_ReturnUrl']);
        $this->assertEquals('192.168.1.1', $payload['vnp_IpAddr']);
        $this->assertEquals('20260725190000', $payload['vnp_CreateDate']);

        // Payload must not contain signature or secret
        $this->assertArrayNotHasKey('vnp_SecureHash', $payload);
        $this->assertArrayNotHasKey('hash_secret', $payload);

        // URL must contain vnp_SecureHash
        $this->assertStringContainsString('vnp_SecureHash=', $result['url']);
        $this->assertStringStartsWith(self::TEST_URL.'?', $result['url']);
    }

    /**
     * Test fallback IP sang 127.0.0.1 khi IP client không hợp lệ IPv4 (e.g. IPv6 hoặc rỗng).
     */
    public function test_create_payment_url_fallbacks_invalid_ip_to_localhost(): void
    {
        $gateway = new VnpayGateway;
        $occurredAt = CarbonImmutable::now();

        $result = $gateway->createPaymentUrl(
            'REF-IP-TEST',
            100000,
            'Test IP',
            'https://komibook.id.vn/return',
            '2001:db8::1',
            $occurredAt
        );

        $this->assertEquals('127.0.0.1', $result['request_payload']['vnp_IpAddr']);
    }

    /**
     * Test boundary values cho amount trong createPaymentUrl.
     */
    #[DataProvider('invalidCreateAmountProvider')]
    public function test_create_payment_url_fails_on_invalid_or_overflow_amounts(int $amount, string $expectedMessage): void
    {
        $gateway = new VnpayGateway;

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage($expectedMessage);

        $gateway->createPaymentUrl(
            'REF-LIMIT-TEST',
            $amount,
            'Test amount limit',
            'https://komibook.id.vn/return',
            '127.0.0.1',
            CarbonImmutable::now()
        );
    }

    public static function invalidCreateAmountProvider(): array
    {
        $maxAmount = intdiv(PHP_INT_MAX, 100);

        return [
            'negative amount' => [-500, 'Payment amount must be a positive integer within valid limits.'],
            'zero amount' => [0, 'Payment amount must be a positive integer within valid limits.'],
            'overflow amount' => [$maxAmount + 1, 'Payment amount must be a positive integer within valid limits.'],
        ];
    }

    /**
     * 2. Test canonicalization & signature với fixture hard-coded cố định.
     */
    public function test_canonicalization_and_signature_with_fixed_fixture(): void
    {
        $gateway = new VnpayGateway;

        $params = [
            'vnp_Amount' => '10000000',
            'vnp_Command' => 'pay',
            'vnp_CreateDate' => '20260725190000',
            'vnp_CurrCode' => 'VND',
            'vnp_IpAddr' => '127.0.0.1',
            'vnp_Locale' => 'vn',
            'vnp_OrderInfo' => 'Test',
            'vnp_OrderType' => 'billpayment',
            'vnp_ReturnUrl' => 'http://test.com',
            'vnp_TmnCode' => self::TEST_TMN_CODE,
            'vnp_TxnRef' => 'FIXED-REF-001',
            'vnp_Version' => '2.1.0',
        ];

        $expectedCanonicalQuery = 'vnp_Amount=10000000&vnp_Command=pay&vnp_CreateDate=20260725190000&vnp_CurrCode=VND&vnp_IpAddr=127.0.0.1&vnp_Locale=vn&vnp_OrderInfo=Test&vnp_OrderType=billpayment&vnp_ReturnUrl=http%3A%2F%2Ftest.com&vnp_TmnCode=KOMITEST&vnp_TxnRef=FIXED-REF-001&vnp_Version=2.1.0';
        $expectedSignature = '5abd1cf81bccf0d78029ca7dd7ec870b2862c5d6e238ceccdd546c153dedcdfb78ac3ce766a0d6f468a487cb9995666dd2780de92c250137bc0cfbd559788d27';

        // Assert canonical query matches exact hard-coded fixture
        $actualCanonicalQuery = $gateway->buildCanonicalQuery($params);
        $this->assertEquals($expectedCanonicalQuery, $actualCanonicalQuery);

        // Assert signature matches exact hard-coded SHA-512 hash
        $actualSignature = $gateway->generateSignature($actualCanonicalQuery, self::TEST_HASH_SECRET);
        $this->assertEquals($expectedSignature, $actualSignature);

        // Valid callback pass
        $paramsWithHash = array_merge($params, ['vnp_SecureHash' => $expectedSignature]);
        $normalized = $gateway->verifyAndNormalizeCallback($paramsWithHash);
        $this->assertEquals('FIXED-REF-001', $normalized['provider_reference']);
        $this->assertEquals(100000, $normalized['amount']);

        // Tampered amount fails
        try {
            $tamperedAmount = $paramsWithHash;
            $tamperedAmount['vnp_Amount'] = '20000000';
            $gateway->verifyAndNormalizeCallback($tamperedAmount);
            $this->fail('Expected InvalidArgumentException on tampered amount');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('Invalid VNPAY secure hash signature.', $e->getMessage());
        }

        // Tampered reference fails
        try {
            $tamperedRef = $paramsWithHash;
            $tamperedRef['vnp_TxnRef'] = 'FIXED-REF-999';
            $gateway->verifyAndNormalizeCallback($tamperedRef);
            $this->fail('Expected InvalidArgumentException on tampered reference');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('Invalid VNPAY secure hash signature.', $e->getMessage());
        }

        // Tampered hash fails
        try {
            $tamperedHash = $paramsWithHash;
            $tamperedHash['vnp_SecureHash'] = 'BADHASH123456';
            $gateway->verifyAndNormalizeCallback($tamperedHash);
            $this->fail('Expected InvalidArgumentException on tampered hash');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('Invalid VNPAY secure hash signature.', $e->getMessage());
        }
    }

    /**
     * 3. Test callback validation failures bằng Data Provider.
     */
    #[DataProvider('invalidCallbackParamsProvider')]
    public function test_verify_and_normalize_callback_fails_on_invalid_payloads(mixed $invalidAmount, string $expectedErrorMessage): void
    {
        $gateway = new VnpayGateway;

        $validParams = [
            'vnp_Amount' => '10000000',
            'vnp_Command' => 'pay',
            'vnp_CreateDate' => '20260725190000',
            'vnp_CurrCode' => 'VND',
            'vnp_IpAddr' => '127.0.0.1',
            'vnp_Locale' => 'vn',
            'vnp_OrderInfo' => 'Test',
            'vnp_OrderType' => 'billpayment',
            'vnp_ReturnUrl' => 'http://test.com',
            'vnp_TmnCode' => self::TEST_TMN_CODE,
            'vnp_TxnRef' => 'CB-REF-123',
            'vnp_Version' => '2.1.0',
        ];

        $params = $validParams;
        $params['vnp_Amount'] = $invalidAmount;

        $vnpParams = [];
        foreach ($params as $k => $v) {
            if (str_starts_with($k, 'vnp_') && $k !== 'vnp_SecureHash' && $k !== 'vnp_SecureHashType') {
                $vnpParams[$k] = $v;
            }
        }

        $canonical = $gateway->buildCanonicalQuery($vnpParams);
        $params['vnp_SecureHash'] = $gateway->generateSignature($canonical, self::TEST_HASH_SECRET);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage($expectedErrorMessage);

        $gateway->verifyAndNormalizeCallback($params);
    }

    public static function invalidCallbackParamsProvider(): array
    {
        return [
            'float amount' => [10000000.0, 'Invalid VNPAY callback amount.'],
            'decimal string' => ['10000000.00', 'Invalid VNPAY callback amount.'],
            'scientific notation' => ['1e7', 'Invalid VNPAY callback amount.'],
            'leading plus' => ['+10000000', 'Invalid VNPAY callback amount.'],
            'leading minus' => ['-10000000', 'Invalid VNPAY callback amount.'],
            'whitespace' => [' 10000000 ', 'Invalid VNPAY callback amount.'],
            'zero string' => ['0', 'Invalid VNPAY callback amount.'],
            'not divisible by 100' => ['10000050', 'Invalid VNPAY callback amount.'],
            'exceeds PHP_INT_MAX' => ['99999999999999999999999999999', 'Invalid VNPAY callback amount.'],
        ];
    }

    /**
     * 4. Test config fail-closed với data provider (bao gồm URL scheme boundaries).
     */
    #[DataProvider('failClosedConfigProvider')]
    public function test_config_fails_closed_when_unconfigured(?string $tmnCode, ?string $hashSecret, ?string $url, string $expectedMessage): void
    {
        config([
            'services.vnpay.tmn_code' => $tmnCode,
            'services.vnpay.hash_secret' => $hashSecret,
            'services.vnpay.url' => $url,
        ]);

        $gateway = new VnpayGateway;

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage($expectedMessage);

        $gateway->createPaymentUrl(
            'REF-001',
            100000,
            'Test',
            'http://test.com',
            '127.0.0.1',
            CarbonImmutable::now()
        );
    }

    public static function failClosedConfigProvider(): array
    {
        return [
            'missing tmn_code' => [null, self::TEST_HASH_SECRET, self::TEST_URL, 'VNPAY merchant code is missing or unconfigured.'],
            'blank tmn_code' => ['   ', self::TEST_HASH_SECRET, self::TEST_URL, 'VNPAY merchant code is missing or unconfigured.'],
            'missing hash_secret' => [self::TEST_TMN_CODE, null, self::TEST_URL, 'VNPAY hash secret is missing or unconfigured.'],
            'blank hash_secret' => [self::TEST_TMN_CODE, '  ', self::TEST_URL, 'VNPAY hash secret is missing or unconfigured.'],
            'invalid url format' => [self::TEST_TMN_CODE, self::TEST_HASH_SECRET, 'invalid-url', 'VNPAY payment URL is invalid or unconfigured.'],
            'ftp scheme url' => [self::TEST_TMN_CODE, self::TEST_HASH_SECRET, 'ftp://sandbox.vnpayment.vn/vpcpay.html', 'VNPAY payment URL must use HTTP or HTTPS scheme.'],
            'javascript scheme url' => [self::TEST_TMN_CODE, self::TEST_HASH_SECRET, 'javascript:alert(1)', 'VNPAY payment URL is invalid or unconfigured.'],
            'file scheme url' => [self::TEST_TMN_CODE, self::TEST_HASH_SECRET, 'file:///etc/passwd', 'VNPAY payment URL must use HTTP or HTTPS scheme.'],
        ];
    }

    /**
     * 5. Test normalization provider_occurred_at & payload filtering.
     */
    public function test_normalization_parses_occurred_at_and_filters_payload(): void
    {
        $gateway = new VnpayGateway;

        $params = [
            'vnp_Amount' => '25000000',
            'vnp_Command' => 'pay',
            'vnp_CreateDate' => '20260725190000',
            'vnp_CurrCode' => 'VND',
            'vnp_IpAddr' => '127.0.0.1',
            'vnp_Locale' => 'vn',
            'vnp_OrderInfo' => 'Normalize Test',
            'vnp_OrderType' => 'billpayment',
            'vnp_ReturnUrl' => 'http://test.com',
            'vnp_TmnCode' => self::TEST_TMN_CODE,
            'vnp_TxnRef' => 'NORM-REF-99',
            'vnp_Version' => '2.1.0',
            'vnp_ResponseCode' => '00',
            'vnp_TransactionNo' => '14111222',
            'vnp_TransactionStatus' => '00',
            'vnp_PayDate' => '20260725193045',
        ];

        $canonical = $gateway->buildCanonicalQuery($params);
        $hash = $gateway->generateSignature($canonical, self::TEST_HASH_SECRET);

        $params['vnp_SecureHash'] = $hash;

        $normalized = $gateway->verifyAndNormalizeCallback($params);

        $this->assertEquals('NORM-REF-99', $normalized['provider_reference']);
        $this->assertEquals('14111222', $normalized['provider_transaction_id']);
        $this->assertEquals(250000, $normalized['amount']);
        $this->assertEquals('VND', $normalized['currency']);
        $this->assertEquals('00', $normalized['response_code']);
        $this->assertEquals('00', $normalized['transaction_status']);

        $this->assertInstanceOf(CarbonImmutable::class, $normalized['provider_occurred_at']);
        $this->assertEquals('2026-07-25 19:30:45', $normalized['provider_occurred_at']->format('Y-m-d H:i:s'));
        $this->assertEquals('Asia/Ho_Chi_Minh', $normalized['provider_occurred_at']->getTimezone()->getName());

        $this->assertArrayNotHasKey('vnp_SecureHash', $normalized['payload']);

        // Case invalid PayDate parses as null
        $paramsInvalidPayDate = $params;
        $paramsInvalidPayDate['vnp_PayDate'] = 'invalid_date';
        $canonical2 = $gateway->buildCanonicalQuery(array_diff_key($paramsInvalidPayDate, ['vnp_SecureHash' => 1]));
        $paramsInvalidPayDate['vnp_SecureHash'] = $gateway->generateSignature($canonical2, self::TEST_HASH_SECRET);

        $normalized2 = $gateway->verifyAndNormalizeCallback($paramsInvalidPayDate);
        $this->assertNull($normalized2['provider_occurred_at']);
    }
}
