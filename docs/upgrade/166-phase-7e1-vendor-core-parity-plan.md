# Kế hoạch batch 7E.1 — Vendor dashboard, sách, đơn hàng và analytics

Ngày: 29/07/2026  
Tiền đề: Gate 7D tại tài liệu 165

## Audit và mục tiêu

- Analytics đang dùng `order_items.subtotal` không tồn tại; doanh thu đúng là snapshot `price * quantity` của item thuộc đơn hoàn tất.
- Danh sách đơn chỉ eager-load user, trong khi UI đọc `items[0].book.cover_image`; vì vậy bìa không thể xuất hiện ở list dù contract chi tiết đã đúng.
- Dashboard chỉ có ba KPI, phụ thuộc global scope ngầm và thiếu breakdown/recent activity để Vendor kiểm tra dữ liệu.
- Books/Orders đã responsive ở Phase 6 nhưng cần giữ empty/error state, bìa chuẩn hóa và tenant isolation sau khi sửa contract.

## Phạm vi source

- Sửa Dashboard/Analytics/Order controller để scope Vendor tường minh, dùng cột thật, trả status breakdown/recent orders và eager-load bìa.
- Nâng Dashboard/Analytics UI theo design system hiện có; bảng có vùng cuộn cục bộ, empty/loading/error rõ và không vẽ dữ liệu giả.
- Chỉ tinh chỉnh Books/Orders nơi contract hiện tại gây sai hoặc thao tác giả; không đổi workflow xuất bản, schema, inventory/finance/promotion.
- Thêm test tenant A/B cho dashboard, analytics, books và orders; xác nhận subtotal không còn được tham chiếu.

## Gate

- SQLite không lỗi cột; doanh thu item đúng `price * quantity` và không lẫn tenant.
- Order list/detail trả bìa chuẩn; Vendor không đọc được book/order của tenant khác.
- Dashboard/analytics có zero state đúng, responsive và keyboard-safe.
- PHPUnit targeted, Vitest, Pint, lint, build, browser smoke và `git diff --check` đạt.

## Prompt Antigravity dự phòng

> Triển khai batch 7E.1 theo tài liệu 166. Chỉ sửa Vendor dashboard, analytics, books, orders và test liên quan. Thay `order_items.subtotal` bằng phép tính từ snapshot `price * quantity`; scope mọi query bằng vendor hiện hành; eager-load orderItems.book để bìa theo PublicMediaUrl. Nâng UI bằng token/primitive hiện có, không thêm dữ liệu giả, không đổi schema, inventory, finance, promotion, Author legacy, Cloudflare hay production. Thêm negative tenant tests và chạy targeted gates. Không commit/push/deploy; báo cáo file thay đổi để Codex nghiệm thu độc lập.

Antigravity hết quota; Codex trực tiếp triển khai nhưng prompt vẫn được lưu theo quy trình dự án.

