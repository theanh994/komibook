# Giai đoạn 6G.1 — nghiệm thu Dashboard, Sách và Xuất bản Vendor

Ngày nghiệm thu cục bộ: 2026-07-29

**Đạt Gate 6G.1 cục bộ.**

- Chín route Dashboard/Analytics/Books/Series/Create/Edit/Publishing/Chapters/DRM
  được kiểm tra ở `375 / 768 / 1024 / 1440`, không mutation.
- Publishing Workflow có loading/error/retry thay cho trang trắng khi book không tồn tại.
- DRM có semantic state, label giấy phép, target 44px và layout mobile.
- Sửa overflow của management shell khi trang dài tạo vertical scrollbar; thay đổi
  chỉ giới hạn `max-width` và `overflow-x` của `admin-main`.
- Frontend `68/68`, build đạt; backend Self Publishing/DRM regression
  `27` tests, `117` assertions đạt; `git diff --check` đạt.
- Không tạo/sửa sách, series, chương, DRM, publishing state; không commit/push/deploy.

