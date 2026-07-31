# Batch 3B.1 — Final Runtime Correction

## 1. Kết luận nghiệm thu dẫn tới correction

Delta gate của correction trước đều pass, nhưng nghiệm thu code/bundle phát hiện:

1. Bốn view dùng top-level `await fetch...()` trong `<script setup>`, khiến bundle sinh `async setup`.
2. `App.vue` và `AdminLayout.vue` không bọc `RouterView` bằng `Suspense`; do đó thay đổi này có nguy cơ làm route không render ở runtime.
3. Test tự bọc component bằng `Suspense`, khác cây component production và che rủi ro trên.
4. Test có tên retry chỉ mount component lần hai; không kích hoạt nút retry.

Mục tiêu correction này là khôi phục lifecycle production an toàn và làm test phản ánh đúng hành vi thật. Không mở rộng chức năng.

## 2. Baseline không chạy lặp

Đã xác nhận trước correction này:

- Backend full: `237 tests`, `1048 assertions`, pass.
- Backend delta sau sửa N+1: `32 tests`, `96 assertions`, Pint pass.
- Frontend correction hiện tại: `10 tests`, pass.
- Frontend build, Oxlint, ESLint và `git diff --check`: pass.

Chỉ chạy lại các delta gate trong mục 7.

## 3. Allowed files

Chỉ được sửa:

- `frontend/src/views/vendor/InventoryAuditView.vue`
- `frontend/src/views/vendor/StockTransferView.vue`
- `frontend/src/views/vendor/LiveEditorView.vue`
- `frontend/src/views/vendor/MultiDevicePreviewView.vue`
- `frontend/src/__tests__/phase3_critical_contracts.spec.js`

## 4. Thay đổi bắt buộc

### Lifecycle của bốn view

- Bỏ top-level `await`.
- Khôi phục `onMounted(() => fetch...())` hoặc lifecycle tương đương nhưng `setup()` phải đồng bộ.
- Không thêm `Suspense` vào router/layout để hợp thức hóa test.
- Giữ nguyên loading/error/empty/retry state và `per_page: 100` đã thực hiện.

### Trạng thái sau lỗi

- Trong `InventoryAuditView` và `StockTransferView`, khi chuỗi request khởi tạo lỗi phải xóa toàn bộ dữ liệu có thể đã được nạp một phần:
  - list chính;
  - `warehouses`;
  - `books`.
- Không để selector giữ dữ liệu cũ sau một lần refresh/retry thất bại.

### Test hành vi

- Không dùng `Suspense` để mount bốn view.
- Không dùng SSR làm cách duy nhất để chứng minh lifecycle `onMounted`, vì SSR không chạy lifecycle client.
- Dùng Vue runtime hiện có, ví dụ custom renderer nhỏ trong chính test, để:
  - mount component theo lifecycle client mà không cần dependency mới;
  - chờ các promise/lifecycle hoàn tất;
  - quan sát text/props/handler của host tree.
- Bổ sung assertion chứng minh view mount đồng bộ mà không có cảnh báo async-setup/Suspense.
- Test retry phải tìm và kích hoạt handler của nút `Thử lại` trên cùng component instance, rồi xác nhận request được gọi lại. Không được giả lập retry bằng unmount/remount hoặc mount instance thứ hai.
- Giữ các test:
  - `per_page: 100`;
  - API error không sinh dữ liệu giả;
  - empty state khác error state.
- Không quay lại `fs.readFileSync` hoặc source-string grep.

## 5. Invariants

- Không đổi endpoint/response contract.
- Không đổi nghiệp vụ chapter, audit hoặc stock transfer.
- Không thêm mock/fallback thành công.
- Không thêm dependency hoặc file nguồn mới.
- Không sửa router/layout/Vite config để phục vụ test.

## 6. Forbidden actions

- Không chạm backend, migration, database hoặc production.
- Không chạm file ngoài allowed list.
- Không chạy lint với `--fix` hoặc mass-format.
- Không reset, checkout, stash, clean, commit hoặc push.

## 7. Delta gates

Từ `frontend`:

- `npm.cmd test -- --run src/__tests__/phase3_critical_contracts.spec.js`
- `npm.cmd run build`
- `npx.cmd --no-install oxlint src/views/vendor/InventoryAuditView.vue src/views/vendor/StockTransferView.vue src/views/vendor/LiveEditorView.vue src/views/vendor/MultiDevicePreviewView.vue src/__tests__/phase3_critical_contracts.spec.js`
- `npx.cmd --no-install eslint src/views/vendor/InventoryAuditView.vue src/views/vendor/StockTransferView.vue src/views/vendor/LiveEditorView.vue src/views/vendor/MultiDevicePreviewView.vue src/__tests__/phase3_critical_contracts.spec.js`

Từ repository root:

- `git diff --check`

## 8. Báo cáo

Báo rõ:

- cách đảm bảo `setup()` đồng bộ;
- cách custom renderer thực thi lifecycle client;
- bằng chứng retry handler được gọi trên cùng instance;
- kết quả từng delta gate.

Không commit hoặc push.
