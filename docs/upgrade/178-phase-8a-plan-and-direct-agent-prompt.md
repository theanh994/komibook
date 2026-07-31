# Giai đoạn 8A — Plan, prompt triển khai trực tiếp và acceptance contract

Ngày: 2026-07-30  
Trạng thái: ADR được người dùng duyệt; Codex được giao trực tiếp chỉnh source

## Thứ tự

1. 8A.1 audit actor/capability/route/policy.
2. 8A.2 chốt ADR commercial parties và public disclosure.
3. 8A.3 tạo manifest chỉ đọc, không backfill.
4. Chạy Gate 8A trước schema batch 8B.

## Prompt triển khai trực tiếp

Mục tiêu: tạo manifest chỉ đọc phân loại catalog trước khi thêm Organization, Warehouse Manager và chứng từ kho.

Allowed files:

- `backend/app/Services/Phase8ReadinessManifestService.php`
- `backend/app/Console/Commands/BuildPhase8ReadinessManifest.php`
- `backend/tests/Feature/Phase8ReadinessManifestTest.php`
- tài liệu `docs/upgrade/176-*` đến `docs/upgrade/179-*`

Invariants:

- Không ghi database.
- Không suy diễn `vendor_id` thành Publisher/Supplier/Responsible organization.
- Không lộ PII, địa chỉ kho, tài khoản ngân hàng hoặc đường dẫn chứng từ.
- Sách cũ có classification riêng.
- Tenant mismatch là conflict, không tự sửa.
- Không commit, push, deploy hoặc chạy database thật.

Gate:

- targeted manifest tests;
- command JSON smoke;
- `git diff --check`;
- kiểm tra source manifest không gọi insert/update/delete.

## Kết quả audit 8A.1

- `users.role` hiện chỉ có helper Admin/Vendor/Customer; chưa có Warehouse Manager.
- `warehouses` thuộc Vendor và chưa có assignment lifecycle.
- `Vendor` chứa một hồ sơ pháp lý nhưng chưa phân loại mô hình hoạt động.
- `Book` có `vendor_id` và provenance, chưa có Publisher/Supplier/Responsible organization.
- `OrderItem` đã có snapshot taxonomy/policy/ebook nhưng chưa có commercial-party snapshot.
- Inventory audit và stock transfer hiện có nhưng chưa đủ state machine/idempotency/ledger của Giai đoạn 8.

## Quyết định 8A.2

ADR `176-phase-8-commercial-party-architecture-adr.md` được duyệt trong cuộc hội thoại ngày 2026-07-30. Source 8B phải tuân thủ quan hệ explicit và snapshot, không dùng trường tên tự do.
