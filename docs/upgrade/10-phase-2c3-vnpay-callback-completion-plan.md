# Kế hoạch KomiBook Batch 2C.3 — Hoàn thiện VNPAY Return/IPN và đóng 2C

- **Ngày lập:** 2026-07-25 (Asia/Ho_Chi_Minh)
- **Trạng thái:** Approved for implementation — batch lớn
- **Điều kiện đầu vào:** Batch 2C.1 và 2C.2 đã được Codex nghiệm thu
- **Tính chất:** backend callback processing + thông báo frontend; không đổi schema

## 1. Mục tiêu

Hoàn thiện toàn bộ phần VNPAY còn lại của Giai đoạn 2C trong một batch:

1. Return URL chỉ xác minh và hiển thị kết quả, không ghi trạng thái.
2. IPN là nguồn xác nhận payment duy nhất.
3. Callback dùng `VnpayGateway`, không còn signing/verification thủ công trong
   controller.
4. Payment transaction và toàn bộ order trong checkout session được cập nhật
   atomically, có row lock và idempotent.
5. Fulfillment chỉ được dispatch sau payment success đã commit.
6. Callback lặp 2–5 lần không cập nhật hoặc dispatch lần hai.

Batch này đủ để đóng 2C. Các phần inventory reservation, ProcessOrder
idempotency, outbox/reconciliation và refund thuộc 2D/2E, không kéo vào batch.

## 2. Phạm vi file code

### Được tạo

- `backend/app/Services/Payments/VnpayCallbackService.php`
- `backend/tests/Feature/VnpayCallbackProcessingTest.php`

### Được sửa

- `backend/app/Http/Controllers/Api/VnpayController.php`
- `frontend/src/views/OrdersView.vue`

### Không được sửa

- `VnpayGateway.php`, `VnpayPaymentService.php` và test 2C.1/2C.2.
- `CheckoutService.php`, `ProcessOrder.php`, models, enums, jobs và mail.
- Migrations, schema, routes, config, `.env`, dependencies và factories.
- Các controller/service/frontend khác.
- Tài liệu upgrade.

## 3. Kiến trúc

`VnpayController` chỉ:

- chuyển query array sang `VnpayCallbackService`;
- trả JSON VNPAY IPN;
- redirect Return về frontend.

Controller không được chứa `hash_hmac`, canonicalization, so sánh signature,
truy vấn order/payment hoặc state transition.

`VnpayCallbackService` inject `VnpayGateway` và có hai public operation:

- xử lý IPN, trả đúng `{RspCode, Message}`;
- resolve trạng thái Return read-only thành một trong:
  `success`, `failed`, `pending`, `invalid_signature`, `invalid_transaction`.

Không log query callback đầy đủ, chữ ký, secret hoặc credential.

## 4. Xác minh chung

Mọi Return/IPN phải gọi `VnpayGateway::verifyAndNormalizeCallback()` trước khi
tin provider reference, amount, currency, response code hoặc transaction ID.

Sau normalization:

- provider cố định `vnpay`;
- tìm `PaymentTransaction` bằng `(provider, provider_reference)`;
- amount và currency phải khớp snapshot transaction;
- transaction phải thuộc checkout session tồn tại;
- provider transaction ID của success IPN phải là chuỗi không rỗng;
- nếu transaction đã có provider transaction ID thì callback phải khớp;
- provider transaction ID không được thuộc payment transaction khác.

Normalized/stored callback payload không chứa secure hash hoặc secret.

## 5. Lock order và transaction

Gateway verification thực hiện trước database transaction vì không ghi DB.

Để biết session cần khóa, service được phép đọc snapshot payment transaction
không khóa. Trong transaction phải xác minh lại theo thứ tự:

1. khóa checkout session;
2. tải lại và khóa payment transaction;
3. khóa các `CheckoutSessionOrder` theo `order_id` tăng dần;
4. khóa toàn bộ order theo ID tăng dần;
5. kiểm tra identity/amount/currency/state;
6. lưu sanitized callback và chuyển trạng thái.

Không dispatch job, gửi mail hoặc gọi dịch vụ ngoài trong transaction.

## 6. IPN state machine

### 6.1 Success

Success chỉ khi đồng thời:

- `vnp_ResponseCode === '00'`;
- `vnp_TransactionStatus === '00'`;
- payment transaction đang `pending`;
- mọi order đang `pending`, `unpaid` và là online/VNPAY.

Trong cùng transaction:

- transaction `pending -> paid`;
- lưu provider transaction ID, provider occurred time, sanitized
  `response_payload`, `paid_at`;
- toàn bộ order `pending -> confirmed`;
- toàn bộ order `payment_status -> paid`;
- giữ payment method online/VNPAY, không đổi thành COD.

Sau khi transaction thành công, dispatch đúng một `ProcessOrder` cho mỗi order
bằng cơ chế after-commit. Callback lặp không được dispatch lại.

### 6.2 Provider failure

Nếu response code hoặc transaction status khác `00` và transaction đang
`pending`:

- transaction `pending -> failed`;
- lưu provider identity/time/payload và `failed_at`;
- order giữ `pending + unpaid` để buyer có thể tạo attempt mới;
- không dispatch fulfillment.

### 6.3 Terminal/idempotent

- Callback success lặp, cùng identity, cho transaction đã `paid`: trả success
  idempotent, không ghi/dispatch lại.
- Callback failure lặp cho transaction đã `failed`: trả success idempotent.
- Callback xung đột với terminal `paid`, `failed`, `expired`, `refunding` hoặc
  `refunded`: không đổi trạng thái, không dispatch và trả “already processed”.
- Không bao giờ hồi sinh `failed/expired` thành `pending/paid`.
- Pending success nhưng order state không hợp lệ: rollback toàn bộ và trả lỗi
  tạm thời để đối soát; không partial update.

## 7. VNPAY IPN response contract

Giữ đúng JSON key casing:

```json
{
  "RspCode": "00",
  "Message": "Confirm Success"
}
```

Mapping:

- `00`: xử lý thành công hoặc duplicate cùng outcome;
- `01`: không tìm thấy provider reference;
- `02`: terminal transaction đã xử lý nhưng callback xung đột;
- `04`: amount/currency không khớp local snapshot;
- `97`: signature, merchant, callback format hoặc gateway verification sai;
- `99`: lỗi transaction/state/DB chưa xử lý được.

Không trả exception message hoặc dữ liệu nội bộ cho provider.

## 8. Return URL read-only

Return callback:

1. verify/normalize bằng gateway;
2. đọc payment transaction theo reference;
3. kiểm tra amount/currency;
4. không ghi database và không dispatch;
5. redirect về `config('app.frontend_url').'/orders?payment=...'`.

Kết quả:

- local transaction `paid` và callback success: `success`;
- callback/provider failure hoặc local transaction `failed`: `failed`;
- callback success nhưng local transaction vẫn `pending`: `pending`;
- verify thất bại: `invalid_signature`;
- transaction không tồn tại hoặc snapshot mismatch: `invalid_transaction`.

Return không được tự đánh dấu paid dù provider response là `00`.

## 9. Frontend

`OrdersView.vue` giữ các toast hiện tại và bổ sung:

- `payment=pending`: thông báo đã nhận kết quả từ cổng thanh toán và đang chờ hệ
  thống xác nhận;
- `payment=invalid_transaction`: cảnh báo không đối chiếu được giao dịch.

Vẫn xóa query parameter sau khi hiển thị. Không đổi API client hoặc luồng checkout.

## 10. Feature tests bắt buộc

`VnpayCallbackProcessingTest` dùng `RefreshDatabase`, config giả, `Queue::fake`,
Redis/File mock; tự tạo callback đã ký qua fixture/helper nhưng phải kiểm tra
gateway contract độc lập.

Tối thiểu:

1. success IPN một vendor cập nhật transaction/order và dispatch sau commit;
2. success IPN ba vendor cập nhật toàn bộ, đúng một job mỗi order;
3. success callback lặp 2–5 lần trả `00`, không thêm dispatch/update;
4. failure IPN chuyển transaction failed, order giữ pending/unpaid, không job;
5. failure callback lặp idempotent;
6. retry create-payment sau failure tạo attempt mới;
7. invalid/tampered signature, merchant, currency/format trả `97`;
8. unknown reference trả `01`;
9. local amount mismatch trả `04`;
10. provider transaction ID thiếu, xung đột hoặc thuộc transaction khác fail
    closed, không partial update;
11. late success cho failed/expired và conflicting terminal callback trả `02`,
    không hồi sinh;
12. order state conflict hoặc thiếu session/order link trả `99` và rollback;
13. mô phỏng DB failure giữa cập nhật nhiều order: transaction/order rollback,
    không dispatch;
14. stored response payload không có signature/secret;
15. Return pending/success/failed/invalid-signature/invalid-transaction redirect
    đúng và không ghi DB/dispatch;
16. controller không còn logic ký/xác minh/state cũ.

Không gọi VNPAY thật, không đọc `.env`, không truy cập production DB/service và
không ghi filesystem.

## 11. Cổng nghiệm thu do Codex chạy

```powershell
cd backend
php artisan test --filter=VnpayCallbackProcessingTest
php artisan test --filter=VnpayPaymentInitiationTest
php artisan test --filter=VnpayGatewayTest
php artisan test --filter=Phase2CheckoutSessionWritePathTest
php vendor/bin/pint --test app/Http/Controllers/Api/VnpayController.php app/Services/Payments/VnpayCallbackService.php tests/Feature/VnpayCallbackProcessingTest.php
php artisan test
cd ../frontend
npm.cmd run build
cd ..
git diff --check
git status --short
git diff --stat
```

Full backend suite và frontend build được chạy vì batch này đóng Giai đoạn 2C.
Không lặp thêm các gate nhỏ nếu targeted gate đã fail ở bước trước.

## 12. Điều kiện đóng 2C

- Create-payment dùng toàn checkout session và idempotent.
- Return hoàn toàn read-only.
- IPN là nguồn payment truth, verified và constant-time qua gateway.
- Một success cập nhật toàn session atomically.
- Duplicate callback không dispatch hoặc chuyển trạng thái lần hai.
- Không fulfillment online trước paid.
- Không secret/signature trong stored payload, log hoặc response lỗi.
- Targeted tests, full backend suite, frontend build, Pint và diff check pass.
- Không commit hoặc push.

## 13. Giới hạn đã biết chuyển sang 2D/2E

- `ProcessOrder` chưa có operation ledger/idempotency key chống queue redelivery.
- Inventory reservation/commit/release chưa được triển khai.
- Outbox, reconciliation, scheduler expiry và refund chưa được triển khai.
- Không coi batch này là bằng chứng production deployment; production smoke/rehearsal
  cần kế hoạch riêng.
