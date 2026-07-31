# KomiBook Batch 2D.3 — Checkout termination and reservation release

- **Ngày:** 2026-07-25
- **Trạng thái:** Approved continuation after Batch 2D.2 acceptance
- **Mục tiêu:** hoàn thiện vòng đời checkout online bị hủy hoặc hết hạn, bảo đảm
  payment, order và inventory kết thúc atomically và retry-safe.

## 1. Quyết định chính sách

1. Một VNPAY attempt `failed` là thất bại của riêng attempt, không phải kết thúc
   checkout. Buyer được tạo attempt mới trong thời hạn checkout; reservation vẫn
   giữ nguyên.
2. Checkout chỉ bị kết thúc khi:
   - buyer hủy rõ ràng; hoặc
   - `checkout_sessions.expires_at <= now()` và checkout vẫn chưa thanh toán.
3. Hủy một order thuộc checkout nhiều vendor là hủy toàn bộ checkout session.
4. Hủy chủ động chuyển reservation `reserved -> released`.
5. Hết hạn chuyển reservation `reserved -> expired`.
6. Pending payment transaction của checkout kết thúc được chuyển `expired`.
   Transaction `failed` giữ nguyên lịch sử; không hồi sinh transaction terminal.
7. Toàn bộ order phải còn `pending/unpaid` và dùng `online` hoặc `vnpay`.
   Checkout có order paid/confirmed/processing/shipped/completed, COD, reservation
   committed hoặc dữ liệu legacy/incomplete phải fail-closed và không cập nhật một
   phần.
8. Callback thành công đến sau hạn không được xác nhận payment, order hoặc dispatch
   fulfillment.

Quyết định (1) làm rõ ADR: “payment failure giải phóng reservation” áp dụng khi
checkout/payment lifecycle đã kết thúc, không áp dụng cho một attempt còn được phép
retry trong cùng checkout.

## 2. Phạm vi source

### Được tạo

- `backend/app/Services/CheckoutSessionLifecycleService.php`
- `backend/app/Console/Commands/ExpireCheckoutSessionsCommand.php`
- `backend/tests/Feature/CheckoutSessionLifecycleTest.php`

### Được sửa

- `backend/app/Services/Payments/VnpayPaymentService.php`
- `backend/app/Services/Payments/VnpayCallbackService.php`
- `backend/app/Http/Controllers/Api/OrderController.php`
- `backend/routes/api.php`
- `backend/routes/console.php`
- `backend/tests/Feature/VnpayPaymentInitiationTest.php`
- `backend/tests/Feature/VnpayCallbackProcessingTest.php`

Không sửa migration/schema, enum, model, `InventoryReservationService`, checkout
write path, `ProcessOrder`, vendor/shipping controller, frontend, dependency hoặc
production config.

## 3. Lifecycle service

Service chuyên trách phải cung cấp hai operation:

- buyer cancellation theo order ID và actor ID;
- system expiry theo checkout session ID/thời điểm hiện tại.

Mỗi operation chạy trong transaction và khóa theo thứ tự ổn định:

1. checkout session;
2. checkout-session-order links và orders theo ID tăng dần;
3. payment transactions theo ID tăng dần;
4. inventory reservations qua semantic method hiện có.

Service phải:

- xác minh ownership và toàn vẹn mọi link/order;
- kiểm tra toàn bộ precondition trước khi ghi;
- hủy toàn bộ pending/unpaid orders trong session;
- chuyển pending transactions sang `expired`;
- gọi `releaseSession()` khi buyer cancel hoặc `expireSession()` khi hết hạn;
- không dispatch fulfillment;
- idempotent với retry cùng kết quả đã hoàn thành;
- rollback toàn bộ nếu bất kỳ bước nào lỗi.

## 4. Entry points

1. API authenticated `POST /orders/{order}/cancel` gọi lifecycle service; controller
   không tự ghi trạng thái. Order không thuộc buyer trả 403/404 theo convention hiện
   có; trạng thái không hợp lệ trả 422.
2. Command `checkout-sessions:expire` quét ID session đến hạn theo batch ổn định,
   gọi lifecycle service từng session và có kết quả đếm rõ ràng.
3. Scheduler chạy command mỗi phút và `withoutOverlapping`.
4. Payment initiation gặp session hết hạn phải hoàn tất expiry trước khi trả 422,
   không tạo attempt mới.
5. VNPAY success IPN đến sau expiry phải kết thúc session nếu còn đủ điều kiện,
   trả response terminal tương thích, không ghi paid/confirmed và không dispatch.
6. VNPAY failure attempt trước deadline chỉ ghi transaction `failed`; reservation và
   order vẫn giữ để retry.

## 5. Cổng nghiệm thu

Test phải bao phủ tối thiểu:

1. buyer cancel single/multi-vendor session;
2. ownership, COD, paid/confirmed, legacy/incomplete fail-closed;
3. cancel lặp idempotent và không đổi on-hand;
4. scheduler expiry chuyển đúng order/payment/reservation;
5. ebook-only session không có reservation vẫn kết thúc đúng;
6. command retry không thay đổi dữ liệu lần hai;
7. payment attempt failed vẫn retry được trước deadline;
8. initiation sau deadline không tạo transaction;
9. late success IPN không hồi sinh hoặc dispatch;
10. rollback injection không để partial state.

Codex sẽ chạy targeted tests, Pint, `git diff --check`, full backend regression và
frontend build gộp khi cần. Không chạy migration production, không commit, không push.
