# Batch 3C.3 — Unbacked Public Content Closure

## 1. Mục tiêu

Đóng khoảng trống cuối của nhóm 3C mà không tự phát minh backend contract:

1. Blog/news hiện hiển thị toàn bộ bài viết, tác giả, ngày, lượt bình luận, xu hướng và hành động điều hướng hardcode dù repository không có model, route hay API blog.
2. Newsletter và nhiều liên kết footer hiện trông như hành động thật nhưng không có submit handler hoặc đích điều hướng thật.

Invariant: **chức năng chưa có backend/route được biểu diễn là chưa khả dụng; UI không dựng nội dung, số liệu, liên kết hoặc thông báo thành công giả.**

Batch này không xây Blog CMS/API. Việc thiết kế schema, quyền xuất bản, pagination và public API thuộc 3D contract-first sau khi được phê duyệt riêng.

## 2. Baseline đã chấp nhận — không chạy lặp

Batch 3C.2 đã được Codex nghiệm thu sau correction:

- Frontend delta: `8 tests`, pass.
- Production build: pass.
- Oxlint: `0 warnings`, `0 errors`.
- ESLint: pass.
- `git diff --check`: pass.

Không chạy lại test 3B, 3C.1 hoặc 3C.2. Chỉ chạy gate mục 7.

## 3. Bằng chứng trực tiếp

### BlogView

- `featuredPost`, `posts`, `popularPosts`, categories và tags đều được hardcode.
- Tác giả, ngày đăng, excerpt, ảnh, số bình luận `24`, nhãn `Hot` và thời gian đọc không đến từ API.
- `goToPost` chỉ `console.log`; repository chỉ có `/blog`, không có route detail.
- Form newsletter không có submit contract.
- Backend không có Blog/Post/News model, controller, route hoặc migration.

### AppFooter

- Newsletter email và nút “Gửi” không có handler/API.
- Nhiều link dùng `href="#"`, gây cảm giác có trang thật nhưng không điều hướng tới contract nào.
- Help Center đã có route thật `/help-center`; các mục chưa có route không được giả làm liên kết hoạt động.

## 4. Allowed files

- `frontend/src/views/BlogView.vue`
- `frontend/src/components/layout/AppFooter.vue`
- `frontend/src/__tests__/phase3_unbacked_content.spec.js` — **NEW**

Mọi file khác đều bị cấm sửa. Không thêm route, backend, store, dependency hoặc asset.

## 5. Thay đổi bắt buộc

### BlogView

- Xóa toàn bộ post/featured/popular/category/tag hardcode và mọi số liệu trang trí giống dữ liệu thật.
- Không gọi một endpoint chưa tồn tại.
- Hiển thị honest unavailable state:
  - tiêu đề Blog/Tin tức có thể giữ;
  - giải thích nội dung biên tập chưa được mở vì chưa có nguồn dữ liệu được xác minh;
  - có link thật về trang chủ và Help Center;
  - không có search/filter/pagination/detail action giả.
- Xóa newsletter form, `console.log` navigation và ảnh external hardcode.
- Không ghi “sắp ra mắt vào ngày…” hoặc tạo timeline giả.

### AppFooter

- Newsletter:
  - xóa form giả; hoặc
  - render text “Đăng ký nhận tin chưa khả dụng” với control disabled rõ ràng.
- Thay link Help Center bằng `router-link` tới `/help-center`.
- Với mục chưa có route/URL thật:
  - render text không tương tác hoặc disabled semantic element;
  - không dùng `href="#"`;
  - không tạo URL/social profile giả.
- Giữ layout và branding trong phạm vi hợp lý; không redesign toàn footer.

## 6. Automated tests

Tạo `phase3_unbacked_content.spec.js` bằng render/hành vi component; không dùng `fs.readFileSync`, source grep hoặc assertion đọc source.

Ít nhất kiểm tra:

1. Blog render unavailable state và link thật về Home/Help Center.
2. Blog không render featured/article/popular records hoặc số bình luận giả.
3. Blog không có search, load-more, newsletter submit hay clickable detail action.
4. Footer có Help Center route thật.
5. Footer newsletter không thể submit thành công giả.
6. Footer không render placeholder navigation `href="#"`.

Không cài dependency mới.

## 7. Delta acceptance gates

Từ `frontend`:

- `npm.cmd test -- --run src/__tests__/phase3_unbacked_content.spec.js`
- `npm.cmd run build`
- `npx.cmd --no-install oxlint src/views/BlogView.vue src/components/layout/AppFooter.vue src/__tests__/phase3_unbacked_content.spec.js`
- `npx.cmd --no-install eslint src/views/BlogView.vue src/components/layout/AppFooter.vue src/__tests__/phase3_unbacked_content.spec.js`

Từ repository root:

- `git diff --check`

## 8. Forbidden scope

- Không tạo Blog model/controller/route/migration/seed/admin UI.
- Không thêm newsletter endpoint hoặc lưu email.
- Không thêm external social URL không được cung cấp.
- Không sửa router, layout khác, backend, dependency, package hoặc lockfile.
- Không chạm `.env`, production, service, Cloudflare hoặc database.
- Không reset, checkout, stash, clean, commit hoặc push.

## 9. Báo cáo

Báo:

- file đã sửa;
- hardcode và action giả đã loại bỏ;
- unavailable/disabled state sau sửa;
- kết quả từng gate;
- contract gap còn lại cho 3D.

Không commit hoặc push.
