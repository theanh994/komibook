# Giai đoạn 6C.1 — Hero carousel và gợi ý sách

## Mục tiêu

Đưa hai tầng đầu của Home feed về dữ liệu thật và tách biệt lỗi:

1. Hero carousel dùng bài viết `home_featured` đã published; nếu CMS chưa có dữ
   liệu thì dùng một slide KomiBook trung tính, không gọi ảnh ngoài.
2. “Gợi ý dành riêng cho bạn” dùng sở thích thể loại đã chủ động lưu khi có;
   nếu không có thì trả fallback phổ biến và giải thích rõ.

## Phạm vi source

- `backend/app/Http/Controllers/Api/BookController.php`
- `backend/routes/api.php`
- `backend/tests/Feature/Phase6HomeFeedTest.php`
- `frontend/src/views/HomeView.vue`
- `frontend/src/components/BookCard.vue` (chỉ target/label của card dùng trong recommendation)

## Invariants

- Chỉ lấy book sellable/published.
- Không dùng giới tính, ngày sinh hoặc lịch sử khi chưa có consent cá nhân hóa
  chuyên biệt.
- Không lộ PII, không trả danh sách độc giả.
- Hero chỉ lấy article published và `published_at <= now()`.
- Mỗi khối có loading/error/empty độc lập; lỗi một khối không làm mất khối khác.
- Không mock dữ liệu production, không gọi ảnh/font ngoài.
- Không đổi route guard, checkout hoặc quyền actor.
- Không commit, push hay deploy.

## Prompt Antigravity dự phòng

> Thực hiện 6C.1 trong checkout chính. Chỉ sửa BookController, api.php,
> Phase6HomeFeedTest và HomeView. Tạo endpoint recommendation an toàn:
> ưu tiên favorite categories đã lưu của user nếu có, nếu không dùng fallback
> popularity; trả mode và explanation, chỉ book sellable, không dùng PII.
> Hero lấy article home_featured published từ API hiện có, có fallback local,
> carousel semantic, target 44px và reduced motion. Mỗi section có
> loading/error/empty riêng. Không commit/push. Báo cáo file đổi, test/build và
> browser evidence 375/768/1024/1440.

## Gate

- Guest fallback recommendation.
- Authenticated user có favorite category nhận đúng mode/scope.
- Hero chỉ hiển thị article hợp lệ hoặc fallback local.
- Carousel keyboard/button, không tràn ngang ở 375/768/1024/1440.
- Focused backend test, frontend tests, build và diff check.
