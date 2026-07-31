# Giai đoạn 11 — KomiBook Newsroom

Ngày triển khai: 2026-07-30

## Mục tiêu

Nâng lõi CMS Giai đoạn 4 thành Newsroom chuyên nghiệp cho Admin, Nhà bán và độc giả,
hoàn thành trước khi tích hợp chatbot.

## Phạm vi đã triển khai

1. **11A:** chốt quyền Admin/Nhà bán/độc giả và page override Newsroom.
2. **11B:** mở rộng `articles`; thêm media, bình luận, audit bình luận, review cộng
   đồng và metric theo ngày.
3. **11C:** tách trang quản lý Newsroom Admin với lọc, KPI và lifecycle.
4. **11D:** thêm Newsroom Nhà bán, tenant isolation và luồng gửi duyệt.
5. **11E:** trình viết Quill, tự lưu, xem trước, ảnh bìa, sách liên quan, SEO và
   lịch sử phiên bản.
6. **11F:** trang danh sách/chi tiết công khai, bài liên quan, sách liên quan,
   gian hàng, chia sẻ và lên đầu trang.
7. **11G:** bình luận chờ duyệt, rate limit, hash email và audit moderation.
8. **11H:** form review cộng đồng, xác minh giao dịch khi có, hàng đợi biên tập,
   chuyển thành bản nháp và analytics.

## Bất biến

- Không tạo CMS song song và không thay Help Center.
- Nội dung công khai chỉ lấy bài `published` đến hạn.
- Nhà bán không tự duyệt/xuất bản và không đọc/sửa bài của Nhà bán khác.
- Review cộng đồng và bình luận không tự công khai.
- HTML được làm sạch ở máy chủ và tại render boundary.
- Không commit, push, deploy hoặc sử dụng database production trong batch này.

## Gate

- Targeted và full backend/frontend.
- Pint scoped, ESLint scoped, production build và `git diff --check`.
- Migration fresh → rollback migration 11 → migrate lại trên SQLite cách ly.
- Browser smoke public và management route ở các viewport chính.
