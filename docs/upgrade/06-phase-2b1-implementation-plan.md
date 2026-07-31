# Kế hoạch triển khai KomiBook Batch 2B.1 — Additive Schema cho Checkout Session và Payment Transaction

- **Ngày lập:** 2026-07-25 (Asia/Ho_Chi_Minh)
- **Repository:** `C:\Projects\DoAnTotNGhiep_komibook`
- **Trạng thái:** Chờ Codex duyệt lại kế hoạch — chưa triển khai mã nguồn/migration/model/test trước khi được phê duyệt.
- **Tài liệu căn cứ:** `docs/upgrade/04-cross-account-continuation-handoff.md` và `docs/upgrade/05-phase-2a-order-payment-inventory-adr.md`

---

## 1. Mục tiêu duy nhất và Quy tắc bắt buộc

### 1.1. Mục tiêu
Tạo schema nền **additive** cho checkout session và payment transaction theo ADR 0001 (Phase 2A) đã duyệt. Không thực hiện backfill, không cutover và không thay đổi hành vi runtime hiện tại.

### 1.2. Quy tắc áp dụng
1. **Chưa triển khai mã nguồn:** Chưa được triển khai migration, model, enum hay test trước khi Codex duyệt lại kế hoạch này.
2. **Không sửa/xóa tài liệu untracked:** Giữ nguyên hai tài liệu untracked `docs/upgrade/04-cross-account-continuation-handoff.md` và `docs/upgrade/05-phase-2a-order-payment-inventory-adr.md`.
3. **Phạm vi file cho phép:**
   - Thêm migration mới dưới `backend/database/migrations/`.
   - Thêm `backend/app/Models/CheckoutSession.php`.
   - Thêm `backend/app/Models/CheckoutSessionOrder.php`.
   - Thêm `backend/app/Models/PaymentTransaction.php`.
   - Thêm `backend/app/Enums/PaymentTransactionStatus.php`.
   - Thêm `backend/tests/Feature/Phase2CheckoutPaymentSchemaTest.php`.
   - Chỉ được sửa `backend/app/Models/User.php` và `backend/app/Models/Order.php` để bổ sung relationship.
4. **Các file/thư mục tuyệt đối không sửa:**
   - `CheckoutService.php`
   - `OrderService.php`
   - `ProcessOrder.php`
   - `VnpayController.php`
   - Controller / Request / Route khác
   - Migration cũ
   - Frontend
   - Production / Config / `.env`
   - Composer / package files
   - Bảng `orders` hiện hữu (không đổi enum/cột hiện có)
5. **Dữ liệu tài chính:**
   - Dùng số nguyên (`unsignedBigInteger` trong DB, cast `integer` trong Model) cho số tiền VND; không dùng float.
   - Mọi khóa ngoại của dữ liệu tài chính phải dùng `restrictOnDelete`, không cascade xóa lịch sử.
6. **Không commit / push / thay đổi Git nguy hiểm:**
   - Không commit, push, reset, checkout, stash, clean hoặc xóa dữ liệu.
   - Không cài dependency mới.
   - Không chạy migration trên database production hoặc development hiện hữu.
7. **Phạm vi kiểm thử và linting:**
   - Batch 2B.1 chỉ chạy `Phase2CheckoutPaymentSchemaTest` và Pint trên các file thuộc phạm vi.
   - Full backend suite được chạy một lần khi đóng toàn bộ Batch 2B (hoặc tại cổng Giai đoạn 2 thích hợp), không chạy trong Batch 2B.1 trừ khi targeted test cho thấy regression ngoài phạm vi.

---

## 2. Chi tiết Thiết kế Schema & Model

### 2.1. Bảng `checkout_sessions`
- `id` (bigIncrements / id)
- `checkout_code`: UUID, unique
- `user_id`: FK tới `users`, `restrictOnDelete`
- `currency`: string(3), mặc định `'VND'`
- `subtotal_amount`: `unsignedBigInteger`
- `discount_amount`: `unsignedBigInteger`, mặc định 0
- `fee_amount`: `unsignedBigInteger`, mặc định 0
- `total_amount`: `unsignedBigInteger`
- `expires_at`: nullable timestamp
- `timestamps`
- Index: `(user_id, created_at)` và `expires_at`
- *Lưu ý:* Không thêm cột `status` (ADR chưa duyệt state machine riêng cho checkout session).

### 2.2. Bảng `checkout_session_orders`
- `id`
- `checkout_session_id`: FK tới `checkout_sessions`, `restrictOnDelete`
- `order_id`: FK tới `orders`, `restrictOnDelete`, UNIQUE (một order chỉ thuộc một checkout session)
- `subtotal_amount`: `unsignedBigInteger`
- `discount_amount`: `unsignedBigInteger`, mặc định 0
- `fee_amount`: `unsignedBigInteger`, mặc định 0
- `commission_rate`: `decimal(5,2)`, mặc định 0
- `commission_amount`: `unsignedBigInteger`, mặc định 0
- `total_amount`: `unsignedBigInteger`
- `timestamps`
- *Lưu ý:* Đây là snapshot phân bổ tiền theo từng order/vendor tại thời điểm checkout, không đọc lại giá/commission hiện hành để thay đổi snapshot về sau.

### 2.3. Bảng `payment_transactions`
- `id`
- `checkout_session_id`: FK tới `checkout_sessions`, `restrictOnDelete`
- `provider`: string max 32
- `provider_reference`: string max 128
- `provider_transaction_id`: nullable string max 128
- `idempotency_key`: string max 128
- `amount`: `unsignedBigInteger`
- `currency`: string max 3, mặc định `'VND'`
- `status`: string max 32, mặc định `'pending'`
- `request_payload`: nullable JSON
- `response_payload`: nullable JSON
- `provider_occurred_at`: nullable timestamp
- `paid_at`: nullable timestamp
- `failed_at`: nullable timestamp
- `expires_at`: nullable timestamp
- `refund_started_at`: nullable timestamp
- `refunded_at`: nullable timestamp
- `timestamps`
- Ràng buộc unique:
  - `(provider, provider_reference)`
  - `(provider, idempotency_key)`
  - `(provider, provider_transaction_id)`
- Index:
  - `(checkout_session_id, status)`
  - `status`
  - `expires_at`
- *Lưu ý:* Payload chỉ chứa dữ liệu đã lọc; không chứa secret, hash secret, credential hoặc dữ liệu thẻ.

### 2.4. Migration Rollback `down()`
- Xóa bảng theo đúng thứ tự ngược phụ thuộc khóa ngoại:
  1. `payment_transactions`
  2. `checkout_session_orders`
  3. `checkout_sessions`
- Không sửa hay xóa dữ liệu bảng cũ.

---

## 3. Backend Enum & Models

### 3.1. Enum `PaymentTransactionStatus`
`backend/app/Enums/PaymentTransactionStatus.php`:
- `pending = 'pending'`
- `paid = 'paid'`
- `failed = 'failed'`
- `expired = 'expired'`
- `refunding = 'refunding'`
- `refunded = 'refunded'`

### 3.2. Models & Relationships
- **`CheckoutSession`** (`backend/app/Models/CheckoutSession.php`):
  - Fillable/casts khai báo rõ ràng.
  - Cast toàn bộ amount thành `integer`, `expires_at` thành `datetime`.
  - Tự sinh UUID cho `checkout_code` khi chưa được cung cấp (`static::creating`), nhưng vẫn cho phép truyền mã xác định từ bên ngoài.
  - Relationships: `user()` (belongsTo), `checkoutSessionOrders()` (hasMany), `paymentTransactions()` (hasMany).
  - Không đặt side effect trong model event.
- **`CheckoutSessionOrder`** (`backend/app/Models/CheckoutSessionOrder.php`):
  - Fillable/casts khai báo rõ ràng.
  - Cast amount thành `integer`, `commission_rate` thành `decimal:2`.
  - Relationships: `checkoutSession()` (belongsTo), `order()` (belongsTo).
- **`PaymentTransaction`** (`backend/app/Models/PaymentTransaction.php`):
  - Fillable/casts khai báo rõ ràng.
  - Cast `amount` thành `integer`, `status` sang `PaymentTransactionStatus`, payload thành `array`, timestamps thành `datetime`.
  - Relationships: `checkoutSession()` (belongsTo).
- **`User`** (`backend/app/Models/User.php`):
  - Thêm relationship `checkoutSessions()` (hasMany).
- **`Order`** (`backend/app/Models/Order.php`):
  - Thêm relationship `checkoutSessionOrder()` (hasOne).

---

## 4. Kiểm thử (Feature Tests)

File `backend/tests/Feature/Phase2CheckoutPaymentSchemaTest.php`:
Dùng `RefreshDatabase` với SQLite in-memory.
Các test case tối thiểu:
1. Tạo checkout session, liên kết order, snapshot tiền, payment transaction, kiểm tra relationships và casts.
2. Test unique constraints (dạng data provider hoặc gộp assertion gọn):
   - Một order không thuộc 2 checkout sessions;
   - `provider_reference` không trùng trong cùng `provider`;
   - `idempotency_key` không trùng trong cùng `provider`;
   - `provider_transaction_id` không trùng trong cùng `provider`.
3. Kiểm tra foreign-key `restrict` bảo vệ lịch sử tài chính khi xóa parent record.
4. Kiểm tra UUID tự sinh cho `checkout_code` và `status` cast đúng enum `PaymentTransactionStatus`.

---

## 5. Cổng kiểm tra Nghiệm thu Batch 2B.1

Sau khi mã nguồn được triển khai (sau phê duyệt), lệnh nghiệm thu bắt buộc:

```powershell
cd C:\Projects\DoAnTotNGhiep_komibook\backend
php artisan test --filter=Phase2CheckoutPaymentSchemaTest
php vendor/bin/pint --test app/Enums/PaymentTransactionStatus.php app/Models/CheckoutSession.php app/Models/CheckoutSessionOrder.php app/Models/PaymentTransaction.php app/Models/User.php app/Models/Order.php tests/Feature/Phase2CheckoutPaymentSchemaTest.php database/migrations/2026_07_25_100000_create_phase2_checkout_and_payment_tables.php
cd ..
git diff --check
git status --short
git diff --stat
```

---

## 6. Ghi chú & Trạng thái Thực thi
- **Không tự tuyên bố Batch 2B hoàn tất** — chờ Codex audit độc lập.
- **Trạng thái hiện tại:** Đã lập file kế hoạch `docs/upgrade/06-phase-2b1-implementation-plan.md` và khôi phục `docs/upgrade/implementation_plan.md`. Chưa chỉnh sửa mã nguồn backend/database/tests.
