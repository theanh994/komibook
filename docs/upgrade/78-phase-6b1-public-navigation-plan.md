# Giai đoạn 6B.1 — public navigation

## Mục tiêu

Hoàn thiện thanh điều hướng công khai với Sách cũ, submenu 10 thể loại published
nhiều sách nhất, search/cart/notification/user state nhất quán ở desktop/mobile.

## Phạm vi dự kiến

- `frontend/src/components/layout/AppHeader.vue`
- `frontend/src/views/CatalogView.vue` chỉ để nhận query điều hướng Sách cũ.
- `backend/app/Http/Controllers/Api/CategoryController.php`
- `backend/app/Http/Controllers/Api/BookController.php` chỉ cho filter
  `provenance=used_resale`.
- test focused cho hai contract trên.
- Test frontend liên quan nếu repository đã có cấu trúc phù hợp.

## Invariants

- Sách cũ dùng trạng thái query của Catalog, không tạo nguồn dữ liệu song song.
- Thể loại lấy từ API published; có loading/error/fallback an toàn.
- Không lộ link role không được cấp quyền.
- Menu và submenu dùng được bằng chuột, bàn phím, Escape.
- Không commit/push/deploy.

## Prompt Antigravity dự phòng

> Thực hiện 6B.1 trong checkout chính. Đọc MASTER và Gate 6A. Chỉ sửa public
> header/navigation cùng contract taxonomy tối thiểu nếu audit chứng minh cần.
> Thêm Sách cũ và submenu tối đa 10 thể loại published nhiều sách nhất, giữ
> search/cart/notification/user menu, responsive 375/768/1024/1440 và
> accessibility. Không đổi authorization/backend ngoài contract tối thiểu,
> không commit/push. Báo cáo file đổi, test/build và browser evidence.

## Gate

- guest/customer desktop/mobile;
- search, categories, used books, cart, account menu;
- keyboard/Escape/focus, overflow;
- build, tests, diff check.
