# Giai đoạn 7B.3 — Kế hoạch bộ lọc lứa tuổi Catalog và prompt Antigravity

Ngày: 29/07/2026  
Trạng thái ban đầu: in-progress

## Mục tiêu

- Khôi phục đúng năm nhóm lứa tuổi đã được người dùng xác nhận trên Catalog.
- Đồng bộ giá trị tạo/sửa sách với giá trị lọc công khai mà không ghi lại hay làm mất dữ liệu sách hiện có.
- Giữ tương thích với các giá trị cũ (`0-5`, `12-17`, `18+`) và khác biệt viết hoa từng được lưu.
- Xác nhận nút yêu thích trên card Catalog chỉ có biểu tượng tim, vùng bấm tối thiểu 44×44 px và nhãn trợ năng.

## Phạm vi source

- `frontend/src/views/CatalogView.vue`: năm nhãn và giá trị query chuẩn.
- `frontend/src/views/vendor/BookFormView.vue`: preset/default chuẩn cho dữ liệu tạo mới.
- `backend/app/Http/Controllers/Api/BookController.php`: ánh xạ alias tương thích ngược khi lọc.
- Test backend/frontend chuyên biệt cho contract lứa tuổi và wishlist.

Không đổi schema, không migrate/backfill dữ liệu, không sửa sách hiện có, không đổi phân trang/giá/danh mục và không commit, push hoặc deploy.

## Invariants và gate

- Query chuẩn phải tìm được cả sách dùng nhãn chuẩn lẫn giá trị legacy cùng nhóm.
- Query không nhận diện vẫn giữ hành vi so khớp chính xác để không che dữ liệu tùy chỉnh.
- URL state/back-forward tiếp tục sử dụng một giá trị ổn định từ `ageOptions`.
- PHPUnit targeted, Vitest targeted, ESLint, Pint, build và `git diff --check` phải đạt.
- Visual/UAT Catalog ở 375/768/1024/1440 được thực hiện trực tiếp trong Gate 7B; không suy diễn từ build.

## Prompt Antigravity dự phòng

> Bảo toàn dirty worktree. Batch 7B.3 chỉ được sửa `CatalogView.vue`, `BookFormView.vue`, `BookController.php` và test chuyên biệt. Khôi phục đúng 5 nhóm: “Nhà trẻ - mẫu giáo (0 - 6)”, “Nhi đồng (6 - 11)”, “Thiếu niên (11 - 15)”, “Tuổi mới lớn (15 - 18)”, “Tuổi trưởng thành (Trên 18 tuổi)”. Catalog gửi nhãn chuẩn; form tạo sách dùng cùng contract. API lọc phải tương thích dữ liệu legacy (`0-5`, `6-11`, `12-17`, `18+` và nhãn cũ) nhưng không backfill/ghi đè DB; giá trị không nhận diện vẫn lọc exact. Giữ wishlist icon-only 44×44 và aria-label qua BookCard dùng chung. Không đổi schema, pagination, price/category, không commit/push/deploy. Chạy targeted PHPUnit/Vitest, lint/Pint/build/diff và báo changed-files.
