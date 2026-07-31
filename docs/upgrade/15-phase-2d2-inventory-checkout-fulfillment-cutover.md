# KomiBook Batch 2D.2 — Checkout and fulfillment inventory cutover

- **Ngày:** 2026-07-25
- **Trạng thái:** Approved continuation after Batch 2D.1 acceptance
- **Mục tiêu:** nối inventory reservation vào checkout và fulfillment mà không
  thay đổi payment failure/retry policy

## 1. Phạm vi source

### Được sửa

- `backend/app/Services/CheckoutService.php`
- `backend/app/Jobs/ProcessOrder.php`
- `backend/tests/Feature/Phase2CheckoutSessionWritePathTest.php`
- `backend/tests/Feature/VnpayPaymentInitiationTest.php`
- `backend/tests/Feature/VnpayCallbackProcessingTest.php`

### Được tạo

- `backend/tests/Feature/InventoryReservationCheckoutIntegrationTest.php`

Không sửa callback/payment service, controller, route, frontend, schema/migration,
model, enum, Redis config hoặc dependency.

## 2. Checkout cutover

1. Loại bỏ Redis decrement/rollback và DB fallback dựa trên `books.stock` khỏi
   `CheckoutService`.
2. Tạo checkout session, orders, order items và links trong một DB transaction.
3. Checkout session có `expires_at = now() + 15 minutes`.
4. Trước commit, gọi `InventoryReservationService::reserve()` cho toàn session:
   - expiry đúng bằng session expiry;
   - operation key ổn định `checkout-reserve:<checkout_code>`.
5. Physical stock không đủ phải rollback session, orders, items, links,
   reservations, allocations và coupon usage.
6. Ebook không tạo reservation và không yêu cầu warehouse stock.
7. COD được system-confirm tại checkout:
   - order chuyển `pending -> confirmed` trong transaction;
   - dispatch `ProcessOrder` chỉ sau commit.
8. Online order giữ `pending/unpaid`; không dispatch trước payment success.
9. Không đọc/ghi Redis trong luồng checkout mới.

## 3. Fulfillment cutover

`ProcessOrder`:

1. Resolve checkout link; legacy order không có checkout session phải fail closed,
   không trừ tồn theo đường cũ.
2. Trong transaction:
   - đọc target và xác định session;
   - khóa checkout session;
   - commit reservation của toàn session qua service;
   - khóa/reload order và kiểm tra trạng thái;
   - chỉ `confirmed -> processing`.
3. Xóa toàn bộ decrement trực tiếp trên `books.stock` và `warehouse_stocks`.
4. Ebook-only checkout được phép không có reservation.
5. Job retry khi order đã `processing` và reservation đã `committed` không trừ tồn
   lần hai và không tạo lại side effect trong phạm vi có thể kiểm soát ở batch này.
6. Nếu order không ở `confirmed`/`processing`, reservation thiếu/sai hoặc commit
   thất bại thì rollback order và inventory.
7. Giữ notification/mail hiện hữu; chuyển chúng sang outbox/ledger thuộc 2E.

## 4. Test bắt buộc

1. Physical checkout tạo reservation/allocation nhưng chưa trừ on-hand.
2. Multi-vendor checkout reserve toàn bộ session.
3. Ebook-only checkout không cần warehouse stock.
4. Không đủ stock rollback toàn bộ và không tăng coupon usage.
5. Checkout không gọi Redis.
6. Online checkout không dispatch và vẫn `pending`.
7. COD checkout chuyển `confirmed` và dispatch sau commit.
8. Online payment success + ProcessOrder commit inventory đúng một lần và chuyển
   từng order `confirmed -> processing`.
9. Multi-order jobs không trừ lại inventory của session.
10. ProcessOrder retry không double-decrement.
11. Legacy order hoặc invalid state fail closed.
12. Commit failure rollback cả inventory và order.
13. Cập nhật fixture ba suite Phase 2 hiện hữu để physical book có warehouse stock;
    không làm suy yếu assertion cũ.

## 5. Deferred sang 2D.3

- Release/expire reservation khi payment failed/expired hoặc order cancelled.
- Scheduler expiry.
- Quyết định retry payment trên checkout đã release hay yêu cầu checkout mới.
- Backfill/cutover dữ liệu production.

## 6. Cổng Codex

1. Targeted integration + checkout + callback/payment regressions.
2. Pint đúng phạm vi.
3. Full backend suite khi targeted đạt.
4. `git diff --check` và audit không còn Redis/direct stock decrement trong
   `CheckoutService`/`ProcessOrder`.

Không chạy migration production, không commit, không push.
