# Batch 2D.1 correction — Inventory reservation identity and integrity

- **Ngày:** 2026-07-25
- **Trạng thái:** Required before acceptance
- **Phạm vi:** sửa service/test và định dạng các model 2D.1; không mở rộng sang 2D.2

## Bằng chứng hiện tại

- Targeted suite đạt `13 tests / 55 assertions` sau khi Codex sửa dữ liệu fixture
  còn thiếu trong đúng một file test.
- Pint chưa đạt ở service, `Book`, `OrderItem` và `WarehouseStock`.
- Full regression chưa chạy vì audit hợp đồng phát hiện các khoảng trống bên dưới.

## Correction bắt buộc

### 1. Operation identity

- Không dùng `LIKE "$operationKey%"`; key `reserve:1` không được khớp
  `reserve:10`.
- Chuẩn hóa cách tạo key cho một và nhiều item sao cho có thể truy vấn chính xác
  toàn bộ reservation thuộc một operation mà không có prefix collision.
- Retry reserve phải so sánh ít nhất session, tập item, book, quantity và expiry.
  Payload khác phải fail closed.
- Operation key rỗng hoặc vượt giới hạn lưu trữ phải fail trước khi ghi DB.

### 2. Target resolution

- Không dùng một scalar số vừa là reservation ID vừa là checkout-session ID.
- API phải có semantics không mơ hồ; ưu tiên model object hoặc các method riêng
  theo reservation/session/operation.
- Lookup theo operation key phải exact và không kéo reservation của operation khác.

### 3. Commit integrity

Trước khi trừ on-hand:

- mỗi reservation phải có allocation;
- tổng allocation phải đúng bằng reservation quantity;
- mọi allocation phải dương;
- warehouse stock của allocation phải thuộc đúng `book_id` của reservation;
- không có allocation ngoài tập reservation đang commit;
- mọi sai lệch phải rollback toàn bộ và giữ trạng thái `reserved`.

Giữ commit idempotent và không trừ lần hai.

### 4. Operation parameter

Không để tham số `$operationKey` không được sử dụng trong `commit`, `release`,
`expire`. Hoặc bỏ tham số khỏi public API và dựa hoàn toàn vào target/state, hoặc
thực thi identity check rõ ràng và nhất quán. Test phải khóa contract đã chọn.

### 5. Test và format

Bổ sung test cho:

- prefix collision;
- retry cùng key nhưng quantity/expiry khác;
- target ID collision;
- allocation thiếu, sai tổng và sai book;
- contract operation của commit/release/expire.

Chạy định dạng trên toàn bộ file 2D.1 đã được phép, không thay đổi hành vi ngoài
correction.

Không sửa migration/schema, checkout, payment, callback, job, controller,
frontend hoặc dependency. Không commit hoặc push.
