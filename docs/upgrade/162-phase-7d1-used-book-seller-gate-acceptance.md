# Nghiệm thu batch 7D.1 — Quản lý nhiều sách cũ

Ngày: 29/07/2026  
Trạng thái: **source và gate tự động đạt; browser error/draft state đạt, ready-data chờ runtime backend mới**

## 1. Kết quả triển khai

- Thêm API trung lập `/api/used-book-seller/listings` và inventory alias; endpoint `/api/author/used-books` cũ vẫn hoạt động.
- Ownership/FK chưa đổi: resolver chỉ dùng Author legacy đã duyệt, không tự tạo Author hay cấp capability mới; response ghi rõ seam `used_book_seller` cho Giai đoạn 8.
- Contract listing chỉ trả dữ liệu sách, ảnh thật, tình trạng, trạng thái và bốn chỉ số tồn; không trả `author_id`, `fulfillment_address_id` hoặc địa chỉ riêng tư.
- Trang `/used-books/manage` độc lập khỏi Author shell, có link trong tài khoản và redirect tương thích từ `/author/manage/used-books`.
- Hàng đợi hỗ trợ thêm/xóa nhiều dòng, ảnh preview, validation từng trường, gửi từng dòng tuần tự, giữ dòng lỗi và thông báo partial-success.
- Danh sách đã đăng dùng card responsive, hiển thị bìa/title/giá/tình trạng/trạng thái, tồn khả dụng/đã giữ/đã bán/đã trả và cập nhật tồn có phản hồi.

## 2. Gate

- Backend `Phase5UsedBookTest` + `Phase7UsedBookSellerTest`: 3 test, 26 assertion — đạt.
- Frontend 7D.1 + auth/proxy/fee regression: 4 file, 24 test — đạt.
- Pint targeted — đạt.
- ESLint targeted — đạt.
- Vite production build — đạt; chỉ còn cảnh báo chunk EbookReader lịch sử.
- `git diff --check` toàn checkout — đạt.

## 3. Browser smoke

- Quan sát trực tiếp `/used-books/manage` tại vùng nhìn 970 px: `document.scrollWidth` nhỏ hơn `innerWidth`, không tràn ngang.
- Form có đầy đủ label, nhóm field, ảnh, cam kết sách thật, nút 44 px, trạng thái loading/error/empty/draft.
- Đã thêm từ 1 lên 3 draft và xóa một draft trống về 2; không gửi listing hay thay đổi dữ liệu thật.
- Categories từ API 8080 cũ vẫn tải độc lập khi endpoint trung lập chưa tồn tại; trang hiển thị error/retry rõ ràng và vẫn giữ hàng đợi thao tác được.
- Ready/listing/inventory browser state chưa thể nghiệm thu trên 5173 vì origin 8080 đang là release ổn định cũ và theo yêu cầu không được thay đổi/deploy. Backend contract mới đã được chứng minh bằng feature test cô lập.
- Ma trận 375/768/1024/1440, keyboard đầy đủ và upload preview ảnh thật vẫn nằm trong phiên giám sát tiếp theo; không ghi dữ liệu thật trong smoke.

## 4. Bảo toàn

- Không migration/backfill, không đổi owner/FK, không database thật, không production, không Cloudflare, không credential hoặc dịch vụ ngoài.
- Không mở rộng Author Studio/copyright/royalty/promotion, không sửa Vendor warehouse.
- Không commit, push hoặc deploy.
