# Giai đoạn 6E.3c — Tủ sách, trình đọc ebook và ghi chú

## Mục tiêu

- Tủ sách phân biệt loading/error/empty/filter-empty, cover 2:3 không méo và
  CTA đọc sách dùng được trên touch, không phụ thuộc hover.
- Mỗi entitlement hiển thị phiên bản đã mua và phiên bản mới nhất khi có dữ
  liệu; không có bộ chọn phiên bản để mua.
- Trình đọc chỉ liệt kê `available_versions` backend cấp: từ phiên bản lúc mua
  trở đi; chuyển phiên bản luôn gọi lại authorization API.
- Ghi chú có search label, loading/error/empty/search-empty riêng; action không
  chỉ xuất hiện khi hover.
- Ba route đạt keyboard, target 44px, reduced motion và responsive.

## Phạm vi source

- `frontend/src/views/MyLibraryView.vue`
- `frontend/src/views/EbookReaderView.vue`
- `frontend/src/views/MyAnnotationsView.vue`

Không đổi entitlement API, DRM, file ebook, annotation API, database hoặc
production.

## Invariants

- Không mua/chọn phiên bản ở thẻ sách hoặc Tủ sách.
- Reader chỉ chọn phiên bản đã mua hoặc mới hơn; backend tiếp tục là authority.
- Không mở/in nội dung ebook thật, không tạo/xóa annotation trong browser gate.
- Không commit, push hoặc deploy.

## Prompt Antigravity dự phòng

> Thực hiện Phase 6E.3c tại checkout chính, chỉ sửa MyLibraryView.vue,
> EbookReaderView.vue và MyAnnotationsView.vue. Giữ nguyên API/DRM. Library có
> loading-error-retry-empty/filter-empty; cover 2:3 object-contain/no radius;
> touch CTA 44px và version labels trung thực. Reader chỉ render
> available_versions backend cấp, từ purchase version trở đi; mọi switch gọi
> generate-link authorization; label/select/aria/44px/reduced-motion; bỏ phụ
> thuộc font ngoài nếu có. Annotations có label, error/retry/search-empty và
> action luôn dùng được bằng focus/touch. Browser 375/768/1024/1440 nhưng không
> mở/in ebook thật, không xóa ghi chú. Chạy Phase5EbookRightsTest, frontend
> tests/build/diff; không commit/push/deploy.

## Gate

- Library và annotations đạt states, semantics, touch và four viewports.
- Reader invalid-access/error state đạt; version selector contract được backend
  tests chứng minh.
- Frontend tests/build, `Phase5EbookRightsTest` và diff check đạt.

