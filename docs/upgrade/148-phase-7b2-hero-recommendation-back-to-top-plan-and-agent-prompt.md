# Giai đoạn 7B.2 — Kế hoạch hero, gợi ý responsive, nút lên đầu trang và prompt Antigravity

Ngày: 29/07/2026  
Trạng thái ban đầu: in-progress

## Mục tiêu thiết kế

- Fallback hero dùng ảnh giá sách riêng của KomiBook thay vì mảng nền xanh đen trống; bài viết nổi bật có ảnh thật vẫn được ưu tiên.
- Tạo asset hero mới, không dùng bìa/CCCD/avatar hoặc dữ liệu riêng tư của người dùng; không có logo, chữ hoặc watermark trong ảnh.
- “Gợi ý dành riêng cho bạn” lấy tối đa 5 sách: 2 card ở mobile hẹp, 3 ở mobile lớn, 4 ở tablet và 5 ở desktop; không tạo hàng thứ hai.
- Nút lên đầu trang xuất hiện sau khi cuộn, 44×44 px, không che nội dung/mobile navigation, có focus rõ và tôn trọng reduced motion.

## Phạm vi

- Asset `frontend/src/assets/komibook-bookshelf-hero.webp` được tạo bằng built-in image generation và tối ưu WebP cho web.
- `HomeView.vue` cho fallback hero và recommendation count/layout.
- `BookController::recommendations` chỉ giới hạn kết quả Home còn 5, không đổi cách xếp hạng/cá nhân hóa.
- `App.vue` cho back-to-top ở các trang có public shell.
- Vitest chuyên biệt, tài liệu gate và ledger.

Không thay CMS hero thật, API recommendation algorithm, dữ liệu sách, route hoặc layout dashboard/reader; không commit/push/deploy.

## Invariants

- Hero API có ảnh vẫn hiển thị ảnh API; fallback chỉ dùng khi không có nội dung nổi bật.
- Ảnh asset Vite và URL `/storage` đều được phân giải đúng.
- API recommendation không cần trả quá 5 item cho Home; frontend tiếp tục xử lý zero/error state trung thực.
- Back-to-top không hiện ngay đầu trang, không dùng smooth scroll khi người dùng yêu cầu reduced motion.

## Gate

- Unit: fallback có asset, recommendation slice 5, quy tắc số card theo breakpoint, back-to-top visibility/behavior.
- Regression Home feed; ESLint, build, `git diff --check`.
- Browser visual 375/768/1024/1440 khi local browser policy cho phép; UAT trực tiếp nếu điều khiển loopback tiếp tục bị chặn.

## Prompt Antigravity dự phòng

> Bảo toàn dirty worktree. Batch 7B.2: dùng asset giá sách công khai trong `frontend/src/assets` làm fallback hero, vẫn ưu tiên ảnh CMS thật. Home recommendations chỉ lấy tối đa 5; CSS hiển thị 2/3/4/5 card theo breakpoint và không xuống hàng thứ hai. Thêm back-to-top 44×44 trong App public shell, hiện sau scroll, focus rõ, không che mobile, reduced-motion dùng auto scroll. Không dùng ảnh riêng tư/bìa người dùng làm hero, không đổi thuật toán API/CMS/schema, không commit/push/deploy. Thêm Vitest, chạy lint/build/diff và báo changed-files.
