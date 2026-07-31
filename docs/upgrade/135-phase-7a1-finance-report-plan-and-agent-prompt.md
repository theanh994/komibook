# Giai đoạn 7A.1 — Kế hoạch báo cáo tài chính và prompt Antigravity

Ngày: 29/07/2026  
Trạng thái ban đầu: in-progress

## Mục tiêu

- Loại lỗi `no such function: DATE_FORMAT` khi chạy SQLite mà vẫn giữ tương thích MySQL.
- Trả chuỗi doanh thu đủ 12 tháng theo thứ tự tăng dần, có tháng 0 để giao diện/chart không phải tự suy diễn.
- Giữ contract KPI, phương thức thanh toán, top Vendor và payout; làm rõ “đã duyệt lũy kế” gồm approved, processing và completed.

## Phạm vi file

Được phép:

- `backend/app/Http/Controllers/Api/Admin/FinanceReportController.php`
- `backend/tests/Feature/Phase7FinanceReportTest.php`
- tài liệu batch/gate 7A.1 trong `docs/upgrade/`

Cấm trong batch này:

- sửa schema/migration/seeder hoặc dữ liệu đang có;
- sửa dashboard/chart thuộc 7C.1;
- commit, push, deploy hoặc dùng database thật.

## Invariants

- Chỉ đơn `completed` đóng góp doanh thu.
- `total_revenue` là toàn thời gian; `monthly_revenue` là tháng hiện tại.
- Chuỗi chart chỉ có 12 tháng gần nhất, theo timezone ứng dụng, mỗi phần tử dùng số nguyên.
- Admin route và global-scope bypass hiện tại không thay đổi.
- Không trả dữ liệu ngân hàng nhạy cảm trong report.

## Gate

1. Feature test trên SQLite in-memory: auth, KPI, đủ 12 tháng, zero month, payment method, top Vendor và payout.
2. Chạy test payout/finance liên quan.
3. PHP syntax/Pint target và `git diff --check`.
4. API/browser smoke trang `/admin/finance-report` nếu local server khả dụng.

## Prompt giao Antigravity dự phòng

> Làm việc duy nhất trong `C:\Projects\DoAnTotNGhiep_komibook`. Bảo toàn dirty worktree; không reset/checkout/stash/clean, commit hoặc push. Mục tiêu: sửa `FinanceReportController` để aggregation tháng hoạt động trên SQLite và MySQL, trả đúng 12 tháng có zero fill, và thêm feature test contract. Chỉ được sửa controller, test `Phase7FinanceReportTest.php` và báo cáo changed-files. Không sửa frontend, migration, seeder hay dữ liệu. Giữ các invariant và chạy toàn bộ gate ghi trong tài liệu này. Nếu phát hiện cần mở rộng phạm vi, dừng và báo Codex.
