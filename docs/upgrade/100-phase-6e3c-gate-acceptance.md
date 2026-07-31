# Giai đoạn 6E.3c — nghiệm thu Tủ sách, Trình đọc và Ghi chú

Ngày nghiệm thu cục bộ: 2026-07-29

## Kết luận

**Đạt Gate 6E.3c cục bộ về contract và responsive.**

Không mở/in ebook thật, không tạo/xóa ghi chú, không commit, push hoặc deploy.

## Kết quả

- Tủ sách có loading/error/retry/empty/filter-empty; filter dùng pressed
  semantics; cover 2:3 `object-contain` không bo ảnh.
- Action trên entitlement card luôn dùng được ở touch và chỉ ẩn theo hover trên
  thiết bị có con trỏ chính xác; target đạt 44px.
- Tủ sách hiển thị phiên bản đã mua và mới nhất từ API; không có version picker
  để mua.
- Reader render danh sách phiên bản được backend cấp, lọc phòng vệ từ
  `purchase_version_id` trở đi và từ chối ID ngoài danh sách trước khi gọi API.
  Mỗi lần đổi bản vẫn gọi `generate-link` để backend tái xác thực.
- Reader và Ghi chú bỏ phụ thuộc Google Fonts runtime, dùng font serif hệ thống;
  reduced motion được tôn trọng.
- Ghi chú có label tìm kiếm, loading/error/retry/empty/search-empty và action
  luôn thấy trên touch/focus.

## Bằng chứng

- Browser `/my-library`, `/annotations`,
  `/reader/999999999/999999999`: `375 / 768 / 1024 / 1440` không overflow và
  không có visible control dưới 44px.
- Local library/annotations rỗng được kiểm tra trực tiếp; reader access-denied
  được kiểm tra bằng order/book ID không tồn tại.
- `Phase5EbookRightsTest`: `3` tests, `25` assertions đạt, gồm việc chỉ trả
  purchase version và các bản mới hơn, từ chối bản cũ hơn.
- Frontend: `11` files, `68` tests đạt; production build đạt.
- `git diff --check`: đạt.

## Theo dõi hiệu năng

Chunk `EbookReaderView` vẫn khoảng `2.53 MB` minified (`~830 KB` gzip). Đây là
debt hiệu năng đáng kể do PDF reader stack và phải được xử lý ở 6J/Gate 6 bằng
code splitting hoặc tải động; không làm sai entitlement/version contract.

