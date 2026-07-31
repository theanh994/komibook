# Kế hoạch batch 7E.2 — Inventory, finance, promotion và fee Vendor

Ngày: 29/07/2026  
Tiền đề: 7E.1 tại tài liệu 167

## Audit

- WarehouseController còn ràng buộc theo `user->author`, giới hạn một kho và lấy địa chỉ Author; điều này trái kiến trúc đóng Author và nghiệp vụ nhiều kho của Nhà bán.
- Inventory audit/stock transfer dùng thuộc tính `stock` trong khi schema `warehouse_stocks` dùng `quantity`; store chưa kiểm tra warehouse/book cùng tenant trước khi tạo phiếu.
- Finance dựa trên payout/hold thật nhưng chưa giải thích commission/service fee cho Vendor.
- Flash Sale kiểm tra từng sách trong vòng lặp nên request nhiều sách có thể ghi một phần trước khi gặp sách sai tenant; bìa proposal chưa chuẩn hóa cùng contract media.

## Phạm vi

- Bỏ coupling Author khỏi thao tác tạo/chuyển kho của Vendor; vẫn giữ dữ liệu FK legacy hiện có, không migration.
- Chuẩn hóa `quantity`, kiểm tra tenant warehouse/book trước mọi phiếu kiểm kê/điều chuyển và giới hạn tổng tồn theo kho cùng Vendor.
- Thêm fee policy/preview read-only vào finance response bằng CommerceFeeService; UI giải thích người mua trả, Nhà bán nhận, commission và service fee.
- Làm đăng ký nhiều sách Flash Sale atomic và tenant-safe; giữ quy trình chờ Admin duyệt.
- Không tạo quyền warehouse_manager, không đổi schema/owner, không ghi dữ liệu thật.

## Gate

- Negative tenant tests cho warehouse, audit, transfer, finance, Flash Sale.
- Không còn tham chiếu `warehouse_stocks.stock`; flash registration không partial-write.
- Payout ledger regression và promotion integrity đạt.
- Frontend tests/lint/build, Pint, browser smoke và diff check đạt.

## Prompt Antigravity dự phòng

> Triển khai batch 7E.2 theo tài liệu 168. Gỡ coupling Author khỏi Vendor warehouse; sửa mọi nghiệp vụ warehouse_stocks sang cột quantity; xác thực warehouse/book cùng vendor trước khi tạo/hoàn tất audit hoặc transfer. Bổ sung fee policy/preview read-only cho Vendor Finance bằng CommerceFeeService. Làm Flash Sale multi-book atomic, tenant-safe và chuẩn hóa bìa; giữ chờ Admin duyệt. Không schema/migration, không tạo warehouse_manager, không sửa Author legacy/Cloudflare/production, không dùng dữ liệu giả, không commit/push/deploy. Thêm negative tests và báo cáo file để Codex nghiệm thu.

Antigravity hết quota; Codex trực tiếp triển khai, prompt vẫn được lưu.

