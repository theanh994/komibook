# Hiệu chỉnh sau Gate 6C — tiện ích thẻ sách trang chủ

Ngày: 2026-07-29

## Yêu cầu

Giữ ba thao tác trên mọi `BookCard` dùng ở Home:

- xem nhanh;
- thêm vào giỏ;
- mua ngay.

Desktop ẩn cụm thao tác cho tới hover/focus; mobile hiển thị để người dùng cảm
ứng vẫn thao tác được. Mỗi nút tối thiểu 44px và có accessible name theo tên sách.

## Phạm vi

- `frontend/src/components/BookCard.vue`
- `frontend/src/views/HomeView.vue`

## Prompt Antigravity dự phòng

> Khôi phục quick view/add-to-cart/buy-now trên BookCard của Home. Desktop chỉ
> hiện overlay khi hover hoặc focus-within; mobile luôn thao tác được. Nút 44px,
> aria-label có tên sách, không tạo nested interactive invalid. Nối quick-view
> vào dialog HomeView hiện có. Không đổi commerce logic, không commit/push.

## Gate

- Quick view mở/đóng đúng.
- Add-to-cart và buy-now emit đúng sự kiện hiện có.
- Keyboard focus làm cụm action hiện ra.
- Mobile không mất action và không overflow.
- Frontend tests/build và diff check.

