# Giai đoạn 10 — ADR một cơ sở dữ liệu runtime

Ngày: 2026-07-30
Trạng thái: **IMPLEMENTED-LOCAL**

## Kết quả kiểm kê

MySQL local có năm schema hiển thị:

- `komibook`: schema duy nhất thuộc ứng dụng;
- `information_schema`, `mysql`, `performance_schema`, `sys`: bốn schema hệ thống bắt buộc của MySQL.

Ứng dụng không chia dữ liệu thành nhiều database. Schema `komibook` hiện có 98 bảng vì các miền thanh toán, đơn hàng, kho, ebook, thông báo và audit được chuẩn hóa riêng.

Hai file SQLite cũ trong checkout là artifact phát triển, không phải database runtime.

Hai file này đã được đóng gói trước khi loại khỏi vị trí hoạt động:

- archive: `backend/storage/app/backups/retired-local-sqlite-20260730.zip`;
- dung lượng: 45.235 bytes;
- SHA-256: `8C07B4087A8F232E00584DF64BCB61AFD61D1404B7F4F543E294246591A059DF`.

## Quyết định

1. Runtime local và production chỉ sử dụng MySQL schema `komibook`.
2. SQLite chỉ được phép dùng `:memory:` qua `phpunit.xml` hoặc file dùng một lần cho migration rehearsal.
3. Không giữ SQLite lâu dài trong checkout.
4. Bỏ cấu hình MariaDB, PostgreSQL và SQL Server vì KomiBook không vận hành các hệ quản trị này.
5. Composer không tự tạo `database/database.sqlite`.
6. Queue batching và failed jobs mặc định dùng cùng MySQL runtime.
7. Không gộp 98 bảng thành các bảng tổng hợp lớn. Việc giữ bảng theo domain bảo toàn khóa ngoại, audit, idempotency và lịch sử tài chính.

## Mô hình sau điều chỉnh

```text
KomiBook runtime
└── MySQL 8.4
    └── komibook
        ├── identity & account
        ├── catalog & publishing
        ├── vendor & organization
        ├── warehouse & inventory
        ├── checkout, payment & order
        ├── ebook entitlement
        ├── used-book marketplace
        ├── finance & reconciliation
        ├── notification & support
        └── audit & migration history

Automated tests
└── SQLite :memory:
```

## Quy tắc kiểm soát

- Không xóa bốn schema hệ thống MySQL.
- Không tạo thêm schema ứng dụng nếu chưa có ADR riêng.
- Không dùng database production cho test.
- Mọi thay đổi schema phải có migration, `down()`, rehearsal SQLite và backup MySQL trước khi áp dụng dữ liệu thật.
- File backup không được commit hoặc đưa vào frontend build.
