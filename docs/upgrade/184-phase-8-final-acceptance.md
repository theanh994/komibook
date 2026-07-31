# Gate hoàn tất Giai đoạn 8

Ngày: 2026-07-30  
Kết luận: **ACCEPTED-LOCAL**

## Phạm vi đã hoàn tất

- Actor Warehouse Manager theo assignment và capability từng kho.
- Portal Quản kho tách trang.
- Phiếu nhập/xuất/điều chuyển/kiểm kê, audit event và immutable ledger.
- Organization, quan hệ Vendor–Organization, mô hình hoạt động Nhà bán.
- Publisher/Supplier/Responsible organization theo listing và publish gate.
- Checkout/order snapshot giữ lịch sử.
- Public organization/book detail và link nhanh gian hàng.
- Used-book exception và retirement entry point Author, không xóa dữ liệu lịch sử.

## Bằng chứng gate

- Backend full regression: **388 tests, 2.034 assertions, pass**.
- Phase 8 targeted trước migration rehearsal: **10 tests, 62 assertions, pass**.
- Migration rollback/reapply SQLite: **1 test, 11 assertions, pass**.
- Frontend full regression: **25 files, 105 tests, pass**.
- Frontend production build cục bộ: pass.
- Pint targeted: pass.
- `git diff --check`: pass.
- Browser smoke:
  - homepage có 30 book card từ dữ liệu local;
  - utility yêu thích/xem nhanh/thêm giỏ/mua ngay hiện diện;
  - không tràn ngang tại 375, 768, 1024, 1440 px;
  - detail legacy có commercial-party reconciliation state và shop link;
  - route Quản kho chưa đăng nhập hiển thị login.

## Rủi ro còn lại, không chặn local acceptance

- Endpoint gợi ý cá nhân hóa trên homepage local đang ở trạng thái lỗi độc lập khi khách chưa đăng nhập; các feed sách chung vẫn tải đủ. Đây là luồng cũ ngoài phạm vi kiến trúc Giai đoạn 8.
- Dữ liệu catalog hiện hữu chưa được tự backfill commercial parties theo chủ đích ADR; cần review/manifest trước migration dữ liệu thật.
- Browser UAT các trạng thái ready của Vendor/Admin/Warehouse Manager cần session thử tương ứng; policy và tenant đã được kiểm bằng feature tests.
- Build còn cảnh báo asset logo lớn và chunk lớn đã có từ trước.
- PHP local cảnh báo OpenSSL được nạp hai lần; không làm fail test.

## Giới hạn phát hành

Gate này chỉ xác nhận source và môi trường local. Chưa commit, push, deploy, sửa Cloudflare, chạy database thật, dùng credential hoặc dịch vụ ngoài.
