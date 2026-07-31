# Giai đoạn 6E.2 — nghiệm thu Cart và Checkout

Ngày nghiệm thu cục bộ: 2026-07-29

## Kết luận

**Đạt Gate 6E.2 cục bộ.**

Batch được thực hiện trực tiếp bởi Codex vì Antigravity hết quota theo ủy quyền
của chủ dự án. Không có checkout thật, giao dịch VNPAY thật, commit, push hoặc
deploy.

## Phạm vi đã nghiệm thu

- Cart chuẩn hóa ebook về số lượng `1`, hiển thị format và trạng thái phiên bản,
  làm mới dữ liệu sách trước khi checkout.
- Ebook-only checkout không yêu cầu địa chỉ/số điện thoại vật lý; physical/mixed
  vẫn bắt buộc hai trường này.
- Điều khoản ebook bắt buộc trước khi nút xác nhận được kích hoạt; nội dung nêu
  rõ mua bản mới nhất, không hoàn trả nội dung số và quyền giữ bản đã mua/đọc
  bản cập nhật.
- Payment option là button có trạng thái pressed; label form và target thao tác
  đạt tối thiểu 44px.
- Checkout result phân biệt loading, thiếu mã đơn, lỗi, pending, paid và
  cancelled/refunded; không coi đơn chưa `paid` là thanh toán thành công.
- Motion ở trang kết quả tôn trọng `prefers-reduced-motion`.

## Bằng chứng tự động

- Frontend: `11` test files, `68` tests đạt.
- Frontend production build: đạt.
- Backend `Phase5EbookRightsTest`: `3` tests, `25` assertions đạt.
- `git diff --check`: đạt.
- Build còn cảnh báo kích thước chunk EbookReader và ảnh logo; đây là hạng mục
  hiệu năng của Gate 6 cuối kỳ, không làm sai contract checkout.

## Bằng chứng browser

- `/cart`, bước 1 và bước 2: đạt tại `375 / 768 / 1024 / 1440`, không overflow.
- Ebook không có bộ chọn phiên bản mua và không có điều khiển tăng số lượng.
- Checkbox điều khoản bật nút xác nhận khi checked và khóa lại khi unchecked.
- Không nhấn nút xác nhận đặt hàng và không chọn VNPAY.
- `/checkout/success` không có `order_id`: hiển thị trạng thái thiếu mã trung
  thực, hai CTA bàn phím được, target đạt 44px, không overflow tại bốn viewport.
- Nhánh dữ liệu paid/pending/error được bảo vệ bằng behavioral tests hiện có;
  không tạo order giả chỉ để chụp giao diện.

## Ghi chú dữ liệu

Ebook legacy trong database local chưa có bản ghi `ebook_versions`, vì vậy Cart
hiển thị thông điệp trung thực “Phiên bản mới nhất sẽ được chốt khi thanh toán”
thay vì bịa số phiên bản. Backend vẫn dùng `currentOrCreate()` để chốt phiên bản
tại checkout. Không tự ý thêm migration/backfill ngoài phạm vi batch.

