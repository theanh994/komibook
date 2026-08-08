# Bảo vệ database production và release gate bắt buộc

Ngày: 2026-08-02 (Asia/Saigon)

## Sự cố đã xác nhận

Release `09c6cf89c2db42e697ec875399c2a2a646484947` kết nối đúng database MySQL `komibook`, nhưng database chỉ còn schema và dữ liệu phiên:

- `0 users`, `0 books`, `0 vendors`, `0 organizations`;
- bảng `migrations` vẫn có đủ 70 migration;
- bảng `sessions` còn dữ liệu phát sinh sau sự cố.

Mẫu trạng thái này phù hợp với việc schema đã bị dựng lại bằng một lệnh phá hủy như `migrate:fresh` hoặc `db:wipe`, không phù hợp với lỗi frontend, CORS, Cloudflare hay Sanctum.

## Phục hồi production

1. Backup trạng thái rỗng trước phục hồi:
   - `C:\komibook_shared\backups\komibook-pre-empty-db-recovery-20260802-104709.sql`
   - SHA-256: `5D7295D894590B55A3D6046CFE8E5F3088215F4FDE54275EDD6C8EBA914BBCD1`
2. Backup được chọn để phục hồi:
   - `C:\komibook_shared\backups\komibook-pre-warehouse-release-no-tablespaces-20260802-004505.sql`
   - SHA-256: `754355B174B91B2BF594D87D335C53FEF5562D5CB69DE6F3C1CDEBA67AF591E9`
3. Backup được nhập thử bằng MySQL `SOURCE` với `utf8mb4` vào database tách biệt trước khi chạm production.
4. Kết quả rehearsal và sau phục hồi: 27 users, 61 books, 11 vendors, 8 organizations, 5 orders và 70 migration.
5. Tài khoản runtime `komibook_app` đã bị thu hồi quyền `DROP` trên `komibook.*`. Các migration tiến tới vẫn có thể dùng `CREATE`, `ALTER` và `INDEX`, nhưng `migrate:fresh`, `db:wipe`, `TRUNCATE` và thao tác drop bảng sẽ bị MySQL từ chối.

## Ba lớp bảo vệ bắt buộc

### 1. Chặn lệnh phá hủy trong ứng dụng

`ProductionCommandGuard` chặn tuyệt đối trên môi trường production:

- `db:wipe`;
- `migrate:fresh`;
- `migrate:refresh`;
- `migrate:reset`;
- `migrate:rollback`;
- `schema:dump`.

Production chỉ sử dụng migration tiến tới. Nếu cần phục hồi, phải dùng backup đã kiểm chứng và có phê duyệt riêng.

### 2. Giới hạn quyền MySQL runtime

Credential trong shared `.env` là credential runtime và không được có `DROP` hoặc `ALL PRIVILEGES` trên database production. Release gate kiểm tra trực tiếp grant hiện hành và chặn cutover nếu quyền phá hủy xuất hiện trở lại.

### 3. Readiness gate trước cutover

Sau khi candidate đã gắn shared `.env`, shared storage, tạo config cache và chạy migration tiến tới, bắt buộc chạy:

```powershell
& .\tools\production\Test-KomiBookReleaseReadiness.ps1 `
  -CandidateBackend 'C:\komibook_releases\<FULL_SHA>\backend'
```

Gate chỉ đọc và phải đạt toàn bộ điều kiện:

- environment là `production`;
- database name đúng `komibook`;
- dữ liệu tối thiểu ở `users`, `books`, `vendors`, `organizations`;
- schema `vendors` có đủ các cột nghiệp vụ chuẩn `onboarding_status`, `business_model`, `is_demo`, `submitted_at`, `last_review_reason`; mã nguồn không được phụ thuộc cột tương thích cũ `rejection_reason`;
- runtime DB user không có quyền `DROP`/`ALL PRIVILEGES`;
- `APP_URL`, session cookie và Sanctum stateful domain đúng production;
- local/public/private storage đều nằm dưới `C:\komibook_shared`;
- candidate không còn migration pending;
- candidate `.env` khớp shared `.env`.

Nếu gate trả khác 0, không sửa Caddyfile, không reload FrankenPHP và không chuyển traffic.

## Thứ tự release bắt buộc

1. Dựng candidate từ clean commit trong `C:\komibook_releases\<FULL_SHA>`.
2. Gắn shared `.env` và shared storage.
3. Chạy `optimize:clear` sau khi `.env` đã được gắn.
4. Chạy `Test-KomiBookMigrationProvenance.ps1` trước migration; cả bốn migration đã ghi nhận trên production phải tồn tại đúng SHA-256 và được ledger báo `Ran`.
5. Chỉ chạy `migrate --force`; không dùng các biến thể fresh/refresh/reset/rollback.
6. Tạo lại config cache bằng credential runtime.
7. Chạy `Publish-KomiBookFrontendAssets.ps1` để đưa toàn bộ index, lazy JS/CSS và public asset vào namespace bất biến `/assets/r<SHA-8>/`; không ghi đè hoặc xóa asset của release cũ.
8. Chạy `Test-KomiBookReleaseReadiness.ps1`; gate phải xác minh toàn bộ asset được `index.html` tham chiếu đã tồn tại trong shared storage.
9. Tạo backup production mới và ghi SHA-256.
10. Validate Caddy candidate, cutover và reload dịch vụ.
11. Smoke-test `/`, `/login`, `/api/books`, đăng nhập → `/api/auth/me`, ảnh bìa và dashboard theo vai trò. Bắt buộc tải trang bằng trình duyệt và kiểm tra console, không chỉ kiểm tra HTTP của HTML.
12. Nếu bất kỳ gate nào thất bại, giữ release cũ; không cố cutover.

Không được gắn cache `immutable` cho phản hồi asset 404. Asset chỉ được cache dài hạn khi matcher `file` xác nhận file tồn tại; phản hồi thiếu file phải dùng `no-store`.

Cloudflare Tunnel không thuộc quy trình sửa lỗi này và không được thay đổi.

## Gate toàn vẹn media public

Lệnh `production:readiness` phải đối chiếu mọi đường dẫn media do database quản lý với public disk dùng chung. Phạm vi gồm ảnh bài viết và media bài viết, ảnh bìa và album sách, avatar, logo Nhà bán/tổ chức, ảnh sách cũ và ảnh chiến dịch thông báo.

- URL ngoài hệ thống và asset tĩnh không thuộc public disk được bỏ qua.
- Đường dẫn tương đối, `/storage/...` và URL production trỏ vào `/storage/...` được chuẩn hóa về cùng một khóa file.
- Chỉ cần một tham chiếu database không có file tương ứng, gate phải trả `blocked` và dừng cutover trước khi Caddy nhận release mới.
- Khôi phục media production chỉ được bổ sung file còn thiếu vào `C:\komibook_shared\storage\app\public`; không ghi đè file đã tồn tại nếu chưa đối chiếu checksum.
