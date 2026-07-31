# Kế hoạch KomiBook Batch 2C.2 — Khởi tạo VNPAY payment theo checkout session

- **Ngày lập:** 2026-07-25 (Asia/Ho_Chi_Minh)
- **Trạng thái:** Approved for implementation — Codex tự lập và đối chiếu
- **Điều kiện đầu vào:** Batch 2C.1 đã được Codex nghiệm thu
- **Tính chất:** backend-only; nối create-payment vào mô hình checkout/payment mới

## 1. Mục tiêu

Khởi tạo đúng một VNPAY payment attempt còn hiệu lực cho toàn bộ checkout
session, dù frontend hiện vẫn gửi `order_id` của một order trong session.

Batch này đồng thời ngăn order online được dispatch sang fulfillment trước khi
payment được IPN xác nhận. Batch chưa xử lý return/IPN, chưa chuyển trạng thái
payment/order và chưa thay đổi frontend.

## 2. Bằng chứng hiện trạng

- `POST /api/vnpay/create` đang nhận `order_id` và ký số tiền của riêng order đó.
- Checkout nhiều vendor tạo một `CheckoutSession` và nhiều order; tổng cần thanh
  toán nằm tại snapshot `checkout_sessions.total_amount`.
- `CheckoutService` hiện dispatch `ProcessOrder` cho cả COD và online ngay sau
  commit, trái invariant “online chưa paid thì chưa fulfillment”.
- `PaymentTransaction` đã có unique key cho provider reference/idempotency key,
  amount integer, currency, status, payload và expiry.
- `VnpayGateway` 2C.1 đã đảm nhiệm cấu hình, canonicalization, ký URL và payload
  không chứa secret/signature.

## 3. Phạm vi file code

### Được tạo

- `backend/app/Services/Payments/VnpayPaymentService.php`
- `backend/tests/Feature/VnpayPaymentInitiationTest.php`

### Được sửa

- `backend/app/Http/Controllers/Api/VnpayController.php`
- `backend/app/Services/CheckoutService.php`

### Không được sửa

- `VnpayGateway.php` và test 2C.1.
- Models, enums, migrations, schema và factories.
- Routes, config, `.env`, frontend và dependency files.
- Return URL, IPN, order/payment transition implementation.
- Tài liệu upgrade.

## 4. Hợp đồng create-payment

Request tiếp tục nhận:

```json
{
  "order_id": 123
}
```

Giữ tương thích frontend hiện tại nhưng backend phải:

1. tải order theo ID và kiểm tra owner bằng user đã xác thực;
2. resolve `Order -> CheckoutSessionOrder -> CheckoutSession`;
3. dùng snapshot `CheckoutSession.total_amount`, không dùng amount của order đầu;
4. khóa checkout session trong database transaction trước khi tìm/tạo attempt;
5. tái sử dụng một attempt `pending` chưa hết hạn;
6. đánh dấu attempt `pending` đã quá hạn thành `expired`, rồi mới tạo attempt mới;
7. attempt `failed`/`expired` là terminal và không được đưa lại về `pending`;
8. tạo provider reference và idempotency key mới, unique, tối đa 128 ký tự;
9. gọi `VnpayGateway` để tạo URL và chỉ lưu `request_payload` đã loại
   secret/signature;
10. không gọi mạng bên ngoài trong database transaction.

Response thành công giữ hai trường cũ và chỉ bổ sung trường không phá vỡ client:

```json
{
  "status": "success",
  "url": "https://...",
  "provider_reference": "...",
  "checkout_code": "..."
}
```

## 5. Điều kiện fail-closed

Không tạo payment transaction khi:

- order không thuộc authenticated user: HTTP 403;
- order không có checkout-session link: HTTP 422;
- checkout session đã hết hạn;
- currency khác `VND` hoặc total không phải integer dương;
- bất kỳ order nào trong session không còn `pending`/`unpaid`;
- checkout không phải phương thức online/VNPAY;
- cấu hình/gateway không hợp lệ: HTTP 503 với thông báo chung, không chứa config,
  secret hoặc exception nội bộ.

Validation request lỗi giữ semantics Laravel 422. Guest tiếp tục bị Sanctum chặn.

## 6. Payment-attempt semantics

- Provider cố định: `vnpay`.
- Status mới: `PaymentTransactionStatus::PENDING`.
- TTL attempt: hằng số nội bộ 15 phút.
- Nếu `CheckoutSession.expires_at` có giá trị, attempt không được sống lâu hơn
  session.
- Retry trong TTL trả lại cùng provider reference và không thêm row.
- Retry sau expiry hoặc sau terminal failure tạo row mới với reference/key mới.
- Gateway request dùng checkout code làm order info và toàn bộ session total.
- Khi tái sử dụng attempt, URL phải được tái tạo từ request payload đã lưu để giữ
  cùng reference, amount, IP và create time; không đọc lại giá/order hiện tại.
- Không lưu payment URL, secure hash hoặc hash secret trong database.

## 7. Chặn fulfillment sớm

`CheckoutService` chỉ dispatch `ProcessOrder` ở luồng hiện tại cho order COD.
Order có `payment_method = online`/VNPAY phải giữ `pending` và không dispatch.
Việc dispatch sau payment success thuộc Batch 2C.3.

Không thay đổi hành vi COD ngoài điều kiện phân nhánh này.

## 8. Feature tests bắt buộc

`VnpayPaymentInitiationTest` dùng `RefreshDatabase`, config test giả và user
Sanctum:

1. guest bị từ chối;
2. owner tạo payment cho checkout một vendor;
3. checkout nhiều vendor từ bất kỳ order con nào đều ký đúng tổng session;
4. order của user khác trả 403 và không tạo transaction;
5. legacy order không có session link trả 422;
6. COD, session expired, order paid/cancelled hoặc currency sai đều fail-closed;
7. hai request liên tiếp trong TTL chỉ có một pending row, cùng reference/URL;
8. pending hết hạn được chuyển `expired` và tạo attempt mới;
9. attempt `failed` tạo attempt mới, không được hồi sinh;
10. request payload lưu trong DB không chứa secure hash hoặc secret;
11. gateway config lỗi trả 503 chung và rollback, không để row mồ côi;
12. checkout VNPAY không dispatch `ProcessOrder`, còn COD vẫn giữ hành vi hiện tại.

Test không đọc `.env`, không truy cập production service và không ghi filesystem.

## 9. Cổng nghiệm thu do Codex chạy

```powershell
cd backend
php artisan test --filter=VnpayPaymentInitiationTest
php artisan test --filter=VnpayGatewayTest
php artisan test --filter=Phase2CheckoutSessionWritePathTest
php vendor/bin/pint --test app/Http/Controllers/Api/VnpayController.php app/Services/CheckoutService.php app/Services/Payments/VnpayPaymentService.php tests/Feature/VnpayPaymentInitiationTest.php
cd ..
git diff --check
git status --short
git diff --stat
```

Chưa chạy full backend suite trong 2C.2. Full suite được gom khi đóng 2C sau IPN
hoặc chạy sớm nếu audit phát hiện thay đổi ngoài phạm vi/rủi ro regression rộng.

## 10. Điều kiện hoàn thành

- Một payment attempt bao phủ toàn checkout session.
- Duplicate create trong TTL không tạo duplicate row.
- Không fulfillment order online trước khi paid.
- API request/response hiện tại không bị phá vỡ.
- Không có secret/signature trong stored payload hoặc response lỗi.
- Targeted tests, regression tests, Pint và diff check đều pass.
- Không commit hoặc push.
