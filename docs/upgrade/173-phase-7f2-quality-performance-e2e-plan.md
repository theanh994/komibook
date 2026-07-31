# Kế hoạch batch 7F.2 — Policy boundary, hiệu năng, CI và E2E

Ngày: 29/07/2026  
Trạng thái: **in-progress**

## Kết quả audit

- Route Vendor/Admin đã có lớp `auth:sanctum` + role; Vendor còn có `active-vendor`. Các controller quan trọng đang dùng global scope hoặc truy vấn `vendor_id` tường minh và đã có tenant-negative test.
- `WarehouseController::index` phát sinh một truy vấn stock cho mỗi sách trong trang, dù toàn bộ book IDs đã có sẵn.
- Các truy vấn danh sách chính đã paginate, nhưng catalog/kho thiếu một số composite/reverse index phục vụ filter và tổng tồn.
- `EbookReaderView` lazy theo route nhưng import `vue-pdf-embed` đồng bộ, làm chunk trang đọc khoảng 2,53 MB minified.
- Asset logo PNG 6,14 MB còn là nợ hiệu năng; không thay binary ở batch này vì cần quy trình asset/visual riêng và không ảnh hưởng tính đúng của luồng.

## Phạm vi source

- Tách `vue-pdf-embed` bằng `defineAsyncComponent`, chỉ tải thư viện khi trang đọc thực sự render PDF.
- Batch-load warehouse stocks và warehouse relation cho toàn bộ sách của trang để bỏ N+1.
- Thêm migration index có tên rõ ràng cho filter catalog/kho/phiếu vận hành; chỉ chạy trên SQLite test, không chạy database dev/production.
- Thêm test hiệu năng/kiến trúc kiểm soát query count, index và lazy PDF chunk.
- Chạy full PHPUnit, full Vitest, lint, build và browser E2E chỉ đọc cho guest/customer/vendor/admin.

## Bất biến

- Không đổi API response, nghiệp vụ, route, quyền hoặc dữ liệu.
- Không hạ middleware/tenant scope và không cache dữ liệu xuyên tenant.
- Migration chỉ thêm index, `down()` chỉ bỏ đúng index mới; không backfill hoặc đổi cột.
- Reader vẫn giữ entitlement/version/progress/DRM; lỗi tải thư viện phải đi qua error boundary hiện có của component async.
- Không sửa Cloudflare, 8080, credential, database thật, dependency version hoặc asset binary; không commit/push/deploy.

## Gate

- Query-count test chứng minh số truy vấn trang kho không tăng theo số sách.
- Schema test xác nhận index mới; tenant-negative regression đạt.
- Build tạo chunk `EbookReaderView` nhỏ và một chunk PDF tách riêng; không còn warning 2,53 MB gắn vào reader route.
- Full PHPUnit/Vitest/lint/build đạt; `git diff --check` sạch.
- Browser smoke theo vai trò và các route critical, không thao tác ghi.

## Prompt Antigravity dự phòng

> Triển khai batch 7F.2 tại `C:\Projects\DoAnTotNGhiep_komibook`. Chỉ sửa `frontend/src/views/EbookReaderView.vue`, một frontend performance test, `backend/app/Http/Controllers/Api/Vendor/WarehouseController.php`, một migration index additive, một backend performance/boundary test và tài liệu acceptance. Không đổi API/route/quyền/dữ liệu/dependency/asset binary/Cloudflare/8080; không commit/push/deploy. Dùng `defineAsyncComponent(() => import('vue-pdf-embed'))`; batch-load stock cho book IDs để bỏ N+1 nhưng giữ response; index phải có tên, reversible và chỉ rehearsal trên SQLite test. Chạy targeted rồi full PHPUnit/Vitest/lint/build; báo query count, chunk sizes, changed files và mọi lỗi còn lại.

