# Giai đoạn 6E.2 — Cart và Checkout

## Mục tiêu

- Cart hiển thị rõ format, phiên bản ebook mới nhất và không cho tăng số lượng ebook.
- Ebook luôn mua phiên bản mới nhất tại thời điểm checkout; không có bộ chọn phiên bản mua.
- Điều khoản ebook bắt buộc trước checkout và diễn đạt đúng quyền giữ bản mua/nhận bản cập nhật.
- Checkout ebook-only không buộc nhập địa chỉ giao hàng vật lý; mixed/physical vẫn bắt buộc.
- Payment, coupon, address và order summary dùng được bằng bàn phím và mobile.
- Trang kết quả checkout phân biệt rõ thiếu mã đơn, lỗi tải, đơn đang xử lý,
  thanh toán thành công và đơn đã hủy/hoàn; không tự suy diễn thanh toán thành công.

## Phạm vi

- `frontend/src/views/CartView.vue`
- `frontend/src/views/CheckoutSuccessView.vue`
- `frontend/src/stores/cart.js`
- `backend/app/Http/Requests/CheckoutRequest.php`
- `backend/app/Http/Controllers/Api/CheckoutController.php`
- `backend/tests/Feature/Phase5EbookRightsTest.php`

## Invariants

- Backend vẫn tự chọn latest released ebook version; frontend không gửi version id.
- Điều khoản ebook được snapshot và ebook không hoàn trả.
- Physical/mixed checkout vẫn cần địa chỉ và số điện thoại.
- Không thực hiện checkout thật, VNPAY thật, coupon thật hoặc gửi dữ liệu thật trong browser gate.
- Không commit, push hoặc deploy.

## Prompt Antigravity dự phòng

> Thực hiện 6E.2 trong checkout chính, chỉ sửa sáu file đã liệt kê. Cart hiển thị
> latest ebook version, ebook quantity luôn 1, không chọn version mua; terms
> bắt buộc. Ebook-only không cần shipping vật lý nhưng physical/mixed vẫn cần.
> Chuẩn hóa cover contain, payment buttons/aria, labels/44px/responsive. Backend
> vẫn chốt latest released version. Chạy Phase5EbookRightsTest, frontend
> tests/build/diff và browser 375/768/1024/1440; không checkout/VNPAY thật,
> không commit/push.
> Chuẩn hóa thêm CheckoutSuccess: loading/invalid/error/pending/paid/cancelled
> phải trung thực, thao tác tối thiểu 44px, không animation bắt buộc khi người
> dùng bật reduced motion.

## Gate

- Ebook duplicate/add/update quantity luôn 1.
- Ebook-only request không shipping được tạo; physical thiếu shipping bị từ chối.
- Terms thiếu bị từ chối; latest version được snapshot.
- Cart step 1/2 keyboard và responsive đạt.
- Checkout result invalid/error và các nhánh trạng thái theo dữ liệu đạt keyboard,
  responsive và không tuyên bố đã thanh toán khi payment chưa paid.
- Frontend/backend tests, build và diff check đạt.
