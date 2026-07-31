# Giai đoạn 6H.3 — nghiệm thu Nội dung và Vận hành Admin

Ngày nghiệm thu cục bộ: 2026-07-29

**Đạt Gate 6H.3 cục bộ.**

- CMS Articles, Notification Campaigns/Create/Analytics, System Config, Help
  Desk và Ticket Detail được kiểm tra ở `375 / 768 / 1024 / 1440`.
- Không route nào tràn ngang toàn document; checkbox/radio nhỏ có label bao toàn
  vùng tương tác từ 44px trở lên.
- CMS và campaign list có loading/error/empty nội tuyến; các form có nhãn và
  target phù hợp. CTA trong preview email không còn là liên kết giả.
- Analytics hiển thị `—` thay vì `0%` khi backend không cung cấp open/click
  telemetry; banner thiếu telemetry tiếp tục mô tả đúng giới hạn dữ liệu.
- Nút thêm cổng thanh toán chưa có chức năng được hiển thị disabled và ghi rõ
  “Chưa hỗ trợ”, tránh thao tác giả.
- Không tạo/publish/xóa article, gửi/retry/xóa campaign, lưu config, reply/update
  ticket hoặc tải tệp trong browser smoke.
- Frontend `68/68` và production build đạt; backend CMS/Campaign/Support/
  Operational Truth regression `12` tests, `57` assertions đạt;
  `git diff --check` đạt.
- Không commit, push hoặc deploy.

