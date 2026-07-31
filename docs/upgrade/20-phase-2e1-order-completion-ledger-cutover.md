# KomiBook Batch 2E.1 — Order completion and ledger cutover

- **Ngày:** 2026-07-25
- **Trạng thái:** Approved continuation after Batch 2D.3 acceptance
- **Mục tiêu:** loại bỏ side effect tài chính/điểm khỏi model và controller, hoàn tất
  order qua service transaction-safe với ledger idempotent.

## 1. Phạm vi

### Được tạo

- `backend/database/migrations/2026_07_25_130000_create_order_completion_ledgers.php`
- `backend/app/Models/OrderTransitionOperation.php`
- `backend/app/Models/LoyaltyPointLedger.php`
- `backend/app/Models/VendorEarningLedger.php`
- `backend/app/Services/OrderFulfillmentService.php`
- `backend/tests/Feature/OrderCompletionLedgerTest.php`

### Được sửa

- `backend/app/Models/Order.php`
- `backend/app/Models/User.php`
- `backend/app/Models/Vendor.php`
- `backend/app/Http/Controllers/Api/OrderController.php`
- `backend/app/Http/Controllers/Api/Vendor/OrderController.php`
- `backend/app/Http/Requests/Vendor/UpdateOrderStatusRequest.php`
- `backend/tests/Feature/AuthorDrmInventoryTest.php`

Không sửa payment/VNPAY, checkout, inventory reservation, `ProcessOrder`, route,
frontend, dependency hoặc production config.

## 2. Schema và ledger

Migration tạo ba bảng:

1. `order_transition_operations`
   - order, operation key unique, actor type/id nullable;
   - transition kind (`order` hoặc `shipping`);
   - from/to state, metadata đã lọc, occurred_at, timestamps.
2. `loyalty_point_ledgers`
   - user, order, operation key unique;
   - loại `order_completed`, số điểm integer dương;
   - unique một earning completion cho mỗi order.
3. `vendor_earning_ledgers`
   - vendor, order, operation key unique;
   - gross, commission, net và currency nguyên VND;
   - unique một earning completion cho mỗi order.

Foreign key dùng restrict. `down()` xóa theo thứ tự ngược. Không lưu secret hoặc
request payload thô.

## 3. Completion invariant

`OrderFulfillmentService` là đường duy nhất trong batch này hoàn tất giao hàng:

1. khóa order, checkout-session-order snapshot, vendor và user;
2. order phải ở `shipped`, shipping transition hợp lệ tới `delivered`;
3. online phải đã `paid`;
4. COD `delivered` được xem là xác nhận đã thu tiền và chuyển payment projection sang
   `paid` trong cùng transaction;
5. gross/commission/net đọc từ snapshot `checkout_session_orders`, không đọc config
   hiện tại và không dùng float;
6. `net = total_amount - commission_amount`, không âm;
7. điểm bằng `floor(order snapshot total / 10000)`;
8. tạo transition operation, point ledger và earning ledger trước khi cập nhật các
   projection;
9. vendor balance và user points chỉ tăng khi insert ledger mới thành công;
10. membership tier được tính lại như projection sau khi point ledger ghi thành công;
11. retry cùng operation key hoặc retry khi order đã completed không tăng lần hai;
12. operation key trùng nhưng payload/order/transition khác phải fail-closed;
13. bất kỳ lỗi nào rollback order, shipping, payment projection, ledgers, points,
   membership tier và vendor balance.

## 4. Shipping và vendor transition

Service kiểm soát:

- vendor: `processing -> shipped`; khi đó shipping bắt đầu ở `pending_pickup`;
- shipping: `pending_pickup -> picked_up -> delivering -> delivered`;
- `pending_pickup|picked_up|delivering -> failed` không tự cancel order, hoàn tiền
  hoặc hoàn kho.

Vendor không được trực tiếp đặt `completed` hoặc `cancelled`. Bulk update phải gọi
policy/service cho từng order theo ID ổn định, không mass update và không bỏ qua order
không hợp lệ.

Operation key ổn định theo order và target transition; controller không tự ghi status,
points, tier, payment hoặc balance.

## 5. Cutover

1. Xóa `Order::updating` side effect tự đặt paid và cộng vendor balance.
2. `OrderController::updateShippingStatus` chỉ validate/authorize rồi gọi service.
3. Xóa logic cộng điểm/tier và gán order status trực tiếp khỏi controller.
4. Vendor controller và request chỉ cho transition thuộc graph được hỗ trợ.
5. `ProcessOrder` vẫn giữ phạm vi hiện tại; cutover notification/email/job operation
   sang outbox thuộc Batch 2E.2.

## 6. Cổng nghiệm thu

Test tối thiểu:

1. physical COD và online hoàn tất đúng ledger/projection;
2. snapshot commission được dùng dù config hiện tại thay đổi;
3. retry operation/job/request 2–5 lần không cộng lặp;
4. conflicting operation key fail-closed;
5. invalid order/shipping/payment/actor transition fail-closed;
6. multi-vendor completion tạo ledger độc lập đúng vendor;
7. ebook-only completion không yêu cầu shipping;
8. shipping failed không cancel/restock/refund;
9. bulk transition áp policy từng order và không partial write;
10. rollback injection không để partial ledger/projection;
11. migration SQLite up/down isolated;
12. model save trực tiếp sang completed không còn tự cộng balance/points.

Codex sẽ chạy targeted tests, Pint, diff-check, isolated migration, full backend và
frontend build ở cổng đóng. Không chạy migration production, không commit, không push.
