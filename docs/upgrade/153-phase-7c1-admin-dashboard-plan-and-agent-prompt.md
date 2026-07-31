# Giai đoạn 7C.1 — Kế hoạch Dashboard Admin dữ liệu thật và prompt Antigravity

Ngày: 29/07/2026  
Trạng thái ban đầu: in-progress

## Mục tiêu

- Mở rộng `/api/admin/stats` từ sáu tổng số thành contract điều hành có xu hướng 6 tháng, phân bổ và hàng đợi thực.
- Trình bày biểu đồ doanh thu/đơn, tăng trưởng tài khoản, phân bổ sách và trạng thái đơn bằng token KomiBook.
- Mỗi biểu đồ có nhãn, số liệu trực tiếp và bảng dữ liệu thay thế; không truyền nghĩa chỉ bằng màu.
- Hiển thị zero/error/loading trung thực và responsive từ mobile đến desktop.

## Phạm vi

- `Admin/DashboardController.php`: tổng hợp dữ liệu thật, portable SQLite/MySQL, không dùng fixture/mock.
- `admin/DashboardView.vue`: KPI, biểu đồ CSS/SVG nhẹ, legend/bảng accessible và hàng đợi vận hành.
- Feature test và Vitest chuyên biệt; tài liệu gate/ledger.

Không thêm thư viện chart, không đổi schema, không sửa dữ liệu, không gọi dịch vụ ngoài và không commit/push/deploy.

## Contract dự kiến

- KPI: users, authors, vendors, books, completed revenue, orders, pending orders.
- `commerce_trend`: nhãn 6 tháng, số đơn và doanh thu đơn completed.
- `account_growth`: user, author, vendor tạo mới theo 6 tháng.
- `book_distribution`: định dạng và trạng thái sách.
- `order_status_distribution`: số lượng theo trạng thái.
- `operational_queues`: đơn pending, hồ sơ Author/Vendor chờ duyệt, sách draft.

## Gate

- Feature test kiểm tra số liệu theo tháng/status/role và 403 ngoài Admin trên SQLite.
- Vitest kiểm tra contract UI, bảng thay thế, trạng thái rỗng/lỗi và không có mock/hardcode metric.
- ESLint/Oxlint/Pint, build và `git diff --check`.
- Visual/UAT Admin dashboard tại 375/768/1024/1440 ở Gate 7C.

## Prompt Antigravity dự phòng

> Bảo toàn dirty worktree. Batch 7C.1 chỉ sửa Admin Dashboard controller/view và test chuyên biệt. Mở rộng `/api/admin/stats` bằng dữ liệu DB thật: KPI, commerce trend 6 tháng (completed order count/revenue), account growth user/author/vendor, book type/status distribution, order status distribution và operational queues. Phải portable SQLite/MySQL, không `DATE_FORMAT`, không mock/fixture runtime, không schema/data write. UI dùng token KomiBook, responsive; chart CSS/SVG nhẹ có label/legend/value và bảng dữ liệu accessible, zero/loading/error trung thực; không cài chart library. Giữ role Admin và tenant/global-scope semantics hiện có. Chạy targeted PHPUnit/Vitest, lint/Pint/build/diff; báo changed-files. Không commit/push/deploy.
