# Gate 6E — nghiệm thu cục bộ hành trình khách hàng

Ngày nghiệm thu cục bộ: 2026-07-29

## Kết luận

**Đạt Gate 6E cục bộ.**

Gate bao phủ xác thực/onboarding entry, cart/checkout, hồ sơ và engagement,
đơn hàng/after-sales, trải nghiệm đọc, Help Center và Customer Support.

## Contract đã bảo toàn

- Ebook trong cart luôn là phiên bản mới nhất và số lượng một; điều khoản ebook là
  bắt buộc; không có bộ chọn phiên bản khi mua.
- Sau khi mua, reader chỉ hiển thị phiên bản lúc mua và các phiên bản mới hơn do
  backend cấp quyền; mỗi lần đổi bản vẫn tái tạo signed link.
- Chỉ sách vật lý có provenance `used_resale` được đưa vào luồng trả hàng.
- Kết quả VNPAY từ query string không tự đánh dấu đơn đã thanh toán trước khi API
  reconciliation xác nhận.
- Tệp hỗ trợ vẫn là private và được kiểm tra phân quyền.

## Bằng chứng gate

- Page ledger của toàn bộ route 6E đã đạt ở bốn viewport bắt buộc. Route
  `/dashboard` của tài khoản customer local chuyển đúng về trang chủ.
- Frontend: `11` files, `68` tests đạt; production build đạt.
- Backend regression tập trung: `49` tests, `297` assertions đạt:
  customer commerce contract, return/refund/invoice, ebook rights,
  author/DRM/inventory/support và security hardening.
- `git diff --check`: đạt.

## Theo dõi sang gate hiệu năng

- Chunk `EbookReaderView` còn khoảng `2.53 MB` minified (`~830 KB` gzip).
- Asset logo hiện khoảng `6.14 MB`.

Hai mục là debt hiệu năng cần xử lý ở batch tối ưu/Gate 6 cuối; không chặn tính đúng
nghiệp vụ của Gate 6E.

## Giới hạn

Đây là nghiệm thu local. Không tạo order, gọi VNPAY, gửi OTP/email, gửi ticket,
đọc/in ebook thật, thay đổi dữ liệu thật, commit, push hoặc deploy.

