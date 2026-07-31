# Nghiệm thu mô hình chứng cứ demo và sửa hồ sơ đối tác

Ngày: 2026-08-01 (Asia/Saigon)

## Mục tiêu

- Không yêu cầu người trình diễn tạo giấy tờ pháp lý, tài khoản ngân hàng, chữ ký, con dấu hoặc hợp đồng giả.
- Tách dữ liệu thật, dữ liệu tham chiếu công khai và dữ liệu mô phỏng.
- Dữ liệu mô phỏng được phép chạy các luồng báo cáo/sản phẩm nhưng không được mang nhãn `verified` và không thể rút tiền thật.
- Sửa lỗi tải logo/tài liệu, sửa hồ sơ tổ chức hiện có thay vì tạo trùng, và cho gian hàng đã duyệt bổ sung hồ sơ mà không mất trạng thái hoạt động.

## Mô hình trạng thái

| Phạm vi | Dữ liệu thật | Dữ liệu demo |
|---|---|---|
| Tổ chức | `data_mode=real`, `status=verified` sau kiểm duyệt | `data_mode=demo`, `status=demo_accepted` |
| Quan hệ/Thỏa thuận | chứng từ thật, `status=verified` | `evidence_mode=demo_statement`, mã `DEMO-*`, `status=demo_accepted` |
| Đối soát | tài khoản ngân hàng đã xác minh | ví `DEMO-VENDOR-*`, `payout_bank_status=demo_disabled` |

`demo_accepted` chỉ có ý nghĩa vận hành trong môi trường trình diễn. Giao diện công khai luôn hiện cảnh báo “Dữ liệu mô phỏng”, không trình bày như quan hệ pháp lý đã xác minh.

## Dữ liệu liên kết mô phỏng

- IPM ↔ NXB Lao Động, NXB Hà Nội.
- Fahasa ↔ NXB Kim Đồng, NXB Trẻ, NXB Giáo Dục.
- Các liên kết trên chỉ phục vụ kiểm thử chuỗi cung ứng và báo cáo; không phải tuyên bố quan hệ thương mại thực tế.

## Sửa lỗi hồ sơ

1. Axios bỏ `Content-Type: application/json` khi payload là `FormData`, để trình duyệt tự sinh multipart boundary.
2. Trang NXB & Nhà cung cấp tự nạp pháp nhân chính hiện có và gửi cập nhật bằng method override `PATCH`; không tạo organization thứ hai.
3. Hồ sơ Nhà bán ở trạng thái `approved` được bổ sung thông tin bằng luồng lưu hồ sơ, không chuyển gian hàng về inactive.
4. Tài khoản demo không bắt buộc tải chứng từ hoặc nhập ngân hàng; trường ngân hàng bị khóa trên giao diện.
5. Guard rút tiền được áp dụng ở cả controller và `PayoutService`.

## Acceptance cục bộ

- Backend đầy đủ: `353 passed`, `1936 assertions`.
- Frontend đầy đủ: `28 test files`, `118 passed`.
- Frontend lint: đạt, không cảnh báo/lỗi.
- Frontend production build: đạt; chỉ còn cảnh báo kích thước chunk vốn không chặn build.
- Migration fresh: đạt.
- Rollback/reapply migration `2026_08_01_010000_add_demo_evidence_modes`: đạt.
- PHP Pint cho toàn bộ tệp PHP thay đổi: đạt.
- `git diff --check`: đạt.

## Gate production

Trước khi migration production phải tạo backup MySQL mới và ghi SHA-256. Sau cutover phải kiểm tra:

- migration mới ở trạng thái đã chạy;
- 8 organization demo mang `data_mode=demo` và không có `verified_at`;
- 6 Vendor demo có ví `DEMO-VENDOR-*` và `payout_bank_status=demo_disabled`;
- 11 quan hệ demo và 5 thỏa thuận phân phối demo mang `demo_accepted`;
- endpoint rút tiền của tài khoản demo bị từ chối;
- trang chủ, đăng nhập, đăng ký Nhà bán, hồ sơ tổ chức công khai, trang tổ chức của Vendor và trang kiểm duyệt Admin tải được, không có lỗi console liên quan.

Không thay đổi đường dẫn hoặc cấu hình Cloudflare Tunnel.
