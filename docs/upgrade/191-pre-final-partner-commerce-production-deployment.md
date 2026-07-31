# Nghiệm thu triển khai production mô hình Partner Commerce

Ngày triển khai: 2026-08-01 (Asia/Saigon)

## Phạm vi

- Chuẩn hóa mô hình Nhà bán, Nhà xuất bản, Nhà cung cấp và Nhà phân phối.
- Bổ sung tổ chức, thành viên tổ chức, thỏa thuận phân phối và xác minh tài khoản nhận tiền.
- Chuyển đổi dữ liệu demo cho IPM, Hikari - Thái Hà Books, Fahasa và năm tài khoản NXB.
- Không thay đổi đường dẫn hoặc cấu hình Cloudflare Tunnel.

## Git và release

- Commit nghiệp vụ: `a82e189d5f11779bd96326da96844cfee65d0eb6`.
- Commit sửa tên khóa ngoại tương thích MySQL: `5ceb7301944f5d40bc7f1e4c3dbc318c8cf069a5`.
- Nhánh đã push: `master`.
- Release active: `C:\komibook_releases\5ceb7301944f5d40bc7f1e4c3dbc318c8cf069a5`.
- Release cũ và cấu hình trước cutover được giữ lại để rollback.

## Sao lưu và migration

- Backup trước migration: `C:\komibook_shared\backups\komibook-pre-partner-commerce-20260801-000032.sql`.
- SHA-256: `6C0683FEA586B960F7D7F07F85D35BE3C57109DC9E54B14A48E71AE19E35279D`.
- Migration mới chạy ở batch `18`.
- Lần chạy đầu dừng do tên khóa ngoại MySQL vượt 64 ký tự. Hai bảng mới tạo dở đã được xác nhận rỗng, xóa riêng và tạo lại sau khi dùng tên khóa ngắn. Không có bảng nghiệp vụ cũ hoặc dữ liệu người dùng bị xóa.

## Dữ liệu demo sau chuyển đổi

- Có 8 tài khoản mục tiêu, 8 tổ chức và 8 quan hệ chủ sở hữu tổ chức.
- IPM, Hikari - Thái Hà Books và Fahasa: `Distributor–Seller`, hồ sơ Nhà bán nháp, ngân hàng chưa xác minh.
- NXB Lao Động và NXB Hà Nội: `Publisher-only`, không tự động có kênh bán.
- NXB Trẻ và NXB Giáo Dục: `Direct Publisher–Seller`, hồ sơ Nhà bán nháp, ngân hàng chưa xác minh.
- NXB Kim Đồng: giữ gian hàng đã duyệt, chuyển sang `Direct Publisher–Seller`, ngân hàng trở về trạng thái chưa xác minh.
- Tệp đăng nhập riêng tư tồn tại tại `backend/storage/app/private/demo-credentials/distributor-seller-accounts-20260731.csv`; nội dung không được ghi vào tài liệu hoặc log nghiệm thu.

## Acceptance

- Kiểm thử Partner Commerce: 5 test, 35 assertion đạt.
- Preflight tách biệt trên `127.0.0.1:8081`: trang SPA, books API, organizations API và CSRF đạt; API cần đăng nhập trả `401` JSON.
- Origin `127.0.0.1:8080`: trang chủ, books API, organizations API và CSRF đạt.
- Public `https://komibook.id.vn`: toàn bộ 8 asset đầu vào trả `200`; books và organizations trả `200`; CSRF trả `204`; ba API bảo vệ trả `401` JSON khi chưa đăng nhập.
- Asset mới được chép bổ sung vào kho dùng chung, không xóa asset cũ. HTML release gắn khóa cache `release=5ceb730` để các trình duyệt không giữ phản hồi 404 ngắn trong lúc cutover.
- `FrankenPHPService` và `cloudflared` đều `Running/Automatic`.
- Không có `production.ERROR` mới trong log kể từ thời điểm cutover `2026-08-01 00:21:06`.

## Trạng thái

Production đã chuyển sang commit `5ceb730`. Tổ chức và hồ sơ Nhà bán demo vẫn ở trạng thái nháp/chưa xác minh theo đúng nguyên tắc: dữ liệu demo không tự cấp quyền bán hoặc quyền rút tiền.

## Sửa lỗi asset và router sau cutover

Ngày 2026-08-01, frontend được chuyển tiếp sang release `f368f2ae4396b41baea30802ecdd604b117f80d7` để xử lý dứt điểm hai lỗi liên quan:

- Tất cả asset và chunk lazy-load được đặt dưới namespace bất biến `/assets/releases/f368f2a/`, tránh trình duyệt hoặc CDN giữ phản hồi 404 từ một lần cutover chưa hoàn tất.
- Vue Router dùng gốc điều hướng độc lập `/`; asset base không còn làm đường dẫn `/login` biến thành `/assets/releases/.../login`.

Gate bổ sung: 116 frontend test đạt, lint đạt, build với asset base phiên bản hóa đạt. Trên production, liên kết đăng nhập trả `href=/login`; mở và tải lại `/login` đều render một form và một ô email, không có lỗi console.
