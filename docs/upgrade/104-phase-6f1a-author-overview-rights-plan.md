# Giai đoạn 6F.1a — kế hoạch Tổng quan, Phân tích và Quyền tác giả

Ngày lập: 2026-07-29

## Mục tiêu và phạm vi

Hoàn thiện bốn trang độc lập trong Author Panel kiểu Vendor:

- `frontend/src/views/vendor/AuthorDashboardView.vue`
- `frontend/src/views/author/AuthorAnalyticsView.vue`
- `frontend/src/views/author/AuthorCopyrightBooksView.vue`
- `frontend/src/views/author/RoyaltyAgreementsView.vue`

## Bất biến

- Không gộp thành dashboard một trang; giữ điều hướng và route quản lý riêng.
- Không đổi API, royalty contract, privacy threshold hoặc quyền Author/Vendor.
- Không chấp nhận royalty, sửa hồ sơ bản quyền hay tạo dữ liệu khi browser smoke.
- Target 44px, loading/error/empty semantics, mobile fallback cho bảng số liệu,
  responsive `375 / 768 / 1024 / 1440`, reduced motion.
- Không commit, push hoặc deploy.

## Prompt dự phòng Antigravity

> Chỉ sửa bốn view Author Dashboard/Analytics/CopyrightBooks/RoyaltyAgreements
> được liệt kê. Giữ nguyên API, privacy suppression, royalty append-only và phân
> quyền. Không gộp các trang. Thêm loading/error/retry/empty semantics, target
> 44px, bảng analytics có mobile cards, responsive bốn viewport và reduced motion.
> Không accept royalty hay tạo dữ liệu, không sửa backend/test/route, không commit
> hoặc push. Báo cáo file đổi và chạy frontend test/build cùng test Author liên quan.

## Gate

Frontend test/build, backend Author Studio/Copyright/Royalty focused regression,
browser bốn route ở bốn viewport không mutation, và `git diff --check`.

