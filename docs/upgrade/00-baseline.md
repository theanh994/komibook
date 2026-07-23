# Báo Cáo Baseline & Trạng Thái Hiện Tại Dự Án KomiBook

- **Ngày khảo sát:** 22/07/2026
- **Phụ trách:** Tech Lead Full-stack
- **Giai đoạn:** GIAI ĐOẠN 0 — BASELINE BASELINE & RECALIBRATION

---

## 1. Phiên Bản Công Cụ & Môi Trường Thực Tế (CLI Output)

- **PHP:** `8.2.12` (cli) (ZTS Visual C++ 2019 x64)
- **Composer:** `2.9.3` (2025-12-30)
- **Node.js:** `v22.15.0`
- **npm:** `11.3.0`
- **PHPUnit:** `PHPUnit 10.5.63` (Xác minh qua `php vendor/bin/phpunit --version`)
- **Laravel Framework:** `11.51.0` (Xác minh qua `php artisan --version`)
- **Vite:** `8.0.8` (Xác minh qua `.\node_modules\.bin\vite.cmd --version`)

---

## 2. Thống Kê Quy Mô Mã Nguồn & Phân Định Route

### Thống kê thư mục & files
- **Backend Controllers:** 40 controller files (`backend/app/Http/Controllers`).
- **Backend Models:** 30 model files (`backend/app/Models`).
- **Database Migrations:** 35 migration files (`backend/database/migrations`).
- **Backend Tests:** 4 test files (`AuthorDrmInventoryTest.php`, `ExampleTest.php` trong `tests/Feature` & `tests/Unit`).
- **Frontend Views & Components:** 67 file `.vue` (60 views trong `frontend/src/views`, 5 components trong `frontend/src/components`, 2 root/layout components).
- **Frontend Router:** 65 named/active routes (khai báo trong `frontend/src/router/index.js`).

### Phân định thống kê Route Backend
- **Khai báo `Route::` trực tiếp trong file (`backend/routes/api.php`):** **139 khai báo**.
- **Số Route Laravel sau mở rộng (`php artisan route:list --path=api`):** **161 route** (do các khai báo `Route::apiResource` tự động mở rộng thành 5 route CRUD chuẩn: index, store, show, update, destroy).

---

## 3. Git Status & Lịch Sử Phát Triển

- **Nhánh Git hiện tại:** `master` (up to date với `origin/master`).
- **Trạng thái Worktree:** Dirty (chứa các thay đổi chưa commit từ trước Giai đoạn 0 của người dùng).
- **Khôi phục `.gitignore`:** Dòng `docs/` vô tình bị thêm ở lượt khảo sát trước đã được xóa bỏ hoàn toàn. `git diff -- .gitignore` xác nhận rỗng.
- **Trạng thái theo dõi Git cho tài liệu baseline:** Ba file tài liệu trong `docs/upgrade/` là các file chưa được theo dõi (`untracked files`) duy nhất được tạo ra trong Giai đoạn 0.

### 8 Commit gần nhất (`git log -n 8`):
1. `053c684` | 2026-07-21 | Chỉnh sửa nâng cấp giao diện xem chi tiết sách, tích hợp thêm danh mục thể loại, trang quản lý bộ sách
2. `4d9d162` | 2026-07-17 | Tích hợp Hạng và quyền lợi điểm tích lũy, đăng ký làm tác giả, kênh tác giả, trung tâm hỗ trợ cơ bản.
3. `b30db01` | 2026-05-30 | Sửa lỗi áp mã giảm giá, lỗi thanh toán bằng VNPAY, đồng bộ font chữ hệ thống, tích hợp đề xuất sách giảm giá từ phía vendor
4. `56fa9a2` | 2026-05-20 | Cập nhật FlashSale + sửa lỗi hiển thị ép dọc
5. `5401f6e` | 2026-05-18 | Cập nhật giao diện Luồng quản lý - sửa lỗi phân quyền
6. `d7485c9` | 2026-05-12 | Cập nhật giao diện Trang chủ, luồng mua hàng và đọc sách
7. `93fe78c` | 2026-05-10 | Cập nhật giao diện Đăng nhập, Đăng ký
8. `9542059` | 2026-05-08 | Tích hợp FlashSale và tạo mã của admin

### Danh sách file thay đổi trước Giai đoạn 0 (22 Modified + 1 Untracked Migration):
- `backend/app/Http/Controllers/Api/AuthController.php`
- `backend/app/Http/Controllers/Api/BookController.php`
- `backend/app/Http/Controllers/Api/OrderController.php`
- `backend/app/Http/Controllers/Api/ProfileController.php`
- `backend/app/Http/Requests/Auth/UpdateProfileRequest.php`
- `backend/app/Http/Requests/Vendor/StoreBookRequest.php`
- `backend/app/Http/Requests/Vendor/UpdateBookRequest.php`
- `backend/app/Http/Resources/UserResource.php`
- `backend/app/Models/User.php`
- `backend/routes/api.php`
- `frontend/src/components/layout/AppHeader.vue`
- `frontend/src/components/profile/UserSidebar.vue`
- `frontend/src/stores/auth.js`
- `frontend/src/views/BookDetailView.vue`
- `frontend/src/views/MyAnnotationsView.vue`
- `frontend/src/views/MyLibraryView.vue`
- `frontend/src/views/NotificationsView.vue`
- `frontend/src/views/OrdersView.vue`
- `frontend/src/views/ProfileView.vue`
- `frontend/src/views/WishlistView.vue`
- `frontend/src/views/vendor/BookFormView.vue`
- `frontend/src/views/vendor/BooksView.vue`
- `backend/database/migrations/2026_07_22_120000_create_user_favorite_categories_table.php` (Untracked)

---

## 4. Bảng Kết Quả Kiểm Tra Chất Lượng Mã Nguồn

| Công cụ kiểm tra | Lệnh đã thực thi | Trạng thái | Chi tiết kết quả | Ghi chú & Phạm vi bị ảnh hưởng |
|---|---|---|---|---|
| **Composer Validate** | `composer validate --no-check-publish` | **PASSED** | `./composer.json is valid` | Cấu hình Composer chuẩn. |
| **PHPUnit Test** | `php artisan test` | **PASSED** | 21 passed (60 assertions, 1.35s) | Chạy trên SQLite `:memory:`, không can thiệp DB dev. |
| **Laravel Pint** | `php vendor/bin/pint --test` | **FAILED** | 65+ file chưa đạt chuẩn CS | Vi phạm style code tại Controllers, Models, Requests, Migrations, Seeders, Routes. |
| **Frontend Build** | `.\node_modules\.bin\vite.cmd build` | **PASSED** | Đóng gói thành công (1.90s) | Cảnh báo chunk `EbookReaderView-DVxhgR1G.js` lớn hơn 2.5 MB. |
| **ESLint (No fix)** | `.\node_modules\.bin\eslint.cmd . --no-cache` | **FAILED** | 118 errors (0 warnings) | Phát hiện 8 lỗi `no-undef` tại `LoginView.vue` và unused variables. |
| **Oxlint (No fix)** | `.\node_modules\.bin\oxlint.cmd .` | **FAILED** | 3 errors (0 warnings) | Biến không dùng tại `router/index.js` & `auth.js`. |
| **Frontend Tests** | N/A | **NONE** | 0 unit / E2E test | Chưa có công cụ và bộ test tự động ở Frontend. |
| **Cảnh báo môi trường** | `php -v` | **WARNING** | Module "openssl" loaded twice | Warning rác từ PHP CLI (`php.ini`), không hỏng mã ứng dụng. |

---

## 5. Giới Hạn & Cam Kết An Toàn Mã Nguồn

1. **Phương pháp đánh giá:** Kết hợp static review, phân tích file route/store/view và thực thi các bộ linter/builder CLI tiêu chuẩn.
2. **Khẳng định tuyệt đối:** Không sửa đổi bất kỳ dòng mã ứng dụng nào trong `backend/app`, `backend/routes`, `backend/database`, `frontend/src`.
3. **Quản lý Git:** Đã hoàn nguyên `.gitignore` về nguyên trạng ban đầu. Chỉ bảo tồn 3 file tài liệu baseline trong `docs/upgrade/`.
