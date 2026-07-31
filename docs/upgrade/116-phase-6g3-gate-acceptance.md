# Giai đoạn 6G.3 — nghiệm thu Tài chính và Flash Sale Vendor

Ngày nghiệm thu cục bộ: 2026-07-29

**Đạt Gate 6G.3 cục bộ.**

- Bốn route Finance, Flash Sale, Live Editor và Multi-device Preview được kiểm tra
  ở `375 / 768 / 1024 / 1440`, không overflow toàn document.
- Nút làm mới Flash Sale có tên truy cập và vùng tương tác tối thiểu 44px.
- Live Editor và Preview dùng lại cải tiến responsive/keyboard đã nghiệm thu tại
  6F.2; không sửa hoặc lưu nội dung trong browser smoke.
- Không tạo payout, đăng ký Flash Sale, tạo coupon hoặc thay đổi dữ liệu tài chính.
- Frontend `68/68`, build đạt; backend Payout/Promotion regression
  `13` tests, `79` assertions đạt; `git diff --check` đạt.
- Không commit, push hoặc deploy.

