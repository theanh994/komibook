# Kế hoạch triển khai KomiBook Batch 2B.2 — tích hợp checkout write-path

- **Ngày lập:** 2026-07-25 (Asia/Ho_Chi_Minh)
- **Repository:** `C:\Projects\DoAnTotNGhiep_komibook`
- **Trạng thái:** Approved for implementation — Codex tự lập và đối chiếu
- **Điều kiện đầu vào:** Batch 2B.1 đã được Codex nghiệm thu
- **Tài liệu căn cứ:**
  - `docs/upgrade/04-cross-account-continuation-handoff.md`
  - `docs/upgrade/05-phase-2a-order-payment-inventory-adr.md`
  - `docs/upgrade/06-phase-2b1-implementation-plan.md`

## 1. Mục tiêu duy nhất

Tích hợp các bảng 2B.1 vào write-path checkout mới:

1. mỗi lần checkout tạo đúng một `checkout_sessions`;
2. mỗi order theo vendor có đúng một `checkout_session_orders`;
3. session, orders, order items, snapshot và coupon usage được ghi trong cùng
   database transaction;
4. response hiện tại vẫn trả mảng orders để frontend không bị phá vỡ;
5. chưa tạo `payment_transactions`, chưa sửa VNPAY và chưa backfill order cũ.

## 2. Bằng chứng hiện trạng

- `CheckoutService::processCheckout()` hiện:
  - tải sách và kiểm tra ebook đã sở hữu;
  - giữ tồn tạm trên Redis hoặc kiểm tra DB fallback;
  - nhóm item theo vendor;
  - tính coupon discount theo vendor;
  - tính membership discount sau coupon;
  - tạo orders và order items trong một transaction;
  - tăng `coupons.used_count` trong transaction;
  - commit rồi mới dispatch `ProcessOrder`;
  - trả về mảng orders.
- `CheckoutController` đặt mảng này tại `response.data`.
- `CartView.vue` đang dùng `res.data[0]`, nên 2B.2 không được đổi response thành
  object/session envelope.
- Commission hiện được đọc từ
  `storage/app/private/system_config.json`, mặc định 10%, và model `Order` tính
  vendor earning trên `order.total_amount`.
- `service_fee` có trong system config nhưng checkout hiện không cộng vào tổng
  người mua. Batch 2B.2 không được âm thầm bắt đầu thu phí.

## 3. Phạm vi file

### Được sửa/tạo

- Sửa `backend/app/Services/CheckoutService.php`.
- Thêm `backend/tests/Feature/Phase2CheckoutSessionWritePathTest.php`.
- Chỉ khi targeted test chứng minh cần thiết, được sửa test checkout hiện hữu để
  cập nhật assertion tương thích; không viết lại test ngoài phạm vi.

### Không được sửa

- Migration/schema/model/enum đã nghiệm thu trong 2B.1.
- `CheckoutController.php` và response contract.
- `VnpayController.php`.
- `ProcessOrder.php`, `OrderService.php`.
- Inventory/warehouse/Redis implementation.
- `Order.php` model event và logic điểm/ledger; các mục này thuộc 2E.
- Frontend, routes, request validation.
- Production config, `.env`, dependency files.
- Tài liệu upgrade hiện hữu.

## 4. Quyết định snapshot tài chính

Mọi giá trị tiền được tính thành integer VND trước khi ghi.

### 4.1. Theo từng vendor/order

```text
subtotal_amount
  = tổng item.price_snapshot × item.quantity

coupon_discount
  = phân bổ coupon hiện có cho vendor, làm tròn integer như logic hiện tại

membership_discount
  = round((subtotal_amount - coupon_discount) × membership_rate / 100)

discount_amount
  = min(subtotal_amount, coupon_discount + membership_discount)

fee_amount
  = 0

total_amount
  = max(0, subtotal_amount - discount_amount + fee_amount)

commission_rate
  = system_config.commission_rate hợp lệ; mặc định 10; chặn trong [0, 100]

commission_amount
  = round(total_amount × commission_rate / 100)
```

`commission_amount` là phần nền tảng dự kiến giữ lại; nó không được cộng thêm
vào số tiền người mua trả.

`service_fee` chưa được checkout áp dụng nên snapshot `fee_amount` phải là 0.
Việc bắt đầu thu service fee là thay đổi pricing riêng, không thuộc 2B.2.

### 4.2. Checkout session

```text
session.subtotal_amount = sum(order snapshots.subtotal_amount)
session.discount_amount = sum(order snapshots.discount_amount)
session.fee_amount       = sum(order snapshots.fee_amount)
session.total_amount     = sum(order snapshots.total_amount)
session.currency         = VND
```

Bất biến:

```text
session.total_amount
  = session.subtotal_amount
  - session.discount_amount
  + session.fee_amount
```

## 5. Nguồn commission

Trong phạm vi nhỏ này, `CheckoutService` dùng một private method chỉ đọc:

1. mặc định `commission_rate = 10`;
2. nếu `storage/app/private/system_config.json` tồn tại và JSON hợp lệ, lấy
   `commission_rate`;
3. ép numeric và chặn trong khoảng 0–100;
4. không log nội dung file;
5. không đọc/ghi `.env`;
6. không thay đổi config hay tạo file config.

Việc chuyển system config sang database/service chuyên trách thuộc Giai đoạn 5.

## 6. Luồng triển khai

### 6.1. Chuẩn bị snapshot trước transaction

Sau khi nhóm item, xác thực coupon và tải membership:

1. tính một cấu trúc snapshot cho từng vendor;
2. tính tổng session từ các snapshot;
3. không ghi DB ở bước này;
4. không thay đổi logic Redis hiện tại trong 2B.2.

### 6.2. Ghi trong một DB transaction

Trong transaction hiện có:

1. tạo `CheckoutSession`;
2. với từng vendor:
   - tạo `Order` với `total_amount` đúng snapshot;
   - tạo `OrderItem`;
   - tạo `CheckoutSessionOrder` với toàn bộ snapshot;
3. tăng `coupon.used_count` nếu coupon hợp lệ;
4. commit.

Nếu bất kỳ bước nào lỗi:

- rollback session, orders, order items, links và coupon usage;
- tiếp tục dùng cơ chế hoàn Redis hiện hữu;
- không dispatch `ProcessOrder`.

Chỉ dispatch jobs sau commit như hiện tại.

## 7. Tương thích và dữ liệu lịch sử

- `processCheckout()` tiếp tục trả `array<Order>`.
- `CheckoutController` và JSON response không đổi.
- Không eager-load hoặc chèn checkout session vào response trong 2B.2.
- Order lịch sử không có `checkoutSessionOrder` là hợp lệ.
- Không backfill lịch sử:
  - không thể tái dựng đáng tin cậy checkout đa vendor từ order riêng lẻ;
  - không có đầy đủ snapshot coupon/membership/commission cũ;
  - không tạo dữ liệu tài chính suy đoán.
- Không tạo payment transaction. 2C sẽ tạo payment attempt cho checkout session.

## 8. Kiểm thử

Thêm `Phase2CheckoutSessionWritePathTest` dùng `RefreshDatabase` và cô lập Redis
bằng mock/fallback có kiểm soát.

Các case:

1. Checkout một vendor:
   - một session;
   - một order link;
   - snapshot và relationship đúng;
   - response vẫn là mảng orders.
2. Checkout ba vendor:
   - một session;
   - ba orders và ba links;
   - tổng session bằng tổng snapshots/orders.
3. Coupon cộng membership:
   - coupon discount và membership discount được cộng vào
     `discount_amount`;
   - membership áp dụng trên số tiền sau coupon;
   - số tiền là integer.
4. Commission:
   - default 10%;
   - config hợp lệ được snapshot;
   - config thiếu/JSON lỗi/ngoài khoảng dùng fallback hoặc clamp an toàn;
   - không cộng commission vào buyer total.
5. Transaction rollback:
   - gây lỗi khi tạo link thứ hai;
   - không còn session/order/order item/link/coupon increment;
   - không dispatch job.
6. Legacy compatibility:
   - order không có link vẫn đọc được;
   - relationship trả `null`.
7. Không có `payment_transactions` được tạo trong 2B.2.

Không lặp lại toàn bộ schema constraint tests của 2B.1.

## 9. Cổng nghiệm thu

### Targeted

```powershell
cd C:\Projects\DoAnTotNGhiep_komibook\backend
php artisan test --filter=Phase2CheckoutSessionWritePathTest
php vendor/bin/pint --test app/Services/CheckoutService.php tests/Feature/Phase2CheckoutSessionWritePathTest.php
```

### Đóng toàn bộ Batch 2B

Sau khi targeted gate pass, Codex chạy full backend suite đúng một lần:

```powershell
php artisan test
```

Sau đó:

```powershell
cd ..
git diff --check
git status --short
git diff --stat
```

Không chạy frontend build/lint trong 2B vì không có frontend diff.

## 10. Rollback

Code rollback:

- bỏ phần tạo session/snapshot/link khỏi `CheckoutService`;
- giữ schema additive 2B.1 vì không ảnh hưởng runtime cũ;
- response contract không đổi nên frontend không cần rollback.

Dữ liệu đã tạo bởi 2B.2 là lịch sử tài chính hợp lệ và không được xóa tự động
khi rollback code. Nếu cần làm sạch dữ liệu thử nghiệm, chỉ thực hiện trên test
database. Không có cleanup production trong batch này.

## 11. Điều kiện hoàn thành

- Targeted tests pass.
- Full backend suite pass một lần để đóng 2B.
- Pint đúng phạm vi pass.
- `git diff --check` sạch.
- Không có frontend/VNPAY/inventory/production diff.
- Không commit hoặc push.
- Codex đọc diff/mã và tự chạy lại các cổng trước khi nghiệm thu.

