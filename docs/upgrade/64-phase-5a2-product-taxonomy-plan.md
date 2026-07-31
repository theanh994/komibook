# KomiBook Phase 5A.2 — Product taxonomy và policy snapshot nền tảng

Ngày: 2026-07-29

## Mục tiêu

- Bổ sung các chiều độc lập `format`, `provenance`, `condition`, `fulfillment_mode`.
- Không suy diễn dữ liệu legacy thành `self_published` hoặc `used_resale`.
- Gắn sách với phiên bản chính sách trả hàng có lịch sử.
- Chặn mọi tổ hợp taxonomy không hợp lệ tại domain service.

## Prompt giao Antigravity

> Triển khai 5A.2 theo ADR 62. Bổ sung migration/backfill tương thích dữ liệu cũ, model/resource/request/controller và focused tests. Legacy ebook/physical phải mặc định `publisher_catalog`; không tự gán self-published/used. Taxonomy mới phải đồng bộ với `books.type` trong giai đoạn tương thích. Không commit/push/deploy, không chạy DB thật.

Antigravity hiện hết quota; Codex trực tiếp thực hiện theo ủy quyền.

## Gate

- Migration fresh và rollback/reapply trên SQLite tạm.
- Feature/unit tests cho mọi combination hợp lệ và các combination bị chặn.
- Regression create/update book hiện có.
- `git diff --check`.
