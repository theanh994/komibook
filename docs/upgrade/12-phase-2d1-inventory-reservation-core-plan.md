# KomiBook Batch 2D.1 — Inventory reservation core

- **Ngày:** 2026-07-25
- **Trạng thái:** Approved continuation after Batch 2C acceptance
- **Mục tiêu:** tạo nền schema, model và domain service hoàn chỉnh cho reservation
  và allocation tồn kho vật lý; chưa cut over checkout/payment/job
- **Tính chất:** source-only; không chạy migration trên production

## 1. Ranh giới batch

Batch này tạo một lõi reservation có thể được kiểm thử độc lập. Luồng hiện hành
`CheckoutService`, VNPAY callback và `ProcessOrder` chưa gọi service mới; việc cutover
được thực hiện trong Batch 2D.2 sau khi 2D.1 được nghiệm thu.

Không sửa controller, route, frontend, payment service, callback service,
`CheckoutService`, `ProcessOrder`, Redis, dependency hoặc migration hiện hữu.

## 2. File source

### Được tạo

- `backend/app/Enums/InventoryReservationStatus.php`
- `backend/app/Models/InventoryReservation.php`
- `backend/app/Models/InventoryReservationAllocation.php`
- `backend/app/Services/Inventory/InventoryReservationService.php`
- `backend/database/migrations/2026_07_25_120000_create_inventory_reservations.php`
- `backend/tests/Feature/InventoryReservationServiceTest.php`

### Được sửa

- `backend/app/Models/CheckoutSession.php`
- `backend/app/Models/OrderItem.php`
- `backend/app/Models/Book.php`
- `backend/app/Models/WarehouseStock.php`

Chỉ được bổ sung relationship/cast/helper cần thiết cho reservation; không đổi
global scope, fillable hoặc hành vi ngoài phạm vi.

## 3. Schema contract

### `inventory_reservations`

- khóa chính;
- `checkout_session_id` FK restrict;
- `order_item_id` FK restrict và unique;
- `book_id` FK restrict;
- `quantity` unsigned integer, lớn hơn 0 ở application boundary;
- `status`: `reserved`, `committed`, `released`, `expired`;
- `operation_key` string unique;
- `expires_at`, `committed_at`, `released_at`, `expired_at` nullable;
- timestamps;
- index phục vụ truy vấn `(book_id, status, expires_at)` và
  `(checkout_session_id, status)`.

### `inventory_reservation_allocations`

- khóa chính;
- `inventory_reservation_id` FK cascade;
- `warehouse_stock_id` FK restrict;
- `quantity` unsigned integer, lớn hơn 0 ở application boundary;
- timestamps;
- unique `(inventory_reservation_id, warehouse_stock_id)`;
- index theo `warehouse_stock_id`.

Migration `down()` xóa allocations trước reservations. Migration phải chạy được
trên MySQL và SQLite test. Không backfill và không sửa dữ liệu production trong batch.

## 4. Service contract

Service chỉ xử lý sách `physical`; ebook không tạo reservation.

### Reserve

- API nhận checkout session đã có order links/items và một expiry xác định.
- Transaction và lock order:
  1. checkout session;
  2. order items theo ID;
  3. warehouse stocks theo `(book_id, warehouse_id, id)`;
  4. reservation/allocation.
- Chỉ book `published` và vendor `active` được reserve.
- Available-to-sell của từng warehouse stock bằng `quantity` trừ tổng allocation
  thuộc reservation `reserved` chưa hết hạn.
- Allocation theo `warehouse_id`, rồi `id` tăng dần; có thể chia một item qua nhiều
  warehouse.
- Không đủ tồn cho bất kỳ item nào thì rollback toàn bộ checkout reservation.
- Cùng `operation_key` với cùng dữ liệu trả lại kết quả hiện hữu, không cấp phát lần
  hai. Cùng key nhưng payload/session khác phải fail closed.
- Không sửa `warehouse_stocks.quantity` hoặc `books.stock` khi reserve.

### Commit

- Chỉ `reserved -> committed`.
- Khóa reservation/allocation/warehouse stocks theo thứ tự ổn định.
- Trừ đúng quantity allocation khỏi từng `warehouse_stocks.quantity`; không cho âm.
- Cập nhật `books.stock` như projection bằng tổng `warehouse_stocks.quantity` của
  book trong cùng transaction.
- Retry cùng operation không trừ lần hai; trạng thái terminal khác fail closed.

### Release và expire

- `reserved -> released` hoặc `reserved -> expired`.
- Không thay đổi on-hand vì reserve chưa trừ on-hand.
- Retry cùng operation không tạo side effect lần hai.
- Không cho chuyển từ `committed`, `released` hoặc `expired` sang trạng thái khác.

Service không dispatch job, gửi mail/notification hoặc truy cập Redis.

## 5. Test bắt buộc

1. Ebook không tạo reservation/allocation.
2. Một physical item reserve ở một warehouse.
3. Một item được chia allocation qua nhiều warehouse theo thứ tự ổn định.
4. Nhiều vendor/item tạo đúng reservation thuộc cùng checkout.
5. Available-to-sell trừ các reservation đang hiệu lực.
6. Reservation đã hết hạn không làm giảm available-to-sell.
7. Không đủ tồn rollback toàn bộ, không để bản ghi một phần.
8. Duplicate reserve cùng operation key idempotent; payload xung đột fail closed.
9. Commit trừ warehouse on-hand đúng một lần và đồng bộ `books.stock`.
10. Commit thiếu on-hand hoặc dữ liệu allocation sai rollback toàn bộ.
11. Release/expire không trừ on-hand và là idempotent.
12. Terminal transition bất hợp lệ fail closed.
13. Unique/FK/cast/relationship và migration rollback được bao phủ phù hợp.

## 6. Cổng nghiệm thu Codex

1. Kiểm tra đúng phạm vi file và `git diff --check`.
2. Chạy `InventoryReservationServiceTest` và Pint cho các file 2D.1.
3. Chạy migration `up/down` trên SQLite cô lập.
4. Chạy regression Phase 2B/2C.
5. Chỉ chạy full backend suite nếu các cổng trên đạt.

Không chạy migration production, không commit, không push.
