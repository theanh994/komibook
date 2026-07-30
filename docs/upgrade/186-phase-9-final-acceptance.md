# Gate hoàn tất Giai đoạn 9

Ngày: 2026-07-30
Kết luận: **ACCEPTED-LOCAL**

## Phạm vi đã hoàn tất

- Actor/tài khoản/kênh **Tác giả/Author** đã bị loại khỏi backend và frontend runtime.
- Metadata `books.author` vẫn được giữ làm thông tin thư mục “Tác giả/Người viết”.
- Luồng bán nhiều sách cũ đã chuyển sang capability `used_book_seller`.
- Địa chỉ giao nhận người bán sách cũ được quản lý riêng tư qua contract trung tính.
- Dữ liệu actor cũ được snapshot nội bộ trước khi schema bị loại bỏ.
- Vai trò cũ được chuyển về Customer; Nhà bán và Quản kho giữ đúng ranh giới nghiệp vụ.
- Xuất bản sản phẩm dựa trên chuỗi Publisher/Supplier/Responsible organization đã xác minh.

## Bằng chứng gate

- Backend full regression: **333 tests, 1.781 assertions, pass**.
- Targeted sau migration MySQL: **5 tests, 40 assertions, pass**.
- Frontend full regression: **24 files, 99 tests, pass**.
- Frontend production build cục bộ: pass, 1.292 modules.
- Migration SQLite dùng một lần:
  - `migrate:fresh`: pass;
  - rollback hai migration Giai đoạn 9: pass;
  - reapply: pass.
- Pint trên source thay đổi: pass.
- `git diff --check`: pass.
- Route scan: không còn route chứa `author`.
- Runtime identifier scan: không còn controller, service, profile, capability hoặc URL của actor Author.

## Ngoại lệ có chủ đích

- Trường dữ liệu `author` và nhãn tiếng Việt “Tác giả/Người viết” vẫn tồn tại để mô tả người viết của cuốn sách.
- Hai migration Giai đoạn 9 và các migration lịch sử phải nhắc tới tên bảng actor cũ để chuyển đổi, snapshot và rollback an toàn.
- `retired_actor_archives` chỉ là kho kỹ thuật nội bộ, không có route/API/UI công khai.
- Việc số lượng test giảm so với Giai đoạn 8 là hệ quả dự kiến của việc xóa các test dành riêng cho nghiệp vụ Author đã nghỉ; các luồng còn hoạt động đều nằm trong bộ hồi quy mới.
- Build còn cảnh báo chunk lớn đã biết; không làm thất bại gate.
- PHP local còn cảnh báo OpenSSL được nạp hai lần; không làm thất bại test.

## Giới hạn phát hành

Gate source ban đầu chỉ sử dụng database dùng một lần. Sau khi người dùng phê duyệt riêng, MySQL local đã được sao lưu và nâng schema để phục vụ UAT; production và Cloudflare không bị thay đổi.

Chưa commit, push, deploy, dùng database production, dịch vụ ngoài hoặc phát sinh chi phí.

## UAT bằng dữ liệu MySQL local

Ngày thực hiện: 2026-07-30

- Backup trước migration:
  `backend/storage/app/backups/komibook-before-phase9-20260730-171613.sql`
- Kích thước backup: **211.812 bytes**.
- SHA-256:
  `57DF86AC39AFF3484988097C04558ED06854F5C6FF3634BDA815ACF4617AACC3`
- Không chạy seeder trên MySQL.
- Migration Giai đoạn 7–9 đã hoàn tất; migration `102000` ở batch 13 và `103000` ở batch 14.
- Hai migration cuối được làm resumable và sửa thứ tự gỡ foreign key/index để tương thích MySQL.
- Trang chủ local đã được kiểm tra với dữ liệu thật: sách hiện hữu, ảnh bìa, giá và các nút yêu thích/xem nhanh/thêm giỏ/mua ngay đều xuất hiện.
- Console trình duyệt sau khi tải dữ liệu thật: không có lỗi.
