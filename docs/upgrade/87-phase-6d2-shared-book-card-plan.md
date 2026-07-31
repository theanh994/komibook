# Giai đoạn 6D.2 — Thẻ sách và ảnh bìa dùng chung

## Mục tiêu

- Trang chủ và Danh mục dùng cùng một thẻ sách.
- Ảnh bìa giữ đúng tỷ lệ, dùng `object-contain`, không bị cắt, kéo giãn hoặc bo góc trực tiếp.
- Giữ ba tiện ích `Xem nhanh`, `Thêm vào giỏ`, `Mua ngay`: hiện trên thiết bị cảm ứng, ẩn đến khi hover/focus trên màn hình có con trỏ.
- Ebook luôn ghi rõ phiên bản mới nhất trên thẻ; không hiển thị lựa chọn phiên bản mua.
- Danh mục tiếp tục có thao tác yêu thích.

## Phạm vi

- `frontend/src/components/BookCard.vue`
- `frontend/src/views/CatalogView.vue`
- Kiểm tra hồi quy `frontend/src/views/HomeView.vue` nhưng chỉ sửa nếu tích hợp dùng chung bị lỗi trực tiếp.

## Invariants

- Không thay đổi cart/checkout, route guard hoặc hợp đồng API.
- Sách hết hàng không được hiện thao tác mua.
- Mỗi nút có nhãn trợ năng theo tên sách và vùng chạm tối thiểu 44px.
- Điều hướng chi tiết dùng liên kết thực, không biến toàn bộ thẻ chứa nhiều nút thành một liên kết giả.
- Không commit, push hoặc deploy.

## Prompt Antigravity dự phòng

> Thực hiện 6D.2 trong checkout chính. Chỉ sửa BookCard và CatalogView; chỉ sửa
> HomeView khi có lỗi tích hợp trực tiếp. Dùng BookCard chung cho Home/Catalog,
> ảnh bìa tỷ lệ 2:3 với object-contain và không bo góc ảnh, giữ quick view/add
> cart/buy now 44px (touch luôn thấy, desktop hiện khi hover/focus), ebook ghi
> phiên bản mới nhất, Catalog giữ wishlist. Dùng router-link thật cho chi tiết.
> Kiểm tra 375/768/1024/1440, keyboard, frontend tests/build/diff. Không
> commit/push.

## Gate

- Home và Catalog cùng dùng `BookCard`.
- Bìa không bị nén/cắt và computed `object-fit` là `contain`.
- Ba tiện ích hoạt động bằng chuột, bàn phím và cảm ứng; không mất sau refactor.
- Ebook hiển thị phiên bản nhưng không có lựa chọn phiên bản mua.
- Không overflow ở 375/768/1024/1440.
- Frontend tests/build và diff check đạt.
