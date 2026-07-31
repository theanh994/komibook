# Giai đoạn 6A.2 — responsive application shells

## Mục tiêu

Làm shell public và quản lý responsive, dễ dùng bằng bàn phím, không che nội dung
và không làm mất chức năng ở mobile.

## Phạm vi source dự kiến

- `frontend/src/App.vue`
- `frontend/src/components/AppHeader.vue`
- `frontend/src/components/AppFooter.vue`
- `frontend/src/layouts/AdminLayout.vue`
- `frontend/src/components/UserSidebar.vue`
- `frontend/src/router/index.js` chỉ cho focus sau điều hướng.

Phạm vi thực tế sẽ được đối chiếu tên file trước khi sửa.

## Invariants

- Không đổi route guard, role/capability hoặc redirect nghiệp vụ.
- Author tiếp tục dùng shell kiểu Vendor và các trang tách biệt.
- Mobile menu/drawer có tên truy cập, Escape, overlay và focus hợp lý.
- Có skip link, landmark, main focus sau chuyển route.
- Không commit/push/deploy.

## Prompt Antigravity dự phòng

> Triển khai đúng Giai đoạn 6A.2 trong checkout chính. Chỉ sửa các shell đã liệt
> kê sau khi xác nhận file tồn tại. Đọc MASTER và
> `pages/management-dashboard.md`. Không đổi route guard, authorization, API,
> backend hoặc nội dung nghiệp vụ. Bổ sung skip link, focus-on-route-change,
> responsive navigation/drawer và safe layout. Không commit/push. Báo cáo file
> đổi, chạy build và browser smoke 375/768/1024/1440.

## Gate

- public shell: Home/Login/Catalog;
- management shell: Author/Vendor/Admin;
- keyboard: skip link, menu/drawer, Escape, focus;
- four-viewports overflow;
- frontend build và diff check.

