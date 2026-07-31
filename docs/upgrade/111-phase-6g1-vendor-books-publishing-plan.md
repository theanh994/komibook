# Giai đoạn 6G.1 — kế hoạch Dashboard, Sách và Xuất bản Vendor

Ngày lập: 2026-07-29

## Phạm vi

Dashboard/Analytics, Books/Series, Book create/edit, Publishing Workflow,
Book Chapters và DRM Settings dưới `/vendor`.

`frontend/src/layouts/AdminLayout.vue` chỉ được phép sửa CSS `admin-main` để chặn
overflow ngang do scrollbar viewport đã được chứng minh trực tiếp ở DRM mobile;
không đổi navigation hay role shell.

## Bất biến

- Giữ nguyên nghiệp vụ Vendor và tách biệt quyền Author.
- Không tạo/sửa/xóa sách, series, chương, DRM hoặc submit publishing trong browser.
- Trang bảng có mobile fallback hoặc overflow nội bộ có chủ đích; không overflow
  toàn document; target 44px, semantic state, bốn viewport.
- Không commit, push hoặc deploy.

## Prompt dự phòng Antigravity

> Audit và chỉnh UI đúng các view Vendor thuộc 6G.1: DashboardView, AnalyticsView,
> BooksView, SeriesView, BookFormView, PublishingWorkflowView, BookChaptersView và
> DrmSettingsView. Giữ API/payload/role/publishing/DRM contract; không sửa Author
> routes hay backend. Bổ sung responsive mobile, table/card fallback, semantics,
> target 44px và loading/error/empty. Không submit/mutate dữ liệu trong smoke,
> không commit/push. Chạy frontend test/build và backend publishing/DRM regression.
