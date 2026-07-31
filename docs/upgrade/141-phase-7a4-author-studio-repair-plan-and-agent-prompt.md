# Giai đoạn 7A.4 — Kế hoạch sửa Author Studio và prompt Antigravity

Ngày: 29/07/2026  
Trạng thái ban đầu: in-progress

## Mục tiêu

- Tài khoản dual Author/Vendor quản lý được tác phẩm mà mình có quan hệ tác giả hợp lệ.
- Tác phẩm mới mở editor có ngay draft chương đầu, viết và lưu không rơi vào index `-1`.
- Đồng bộ contract `order`, `is_free`, revision/autosave; bỏ giá theo chương không có backend và không đúng nghiệp vụ ebook toàn cuốn.
- Journey create work → first chapter → save → autosave → reload hoạt động.

## Phạm vi

- `ChapterController`, `AuthorCommerceService` và feature test 7A.4.
- `LiveEditorView.vue` và Vitest 7A.4.
- tài liệu plan/gate.

Không sửa publishing approval/DRM/ebook entitlement, schema, dữ liệu thật, hoặc redesign toàn Author Studio; không commit/push/deploy.

## Invariants

- Quyền tác giả được xét độc lập với role Vendor theo ADR 4B; Vendor không có quyền tác giả vẫn chỉ quản lý sách đúng vendor_id.
- Không nới quyền cross-author/cross-vendor.
- `order` là tên canonical; `chapter_number` chỉ là alias tương thích đầu vào.
- Autosave dùng optimistic revision và không ghi nhầm chương sau khi người dùng chuyển selection.
- Chương đầu có title mặc định và không được tạo nhiều draft chưa lưu cùng lúc.

## Gate

- Backend feature: dual capability, chapter alias/order, revision, reload và negative authorization.
- Frontend unit: empty editor tạo draft, POST canonical, state sau save.
- Regression Phase5 Author Studio + Phase3 editor; lint/build/Pint/diff check.
- Browser journey khi local stack có thể chạy.

## Prompt Antigravity dự phòng

> Bảo toàn dirty worktree trong checkout chính. Sửa Author Studio đúng phạm vi: ChapterController phải xét quyền Author độc lập trước fallback Vendor; hỗ trợ alias `chapter_number` nhưng trả/ghi canonical `order`. LiveEditor phải tạo draft chương đầu hợp lệ, không dùng index -1, dùng `is_free/order`, loại giá theo chương không có backend, autosave không ghi nhầm chapter. Thêm backend feature test và Vitest. Không sửa schema/dữ liệu/publishing/DRM, không commit/push. Chạy gate và báo changed-files.

## Điều chỉnh phạm vi có bằng chứng

Preflight kiểm thử phát hiện `vendors.user_id` là duy nhất trong khi `AuthorCommerceService` luôn tạo thêm Vendor tương thích cho Author chưa có commerce profile. Vì vậy service được bổ sung vào phạm vi để tái sử dụng Vendor hiện hữu của cùng tài khoản; thay đổi này tuân thủ ADR Author–Vendor đã duyệt và không làm thay đổi schema.
