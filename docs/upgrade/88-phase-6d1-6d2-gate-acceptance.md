# Nghiệm thu cục bộ Giai đoạn 6D.1–6D.2

Ngày nghiệm thu: 2026-07-29

## 6D.1 — Bộ lọc Danh mục

- Backend hỗ trợ kết hợp `target_age`, effective price, định dạng và nguồn gốc sách cũ.
- Sáu khoảng giá cố định thay thế nhập số tự do.
- Trạng thái category/search/type/provenance/age/price/sort/page được lưu trong URL.
- Deep link và back/forward khôi phục đúng trạng thái.
- Drawer mobile có backdrop, Escape, `aria-hidden` và `inert` khi đóng.
- Nút mở và đóng drawer có vùng chạm 44px.
- Browser kiểm tra trực tiếp tại 375/768/1024/1440: không overflow.
- `Phase6PublicNavigationTest`: 3 test, 10 assertion đạt.

## 6D.2 — Thẻ sách và bìa sách dùng chung

- Home và Catalog cùng dùng `BookCard`.
- Ảnh bìa computed `object-fit: contain`, `border-radius: 0px`, tỷ lệ vùng ảnh 2:3.
- Mỗi thẻ còn đủ `Xem nhanh`, `Thêm vào giỏ`, `Mua ngay`, mỗi nút 44x44.
- Ở 375px các tiện ích luôn hiện; với thiết bị có chuột từ 640px trở lên, tiện ích ẩn mặc định và hiện khi hover/focus-within.
- Browser xác nhận focus bàn phím làm nhóm tiện ích hiện và quick view mở đúng sách.
- Catalog giữ nút yêu thích; ebook ghi phiên bản mới nhất, không có lựa chọn phiên bản để mua.
- Browser kiểm tra Home tại 375/768/1024/1440: không overflow.

## Gate tự động

- Frontend: 11 file test, 67 test đạt.
- Frontend production build: đạt.
- `git diff --check`: đạt.

Đây là nghiệm thu cục bộ; không phải commit, push, deploy hay nghiệm thu production.
