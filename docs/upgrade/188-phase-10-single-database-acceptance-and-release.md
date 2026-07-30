# Gate Giai đoạn 10 — Một database runtime

Ngày: 2026-07-30
Kết luận source: **ACCEPTED-LOCAL**

## Thay đổi đã nghiệm thu

- MySQL `komibook` là database runtime duy nhất của ứng dụng.
- Bốn schema hệ thống MySQL được giữ nguyên.
- SQLite chỉ còn vai trò test `:memory:` hoặc rehearsal dùng một lần.
- MariaDB, PostgreSQL và SQL Server không còn trong danh sách connection runtime.
- Composer không tự tạo SQLite cố định.
- Queue batching và failed jobs dùng cùng connection runtime.
- Hai SQLite cũ đã được archive có checksum rồi loại khỏi vị trí hoạt động.

## Bằng chứng

- Backend: **335 tests, 1.787 assertions, pass**.
- Frontend: **24 files, 100 tests, pass**.
- Frontend production build: pass, 1.292 modules.
- Composer validation: pass.
- Migration MySQL: không còn migration pending.
- `CHECK TABLE` MySQL: **98/98 bảng OK**, không có lỗi.
- `git diff --check`: pass.
- MySQL inventory: một schema ứng dụng `komibook`, 98 bảng.

## Lưu ý

- 98 bảng không phải 98 database. Chúng là các bảng chuẩn hóa trong cùng schema.
- Không gộp các bảng tài chính, kho, thanh toán và audit vì sẽ làm yếu khóa ngoại, idempotency và lịch sử nghiệp vụ.
- Ảnh upload nằm ngoài database, trong `backend/storage/app/public`. Mỗi môi trường phải chạy `php artisan storage:link` để nối `/storage` tới kho tệp công khai; thiếu liên kết này sẽ làm URL trong database đúng nhưng trình duyệt vẫn nhận 404.
- `BookCard` có trạng thái dự phòng giữ nguyên tỉ lệ 2:3 và nhãn truy cập khi tệp ảnh bị thiếu hoặc tải lỗi, không còn hiển thị biểu tượng ảnh vỡ.
- Audit dữ liệu thật ghi nhận các bìa Komi tập 1–10 có tệp cục bộ. Komi tập 11–15 vẫn giữ nguyên đường dẫn người dùng đã nhập nhưng tệp nguồn không còn trong kho cục bộ; giao diện hiển thị trạng thái dự phòng và không tự ý thay hoặc xóa dữ liệu này.
- Build còn cảnh báo chunk lớn đã biết; không làm fail gate.
- PHP local còn cảnh báo OpenSSL được nạp hai lần; không làm fail test.

## Release

Trước build/deploy phải chạy `php artisan storage:link` idempotent trên checkout đang phục vụ. Commit, push và triển khai production chỉ được ghi nhận ở đây sau khi từng thao tác hoàn tất và đã có smoke test public.
