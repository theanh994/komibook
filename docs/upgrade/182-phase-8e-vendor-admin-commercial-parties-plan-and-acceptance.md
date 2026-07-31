# Giai đoạn 8E — Vendor/Admin và commercial parties

Ngày: 2026-07-30  
Trạng thái: Accepted-local

## Kế hoạch và prompt từng source batch

Ràng buộc chung: Publisher, Supplier và Responsible organization phải explicit; chỉ dùng relationship đã verified và còn hiệu lực; không cho nhập tên tự do để né duyệt; thay đổi tạo phiên bản mới.

- **8E.1:** Vendor quản lý nhân sự kho. Prompt: mời, kích hoạt, đình chỉ, thu hồi và capability theo kho; gate bằng tenant/transition tests.
- **8E.2:** Vendor quản lý organization/đối tác. Prompt: form tách organization và relationship, evidence luôn private; gate bằng disclosure và cross-vendor tests.
- **8E.3:** Admin review queue. Prompt: duyệt/từ chối/đình chỉ có reason và audit, Vendor không tự verified; gate bằng negative authorization.
- **8E.4:** publish gate theo mô hình Nhà bán. Prompt: Direct Publisher vẫn lưu đủ ba role; Bookstore phải chọn đủ party verified; used-book có ngoại lệ; gate bằng publication eligibility tests.

## Kết quả

- Direct Publisher có thể dùng cùng một organization cho ba vai trò nhưng dữ liệu vẫn tách rõ.
- Bookstore không thể dùng quan hệ chưa duyệt hoặc của Vendor khác.
- Publishing workflow có bộ chọn ba commercial party và giữ tương thích listing legacy.
- Admin có hàng đợi Organization/Relationship riêng.

## Gate

`Phase8CommercialPartiesTest`: 2 tests, 13 assertions, pass.

