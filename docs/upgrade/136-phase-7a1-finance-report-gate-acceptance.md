# Giai đoạn 7A.1 — Nghiệm thu báo cáo tài chính

Ngày: 29/07/2026  
Kết quả: accepted-local

## Thay đổi được nghiệm thu

- Aggregation tháng chọn biểu thức phù hợp SQLite/MySQL/PostgreSQL, không còn gọi `DATE_FORMAT` trên SQLite.
- Chuỗi `revenue_by_month` luôn có đủ 12 tháng, tăng dần và zero-fill.
- Sửa điều kiện `orders.status` bị mơ hồ sau join Vendor.
- Payout “đã duyệt lũy kế” gồm approved, processing và completed.
- Không thay migration, seeder hoặc dữ liệu hiện hữu.

## Bằng chứng gate

- `Phase7FinanceReportTest`: 2 passed, 22 assertions.
- `Phase4PayoutCampaignOperationsTest`: 7 passed, 28 assertions.
- Pint target: passed.
- PHP syntax: passed.
- `git diff --check`: passed.
- Browser local đi đúng route nhưng phiên trình duyệt kiểm thử chưa đăng nhập nên chuyển về Login. API authenticated feature test là bằng chứng runtime của batch; visual Admin sẽ được kiểm lại cùng phiên đăng nhập ở Gate 7A/7C.

## Lỗi được phát hiện trong nghiệm thu

Lần chạy đầu tiên tìm thấy `status` mơ hồ trong truy vấn join `orders`/`vendors`; đã giới hạn thành `orders.status` và chạy lại gate xanh.

Kết quả này là nghiệm thu cục bộ, không ngụ ý đã commit, push, deploy hoặc nghiệm thu production.
