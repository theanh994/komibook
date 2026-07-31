# Giai đoạn 6F.1b — nghiệm thu Commerce của Tác giả

Ngày nghiệm thu cục bộ: 2026-07-29

**Đạt Gate 6F.1b cục bộ.**

- Ba chức năng vẫn là ba route riêng trong Author Panel kiểu Vendor.
- Form địa chỉ, sách cũ, coupon và Flash Sale có accessible name, target 44px,
  trạng thái loading/error/notice/empty rõ ràng và mobile một cột.
- Cam kết sách thật và trách nhiệm sách giả vẫn bắt buộc; địa chỉ giữ/gửi sách vẫn
  là dữ liệu owner/admin only.
- Browser ba route ở `375 / 768 / 1024 / 1440`: không overflow; không lưu form,
  chọn file, đổi tồn, tạo coupon hoặc đăng ký Flash Sale.
- Frontend `68/68`, build đạt; backend Warehouse Privacy/Used Book/Author Promotion
  `6` tests, `32` assertions đạt; `git diff --check` đạt.
- Không commit, push hoặc deploy.

