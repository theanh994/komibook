# Giai đoạn 6C.2 — Vendor Feed

## Mục tiêu

Thêm tầng thứ ba của Home feed: bài viết mới đã duyệt từ nhà xuất bản, Vendor
hoặc Tác giả, có nguồn, metadata, CTA và responsive rõ ràng.

## Phạm vi

- `backend/app/Http/Controllers/Api/ArticleController.php`
- `backend/tests/Feature/Phase6HomeFeedTest.php`
- `frontend/src/views/HomeView.vue`

## Invariants

- Chỉ article `published` và `published_at <= now()`.
- Public payload nguồn chỉ gồm id, tên/pen name/shop name và role cần hiển thị;
  không trả email hay PII.
- Loading/error/empty độc lập với hero, recommendation và commerce feed.
- Không mock bài viết khi CMS rỗng; empty state phải trung thực.
- Không commit, push hay deploy.

## Prompt Antigravity dự phòng

> Thực hiện 6C.2 trong checkout chính. Chỉ sửa ArticleController,
> Phase6HomeFeedTest và HomeView. Public article API eager-load nguồn ở mức tối
> thiểu, tuyệt đối không lộ email/PII. Thêm Vendor Feed sau recommendation, có
> cover fallback, publisher label, category, ngày, excerpt, CTA và
> loading/error/empty riêng. Kiểm tra responsive 375/768/1024/1440, không
> commit/push.

## Gate

- Draft/future article không xuất hiện.
- Public payload không có email.
- Home empty state đúng khi local CMS chưa có bài.
- Không overflow; link/card target đạt 44px.
- Focused tests, frontend tests/build và diff check.

