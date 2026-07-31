# Nghiệm thu batch 7F.2 — Quality, performance, CI và E2E

Ngày: 29/07/2026  
Kết quả: **accepted-local with declared baselines**

## Đã hoàn thành

- Trang kho gom stock cho toàn bộ book IDs của trang bằng một truy vấn, không còn N+1 theo số sách.
- Bổ sung index additive cho filter sách theo tenant/status/type/stock, reverse lookup warehouse stock và timeline kiểm kê/điều chuyển.
- Middleware boundary được kiểm tra cho Vendor (`auth:sanctum`, `role:vendor`, `active-vendor`) và Admin (`auth:sanctum`, `role:admin`); tenant-negative suite tiếp tục đạt.
- `vue-pdf-embed` chuyển sang async component phía sau `pdfUrl`; chunk route `EbookReaderView` giảm từ 2.533,48 KB xuống 51,46 KB (14,61 KB gzip).
- Thư viện PDF còn 2.482,20 KB nhưng trở thành chunk tải theo nhu cầu khi mở tài liệu, không nằm trong tải route reader ban đầu.

## Gate tự động

- Target backend performance/vendor: 5 test, 67 assertion đạt.
- Full backend: **377 test, 1.961 assertion đạt**.
- Full frontend: **24 file, 102 test đạt**.
- Frontend lint và production build đạt.
- Migration index `up` và `down` đạt trên SQLite tạm biệt lập; file tạm đã được xóa.
- Pint toàn bộ file source/test thuộc 7E/7F đạt; `git diff --check` đạt.

Full-repository Pint vẫn liệt kê 48 file lịch sử ngoài phạm vi batch chưa đúng style. Không chạy auto-fix diện rộng để tránh ghi đè thay đổi cục bộ; đây là baseline định dạng, không phải lỗi runtime/test.

## Browser E2E chỉ đọc

- Guest: Home, Catalog, trang chuyển đổi Author và guard `/used-books/manage` đúng; Home vẫn có các card ebook/sách vật lý cùng tim/xem nhanh/giỏ/mua ngay.
- Customer: Orders, Tủ sách, Wishlist và quản lý nhiều sách cũ đúng route, không tràn ngang.
- Vendor: dashboard, analytics, orders, finance, Flash Sale, warehouses, inventory audits/transfers đúng route, không tràn ngang.
- Admin: dashboard, finance report, reconciliation, system config/fees và notification campaigns đúng route, không tràn ngang.
- Browser tích hợp không hỗ trợ ép viewport; kiểm tra trực tiếp dùng vùng nhìn hiện tại 970 px. Contract responsive/CSS và các gate tự động trước đó vẫn đạt, nhưng không suy diễn thành UAT thủ công đủ 375/768/1024/1440.

## Baseline được giữ trung thực

- Origin 8080 là release cũ chưa deploy nên recommendation và finance report vẫn có thể trả lỗi trên browser 5173; UI hiển thị error-state thật. Source hiện tại đã qua regression tương ứng.
- Logo PNG 6,14 MB cần một batch asset/visual riêng; không thay binary ở 7F.2.
- Không đổi Cloudflare/origin, database thật, credential, dependency; không commit, push hoặc deploy.

