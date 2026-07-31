# Giai đoạn 6F.1a — nghiệm thu Tổng quan, Phân tích và Quyền tác giả

Ngày nghiệm thu cục bộ: 2026-07-29

**Đạt Gate 6F.1a cục bộ.**

- Giữ Author Panel dạng shell nhiều trang như Vendor.
- Dashboard, Analytics, Copyright Books và Royalty có loading/error/retry/empty
  semantics phù hợp; target chính đạt 44px.
- Analytics chuyển sang card theo tác phẩm trên mobile, giữ bảng trên desktop và
  tiếp tục ẩn cohort dưới ngưỡng privacy.
- Royalty nằm gọn trong management shell, không còn tạo nền/full-screen lồng nhau.
- Browser bốn route ở `375 / 768 / 1024 / 1440`: không overflow, heading rõ,
  không mutation.
- Frontend `68/68`, build đạt; backend Author Studio/Copyright/Self Publishing
  `16` tests, `105` assertions đạt; `git diff --check` đạt.
- Không accept royalty, chỉnh bản quyền, commit, push hoặc deploy.

