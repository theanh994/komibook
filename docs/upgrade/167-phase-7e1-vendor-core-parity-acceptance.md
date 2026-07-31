# Nghiệm thu batch 7E.1 — Vendor core parity

Ngày: 29/07/2026  
Kết quả: **accepted-local**

## Đã hoàn thành

- Analytics dùng `SUM(order_items.price * order_items.quantity)`, không còn tham chiếu cột `subtotal` không tồn tại.
- Dashboard scope Vendor tường minh và bổ sung tổng đơn, đơn hoàn tất, sách nháp, breakdown trạng thái, đơn gần đây bằng dữ liệu thật.
- Danh sách đơn eager-load `orderItems.book`; bìa dùng cùng `PublicMediaUrl` với order detail/customer surfaces.
- Dashboard tương thích response cũ trên origin 8080; Analytics hiển thị lỗi thật thay vì dữ liệu giả khi origin cũ vẫn còn lỗi.
- Loại hai nút `In hóa đơn`/`Xử lý nhanh` chưa có hành vi khỏi danh sách đơn.
- Tenant A/B test bao phủ dashboard, analytics, books, orders và negative access.

## Gate

- Backend: 12 test, 70 assertion đạt.
- Frontend: 14 test đạt; lint và build đạt.
- Pint và `git diff --check`: đạt.
- Browser Vendor tại 970 px: dashboard, analytics error-state và orders không tràn ngang; hai thao tác giả không còn.
- Origin 8080 không deploy nên analytics vẫn phản ánh lỗi bản release cũ và bìa order list vẫn chờ backend mới; đây là giới hạn runtime, không phải lỗi source hiện tại.

Không commit, push, deploy; không đổi Cloudflare, schema hoặc dữ liệu thật.

