# Giai đoạn 6E.3a — Hồ sơ, yêu thích và thông báo

## Mục tiêu

- Giữ nguyên toàn bộ nghiệp vụ hồ sơ nhưng làm tab, form, địa chỉ và bảo mật dễ
  thao tác bằng bàn phím/cảm ứng.
- Wishlist dùng thẻ sách chung để thống nhất bìa `2:3 object-contain`, thông tin
  phiên bản và các tiện ích quick/cart/buy đã được duyệt.
- Notifications có loading/error/empty riêng, item thông báo là điều khiển có
  ngữ nghĩa và không chỉ dựa vào màu để biểu thị chưa đọc.
- Ba route đạt `375 / 768 / 1024 / 1440`, không overflow và target chính tối
  thiểu 44px.

## Phạm vi source

- `frontend/src/views/ProfileView.vue`
- `frontend/src/views/WishlistView.vue`
- `frontend/src/views/NotificationsView.vue`
- `frontend/src/components/profile/UserSidebar.vue`

Không thay đổi contract API, database, auth, role, checkout hoặc production.

## Invariants

- Không tự gửi form hồ sơ, đổi mật khẩu, xóa địa chỉ, đánh dấu thông báo hoặc
  thay đổi wishlist trong browser gate.
- Wishlist chỉ hiển thị dữ liệu API thật; không tạo sách fallback.
- Trạng thái lỗi không được trình bày như danh sách rỗng thành công.
- Không commit, push hoặc deploy.

## Prompt Antigravity dự phòng

> Thực hiện Phase 6E.3a tại checkout chính, chỉ sửa ProfileView.vue,
> WishlistView.vue, NotificationsView.vue và UserSidebar.vue. Giữ nguyên
> API/business logic. Trên mobile, UserSidebar phải thu gọn để nội dung trang
> xuất hiện sớm; desktop vẫn giữ sidebar đầy đủ.
> Chuẩn hóa form label/id, tab semantics, target 44px, focus/keyboard,
> loading-error-empty. Wishlist tái dùng BookCard chung để giữ bìa 2:3
> object-contain và quick/cart/buy; Notifications dùng button/item semantic,
> aria unread và error retry. Browser gate 375/768/1024/1440, không submit,
> delete, mark-read hoặc toggle wishlist thật. Chạy frontend tests/build và
> git diff --check; báo cáo changed files; không commit/push/deploy.

## Gate

- Hồ sơ: tab/form/address/security không mất chức năng, label và target đạt.
- Wishlist: shared BookCard, empty/error/retry và responsive đạt.
- Notifications: loading/error/empty/list, unread semantics và pagination đạt.
- Frontend tests, production build, browser four-viewports và diff check đạt.
