# Giai đoạn 6I.2 — kế hoạch Trang chính sách có phiên bản

Ngày lập: 2026-07-29

## Phạm vi

- Tạo `backend/app/Http/Controllers/Api/PolicyController.php` và public read-only
  route để đọc phiên bản return policy đang hiệu lực.
- Bổ sung regression vào `backend/tests/Feature/Phase6PublicNavigationTest.php`.
- Tạo `frontend/src/views/PolicyPageView.vue`.
- Thêm route `/terms`, `/privacy`, `/policies/ebooks`,
  `/policies/used-books`, `/policies/copyright`.
- Cập nhật Footer, Register và Cart để dùng route thật; Cart hiển thị phiên bản
  chính sách ebook đang hiệu lực khi API cung cấp.

## Bất biến

- API chỉ đọc, chỉ trả policy đang hiệu lực và không lộ dữ liệu riêng tư.
- Ebook không được trả lại; khách luôn mua bản mới nhất; quyền đọc phiên bản bắt
  đầu từ bản đã mua và bao gồm bản mới hơn.
- Chỉ sách cũ đủ điều kiện theo policy snapshot mới vào return flow; người cung
  cấp chịu trách nhiệm về tính xác thực và có luồng tranh chấp hàng giả.
- Không viết điều khoản vượt quá hành vi đã có trong code/database; nội dung
  pháp lý tổng quát được ghi là phiên bản nội dung, không giả danh tư vấn pháp lý.
- `375 / 768 / 1024 / 1440`, semantic headings, target 44px, reduced motion.
- Không mutation DB trong smoke; không commit, push hoặc deploy.

## Prompt dự phòng Antigravity

> Tạo public read-only endpoint trả các ReturnPolicyVersion đang hiệu lực; thêm
> test. Tạo năm policy pages Terms/Privacy/Ebook/Used Books/Copyright, nối router,
> footer, Register và Cart. Cart phải tham chiếu policy ebook cùng phiên bản API.
> Giữ đúng no-return ebook, latest-at-purchase, entitlement từ bản đã mua trở đi,
> used-book-only return và counterfeit responsibility. Không bịa pháp lý/contact.
> Responsive bốn viewport, semantic headings, target 44px. Không mutation trong
> smoke; không commit/push. Chạy frontend test/build và policy/checkout tests.

## Gate

1. Endpoint chỉ đọc trả đúng policy active mới nhất.
2. Năm route và footer link đạt bốn viewport.
3. Register không còn `href="#"`; Cart có link và version reference.
4. Policy/checkout backend tests, frontend tests/build và `git diff --check` đạt.
5. Không còn footer “Chưa có trang”.
