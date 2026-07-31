# Batch 3B.1 — Acceptance Correction Plan

## 1. Mục tiêu

Khép các sai lệch còn lại sau nghiệm thu độc lập Batch 3B.1, không mở rộng sang 3C:

1. Các màn hình vendor đã khai báo `error` nhưng phải thực sự render error state và retry.
2. Bộ chọn sách vật lý phải tải đủ tập sách vendor dự kiến dùng, không âm thầm chỉ lấy trang mặc định đầu tiên.
3. Test frontend phải kiểm tra hành vi/contract có thể thực thi; không dùng `fs.readFileSync` và dò chuỗi nguồn như bằng chứng chính.

## 2. Baseline đã được Codex xác nhận — không lặp lại

Trước correction:

- Backend full regression: `237 tests`, `1048 assertions`, pass.
- Frontend full Vitest: `17 tests`, pass.
- Frontend production build: pass.
- ESLint trên toàn bộ file frontend Batch 3B.1: pass.
- Sau correction N+1 của Codex trong ba file backend: `32 tests`, `96 assertions`, Pint và `git diff --check` đều pass.

Correction này chỉ chạm frontend. Antigravity không chạy lại backend test, backend Pint hoặc toàn bộ các gate 3B.1. Codex chỉ tái chạy gate theo delta ở mục 7; full regression tiếp theo được dồn tới mốc đóng 3C hoặc khi có thay đổi hạ tầng dùng chung.

## 3. Allowed files

Chỉ được sửa:

- `frontend/src/views/vendor/InventoryAuditView.vue`
- `frontend/src/views/vendor/StockTransferView.vue`
- `frontend/src/views/vendor/LiveEditorView.vue`
- `frontend/src/views/vendor/MultiDevicePreviewView.vue`
- `frontend/src/__tests__/phase3_critical_contracts.spec.js`

Không tạo file nguồn mới. Nếu không thể viết test hành vi với dependency hiện có, dừng và báo blocker; không cài package.

## 4. Corrections bắt buộc

### InventoryAuditView và StockTransferView

- Gọi `/api/vendor/books` với `{ params: { per_page: 100 } }`.
- Tiếp tục xử lý đúng response paginator hiện có.
- Khi bất kỳ request khởi tạo nào lỗi:
  - không render danh sách/selector như tải thành công;
  - render error state rõ ràng;
  - có nút gọi lại đúng hàm fetch;
  - không giữ dữ liệu cũ hoặc dữ liệu giả.
- Khi API thành công nhưng danh sách rỗng, render empty state trung thực, phân biệt với error.

### LiveEditorView

- `error` phải được dùng trong template.
- Khi tải book/chapter lỗi, render error state và retry; không mở editor với draft/chapter giả.
- Khi API thành công nhưng chưa có chapter, có thể cho phép luồng tạo chapter mới hiện hữu, nhưng phải phân biệt rõ empty state với API error.

### MultiDevicePreviewView

- Khi tải lỗi, render error state và nút retry.
- Khi tải thành công nhưng chưa có chapter, render empty state; không sinh nội dung mẫu.
- Không gọi API lặp ngoài thao tác retry hoặc lifecycle dự kiến.

### Frontend tests

- Xóa cách test dùng `fs.readFileSync`, `path`, `fileURLToPath` và dò literal trong source.
- Dùng Vitest với mock `apiClient` và Vue runtime/dependency hiện có để kiểm tra tối thiểu:
  1. hai selector gửi `per_page: 100`;
  2. API reject làm xuất hiện error state và không sinh item giả;
  3. thao tác retry gọi lại request;
  4. API success với mảng rỗng tạo empty state, không bị xem là error.
- Có thể stub PrimeVue/router/toast bằng Vitest. Không cài `@vue/test-utils`, jsdom hoặc dependency mới.
- Nếu giới hạn môi trường khiến việc mount component không khả thi, dừng và báo blocker thay vì quay lại source-grep test.

## 5. Invariants

- Không thay endpoint backend hoặc response contract.
- Không thêm mock/fallback/hardcode “thành công”.
- Không đổi workflow tạo/sửa chapter, inventory audit hoặc stock transfer.
- Không thay dependency, `package.json`, lockfile, Vite config hay router.
- Không chạm file ngoài allowed list.

## 6. Forbidden actions

- Không sửa backend, migration, database hoặc tài liệu khác.
- Không chạy command có `--fix`, không mass-format.
- Không chạm `.env`, production, service hoặc Cloudflare.
- Không reset, checkout, stash, clean, commit hoặc push.

## 7. Delta acceptance gates

Chạy từ `frontend`:

- `npm.cmd test -- --run src/__tests__/phase3_critical_contracts.spec.js`
- `npm.cmd run build`
- `npx.cmd --no-install oxlint src/views/vendor/InventoryAuditView.vue src/views/vendor/StockTransferView.vue src/views/vendor/LiveEditorView.vue src/views/vendor/MultiDevicePreviewView.vue src/__tests__/phase3_critical_contracts.spec.js`
- `npx.cmd --no-install eslint src/views/vendor/InventoryAuditView.vue src/views/vendor/StockTransferView.vue src/views/vendor/LiveEditorView.vue src/views/vendor/MultiDevicePreviewView.vue src/__tests__/phase3_critical_contracts.spec.js`

Chạy từ repository root:

- `git diff --check`

## 8. Báo cáo kết quả

Báo:

- file đã sửa;
- hành vi error/retry/empty đã khép;
- loại test hành vi đã dùng;
- kết quả từng delta gate;
- blocker còn lại, nếu có.

Không commit hoặc push.
