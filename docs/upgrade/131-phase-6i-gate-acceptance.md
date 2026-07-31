# Gate 6I — nghiệm thu Footer, trang thông tin và chính sách

Ngày nghiệm thu cục bộ: 2026-07-29

**Đạt Gate 6I cục bộ.**

- Bốn trang thông tin `/about`, `/for-authors`, `/contact`, `/faq` và năm trang
  chính sách `/terms`, `/privacy`, `/policies/ebooks`,
  `/policies/used-books`, `/policies/copyright` đã đạt page gate ở
  `375 / 768 / 1024 / 1440`.
- Footer dùng route thật cho toàn bộ liên kết trong phạm vi; không còn nhãn
  “Chưa có trang”.
- Chính sách ebook và sách cũ dùng phiên bản đang hiệu lực từ API công khai,
  read-only. Cart hiển thị đúng phiên bản chính sách ebook trước bước xác nhận
  mua hàng.
- Nội dung giữ đúng các bất biến: mua ebook luôn nhận bản mới nhất; không hoàn
  trả ebook; quyền đọc bắt đầu từ phiên bản lúc mua; trả hàng/hoàn tiền chỉ dành
  cho sách cũ đủ điều kiện; người cung cấp chịu trách nhiệm về sách giả.
- Edge audit `320 / 1536` đạt, search target đạt `44px`, Home giữ nguyên ba tiện
  ích trên thẻ sách.
- Frontend tests/lint/build và backend policy/checkout/return tests liên quan đều
  đạt; hồi quy đầy đủ được chốt tại Gate Giai đoạn 6.
- Không commit, push hoặc deploy.

