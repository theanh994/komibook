# Giai đoạn 8C — Cổng Warehouse Manager

Ngày: 2026-07-30  
Trạng thái: Accepted-local

## Kế hoạch và prompt từng source batch

Ràng buộc chung: dùng design system KomiBook và quy tắc `ui-ux-pro-max`; mỗi trang có loading/empty/error/ready, target tối thiểu 44px; không đưa tác vụ giá, tài chính hoặc pháp lý vào cổng Quản kho.

- **8C.1:** layout, navigation, dashboard và bộ chọn assignment. Prompt: tạo workspace `/warehouse-manager`, route guard theo capability, menu tách trang; gate bằng guard test/build.
- **8C.2:** trang tồn kho và phạm vi kho được giao. Prompt: chỉ tải assignment đang active, không lộ địa chỉ riêng tư ngoài dữ liệu vận hành cần thiết; gate bằng assignment scope test.
- **8C.3:** responsive/accessibility. Prompt: kiểm tra 375/768/1024/1440, heading/landmark/label/focus và trạng thái forbidden; gate bằng contract test, build và browser smoke.

## Kết quả

- Có ba trang độc lập: Tổng quan, Tồn kho, Phiếu kho.
- UserSidebar và menu tài khoản ưu tiên Không gian Quản kho.
- Truy cập chưa đăng nhập được chuyển tới giao diện đăng nhập; cross-assignment bị chặn ở API.
- Trang quản lý không bị dồn thành một dashboard khó thao tác.

## Gate

- `auth_guard.spec.js` và `phase8_warehouse_commercial_parties.spec.js`: pass.
- Frontend build: pass.
- Public/responsive browser smoke không tràn ngang tại bốn breakpoint.

