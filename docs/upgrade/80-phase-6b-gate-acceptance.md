# Gate 6B — nghiệm thu navigation toàn hệ thống

Ngày: 2026-07-29  
Kết quả: **ĐẠT**

## Phạm vi đã nghiệm thu

- Public header có `Trang chủ`, `Danh mục`, `Sách cũ`, `Tin tức`, `Tủ sách`.
- Danh mục tải tối đa 10 thể loại có nhiều sách published/sellable nhất, đếm cả
  quan hệ legacy và pivot, sắp xếp ổn định.
- `Sách cũ` đi tới đúng `/catalog?provenance=used_resale`.
- Author, Vendor và Admin dùng link thật, parent submenu là button thật.
- Route hiện tại có `aria-current="page"`; breadcrumb dùng title của route.
- User sidebar dùng link thật để chuyển sang đúng workspace.
- Drawer mobile đóng khi đổi route hoặc nhấn Escape và inert khi đóng.
- Không thay route guard, capability hay quyền actor.

## Browser evidence

- Public navigation: desktop 1440 và mobile 375; submenu, Escape, route Sách cũ.
- Admin: dashboard, deep link `/admin/books/categories`, submenu, breadcrumb,
  Escape, back và `aria-current`.
- Author: dashboard và `/author/manage/analytics`, active state đúng.
- Vendor: dashboard và active state đúng.
- Guest bị chuyển về login khi truy cập route Admin.
- Không tràn ngang ở các trang đã kiểm tra.

## Gate kỹ thuật

- `npm.cmd run build`: đạt.
- `npm.cmd run test -- --run`: 11 files, 67 tests đạt.
- `php artisan test --filter=Phase6PublicNavigationTest`: 2 tests,
  7 assertions đạt.
- `git diff --check`: đạt.
- Cảnh báo chunk EbookReader lớn vẫn là backlog hiệu năng đã ghi nhận, không do 6B.

