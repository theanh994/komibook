# KomiBook Phase 5A.1 — Warehouse isolation và private fulfillment address

Ngày: 2026-07-29

## Mục tiêu

- Mọi truy vấn kho/tồn kho phải bị giới hạn theo đúng vendor đang đăng nhập.
- Author Commerce chỉ có một địa chỉ fulfillment active đã được admin xác minh.
- Kho của tác giả chỉ tham chiếu địa chỉ đã đăng ký, không nhận địa chỉ tùy ý.
- Địa chỉ/điện thoại không xuất hiện trong public/customer payload.

## Phạm vi source

- Migration/model/resource/controller cho `author_fulfillment_addresses`.
- Quan hệ Author/Warehouse và khóa tham chiếu nullable để tương thích dữ liệu cũ.
- API owner/admin để lưu, xem và xác minh địa chỉ.
- Harden `WarehouseController` cho index, create và adjust/transfer.
- Feature tests tenant isolation, ownership và privacy.

## Prompt giao Antigravity

> Thực hiện Phase 5A.1 theo ADR 62. Chỉ sửa source/migration/test trong phạm vi warehouse isolation và private author fulfillment address. Không đổi role Author thành Vendor, không lộ địa chỉ/điện thoại trong public/customer payload, không sửa lịch sử Git, không commit/push/deploy và không chạy database thật. Bảo toàn dữ liệu legacy; mọi warehouse/book/source/target phải thuộc actor. Viết focused tests và báo cáo diff/gate.

Antigravity hiện hết quota; người dùng đã giao Codex trực tiếp thực hiện. Prompt được lưu để đảm bảo truy vết quy trình.

## Gate

- PHPUnit focused cho Phase 5A.1 và các inventory tests liên quan.
- `php artisan route:list` xác nhận route owner/admin.
- `git diff --check`.
