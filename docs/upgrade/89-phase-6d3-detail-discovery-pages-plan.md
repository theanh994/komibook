# Giai đoạn 6D.3 — Chi tiết sách và trang khám phá

## Mục tiêu

- Hoàn thiện Book Detail, Flash Sale, Blog và Article theo design system Giai đoạn 6.
- Giữ bìa sách không crop/bo góc trực tiếp; bỏ ảnh fallback từ dịch vụ ngoài.
- Bảo đảm CTA, breadcrumb, đọc thử, đánh giá, series/related và trạng thái loading/error/empty rõ ràng.
- Blog/Article hiển thị metadata NXB/Tác giả an toàn do API đã cấp.

## Phạm vi

- `frontend/src/views/BookDetailView.vue`
- `frontend/src/views/FlashSaleView.vue`
- `frontend/src/views/BlogView.vue`
- `frontend/src/views/ArticleView.vue`

## Invariants

- Không thay hợp đồng mua ebook, cart/checkout, quyền đánh giá/đọc thử hoặc route guard.
- Ebook ở trang chi tiết chỉ bán phiên bản mới nhất; không có bộ chọn phiên bản mua.
- Không đưa email hoặc địa chỉ fulfillment vào public UI.
- Không dùng ảnh/mock từ dịch vụ ngoài để che lỗi dữ liệu.
- Vùng chạm tối thiểu 44px, focus rõ, hỗ trợ reduced motion.
- Không commit, push hoặc deploy.

## Prompt Antigravity dự phòng

> Thực hiện 6D.3 trong checkout chính. Chỉ sửa BookDetailView, FlashSaleView,
> BlogView và ArticleView. Chuẩn hóa bìa object-contain/no image radius, bỏ
> external fallback, cải thiện semantic breadcrumb/CTA/loading/error/empty,
> review/sample/series/related, Blog/Article metadata public an toàn và
> responsive 375/768/1024/1440. Giữ nguyên business/authorization/API; ebook
> không chọn version lúc mua. Chạy frontend tests/build/diff. Không commit/push.

## Gate

- Catalog → detail → cart/sample smoke đạt.
- Flash Sale không dùng ảnh fallback ngoài và có state trung thực.
- Blog/Article có cover, publisher, date, back links và content semantics.
- Book Detail không crop bìa; CTA/review/sample keyboard được.
- Không overflow ở 375/768/1024/1440.
- Frontend tests/build và diff check đạt.
