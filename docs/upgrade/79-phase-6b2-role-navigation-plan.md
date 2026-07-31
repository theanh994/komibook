# Giai đoạn 6B.2 — role navigation

## Mục tiêu

Chốt navigation Author/Vendor/Admin/User với active state, submenu, breadcrumb,
deep link và hành vi mobile nhất quán.

## Phạm vi dự kiến

- `frontend/src/layouts/AdminLayout.vue`
- `frontend/src/components/profile/UserSidebar.vue`
- `frontend/src/router/index.js` chỉ nếu metadata title thiếu trực tiếp.

## Invariants

- Không đổi route guard, capability hay quyền actor.
- Author không dùng route Vendor để thực hiện chức năng Author.
- Link điều hướng phải là link thật; parent submenu là button thật.
- Mobile drawer inert khi đóng, Escape và route change đóng drawer.
- Không commit/push/deploy.

## Prompt Antigravity dự phòng

> Thực hiện 6B.2 trong checkout chính. Chỉ sửa AdminLayout, UserSidebar và
> metadata title tối thiểu nếu cần. Chuyển navigation về semantic link/button,
> active/aria-current, submenu, breadcrumb và mobile drawer; giữ nguyên toàn bộ
> guard/capability/redirect. Kiểm tra Author/Vendor/Admin/User, không
> commit/push. Báo cáo file đổi, test/build và browser evidence.

## Gate

- guest/customer/author/vendor/admin route smoke;
- deep link, active, submenu, Escape và back;
- 375/768/1024/1440;
- build, tests, diff check.

