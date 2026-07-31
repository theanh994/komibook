# KomiBook Phase 5E — Sách cũ, return/refund và counterfeit

Ngày: 2026-07-29

Mục tiêu: Author đăng sách cũ với ảnh thật, condition, khuyết điểm và cam kết hàng thật; tồn kho dùng duy nhất địa chỉ fulfillment đã xác minh; chỉ `physical + used_resale` được return/refund; buyer có dispute hàng giả/sai mô tả và admin có quyết định/hold/sanction audit.

## Prompt giao Antigravity

> Triển khai 5E theo ADR 62. Chỉ approved Author có địa chỉ verified được đăng sách cũ; listing bắt buộc ảnh thật, condition, defects và attestation. Return service dựa trên immutable taxonomy/policy snapshot. Không lộ địa chỉ tác giả. Thêm permission/focused tests. Không commit/push/deploy.

Antigravity hết quota; Codex trực tiếp thực hiện.

Gate: listing ownership/privacy; chỉ used_resale returnable; dispute evidence/admin decision/cross-account tests.
