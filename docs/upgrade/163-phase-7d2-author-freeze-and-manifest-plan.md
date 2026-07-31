# Kế hoạch batch 7D.2 — Đóng entry point Author và manifest chuyển đổi

Ngày: 29/07/2026  
Kiến trúc chi phối: tài liệu 158  
Tiền đề: 7D.1 tại tài liệu 162

## 1. Kết quả audit

- Entry point Author mới vẫn còn ở Header, UserSidebar, Footer, trang `/for-authors`, route đăng ký/xác minh và API onboarding.
- Kênh Author legacy vẫn liên kết Studio, chapter, copyright, royalty và publishing; các deep link này cần giữ cho record hiện hữu cho tới khi manifest và regression chứng minh truy cập lịch sử an toàn.
- `authors` còn được tham chiếu bởi fulfillment address, commerce profile, book author/delegation, copyright, royalty và used-book listing; không được rename/drop/migrate owner trong 7D.2.
- Ebook entitlement thuộc user/book/version, không được làm mất quyền đọc khi đóng entry point Author.
- 7D.1 đã tách bề mặt sách cũ sang `/used-books/manage`; owner vẫn là Author legacy có kiểm soát.

## 2. Phạm vi triển khai

- Thêm cơ chế freeze có thông báo rõ: không tạo hồ sơ Author mới; hồ sơ legacy hiện hữu vẫn đọc/hoàn tất thao tác bảo toàn được duyệt.
- Loại CTA đăng ký Author khỏi navigation công khai; `/for-authors` chuyển thành trang thông báo sản phẩm đã dừng và hướng người dùng sang Người bán sách cũ hoặc đăng ký Nhà bán phù hợp.
- Giữ route/deep link Author legacy cho tài khoản đã có dữ liệu; không xóa controller/model/table.
- Tạo manifest read-only/dry-run thống kê theo record: user, author, book-author, book, used-book listing, fulfillment address, entitlement, copyright, royalty/agreement và xung đột ownership.
- Phân loại `keep_active`, `archive_read_only`, `map_used_book_seller`, `conflict_review`; không ghi thay đổi dữ liệu.

## 3. File dự kiến

- Backend: `AuthorController.php`, service/command manifest mới, test freeze/manifest; route chỉ khi cần endpoint/command rõ ràng.
- Frontend: `AppHeader.vue`, `AppFooter.vue`, `UserSidebar.vue`, `PublicInfoView.vue`, router và test hợp đồng.
- Docs: acceptance 7D.2 và Gate 7D.

Không sửa migration/schema/FK, ebook reader/entitlement semantics, order/finance ledger, Vendor warehouse, production, Cloudflare hoặc dữ liệu thật.

## 4. Gate

- Người dùng mới không tạo được Author và nhận thông báo/hướng đi thay thế rõ ràng.
- Author legacy vẫn truy cập được ebook đã mua, sách đã xuất bản và lịch sử copyright/royalty theo quyền hiện hành.
- Manifest chạy read-only trên SQLite fixture, có classification và conflict rows, không phát sinh insert/update/delete.
- Public navigation không còn CTA đăng ký Author; deep link legacy có kiểm soát và không vòng redirect.
- Targeted backend/frontend tests, Pint/ESLint, build, browser smoke và `git diff --check` đạt.

## 5. Prompt Antigravity dự phòng

> Triển khai đúng batch 7D.2 theo tài liệu 163 và kiến trúc 158. Freeze việc tạo Author mới, loại CTA onboarding công khai, nhưng giữ deep link/data history cho Author legacy. Tạo manifest read-only/dry-run phân loại keep_active, archive_read_only, map_used_book_seller và conflict_review cho toàn bộ liên kết Author/book/listing/entitlement/copyright/royalty; tuyệt đối không ghi dữ liệu, migration, đổi owner/FK hoặc drop bảng. Không sửa Vendor warehouse, production, Cloudflare, credential; không commit/push/deploy. Chạy gate và báo cáo file thay đổi để Codex nghiệm thu độc lập.

Antigravity hết quota; Codex được giao trực tiếp triển khai nhưng prompt vẫn được lưu theo quy trình dự án.
