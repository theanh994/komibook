# Nghiệm thu batch 7D.2 — Đóng entry point Author và manifest chuyển đổi

Ngày: 29/07/2026  
Kết quả: **accepted-local**

## Phạm vi đã hoàn thành

- API hồ sơ, lưu nháp và OTP không còn tạo Author mới; phản hồi `410 Gone` kèm hướng sang Nhà bán/sách cũ.
- Hồ sơ Author legacy vẫn được phép hoàn tất hoặc bảo toàn thao tác đang có; không đổi bảng, khóa ngoại hay owner.
- Header, sidebar, footer và `/for-authors` không còn CTA đăng ký Author mới.
- `/author/register` và `/author/verify` chỉ dành cho tài khoản có `author_profile`; tài khoản khác được chuyển tới `/for-authors`.
- Command `author-retirement:manifest` xuất JSON read-only gồm bốn classification `keep_active`, `archive_read_only`, `map_used_book_seller`, `conflict_review`; không chứa địa chỉ hay thông tin ngân hàng.

## Bằng chứng gate

- Backend: 18 test, 87 assertion đạt cho onboarding legacy, OTP legacy, used-book và manifest.
- Frontend: 20 test đạt cho guard, retirement surface và used-book manager.
- Pint: đạt sau format; frontend lint: đạt; Vite build: đạt.
- Manifest trên dữ liệu local: `mode=dry_run`, `writes_performed=false`; không có migration hoặc data mutation.
- Browser tại 1280 px: `/for-authors` không tràn ngang; deep link `/author/register` của tài khoản không có Author chuyển đúng về `/for-authors`.
- `git diff --check`: đạt.

## Giới hạn runtime

- Không deploy lên origin `127.0.0.1:8080`; Cloudflare không thay đổi.
- Không chạy migration/backfill, không ghi database thật, không commit/push/deploy.
- Việc tạo actor Quản kho và chuyển owner thực tế vẫn thuộc Phase 8 theo tài liệu 158.

