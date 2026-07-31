# Nghiệm thu batch 7C.3 — Commission và phí

Ngày: 29/07/2026  
Trạng thái: **source và gate tự động đạt; đăng nhập local đã sửa, UAT cửa sổ hiện tại đạt; còn ma trận breakpoint do người dùng giám sát**

## 1. Phạm vi đã triển khai

- API preview giữ trường cũ và bổ sung `seller_gross`, `customer_pays`, `seller_net`, `platform_net_before_tax`, `tax_rate`, `tax_amount`, `tax_configured`.
- Commission và service fee làm tròn half-up độc lập tới VND nguyên.
- Quản lý phí được nhúng thành tab trong Cấu hình hệ thống.
- `/admin/fee-schedules` chuyển hướng tương thích tới `/admin/system-config?section=fees`.
- Menu Admin không còn mục Commission và phí tách rời.
- UI giải thích người chịu phí, dòng tiền, lịch sử bất biến và ranh giới thuế chưa cấu hình.

## 2. Gate đã chạy

- `Phase4CommerceFeeHistoryTest`: 4 test, 55 assertion — đạt.
- Regression checkout + fee + return/refund: 28 test, 201 assertion — đạt.
- Frontend fee/admin contract: 3 file, 10 test — đạt.
- Vite production build — đạt; chỉ còn cảnh báo kích thước chunk lịch sử, không phát sinh từ 7C.3.
- `git diff --check` phạm vi batch — đạt.
- Công cụ tra cứu bổ sung của `ui-ux-pro-max` không chạy vì môi trường không có Python; triển khai dùng trực tiếp master design system đã duyệt và checklist của skill.

## 3. Browser UAT còn lại

Runtime local đã được sửa để Vite 5173 nạp `.env.local`, proxy API tới origin 8080 và rewrite cookie production chỉ trong chế độ local. Cloudflare/origin/release active không thay đổi. `/api/books` trả 200 với 12 sách trong trang hiện tại; CSRF trả 204 và hai cookie local được lưu đúng; trang chủ hiển thị card, không có lỗi console.

Điều hướng `/admin/fee-schedules` vẫn cần phiên Admin của chủ dự án. Không dò, nhập hoặc lưu credential.

Sau khi chủ dự án đăng nhập Admin trong tab local, cần kiểm tra:

- deep link chuyển tới tab phí;
- 375, 768, 1024 và 1440 px không tràn ngang ngoài bảng lịch sử có chủ ý;
- tab, form, preview, loading/error/empty/ready và focus bàn phím;
- console không có lỗi liên quan 7C.3;
- không gửi form tạo phiên bản phí khi chỉ nghiệm thu giao diện.

## 4. Bảo toàn

- Không migration/backfill, không database thật, production, credential hoặc dịch vụ ngoài.
- Không sửa Author, Vendor, warehouse hoặc kiến trúc Quản lý kho.
- Không commit, push hoặc deploy.

## 5. Bổ sung nghiệm thu sau khi đăng nhập

- Proxy dev 5173 chỉ trong `VITE_LOCAL_COOKIE_MODE` nay gửi Origin/Referer stateful đã cấu hình cho Sanctum; đăng nhập Admin thành công và chuyển đúng deep link tab phí. Cloudflare và origin 8080 không thay đổi.
- `login()` xác nhận lại `/api/auth/me`; không còn báo thành công giả khi session bị mất.
- Preview phí có compatibility fallback cho API 8080 ổn định cũ: 100.000 đ doanh thu gộp, 100.000 đ khách trả, 10.000 đ commission, 90.000 đ người bán nhận, 10.000 đ nền tảng nhận trước thuế.
- Đã quan sát Dashboard, danh sách/tạo/phân tích chiến dịch và System Config/fees tại vùng nhìn thực tế 1256 px; không tràn ngang. Trang analytics không có campaign thật hiển thị error/retry đúng hợp đồng.
- Browser tích hợp không hỗ trợ ép viewport; 375/768/1024/1440 vẫn được giữ trong ma trận giám sát của người dùng, không suy diễn từ kiểm tra 1256 px.
