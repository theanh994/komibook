# Giai đoạn 6I.3 — kế hoạch Hiệu chỉnh mép toàn cục

Ngày lập: 2026-07-29

## Phạm vi

- `frontend/src/components/layout/AppHeader.vue`: sửa duy nhất search target đã
  đo được 38px ở desktop và xác nhận mobile tương ứng.
- `frontend/src/assets/main.css`: bỏ `min-width: 20rem` trên `html` vì edge audit
  `320px` đo trực tiếp `clientWidth = 305px`, `scrollWidth = 320px` khi thanh cuộn
  dọc hiện diện; đây là nguyên nhân tràn ngang 15px trên toàn bộ route đại diện.
- Không mở rộng source ngoài hai file trên nếu edge audit `320 / 1536` không tạo
  bằng chứng lỗi mới.

## Bất biến

- Giữ nguyên điều hướng, tìm kiếm, menu role và các tiện ích thẻ sách Home.
- Search target tối thiểu 44px, không làm thay đổi query/submit behavior.
- Edge smoke chỉ đọc cho public/customer/author/vendor/admin shell; không mutation.
- Không commit, push hoặc deploy.

## Prompt dự phòng Antigravity

> Chỉ sửa AppHeader search input để desktop/mobile đạt target 44px và bỏ
> `html { min-width: 20rem; }` trong main.css khi phép đo trực tiếp tại viewport
> 320px cho thấy thanh cuộn dọc làm vùng nội dung còn 305px nhưng document vẫn bị
> ép 320px. Giữ nguyên search/menu/router behavior và tiện ích BookCard. Sau đó
> audit shell ở 320 và 1536 cho public, customer, author, vendor, admin; không
> mutation. Không sửa file khác nếu không có bằng chứng trực tiếp. Chạy frontend
> tests/build và git diff check; không commit/push.

## Gate

1. Hai search input đạt tối thiểu 44px.
2. Edge audit `320 / 1536` không tràn ngang toàn document.
3. Home vẫn có quick view/add cart/buy now trên BookCard.
4. Frontend tests/build và `git diff --check` đạt.
