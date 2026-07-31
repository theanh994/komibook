# Giai đoạn 7A.2 — Kế hoạch ảnh bìa đơn hàng và prompt Antigravity

Ngày: 29/07/2026  
Trạng thái ban đầu: in-progress

## Mục tiêu

- Chuẩn hóa URL ảnh/media công khai ở một nơi duy nhất.
- Không ghép lặp `/storage/` và không làm hỏng URL `http(s)` trong đơn khách hàng, đơn Vendor, chi tiết đơn, BookResource và thư viện.
- Giữ null là null; không tạo URL ảnh giả.

## Phạm vi

Được phép:

- helper media URL dùng chung trong `backend/app/Support/`;
- `OrderResource`, `CustomerOrderDetailResource`, `BookResource`;
- trường bìa trong `OrderController::myLibrary`;
- feature test 7A.2 và tài liệu gate.

Cấm: migration, seeder, ghi/xóa file media, sửa dữ liệu sách hiện hữu, frontend ngoài lỗi contract, commit/push/deploy.

## Invariants

- `https://...` và `http://...` giữ nguyên.
- `/storage/...` giữ nguyên.
- `books/covers/a.jpg` thành `/storage/books/covers/a.jpg`.
- null/chuỗi rỗng thành null; protocol không được phép không trở thành liên kết ngoài.
- Resource không lộ file path ebook hoặc thông tin riêng tư.

## Gate

1. Feature test helper và API khách hàng/Vendor/thư viện.
2. Regression order/ebook phù hợp.
3. Pint target, PHP syntax, `git diff --check`.
4. Browser smoke đơn hàng khi có phiên đăng nhập phù hợp.

## Prompt Antigravity dự phòng

> Làm việc trong checkout chính `C:\Projects\DoAnTotNGhiep_komibook`, bảo toàn dirty worktree. Tạo helper chuẩn hóa media URL và áp dụng đúng các resource/controller trong phạm vi tài liệu này; thêm feature test cho relative, `/storage`, `http(s)`, null và API order/library. Không sửa migration, seeder, dữ liệu/media thật hoặc frontend; không commit/push. Chạy gate và báo changed-files. Nếu cần mở rộng ngoài phạm vi, dừng và báo Codex.
