# Gate 7B — Giao diện công khai Home và Catalog

Ngày: 29/07/2026  
Kết quả: automated accepted; visual/UAT pending

## Phạm vi đã đạt bằng kiểm thử tự động

- 7B.1: BookCard bìa trọn vẹn, top vuông, không category/author, có tim icon-only và ba tiện ích quick/cart/buy với hover/focus/touch.
- 7B.2: hero giá sách mới, gợi ý tối đa 5 và responsive 2/3/4/5, back-to-top hỗ trợ reduced motion.
- 7B.3: đúng năm nhóm lứa tuổi, query/API tương thích dữ liệu legacy, form nhập sách đồng bộ.

## Gate tích hợp

- Backend Home/Catalog: 11 tests passed, 59 assertions.
- Frontend BookCard/Home/Catalog: 3 files, 6 tests passed.
- ESLint/Oxlint/Pint các file thay đổi: passed.
- Vite build: passed; asset hero WebP 133.13 KB.
- Diff whitespace check: passed.

## Phần còn chờ người dùng giám sát

- Home và Catalog ở 375, 768, 1024, 1440 px; kiểm tra biên 320 và 1536 px.
- Hover/focus/keyboard trên card, quick view/cart/buy/wishlist, ảnh bìa thật, back-to-top và back-forward của bộ lọc.

Không tuyên bố `accepted-local`/`closed` cho visual gate khi chưa có UAT. Local dev server tiếp tục chạy để người dùng làm mới tab và kiểm tra. Không commit, push hoặc deploy.
