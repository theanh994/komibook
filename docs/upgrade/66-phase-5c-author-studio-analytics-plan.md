# KomiBook Phase 5C — Author Studio, dashboard và reader analytics

Ngày: 2026-07-29

## Mục tiêu

- Author Commerce là capability riêng, không đổi `users.role` thành Vendor.
- Tác giả được tạo ebook tự xuất bản, viết chương, autosave/revision và preview trong Author Studio.
- Dashboard dùng dữ liệu thật theo tác phẩm.
- Reader analytics chỉ trả aggregate, áp dụng ngưỡng cohort tối thiểu 5 và không trả tên/email.

## Prompt giao Antigravity

> Triển khai 5C theo ADR 62. Tái sử dụng editor/chapter core hiện có nhưng thêm policy access cho approved Author. Tạo Author Commerce profile nội bộ, tuyệt đối không đổi role hoặc mở vendor routes. Dashboard/analytics dùng query thật và không trả PII; nhóm dưới 5 người phải bị suppress. Thêm Author Studio UI/routes/tests. Không commit/push/deploy.

Antigravity hết quota; Codex trực tiếp thực hiện.

## Gate

- Permission tests author/customer/cross-author/vendor/admin.
- Studio create/list/write/preview focused tests.
- Analytics payload scan không có `name`, `email`, reader IDs.
- Frontend unit/build.
