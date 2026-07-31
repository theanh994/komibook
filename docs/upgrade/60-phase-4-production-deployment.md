# Báo cáo triển khai production Giai đoạn 4

Ngày triển khai: 2026-07-26/27 (Asia/Saigon)

## Kết quả

- Production đang phục vụ release bất biến `f53d5453ad80f25d20c332cf3538179b604abcd4`.
- `master` cục bộ và `origin/master` cùng trỏ tới release trên.
- FrankenPHP và Cloudflared đều ở trạng thái `Running`, kiểu khởi động `Automatic`.
- Cấu hình active có SHA-256 `F5344B418133C2E00DCF0694BD11120E7D36F93099221FA636BCA2CC592CCC7F`.
- Cấu hình rollback về release trước được giữ lại, SHA-256 `0045A4DA60AABD95C188E62429CC7AE0119A7DBE91B48BB7CC23679CFD451338`.
- Không commit, ghi đè hoặc dọn các tài liệu bàn giao cục bộ có sẵn.

## Các commit đã đẩy

1. `9c98f31cfc55e4e4248fc206bccd9e85717706d5` — Hoàn thiện Giai đoạn 4 cho vận hành và xuất bản.
2. `6a469d38eadbdd1d88d567f50bf98323abe03ebd` — Sửa trùng tên route CMS khi cache production.
3. `e5e0fcc85e4673384d47312068b0a7484c3b5efa` — Sửa migration Giai đoạn 4 cho MySQL production.
4. `f53d5453ad80f25d20c332cf3538179b604abcd4` — Sửa backfill nhà bán trên dữ liệu production.

## Database và rollback

- Backup trước migration: `C:\komibook_shared\backups\komibook-pre-9c98f31-20260726-234810.sql`.
- SHA-256 backup: `BB7B3437EB82D42D8BDAF00AAA25B29B3B242D19C358BC6F917A4EDF86DB7A3D`.
- Toàn bộ migration Giai đoạn 4 đã chạy; `migrate:status` không còn migration pending.
- Hai lỗi tương thích MySQL/dữ liệu thật được rollback sạch trước khi sửa và chạy lại: giới hạn tên identifier 64 ký tự và production không có cột legacy `vendors.rejection_reason`.
- Lần cutover đầu trả 404 vì production phục vụ `frontend/dist-social`; rollback tự động đã đưa origin về 200. Sau khi bổ sung đúng artifact và kiểm tra cô lập, cutover lần hai thành công.

## Acceptance production

- Origin `http://127.0.0.1:8080/` và `/api/books`: HTTP 200.
- Public `https://komibook.id.vn/` và `/api/books`: HTTP 200.
- Smoke đầy đủ đã đạt: 8/8 asset, 3/3 API công khai, 3/3 API bảo vệ trả 401 JSON khi là khách, CSRF 204, cookie session/XSRF có thuộc tính bảo mật cần thiết.
- Browser smoke đạt tại trang chủ, blog, flash sale và catalog; không có cảnh báo giao diện.
- Không có `production.ERROR` mới sau thời điểm cutover thành công; cổng preflight 8081/2020 đã được đóng.

## Chốt vận hành chưa bật

Laravel scheduler chưa được đăng ký thành Windows Scheduled Task. Lịch tổng hợp có `campaigns:dispatch-due`; với queue `sync`, lệnh này có thể gửi email marketing thật ngay cho người dùng đã đồng ý nhận tiếp thị. Việc bật scheduler vì vậy cần một phê duyệt riêng cho tác động dịch vụ ngoài/email. Website và API không phụ thuộc scheduler để tiếp tục phục vụ request hiện tại.

## Ghi chú

- Không thay đổi credential, không kích hoạt dịch vụ trả phí và không deploy qua checkout Herd cũ.
- Năm tệp tạm phục vụ đóng gói/phục hồi trong `C:\tmp` đã được xóa. Các release và cấu hình thất bại được giữ lại làm bằng chứng điều tra và tham chiếu rollback.
- Frontend audit còn ghi nhận 2 cảnh báo dependency (1 high, 1 critical); không chạy sửa tự động vì có thể thay đổi dependency ngoài phạm vi release đã nghiệm thu.
