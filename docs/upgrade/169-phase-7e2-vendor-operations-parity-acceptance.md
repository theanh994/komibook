# Nghiệm thu batch 7E.2 — Vận hành kho, tài chính và khuyến mãi Nhà bán

Ngày: 29/07/2026  
Kết quả: **accepted-local**

## Đã hoàn thành

- Kho Nhà bán không còn phụ thuộc hồ sơ/địa chỉ Author và cho phép một Nhà bán quản lý nhiều kho bằng địa chỉ đã khai báo riêng.
- Kiểm kê và điều chuyển dùng đúng cột `warehouse_stocks.quantity`; mọi warehouse/book đầu vào đều được xác thực theo tenant trước khi tạo chứng từ hoặc cập nhật tồn.
- Khi hoàn tất kiểm kê/nhận điều chuyển, tồn tổng của sách chỉ cộng các kho thuộc đúng Nhà bán.
- Tài chính trả về lịch phí hiệu lực, ví dụ tính phí có nguồn gốc từ `CommerceFeeService` và giải thích rõ commission khác service fee; giao diện cũ không tự đoán tỷ lệ.
- Đăng ký Flash Sale nhiều sách được kiểm tra toàn bộ trước và ghi trong một transaction, không còn nguy cơ tạo một phần đề xuất rồi thất bại ở sách sau.
- Bìa sách trong danh sách đăng ký Flash Sale dùng chuẩn URL media chung.
- Giao diện tài chính và Flash Sale có error-state trung thực, không thay bằng số liệu giả.

## Gate

- Backend: 38 test, 193 assertion đạt, gồm hồi quy Author/warehouse cũ, payout, promotion và tenant-negative mới.
- Frontend: 14 test đạt; lint và build đạt.
- Pint và `git diff --check`: đạt.
- Browser Nhà bán tại 970 px: `/vendor/finance`, `/vendor/flash-sales`, `/vendor/warehouses`, `/vendor/inventory/audits`, `/vendor/inventory/transfers` không tràn ngang.
- Origin 8080 chưa deploy backend mới: trang tài chính chủ động báo chưa có preview phí thay vì suy đoán; dữ liệu payout hiện có vẫn hiển thị đúng. Không có thao tác ghi dữ liệu trong browser smoke.

Không commit, push, deploy; không đổi Cloudflare, schema hoặc dữ liệu thật.

