# Giai đoạn 6C.3 — Commerce/content feed

## Mục tiêu

Hoàn tất Home feed theo đúng thứ tự với sáu nhóm dữ liệu thật:

- ebook bán chạy;
- sách vật lý bán chạy;
- ebook mới nhất;
- sách vật lý mới nhất;
- ebook có góc đọc thử;
- sách cũ giá tốt.

## Phạm vi

- `backend/app/Http/Controllers/Api/BookController.php`
- `backend/tests/Feature/Phase6HomeFeedTest.php`
- `frontend/src/views/HomeView.vue`
- `frontend/src/components/BookCard.vue` chỉ khi cần page gate.

## Invariants

- Chỉ book sellable/published.
- Ebook và physical không trộn nhóm.
- Góc đọc thử phải có chapter `is_free`.
- Sách cũ phải đúng `physical + used_resale`.
- Mỗi nhóm gọi API và có loading/error/empty riêng.
- Tối đa 5 item mỗi nhóm trên Home.
- Không mock production, không commit/push/deploy.

## Prompt Antigravity dự phòng

> Thực hiện 6C.3 trong checkout chính. Chỉ sửa BookController,
> Phase6HomeFeedTest, HomeView và BookCard nếu page gate bắt buộc. Thêm filter
> `has_sample` an toàn; dựng sáu nhóm home feed bằng API thật, giới hạn 5,
> loading/error/empty độc lập, không trộn type/provenance. Giữ checkout và
> quyền actor. Kiểm tra 375/768/1024/1440, test/build/diff, không commit/push.

## Gate

- Type/provenance/sample filter đúng.
- Sáu section fail độc lập và đúng thứ tự.
- Không overflow, target 44px, card grid responsive.
- Focused backend test, frontend tests/build và diff check.

