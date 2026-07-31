# Giai đoạn 6I.2 — nghiệm thu Trang chính sách có phiên bản

Ngày nghiệm thu cục bộ: 2026-07-29

**Đạt Gate 6I.2 cục bộ.**

- Public read-only endpoint `/api/policies/returns` trả policy đang hiệu lực mới
  nhất theo `policy_key`, không mutation; test xác nhận phiên bản tương lai không
  bị công bố sớm.
- `/terms`, `/privacy`, `/policies/ebooks`, `/policies/used-books`,
  `/policies/copyright` được kiểm tra ở `375 / 768 / 1024 / 1440`, không tràn
  ngang và không còn footer “Chưa có trang”.
- Trang ebook và sách cũ hiển thị phiên bản/hiệu lực lấy từ dữ liệu policy thật.
- Register dùng route Điều khoản/Bảo mật thật; Cart hiển thị “chính sách ebook
  v1” từ API trước nút đặt hàng. Browser smoke không đánh dấu consent hoặc gửi đơn.
- Nội dung giữ đúng latest ebook at purchase, no-return ebook, entitlement từ
  phiên bản đã mua, used-book-only return và counterfeit responsibility.
- Frontend `68/68` và production build đạt; backend Policy/Checkout/Return
  regression `20` tests, `131` assertions đạt.
- Không commit, push hoặc deploy.

