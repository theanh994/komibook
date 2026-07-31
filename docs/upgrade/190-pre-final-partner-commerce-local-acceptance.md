# Nghiệm thu cục bộ — Partner commerce trước phát hành

Ngày nghiệm thu: 2026-07-31
Phạm vi: IPM, Hikari–Thái Hà Books, Fahasa; NXB Lao Động, Hà Nội, Kim Đồng, Trẻ, Giáo Dục; capability, quan hệ phân phối và payout.

## Kết luận

Source cục bộ đạt gate kỹ thuật để chuẩn bị một lần phát hành có kiểm soát. Kết quả này không đồng nghĩa production đã thay đổi.

## Bằng chứng trực tiếp

- Backend: `349 passed`, `1917 assertions`.
- Frontend: `26 test files`, `115 passed`.
- Lint frontend: không còn cảnh báo/lỗi.
- Build frontend production: thành công; chỉ còn cảnh báo kích thước chunk vốn không chặn build.
- PHP Pint cho toàn bộ tệp PHP thuộc batch: đạt.
- Migration: fresh toàn bộ schema, rollback migration partner commerce và migrate lại đều thành công trên SQLite tạm; tệp tạm đã được xóa.
- Trình duyệt: guest truy cập `/organization-portal` được chuyển tới trang đăng nhập với redirect đúng; không có lỗi console.
- `git diff --check`: đạt.

## Ma trận dữ liệu sau khi phát hành

| Đơn vị | Mô hình | Trạng thái khởi tạo |
|---|---|---|
| IPM | Distributor + Supplier + Seller | Vendor draft, organization draft |
| Hikari–Thái Hà Books | Distributor + Supplier + Seller | Vendor draft, organization draft |
| Fahasa | Distributor + Supplier + Seller | Vendor draft, organization draft |
| NXB Lao Động | Publisher-only | Organization manager, không có Vendor |
| NXB Hà Nội | Publisher-only | Organization manager, không có Vendor |
| NXB Trẻ | Direct Publisher + Supplier + Seller | Vendor draft |
| NXB Giáo Dục | Direct Publisher + Supplier + Seller | Vendor draft |
| NXB Kim Đồng | Direct Publisher + Supplier + Seller | Bảo toàn gian hàng hiện tại; ngân hàng về trạng thái cần xác minh |

## Điều kiện trước production

1. Tạo backup MySQL production mới và xác nhận có thể đọc file backup.
2. Commit/push/deploy source theo yêu cầu riêng của người dùng.
3. Chạy migration production.
4. Chạy lệnh chuyển đổi với acknowledgement production chính xác.
5. Kiểm tra manifest trước/sau, số lượng tài khoản, organization, membership, Vendor draft và quan hệ self.
6. Không tự xác minh organization, quan hệ hoặc ngân hàng; người dùng/Admin điền và duyệt hồ sơ thật sau đó.

## Dữ liệu riêng tư

Thông tin đăng nhập mới của IPM, Hikari và Fahasa chỉ được tạo khi lệnh chuyển đổi thực sự chạy. CSV nằm trong private storage và không được commit hoặc hiển thị trong log/terminal.
