# Giai đoạn 3F — Social Auth Production Readiness

## Mục tiêu

- Giữ đăng nhập/đăng ký bằng Email và Google.
- Thay lựa chọn OTP số điện thoại trên `/login` và `/register` bằng Facebook Login.
- Không kích hoạt dịch vụ SMS trả phí, gắn phương thức thanh toán hoặc phát sinh chi phí.
- Giữ số điện thoại như dữ liệu hồ sơ/giao hàng; không xóa dữ liệu hay endpoint OTP đang được luồng xác minh tác giả sử dụng.

## Hợp đồng bảo mật

- Frontend chỉ gửi Google ID token hoặc Facebook user access token.
- Backend tự xác minh token với đúng Google Client ID hoặc Meta App.
- Facebook token phải hợp lệ, thuộc đúng `FACEBOOK_APP_ID` và trả về cùng `user_id`.
- Client không được tự khai báo `google_id` hoặc `facebook_id`.
- Tài khoản mới dùng challenge UUID một lần, hết hạn sau 10 phút.
- Không đưa `FACEBOOK_APP_SECRET` vào frontend, Git hoặc log.

## Cấu hình không chứa bí mật trong Git

Frontend build:

- `VITE_GOOGLE_CLIENT_ID`
- `VITE_FACEBOOK_APP_ID`
- `VITE_FACEBOOK_GRAPH_VERSION`

Backend runtime:

- `GOOGLE_CLIENT_ID`
- `FACEBOOK_APP_ID`
- `FACEBOOK_APP_SECRET`
- `FACEBOOK_GRAPH_VERSION`

Giá trị Graph API version phải lấy từ Meta App Dashboard tại thời điểm cấu hình, thay vì đóng đinh một phiên bản có thể hết hạn trong mã nguồn.

## Việc người quản trị cần làm trước production

1. Tạo/cấu hình Google OAuth Web Client và Meta App bằng tài khoản của chủ dự án.
2. Thêm domain `komibook.id.vn`, URL chính sách bảo mật và URL xóa dữ liệu theo yêu cầu của Meta.
3. Chuyển Meta App sang trạng thái cho phép người dùng thật sau khi hoàn tất các trường bắt buộc.
4. Cấu hình biến môi trường qua kênh bí mật; không gửi secret trong chat.
5. Build lại frontend, làm mới cache cấu hình backend và triển khai theo quy trình riêng đã được phê duyệt.
6. Smoke test bằng tài khoản Google và Facebook thật; kiểm tra cả tài khoản mới, tài khoản đã liên kết và trường hợp Facebook không trả email.

## Cổng nghiệm thu source

- Backend feature test cho challenge, replay, liên kết email, fail-closed và xác minh quyền sở hữu Meta App.
- Frontend test xác nhận nút Facebook thay nút OTP công khai và access token đi qua endpoint backend.
- Targeted Pint, Oxlint, ESLint, Vitest, backend test và frontend production build.
- Full regression của Giai đoạn 3 trước khi commit.

## Ngoài phạm vi

- Mua SMS/OTP, dùng Firebase Blaze, Twilio/eSMS trả phí.
- Cấu hình secret production hoặc thay đổi Meta/Google Console.
- Xóa dữ liệu số điện thoại, migration phá hủy hoặc thay quy trình giao hàng.
- Triển khai production khi chưa có phê duyệt riêng.
