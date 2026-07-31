# Giai đoạn 6H.1 — kế hoạch Quản trị cốt lõi

Ngày lập: 2026-07-29

## Phạm vi

- `frontend/src/views/admin/DashboardView.vue`
- `frontend/src/views/admin/UsersView.vue`
- `frontend/src/views/admin/UserDetailView.vue`
- `frontend/src/views/admin/VendorApprovalsView.vue`
- `frontend/src/views/admin/BooksView.vue`
- `frontend/src/views/admin/CategoriesView.vue`
- `frontend/src/views/admin/ReviewModerationView.vue`
- `frontend/src/views/admin/PublishingReviewView.vue`
- Chỉ mở rộng `frontend/src/layouts/AdminLayout.vue` nếu bằng chứng trực tiếp cho
  thấy lỗi dùng chung của management shell.

## Bất biến

- Giữ nguyên quyền Admin, API contract, quy trình duyệt/từ chối, kiểm duyệt sách
  và đánh giá.
- Browser smoke chỉ đọc; không duyệt/từ chối, khóa tài khoản, xóa sách/thể loại
  hoặc thay đổi trạng thái xuất bản.
- Mỗi route phải dùng được ở `375 / 768 / 1024 / 1440`, không tràn ngang toàn
  document; bảng lớn có mobile fallback hoặc vùng cuộn được gắn nhãn.
- Heading, loading/error/empty state, label, keyboard focus và target tối thiểu
  44px phải rõ ràng; tôn trọng reduced motion.
- Không sửa backend, commit, push hoặc deploy.

## Prompt dự phòng Antigravity

> Chỉ sửa các view Admin Dashboard, Users/UserDetail, VendorApprovals, Books,
> Categories, ReviewModeration và PublishingReview; chỉ sửa AdminLayout nếu có
> bằng chứng lỗi shell dùng chung. Giữ nguyên API, quyền và state machine duyệt.
> Cải thiện responsive 375/768/1024/1440, table/card fallback hoặc labeled
> scroll region, semantic loading/error/empty, form labels, keyboard focus và
> target 44px. Browser smoke chỉ đọc, tuyệt đối không duyệt/từ chối/khóa/xóa/
> đổi trạng thái. Không sửa backend, commit hay push. Báo cáo file thay đổi và
> chạy frontend tests/build cùng regression admin/approval/publishing/review.

## Gate

1. Audit trực tiếp tám nhóm route ở bốn viewport.
2. Frontend tests và production build đạt.
3. Backend regression Admin/Approval/Publishing/Review đạt.
4. `git diff --check` đạt.
5. Cập nhật page ledger và acceptance report.
