# Kế hoạch KomiBook Batch 2C.1 — VNPAY gateway signing/verification

- **Ngày lập:** 2026-07-25 (Asia/Ho_Chi_Minh)
- **Trạng thái:** Approved for implementation — Codex tự lập và đối chiếu
- **Điều kiện đầu vào:** Batch 2B đã được Codex nghiệm thu
- **Tính chất:** additive; chưa nối vào controller hoặc thay đổi runtime

## 1. Mục tiêu

Tách logic giao thức VNPAY thành một service nhỏ, deterministic và testable:

- kiểm tra cấu hình theo nguyên tắc fail-closed;
- tạo request/payment URL và chữ ký;
- xác minh callback bằng `hash_equals`;
- chuẩn hóa callback thành dữ liệu integer VND;
- không thay đổi timezone global;
- không lưu hoặc trả secret trong payload.

Batch 2C.1 chưa sửa `VnpayController`, route, frontend, order/payment state hoặc
database. Việc nối service vào create endpoint và IPN được tách sang 2C.2/2C.3.

## 2. Bằng chứng hiện trạng

`VnpayController` hiện:

- lặp logic canonicalization/signing ở create, return và IPN;
- so sánh chữ ký bằng `==`, chưa dùng constant-time comparison;
- đổi timezone global bằng `date_default_timezone_set`;
- lấy `order_id` làm gốc giao dịch và chỉ thanh toán một order;
- IPN tự gán trạng thái và dispatch job;
- chưa có lớp giao thức độc lập để test merchant/amount/currency/reference.

2C.1 chỉ giải quyết lớp giao thức, không rewiring hành vi nói trên.

## 3. Phạm vi file

### Được tạo

- `backend/app/Services/Payments/VnpayGateway.php`
- `backend/tests/Unit/Services/Payments/VnpayGatewayTest.php`

### Không được sửa

- `VnpayController.php`, routes và frontend.
- Checkout, Order, ProcessOrder và inventory.
- Models, migrations và schema 2B.
- Config files, `.env`, dependency files.
- Các test hiện hữu.
- Tài liệu upgrade.

## 4. Hợp đồng `VnpayGateway`

Service đọc các giá trị sau bằng Laravel config:

- `services.vnpay.tmn_code`
- `services.vnpay.hash_secret`
- `services.vnpay.url`

Không log hoặc expose `hash_secret`.

### 4.1. Cấu hình

Fail-closed khi:

- thiếu/blank merchant code;
- thiếu/blank hash secret;
- URL thiếu, không hợp lệ hoặc không phải HTTP(S).

Ném exception chuẩn, thông điệp không chứa giá trị secret.

### 4.2. Tạo payment request

Public method nhận dữ liệu đã kiểm tra:

- provider reference;
- amount integer VND;
- order information;
- return URL;
- client IP;
- thời điểm tạo dạng `CarbonInterface`.

Trả:

```php
[
    'url' => '...',
    'request_payload' => [
        // các trường vnp_ đã canonicalize, không có secret/signature
    ],
]
```

Yêu cầu:

- amount dương, không overflow khi nhân 100;
- currency cố định `VND`;
- locale `vn`;
- command `pay`;
- order type `billpayment`;
- timestamp dùng `Asia/Ho_Chi_Minh` từ object thời gian, không đổi timezone global;
- IP không phải IPv4 chuyển thành `127.0.0.1`;
- loại trường `null`/chuỗi rỗng nhưng không loại số hoặc chuỗi `"0"`;
- sort key tăng dần;
- canonical query dùng một implementation duy nhất;
- URL có `vnp_SecureHash`, nhưng `request_payload` không chứa hash hoặc secret.

### 4.3. Xác minh và chuẩn hóa callback

Public method nhận mảng query callback:

1. chỉ lấy trường bắt đầu bằng `vnp_`;
2. tách `vnp_SecureHash` và bỏ `vnp_SecureHashType` khỏi dữ liệu ký;
3. canonicalize giống create request;
4. xác minh bằng `hash_equals`;
5. fail-closed nếu sai signature;
6. xác minh `vnp_TmnCode` khớp config;
7. xác minh `vnp_CurrCode === 'VND'`;
8. `vnp_Amount` phải là chuỗi/số nguyên dương chia hết cho 100;
9. `vnp_TxnRef` không rỗng;
10. chuẩn hóa provider transaction ID, response code, transaction status và
    thời gian provider nếu có.

Trả một array không chứa signature/secret, tối thiểu:

```php
[
    'provider_reference' => '...',
    'provider_transaction_id' => '...',
    'amount' => 100000, // integer VND
    'currency' => 'VND',
    'response_code' => '00',
    'transaction_status' => '00',
    'provider_occurred_at' => CarbonImmutable|null,
    'payload' => [...], // callback đã lọc signature
]
```

Không ghi database và không tạo side effect.

## 5. Test

`VnpayGatewayTest` dùng `Tests\TestCase`, config test và thời gian cố định.

Các nhóm test:

1. deterministic create request:
   - URL/field/currency/timezone/IP đúng;
   - amount nhân 100;
   - payload không có secret/signature;
   - timezone global không đổi.
2. canonicalization/signature:
   - fixture cố định cho expected hash;
   - callback hợp lệ pass;
   - sửa amount/reference/hash làm verify fail.
3. callback validation:
   - merchant sai;
   - currency sai;
   - amount không nguyên, không chia hết 100, bằng 0;
   - reference rỗng.
4. config fail-closed:
   - thiếu merchant;
   - thiếu secret;
   - URL sai.
5. normalization:
   - provider time hợp lệ thành `CarbonImmutable`;
   - provider time thiếu/sai thành `null` hoặc exception theo hợp đồng duy nhất;
   - signature không xuất hiện trong normalized payload.

Dùng data provider để gộp các case validation; không lặp setup dài.

## 6. Cổng nghiệm thu do Codex chạy

```powershell
cd backend
php artisan test --filter=VnpayGatewayTest
php vendor/bin/pint --test app/Services/Payments/VnpayGateway.php tests/Unit/Services/Payments/VnpayGatewayTest.php
cd ..
git diff --check
git status --short
git diff --stat
```

Không chạy full backend suite ở 2C.1. Full suite được gom tại lúc đóng 2C hoặc
khi một thay đổi runtime có nguy cơ regression rộng.

## 7. Điều kiện hoàn thành

- Service hoàn toàn additive và chưa được controller sử dụng.
- Test deterministic và validation pass.
- Không có secret trong output/test failure.
- Pint và diff check pass.
- Không có runtime/frontend/schema/config diff.
- Không commit hoặc push.
- Codex audit độc lập trước khi sang 2C.2.

