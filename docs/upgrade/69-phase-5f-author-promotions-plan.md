# KomiBook Phase 5F — Author coupon và Flash Sale

Ngày: 2026-07-29

Mục tiêu: Author tạo coupon giới hạn theo tác phẩm của mình; đăng ký Flash Sale cho ebook tự xuất bản/sách cũ đủ điều kiện; admin vẫn approve/reject; checkout dùng snapshot và stacking policy.

## Prompt giao Antigravity

> Triển khai 5F theo ADR 62. Author promotion phải kiểm tra accepted authorship và taxonomy cho từng book; không mở admin/vendor permission, không cho cross-author. Coupon có usage/time/discount/stacking/scope book và audit owner. Flash Sale registration giữ admin decision workflow. Cập nhật checkout coupon eligibility và focused tests. Không commit/push/deploy.

Antigravity hết quota; Codex trực tiếp thực hiện.

Gate: author/cross-author permission, limits/time/stacking, Flash Sale eligibility và checkout snapshot.
