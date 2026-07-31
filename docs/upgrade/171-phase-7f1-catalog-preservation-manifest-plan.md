# Kế hoạch batch 7F.1 — Manifest bảo toàn catalog và dữ liệu chuyển tiếp

Ngày: 29/07/2026  
Trạng thái: **in-progress**

## Mục tiêu

- Lập inventory chỉ đọc cho sách, khóa ổn định ISBN/slug, bìa, tồn tổng, quan hệ Nhà bán, Author legacy, sách cũ và kho.
- Phát hiện xung đột có thể khiến một upsert ghi đè title, mô tả, giá, tồn hoặc bìa do người dùng nhập.
- Cung cấp command dry-run JSON để người vận hành xem xét trước bất kỳ lần nhập dữ liệu bổ sung nào.
- Không tự động map Author thành Quản lý kho và không ghi dữ liệu trong batch này vì chưa có tập dữ liệu import cụ thể.

## Phạm vi source

- `backend/app/Services/CatalogPreservationManifestService.php`
- `backend/app/Console/Commands/BuildCatalogPreservationManifest.php`
- `backend/tests/Feature/Phase7CatalogPreservationTest.php`
- Tài liệu ledger/acceptance Giai đoạn 7.

## Bất biến

- `mode=dry_run`, `writes_performed=false` trong mọi kết quả.
- Không chứa địa chỉ kho/Author, giấy tờ, tài khoản ngân hàng, credential hoặc token.
- Không `update`, `upsert`, `delete`, migration, backfill hay ghi file dữ liệu.
- Mỗi sách được định danh bằng ISBN chuẩn hóa nếu có, nếu không dùng slug; thiếu khóa hoặc trùng khóa phải vào `conflict_review`.
- Chênh tồn `books.stock` và tổng `warehouse_stocks.quantity`, hoặc stock nối chéo tenant, phải hiện rõ trong conflicts.
- Quan hệ Author/used-book/warehouse legacy chỉ được thống kê để bảo toàn, không tự chuyển actor.

## Gate

- Test chứng minh fingerprint database trước/sau không đổi.
- Test bao phủ duplicate ISBN/slug, thiếu stable key, tồn lệch và stock chéo tenant.
- Chạy command trên database dev hiện tại ở chế độ chỉ đọc và lưu lại summary, không xuất dữ liệu nhạy cảm.
- Pint, PHP lint và `git diff --check` đạt.

## Prompt Antigravity dự phòng

> Mục tiêu: triển khai batch 7F.1 manifest bảo toàn catalog chỉ đọc trong checkout `C:\Projects\DoAnTotNGhiep_komibook`. Chỉ được sửa ba file source/test đã nêu trong mục Phạm vi source. Không sửa schema, route, UI, seed, dữ liệu, Cloudflare hay cấu hình production; không commit/push/deploy. Manifest phải dùng truy vấn chỉ đọc, trả `mode=dry_run`, `writes_performed=false`, không lộ địa chỉ/PII, phát hiện duplicate/missing ISBN-slug, chênh stock và stock chéo tenant, thống kê liên kết Author legacy/used-book/warehouse mà không tự map actor. Chạy targeted test, command dry-run, Pint và báo cáo changed files cùng bằng chứng gate. Nếu có dữ liệu import hoặc xung đột nội dung, dừng để Codex/người dùng duyệt; tuyệt đối không upsert.

