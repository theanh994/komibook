<?php

namespace App\Services\Payments;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Str;
use InvalidArgumentException;

class VnpayGateway
{
    /**
     * Lấy và kiểm tra cấu hình VNPAY theo nguyên tắc fail-closed.
     * Chỉ chấp nhận URL có scheme HTTP hoặc HTTPS.
     *
     * @return array{tmn_code: string, hash_secret: string, url: string}
     *
     * @throws InvalidArgumentException
     */
    protected function getConfig(): array
    {
        $tmnCode = config('services.vnpay.tmn_code');
        $hashSecret = config('services.vnpay.hash_secret');
        $url = config('services.vnpay.url');

        if (empty($tmnCode) || ! is_string($tmnCode) || trim($tmnCode) === '') {
            throw new InvalidArgumentException('VNPAY merchant code is missing or unconfigured.');
        }

        if (empty($hashSecret) || ! is_string($hashSecret) || trim($hashSecret) === '') {
            throw new InvalidArgumentException('VNPAY hash secret is missing or unconfigured.');
        }

        if (empty($url) || ! is_string($url) || filter_var($url, FILTER_VALIDATE_URL) === false) {
            throw new InvalidArgumentException('VNPAY payment URL is invalid or unconfigured.');
        }

        $scheme = parse_url($url, PHP_URL_SCHEME);
        if (! is_string($scheme) || ! in_array(strtolower($scheme), ['http', 'https'], true)) {
            throw new InvalidArgumentException('VNPAY payment URL must use HTTP or HTTPS scheme.');
        }

        if (strtolower((string) parse_url($url, PHP_URL_HOST)) !== 'sandbox.vnpayment.vn') {
            throw new InvalidArgumentException('Only the VNPAY Sandbox payment URL is allowed.');
        }

        return [
            'tmn_code' => trim($tmnCode),
            'hash_secret' => trim($hashSecret),
            'url' => trim($url),
        ];
    }

    /**
     * Chuẩn hóa chuỗi query canonical từ mảng tham số.
     * Quy tắc: Lọc bỏ null và chuỗi rỗng (giữ "0" hoặc 0), ksort theo key, urlencode key & value.
     */
    public function buildCanonicalQuery(array $params): string
    {
        $filtered = [];
        foreach ($params as $key => $value) {
            if ($value !== null && $value !== '') {
                $filtered[$key] = (string) $value;
            }
        }

        ksort($filtered);

        $queryParts = [];
        foreach ($filtered as $key => $value) {
            $queryParts[] = urlencode($key).'='.urlencode($value);
        }

        return implode('&', $queryParts);
    }

    /**
     * Tính toán chữ ký HMAC SHA512.
     */
    public function generateSignature(string $canonicalQuery, string $hashSecret): string
    {
        return hash_hmac('sha512', $canonicalQuery, $hashSecret);
    }

    /**
     * Tạo payment request URL và payload đã chuẩn hóa.
     *
     * @param  string  $providerReference  Mã tham chiếu giao dịch (TxnRef)
     * @param  int  $amount  Số tiền VND (số nguyên dương)
     * @param  string  $orderInfo  Nội dung thanh toán
     * @param  string  $returnUrl  URL chuyển hướng sau thanh toán
     * @param  string  $clientIp  Địa chỉ IP khách hàng
     * @param  CarbonInterface  $occurredAt  Thời điểm tạo giao dịch
     * @return array{url: string, request_payload: array<string, string>}
     */
    public function createPaymentUrl(
        string $providerReference,
        int $amount,
        string $orderInfo,
        string $returnUrl,
        string $clientIp,
        CarbonInterface $occurredAt
    ): array {
        $config = $this->getConfig();

        $providerReference = trim($providerReference);
        if (! preg_match('/^[A-Za-z0-9]{1,100}$/', $providerReference)) {
            throw new InvalidArgumentException('VNPAY transaction reference must contain 1-100 alphanumeric characters.');
        }

        $maxAmount = min(intdiv(PHP_INT_MAX, 100), 9_999_999_999);
        if ($amount <= 0 || $amount > $maxAmount) {
            throw new InvalidArgumentException('Payment amount must be a positive integer within valid limits.');
        }

        if (filter_var($returnUrl, FILTER_VALIDATE_URL) === false
            || ! in_array(strtolower((string) parse_url($returnUrl, PHP_URL_SCHEME)), ['http', 'https'], true)) {
            throw new InvalidArgumentException('VNPAY return URL must be a valid HTTP or HTTPS URL.');
        }

        $normalizedOrderInfo = Str::ascii($orderInfo);
        $normalizedOrderInfo = preg_replace('/[^A-Za-z0-9 ]+/', ' ', $normalizedOrderInfo) ?? '';
        $normalizedOrderInfo = preg_replace('/\s+/', ' ', trim($normalizedOrderInfo)) ?? '';
        $normalizedOrderInfo = mb_substr($normalizedOrderInfo, 0, 255);
        if ($normalizedOrderInfo === '') {
            $normalizedOrderInfo = 'Thanh toan KomiBook';
        }

        $validIp = filter_var($clientIp, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) ? $clientIp : '127.0.0.1';
        $vietnamTime = $occurredAt->copy()->setTimezone('Asia/Ho_Chi_Minh');
        $createDate = $vietnamTime->format('YmdHis');
        $expireDate = $vietnamTime->addMinutes(15)->format('YmdHis');

        $params = [
            'vnp_Version' => '2.1.0',
            'vnp_Command' => 'pay',
            'vnp_TmnCode' => $config['tmn_code'],
            'vnp_Amount' => (string) ($amount * 100),
            'vnp_CurrCode' => 'VND',
            'vnp_TxnRef' => $providerReference,
            'vnp_OrderInfo' => $normalizedOrderInfo,
            'vnp_OrderType' => 'billpayment',
            'vnp_Locale' => 'vn',
            'vnp_ReturnUrl' => $returnUrl,
            'vnp_IpAddr' => $validIp,
            'vnp_CreateDate' => $createDate,
            'vnp_ExpireDate' => $expireDate,
        ];

        $canonicalQuery = $this->buildCanonicalQuery($params);
        $secureHash = $this->generateSignature($canonicalQuery, $config['hash_secret']);

        $paymentUrl = $config['url'].'?'.$canonicalQuery.'&vnp_SecureHash='.$secureHash;

        ksort($params);

        return [
            'url' => $paymentUrl,
            'request_payload' => $params,
        ];
    }

    /**
     * Xác minh chữ ký và chuẩn hóa dữ liệu callback (IPN / Return).
     *
     * @param  array<string, mixed>  $queryParams
     * @return array{
     *     provider_reference: string,
     *     provider_transaction_id: string|null,
     *     amount: int,
     *     currency: string,
     *     response_code: string|null,
     *     transaction_status: string|null,
     *     provider_occurred_at: CarbonImmutable|null,
     *     payload: array<string, mixed>
     * }
     */
    public function verifyAndNormalizeCallback(array $queryParams): array
    {
        $config = $this->getConfig();

        $incomingHash = $queryParams['vnp_SecureHash'] ?? null;
        if (empty($incomingHash) || ! is_string($incomingHash)) {
            throw new InvalidArgumentException('Callback payload is missing secure hash.');
        }

        // Tách các tham số vnp_ để ký. Theo mẫu ReturnURL chính thức của
        // VNPAY, vnp_SecureHashType (nếu có) vẫn thuộc dữ liệu checksum;
        // chỉ chính giá trị chữ ký vnp_SecureHash bị loại bỏ.
        $vnpParams = [];
        foreach ($queryParams as $key => $value) {
            if (str_starts_with($key, 'vnp_') && $key !== 'vnp_SecureHash') {
                $vnpParams[$key] = $value;
            }
        }

        $canonicalQuery = $this->buildCanonicalQuery($vnpParams);
        $expectedHash = $this->generateSignature($canonicalQuery, $config['hash_secret']);

        if (! hash_equals(strtolower($expectedHash), strtolower($incomingHash))) {
            throw new InvalidArgumentException('Invalid VNPAY secure hash signature.');
        }

        // Kiểm tra Merchant Code
        $cbTmnCode = $queryParams['vnp_TmnCode'] ?? '';
        if ((string) $cbTmnCode !== $config['tmn_code']) {
            throw new InvalidArgumentException('VNPAY merchant code mismatch.');
        }

        // Kiểm tra Đơn vị tiền tệ
        $cbCurrency = $queryParams['vnp_CurrCode'] ?? '';
        if ((string) $cbCurrency !== 'VND') {
            throw new InvalidArgumentException('VNPAY currency must be VND.');
        }

        // Kiểm tra Số tiền (vnp_Amount): chỉ chấp nhận integer dương hoặc string gồm các chữ số thập phân
        $cbAmountRaw = $queryParams['vnp_Amount'] ?? null;
        if ($cbAmountRaw === null || is_float($cbAmountRaw) || is_bool($cbAmountRaw) || is_array($cbAmountRaw)) {
            throw new InvalidArgumentException('Invalid VNPAY callback amount.');
        }

        if (is_int($cbAmountRaw)) {
            if ($cbAmountRaw <= 0) {
                throw new InvalidArgumentException('Invalid VNPAY callback amount.');
            }
            $rawAmount = $cbAmountRaw;
        } elseif (is_string($cbAmountRaw)) {
            if (! ctype_digit($cbAmountRaw)) {
                throw new InvalidArgumentException('Invalid VNPAY callback amount.');
            }
            if (bccomp($cbAmountRaw, (string) PHP_INT_MAX) > 0) {
                throw new InvalidArgumentException('Invalid VNPAY callback amount.');
            }
            $rawAmount = (int) $cbAmountRaw;
        } else {
            throw new InvalidArgumentException('Invalid VNPAY callback amount.');
        }

        if ($rawAmount <= 0 || ($rawAmount % 100) !== 0) {
            throw new InvalidArgumentException('Invalid VNPAY callback amount.');
        }

        $amountInt = (int) ($rawAmount / 100);

        // Kiểm tra Mã tham chiếu giao dịch
        $cbTxnRef = $queryParams['vnp_TxnRef'] ?? '';
        if (empty($cbTxnRef) || ! is_string($cbTxnRef) || trim($cbTxnRef) === '') {
            throw new InvalidArgumentException('VNPAY transaction reference is missing.');
        }

        // Parse thời gian xảy ra giao dịch tại VNPAY nếu có (vnp_PayDate dạng YmdHis)
        $occurredAt = null;
        $cbPayDate = $queryParams['vnp_PayDate'] ?? null;
        if (is_string($cbPayDate) && strlen($cbPayDate) === 14 && ctype_digit($cbPayDate)) {
            try {
                $occurredAt = CarbonImmutable::createFromFormat('YmdHis', $cbPayDate, 'Asia/Ho_Chi_Minh');
                if ($occurredAt === false) {
                    $occurredAt = null;
                }
            } catch (\Throwable $e) {
                $occurredAt = null;
            }
        }

        ksort($vnpParams);

        return [
            'provider_reference' => (string) $cbTxnRef,
            'provider_transaction_id' => isset($queryParams['vnp_TransactionNo']) ? (string) $queryParams['vnp_TransactionNo'] : null,
            'amount' => $amountInt,
            'currency' => 'VND',
            'response_code' => isset($queryParams['vnp_ResponseCode']) ? (string) $queryParams['vnp_ResponseCode'] : null,
            'transaction_status' => isset($queryParams['vnp_TransactionStatus']) ? (string) $queryParams['vnp_TransactionStatus'] : null,
            'provider_occurred_at' => $occurredAt,
            'payload' => $vnpParams,
        ];
    }
}
