# Giai đoạn 7A.3 — Kế hoạch định tuyến kênh Author và prompt Antigravity

Ngày: 29/07/2026  
Trạng thái ban đầu: in-progress

## Mục tiêu

- Tài khoản có capability `approved_author` đăng nhập mặc định vào `/author/manage/dashboard`, kể cả khi đồng thời có Vendor profile/role.
- Admin vẫn ưu tiên Admin; Vendor không phải Author chỉ vào Vendor khi capability `active_vendor` đang hoạt động.
- Giữ các deep link và nút chuyển kênh rõ ràng; không thay permission backend.

## Phạm vi

- `frontend/src/router/guard.js`
- redirect `/dashboard` trong `frontend/src/router/index.js`
- `frontend/src/__tests__/auth_guard.spec.js`
- tài liệu plan/gate batch.

Cấm: thay role/schema/backend authorization, xóa kênh Vendor, localStorage chứa token/profile, commit/push/deploy.

## Invariants

- Thứ tự landing: Admin → approved Author → active Vendor → Home.
- Direct redirect an toàn từ query vẫn được giữ.
- Route Author vẫn kiểm `approved_author`; route Vendor và backend vẫn kiểm quyền Vendor.
- Tài khoản hai capability có thể mở Vendor bằng link chuyển kênh, nhưng đăng nhập không âm thầm đưa Author sang Vendor.

## Gate

- Vitest matrix admin/author/vendor/dual/customer và regression auth guard.
- Lint/build frontend mục tiêu; `git diff --check`.
- Browser login/redirect smoke khi local stack và tài khoản thử sẵn sàng.

## Prompt Antigravity dự phòng

> Trong checkout chính, bảo toàn dirty worktree. Tạo pure helper chọn dashboard theo thứ tự Admin → approved Author → active Vendor → Home, dùng helper trong redirect `/dashboard` và bổ sung Vitest matrix. Chỉ sửa ba file source/test ghi trong plan; không đổi backend role/permission, không thêm lưu token/profile vào localStorage, không commit/push. Chạy gate và báo changed-files.
