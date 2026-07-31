# Giai đoạn 6I.1 — kế hoạch Trang thông tin công khai

Ngày lập: 2026-07-29

## Phạm vi

- Tạo `frontend/src/views/PublicInfoView.vue`.
- Thêm route `/about`, `/for-authors`, `/contact`, `/faq`.
- Cập nhật `frontend/src/components/layout/AppFooter.vue` cho bốn liên kết mới.

## Bất biến

- Không bịa hotline, email, mạng xã hội, số lượng người dùng hoặc cam kết SLA.
- Liên hệ phải dẫn tới Help Center và luồng support thật; khách chưa đăng nhập
  được thông báo rõ yêu cầu đăng nhập khi gửi ticket.
- Trang Tác giả phản ánh đúng mô hình: studio viết sách, ebook tự xuất bản, phân
  tích độc giả, địa chỉ fulfillment riêng tư và sách cũ tách biệt.
- Nội dung dùng được ở `375 / 768 / 1024 / 1440`, heading/landmark/focus rõ,
  target tối thiểu 44px và không tràn ngang.
- Không sửa backend, commit, push hoặc deploy.

## Prompt dự phòng Antigravity

> Tạo một public information view dùng chung cho About, For Authors, Contact và
> FAQ; thêm bốn route và nối footer. Không bịa contact/social/SLA/metrics.
> Nội dung Tác giả phải khớp capability thật; Contact dẫn Help Center và support
> thật. Thiết kế content-first, responsive 375/768/1024/1440, semantic headings,
> keyboard focus, target 44px và reduced motion. Không sửa backend, commit hoặc
> push. Báo cáo file thay đổi và chạy frontend test/build.

## Gate

1. Bốn route và toàn bộ footer link mở đúng ở bốn viewport.
2. Không còn placeholder “Chưa có trang” cho nhóm thông tin.
3. Frontend tests/build và `git diff --check` đạt.
4. Cập nhật page ledger và acceptance report.
