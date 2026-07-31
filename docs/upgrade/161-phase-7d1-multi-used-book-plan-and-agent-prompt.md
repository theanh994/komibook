# Kế hoạch batch 7D.1 — Quản lý nhiều sách cũ, tách bề mặt khỏi Tác giả

Ngày: 29/07/2026  
Checkout: `C:\Projects\DoAnTotNGhiep_komibook`  
Kiến trúc chi phối: `docs/upgrade/158-author-to-warehouse-manager-architecture-change.md`

## 1. Kết quả audit

- `used_book_listings` vẫn sở hữu bởi `author_id` và địa chỉ `author_fulfillment_addresses`; Giai đoạn 7 không được migrate owner.
- API hiện chỉ tạo từng listing và UI chỉ có một form đơn, không có hàng đợi nhiều sách.
- `index()` trả listing thô, chưa có contract trung lập đầy đủ cho title, cover, giá và trạng thái sách.
- Privacy, attestation, return và counterfeit dispute đã có test nền; phải được giữ nguyên.
- Bề mặt hiện nằm trong `AuthorCommerceView` và navigation “Sách cũ & Kho”, làm tăng phụ thuộc nhận thức vào vai trò Author sắp retire.

## 2. Mục tiêu

- Tạo trang quản lý độc lập mang ngôn ngữ **Người bán sách cũ**, không trình bày như Author Studio hoặc kho Vendor.
- Cho phép chuẩn bị nhiều dòng sách trong một hàng đợi; mỗi dòng có title, tác giả gốc, danh mục, giá, tình trạng, khuyết điểm, số lượng, ảnh thật và cam kết hàng thật.
- Gửi từng dòng qua endpoint đơn hiện có hoặc compatibility endpoint trung lập; lỗi theo từng dòng, không xóa draft hợp lệ.
- Danh sách đã đăng hiển thị dữ liệu sách, trạng thái, tồn khả dụng/đã giữ/đã bán/đã trả và cập nhật tồn theo ownership hiện hành.
- Chuẩn bị seam cho capability `used_book_seller` của Giai đoạn 8 nhưng không migrate dữ liệu thật trong Giai đoạn 7.

## 3. Phạm vi dự kiến

Được sửa/tạo:

- `backend/app/Http/Controllers/Api/AuthorUsedBookController.php` hoặc compatibility controller trung lập mới.
- `backend/app/Models/UsedBookListing.php`.
- `backend/app/Http/Resources/UsedBookListingResource.php` nếu cần contract ổn định.
- `backend/routes/api.php` cho alias trung lập có bảo vệ tương đương; endpoint Author cũ vẫn hoạt động.
- `backend/tests/Feature/Phase5UsedBookTest.php` và test 7D.1 mới.
- `frontend/src/views/author/AuthorCommerceView.vue` chỉ để giữ redirect/compatibility, không mở rộng Author.
- Trang mới dưới `frontend/src/views/account/` hoặc `frontend/src/views/used-books/`.
- `frontend/src/router/index.js`, navigation tài khoản và test liên quan.

Không được sửa migration, owner/FK, database thật, fulfillment privacy, return/refund ledger, dispute evidence, Author Studio/copyright/royalty/promotion, Vendor warehouse hoặc production.

## 4. Thiết kế tương thích

- API/adapter trung lập phải tách tên bề mặt khỏi `Author`, nhưng resolver Giai đoạn 7 có thể ánh xạ user hiện tại tới Author legacy đã được duyệt.
- Không tự tạo Author mới và không cấp capability mới ngầm.
- Nếu tài khoản chưa có legacy eligibility, trả trạng thái rõ ràng rằng chuyển đổi `used_book_seller` chưa hoàn tất; không chuyển hướng sang đăng ký Author mới.
- Không bulk transaction toàn bộ hàng đợi. UI gửi từng dòng để một lỗi không rollback các dòng khác và lưu draft lỗi tại client.
- Endpoint cũ `/api/author/used-books` được giữ để tương thích cho tới Giai đoạn 8.

## 5. UI/UX

- Dùng master `design-system/komibook/MASTER.md` và `ui-ux-pro-max`.
- Desktop: danh sách/hàng đợi tách rõ; mobile: mỗi dòng thành card, không dùng bảng ép chữ.
- Label hiển thị, lỗi ngay dưới dòng, ảnh preview có alt, điều khiển tối thiểu 44 px.
- Có loading, empty, error, partial-success và retry từng dòng.
- Không dùng từ “kho Tác giả”; dùng “Sách cũ của tôi”, “Địa chỉ gửi hàng riêng tư”, “Người bán sách cũ”.

## 6. Acceptance gate

- Backend: owner isolation, verified private address, listing contract, inventory update, privacy và counterfeit regression.
- Frontend: tạo/xóa draft row, giữ draft lỗi, partial success, responsive card/list và navigation trung lập.
- Browser: 375/768/1024/1440 px, keyboard, upload preview, empty/error/ready; không gửi dữ liệu thật khi chỉ smoke.
- Không thêm phụ thuộc mới vào Author Studio/copyright/royalty/promotion.
- Build, targeted tests và `git diff --check` đạt.

## 7. Prompt dự phòng cho Antigravity

> Triển khai đúng batch 7D.1 theo tài liệu 161 và kiến trúc 158. Tạo bề mặt “Người bán sách cũ” độc lập, hỗ trợ hàng đợi nhiều sách với lỗi từng dòng và giữ draft; giữ endpoint Author cũ tương thích và dùng resolver legacy tạm thời, không tạo/migrate Author hoặc owner. Giữ tuyệt đối privacy địa chỉ, ảnh thật, tình trạng, attestation, return và counterfeit dispute. Chỉ sửa file được liệt kê, không migration/database thật/Author Studio/Vendor warehouse/production, không commit/push/deploy. Báo cáo file đổi và gate để Codex nghiệm thu độc lập.

Antigravity hiện hết quota; Codex được người dùng giao trực tiếp triển khai nhưng vẫn giữ prompt theo quy trình dự án.
