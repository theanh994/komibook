# Giai đoạn 7B.1 — Kế hoạch khôi phục BookCard dùng chung và prompt Antigravity

Ngày: 29/07/2026  
Trạng thái ban đầu: in-progress

## Mục tiêu thiết kế

Áp dụng `ui-ux-pro-max` và ảnh tham chiếu IPM do người dùng cung cấp:

- Thẻ gọn, ưu tiên bìa và giá; hai góc trên vuông, phần chân có thể bo nhẹ.
- Bìa giữ đúng tỉ lệ, hiển thị trọn vẹn bằng `object-contain`, không padding làm ảnh bị thu nhỏ giả tạo.
- Không hiển thị thể loại và tác giả trên thẻ; vẫn giữ title, giá, giá gốc/giảm giá và phiên bản ebook.
- Trái tim là icon-only; xem nhanh, thêm giỏ và mua ngay nằm trong overlay của từng thẻ.
- Pointer chính xác: action ẩn khi nghỉ và hiện khi hover/focus; touch/coarse pointer luôn thao tác được.
- Hover/focus nâng nhẹ, tăng bóng và scale bìa trong 150–300 ms; reduced motion tắt dịch chuyển/scale.

## Phạm vi

- `frontend/src/components/BookCard.vue`.
- Các điểm dùng BookCard tại Home/Catalog/Wishlist chỉ để truyền đúng wishlist state/event và giữ đủ ba tiện ích.
- Vitest chuyên biệt, tài liệu gate và ledger.

Không đổi API, cart/wishlist business rule, quick-view modal, dữ liệu sách, schema hoặc các card custom ngoài BookCard; không commit/push/deploy.

## Invariants

- Mọi nút icon có `aria-label`, vùng chạm tối thiểu 44×44 px và không kích hoạt link chi tiết.
- Sách hết hàng không phát action mua; ebook vẫn luôn mua được theo quyền hiện hành và hiện phiên bản mới nhất.
- Bìa null có empty state; URL bìa giữ contract hiện hữu.
- Home phải có lại wishlist và đủ quick-view/add-to-cart/buy-now như Catalog.
- Keyboard focus hiển thị action; touch không phụ thuộc hover.

## Gate

- Unit render BookCard: không category/author, có title/price/version, wishlist và ba action đúng điều kiện.
- Unit event/contract tại Home/Catalog/Wishlist; ESLint và Vite build.
- Browser visual tại 375, 768, 1024, 1440 px; kiểm tra hover, focus, touch fallback, bìa dọc/ngang và reduced motion khi local stack khả dụng.
- `git diff --check`.

## Prompt Antigravity dự phòng

> Bảo toàn dirty worktree trong checkout chính. Khôi phục shared BookCard gần ảnh IPM: hai góc trên vuông, bìa `object-contain` trọn vẹn không padding, không hiện category/author, giữ title/giá/giá gốc/phiên bản ebook. Trả lại wishlist icon-only và giữ quick view, add cart, buy now trong overlay. Hover/focus 150–300 ms có lift/shadow/cover scale; coarse pointer luôn thấy action; reduced-motion không transform. Home, Catalog và Wishlist phải truyền state/event đúng, vùng chạm 44×44 và aria-label đầy đủ. Không đổi API/business rule/modal/schema, không commit/push/deploy. Thêm Vitest, chạy lint/build/diff check và báo changed-files.
