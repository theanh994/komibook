# Giai đoạn 7A.2 — Nghiệm thu ảnh bìa đơn hàng

Ngày: 29/07/2026  
Kết quả: accepted-local

## Thay đổi được nghiệm thu

- Thêm `PublicMediaUrl` làm contract duy nhất cho media từ public storage.
- Áp dụng cho OrderResource, CustomerOrderDetailResource, BookResource, gallery và thư viện khách hàng.
- URL tương đối được thêm đúng một `/storage/`; URL `/storage` và `http(s)` giữ nguyên; null vẫn là null.
- Protocol-relative không được biến thành liên kết ngoài.
- Không sửa/xóa file media hoặc dữ liệu sách.

## Bằng chứng gate

- `Phase7OrderCoverUrlTest`: 2 passed, 17 assertions.
- Regression `Phase3CustomerCommerceContractTest` + `Phase5EbookRightsTest`: 8 passed, 70 assertions.
- PHP syntax và Pint target: passed.
- `git diff --check`: passed.
- Browser smoke chưa thể thực hiện vì local `127.0.0.1:5173` đang không phục vụ trang tại thời điểm gate. Contract đã được kiểm bằng cả API customer, API Vendor, chi tiết đơn, thư viện và BookResource; browser sẽ chạy lại khi khởi động local stack ở Gate 7A.

Đây là nghiệm thu cục bộ, không ngụ ý commit, push, deploy hoặc production approval.
