# KomiBook Batch 2C.3 correction — Bổ sung Order `confirmed`

- **Ngày duyệt:** 2026-07-25 (Asia/Ho_Chi_Minh)
- **Trạng thái:** Approved by user
- **Lý do:** Callback success cần `pending -> confirmed`, nhưng enum
  `orders.status` hiện chưa chứa `confirmed`
- **Tính chất:** source-only; không chạy migration trên production

## 1. Phạm vi source

### Được tạo

- `backend/database/migrations/2026_07_25_110000_add_confirmed_to_orders_status.php`

### Được sửa

- `backend/app/Services/Payments/VnpayCallbackService.php`
- `backend/tests/Feature/VnpayCallbackProcessingTest.php`

Không sửa controller, route, frontend, model, gateway, payment-initiation
service, CheckoutService, ProcessOrder, migration cũ, config hoặc dependency.

## 2. Migration contract

`up()` thay enum `orders.status` thành:

`pending`, `confirmed`, `processing`, `shipped`, `completed`, `cancelled`.

Giữ default `pending`, nullable/index/thuộc tính hiện có và không sửa dữ liệu.
Implementation phải chạy được với MySQL production và SQLite test bằng Laravel
schema builder; không dùng SQL chỉ dành cho một database nếu không có nhánh
driver an toàn.

`down()`:

1. chuyển các row `confirmed` về `processing`, là trạng thái tương đương gần
   nhất của runtime cũ;
2. thu enum về danh sách ban đầu;
3. không ảnh hưởng các trạng thái khác.

Không chạy migration thật trong lúc triển khai.

## 3. Callback corrections

Trong `handleIpn()`:

- duplicate failure chỉ idempotent khi provider transaction identity tương
  thích:
  - cả stored và incoming đều không có ID; hoặc
  - cả hai là cùng một chuỗi không rỗng;
- stored ID null nhưng incoming có ID khác không được tự động coi là duplicate
  hợp lệ;
- giữ toàn bộ lock order, response mapping và atomicity hiện tại.

Trong `handleReturn()`:

- nếu transaction đã lưu provider transaction ID, incoming ID phải là cùng một
  chuỗi không rỗng ở mọi local state;
- terminal transaction có stored ID null chỉ tương thích với incoming ID null;
- identity mismatch trả `invalid_transaction`;
- pending chưa có stored identity vẫn được hiển thị `pending` sau callback đã
  verify;
- không ghi database hoặc dispatch.

## 4. Test correction

Bổ sung hoặc điều chỉnh trong test hiện hữu:

1. success IPN lưu được order status `confirmed`;
2. multi-vendor success đều `confirmed`;
3. duplicate failure: null/null là idempotent, null/non-null và ID khác nhau trả
   `02`;
4. Return paid/failed có identity mismatch trả `invalid_transaction`;
5. Return pending vẫn read-only;
6. migration được `RefreshDatabase` áp dụng và không phá các trạng thái cũ.

## 5. Cổng Codex

Sau khi nhận source:

1. chạy targeted callback suite và Pint;
2. chạy migration up/down trên database SQLite cô lập;
3. chạy regression gateway/payment-initiation/checkout;
4. nếu đạt, chạy full backend suite và frontend build;
5. chạy diff check và xác nhận không có file ngoài phạm vi.

Không chạy migration production, không commit hoặc push.
