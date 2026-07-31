# Giai đoạn 6D.1 — Catalog filters

## Mục tiêu

- Lọc theo lứa tuổi.
- Thay nhập giá tự do bằng sáu khoảng giá cố định.
- Lọc Tất cả / Sách giấy / Ebook / Sách cũ.
- Đồng bộ category, search, type, age, price, sort và page vào URL.
- Back/forward khôi phục đúng trạng thái.
- Mobile dùng filter drawer có backdrop, Escape và đóng khi áp dụng.

## Phạm vi

- `backend/app/Http/Controllers/Api/BookController.php`
- `backend/tests/Feature/Phase6PublicNavigationTest.php`
- `frontend/src/views/CatalogView.vue`

## Invariants

- Book public vẫn chỉ sellable/published.
- Sách cũ luôn là `physical + used_resale`.
- Sáu khoảng giá dùng effective price `COALESCE(sale_price, price)`.
- Không thay route guard, cart hay checkout.
- Không commit, push hoặc deploy.

## Prompt Antigravity dự phòng

> Thực hiện 6D.1 trong checkout chính. Chỉ sửa BookController,
> Phase6PublicNavigationTest và CatalogView. Thêm target_age filter, sáu price
> bands, format/Sách cũ, URL state/back-forward và filter drawer mobile có
> Escape/backdrop/inert phù hợp. Chỉ book sellable; used là physical +
> used_resale. Kiểm tra 375/768/1024/1440, keyboard, build/tests/diff. Không
> commit/push.

## Gate

- Age/price/type/provenance backend filter đúng.
- Reload/deep link/back/forward giữ state.
- Drawer 375/768 đóng/mở/Escape.
- Không overflow, target 44px.
- Focused backend tests, frontend tests/build và diff check.

