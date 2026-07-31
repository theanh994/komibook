# Gate 6C — nghiệm thu Home feed

Ngày: 2026-07-29  
Kết quả: **ĐẠT**

## Feed đã nghiệm thu

1. Hero carousel lấy bài `home_featured` đã published; local CMS rỗng dùng slide
   KomiBook trung tính, không gọi ảnh ngoài.
2. Gợi ý sách dùng favorite categories đã chủ động lưu; guest/thiếu preference
   dùng fallback phổ biến và có explanation.
3. Vendor Feed chỉ lấy bài published, hiển thị nguồn an toàn, không lộ email/PII.
4. Ebook/sách vật lý bán chạy và mới nhất được tách riêng.
5. Góc ebook đọc thử chỉ lấy sách có chapter free; Sách cũ giá tốt chỉ lấy
   `physical + used_resale`.

Mỗi nhóm có loading/error/empty độc lập và giới hạn tối đa 5 sách ở Home.

## Browser evidence

- Guest Home dùng dữ liệu API local thật.
- CMS local rỗng hiển thị đúng fallback hero và empty Vendor Feed.
- Sáu nhóm commerce hiển thị đúng thứ tự và đúng type.
- 375/768/1024/1440 không tràn ngang.
- Các target trong Hero, recommendation, Vendor Feed và commerce feed đạt 44px.

## Gate kỹ thuật

- Frontend: 11 files, 67 tests đạt.
- Frontend build: đạt.
- `Phase6HomeFeedTest`: 4 tests, 18 assertions đạt.
- `Phase6PublicNavigationTest`: 2 tests, 7 assertions đạt.
- `git diff --check`: đạt.
- Cảnh báo EbookReader chunk lớn tiếp tục là backlog hiệu năng ngoài phạm vi 6C.

## Giới hạn riêng tư

Hệ thống chưa có consent cá nhân hóa chuyên biệt cho demographic/history. Vì
vậy 6C chỉ cá nhân hóa bằng favorite categories do người dùng chủ động chọn;
không dùng giới tính, ngày sinh hay lịch sử để suy luận ngầm.

