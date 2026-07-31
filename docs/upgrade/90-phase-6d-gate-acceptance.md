# Gate 6D — Nghiệm thu cục bộ Catalog và khám phá sách

Ngày nghiệm thu: 2026-07-29

## Phạm vi đạt

- 6D.1: Catalog filters, sáu khoảng giá, lứa tuổi, định dạng, Sách cũ và URL state.
- 6D.2: BookCard/cover system dùng chung cho Home và Catalog.
- 6D.3: Book Detail, Flash Sale, Blog và Article.

## Browser gate

- Đã kiểm tra trực tiếp 375/768/1024/1440 cho Catalog, Home, Book Detail, Flash Sale và Blog; không horizontal overflow.
- Catalog drawer: backdrop, Escape, `aria-hidden`, `inert`, deep link và back/forward đạt.
- Book card: bìa `object-fit: contain`, ảnh `border-radius: 0px`; ba tiện ích 44x44; touch/mobile hiện, desktop hover/focus hiện.
- Book Detail: bìa contain/no radius; ebook ghi `Phiên bản 2`, không có select phiên bản mua; tag và review stars 44px.
- Smoke Book Detail → Thêm vào giỏ → Mua ngay → `/cart` đạt.
- Flash Sale local hiện empty state đúng dữ liệu; không dùng external image fallback.
- Blog local hiện empty published state; Article route không tồn tại hiện error/back state đúng. Local hiện chưa có bài published để kiểm tra content-state của một bài thật.

## Gate tự động

- Frontend: 11 file test, 67 test đạt.
- Frontend production build: đạt.
- Backend `Phase6PublicNavigationTest`: 3 test, 10 assertion đạt.
- `git diff --check`: đạt.

Đây là nghiệm thu local. Không có commit, push, deploy, production hoặc database thật.
