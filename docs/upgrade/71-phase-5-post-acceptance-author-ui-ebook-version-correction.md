# KomiBook Phase 5 — điều chỉnh UI Tác giả và phiên bản ebook

Ngày: 2026-07-29

## Mục tiêu

1. Khu vực quản lý Tác giả dùng lại shell/sidebar quen thuộc của Vendor nhưng giữ capability Author độc lập. Các chức năng được chia thành trang riêng.
2. Khách luôn mua phiên bản ebook mới nhất tại thời điểm checkout. Thẻ sách ghi rõ phiên bản đang bán; không có bộ chọn phiên bản khi mua hoặc tại Tủ sách.
3. Sau khi mua, bộ chọn phiên bản chỉ xuất hiện trong trình đọc và chỉ liệt kê phiên bản từ bản đã mua trở đi.

## Batch A — Author management shell

Phạm vi:

- dùng `AdminLayout` làm shell cho `/author/manage`;
- menu Author riêng: Tổng quan, Tác phẩm, Phân tích, Địa chỉ kho, Sách cũ, Khuyến mãi, Bản quyền và Royalty;
- tách địa chỉ, listing sách cũ và promotion thành URL/trang riêng;
- không cấp role Vendor và không mở `/vendor/*`.

Gate:

- route guard `approved_author`;
- browser smoke sidebar, mobile collapse và từng trang;
- frontend test/lint/build.

## Batch B — Ebook version purchase/read contract

Phạm vi:

- catalog/card trả và hiển thị phiên bản ebook mới nhất;
- checkout luôn snapshot `ebook_version_id` mới nhất ở thời điểm tạo đơn;
- Tủ sách chỉ hiển thị bản đã mua và bản mới nhất, không chọn phiên bản;
- reader lấy danh sách entitlement và cho đổi phiên bản từ bản đã mua trở đi;
- backend từ chối version cũ hơn purchase version hoặc version của ebook khác.

Gate:

- focused ebook rights tests;
- frontend test/lint/build;
- full backend regression;
- browser smoke catalog → checkout → library → reader.

Không commit, push, deploy, dùng database thật hoặc gửi email thật trong correction này.

## Quy trình thực hiện

Antigravity không được gọi vì người dùng đã thông báo hết quota tuần và giao Codex toàn quyền sửa trực tiếp. Prompt giao việc dự phòng cho mỗi batch vẫn được xác định như sau:

### Prompt dự phòng Batch A

> Chỉ sửa khu quản lý Author trong `frontend/src/layouts/AdminLayout.vue`, `frontend/src/router/index.js` và các view Author được liệt kê trong kế hoạch. Dùng shell/sidebar kiểu Vendor nhưng không cấp role Vendor, không mở quyền `/vendor/*`, tách từng chức năng thành route riêng, giữ guard `approved_author`. Không commit hoặc push. Báo cáo file đổi và gate frontend.

### Prompt dự phòng Batch B

> Chỉ sửa hợp đồng phiên bản ebook trong model/resource/controller/service/test đã nêu và các card, Library, Reader liên quan. Checkout luôn snapshot bản mới nhất; không cho chọn bản khi mua hoặc trong Library; reader chỉ nhận danh sách từ purchase version trở đi và backend phải từ chối bản ngoài entitlement. Không commit hoặc push. Báo cáo file đổi và gate backend/frontend.

## Kết quả nghiệm thu

- Batch A: `/author/manage/*` sử dụng `AdminLayout`, sidebar Author riêng và các trang Tổng quan, Tác phẩm, Phân tích độc giả, Địa chỉ gửi sách, Sách cũ, Khuyến mãi, Bản quyền, Royalty.
- Batch B: catalog/card ghi phiên bản đang bán; Library chỉ hiển thị thông tin; reader mặc định bản mới nhất và là nơi duy nhất cho chọn bản đã mua hoặc bản cập nhật mới hơn.
- Browser smoke xác nhận chuyển từ phiên bản cập nhật 2 về phiên bản mua 1 trong reader; URL reader không mang `version_id`.
- Backend focused: 2 test ebook/19 assertions và 9 test Author/47 assertions đều qua.
- Full backend: 342 test/1.677 assertions.
- Frontend: 67 test, ESLint/Oxlint sạch, build qua.
- Không commit, push, deploy hoặc dùng database thật.
