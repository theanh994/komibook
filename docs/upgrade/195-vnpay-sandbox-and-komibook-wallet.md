# Phương thức thanh toán được hỗ trợ

Ngày cập nhật: 02/08/2026.

KomiBook chỉ còn ba phương thức thanh toán:

1. COD — thanh toán khi nhận hàng.
2. VNPAY Sandbox — chuyển hướng sang cổng thử nghiệm VNPAY, tuyệt đối không dùng tiền thật.
3. Ví KomiBook giả lập — số dư nội bộ chỉ dành cho dữ liệu demo, không nạp, rút hoặc quy đổi.

MoMo và payOS đã bị loại khỏi cấu hình, giao diện, validation checkout và các đường tạo giao dịch mới. Dữ liệu giao dịch lịch sử của hai provider này không bị xóa; yêu cầu hoàn tiền lịch sử phải do Admin xử lý thủ công để bảo toàn dấu vết kiểm toán.

## Luồng VNPAY Sandbox

- `VNPAY_MODE=sandbox` và `VNPAY_URL` phải trỏ đúng cổng sandbox.
- `VNPAY_RETURN_URL` phải là URL HTTPS công khai của route `/api/vnpay/return`.
- Mã giao dịch chỉ dùng chữ và số, tối đa 100 ký tự.
- Nội dung thanh toán được chuẩn hóa không dấu và bỏ ký tự đặc biệt.
- Yêu cầu có `vnp_CreateDate` và `vnp_ExpireDate`, thời hạn 15 phút.
- Chữ ký dùng HMAC SHA-512 trên toàn bộ tham số `vnp_` đã sắp xếp.
- Browser return chỉ dùng để điều hướng giao diện; IPN có chữ ký hợp lệ mới là nguồn xác nhận thanh toán.

## An toàn vận hành

- Không hiển thị hoặc ghi log `VNPAY_HASH_SECRET`.
- Không bật URL production của VNPAY khi chưa có phê duyệt thay đổi phạm vi sang giao dịch thật.
- Lệnh `php artisan payments:enable-demo` chỉ chạy ở local/testing; lệnh sẽ xóa cấu hình MoMo/payOS, bật VNPAY Sandbox và Ví KomiBook giả lập.
- Trước release phải kiểm tra capability công khai chỉ trả về `vnpay` và `demo_wallet`; COD được hiển thị cố định ở checkout.

## Tiêu chí nghiệm thu

- Checkout chỉ hiển thị COD, VNPAY Sandbox và Ví KomiBook giả lập.
- VNPAY chuyển hướng được tới trang thanh toán sandbox với mã yêu cầu hợp lệ.
- Callback sai chữ ký không thay đổi đơn hàng.
- IPN hợp lệ được xử lý idempotent và là đường duy nhất xác nhận paid.
- Ví KomiBook giả lập không gọi dịch vụ ngoài và không phát sinh chi phí.
