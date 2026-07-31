# Implementation Plan - Batch 3B.1: Critical Frontend–Backend Contract Closure

Khép kín ba nhóm contract quan trọng của Giai đoạn 3:
1. **Ebook ownership & access contract** (`EbookAccessService`, My Library, Reader, Annotations).
2. **Customer Order Detail & Invoice** (`/api/my-orders/{order}` real data contract & Invoice print without mock fallbacks).
3. **Vendor Book Selector & Mock Fallback Cleanup** (`/api/vendor/books` integration across 7 vendor views, deleting mock fallbacks, handling error/retry states).

---

## Allowed Files Scoping (Ranh giới tệp được phép)

### Backend
- `backend/routes/api.php`
- `backend/app/Http/Controllers/Api/BookController.php`
- `backend/app/Http/Controllers/Api/OrderController.php`
- `backend/app/Http/Controllers/Api/BookAnnotationController.php`
- `backend/app/Http/Resources/OrderResource.php`
- `backend/app/Services/EbookAccessService.php` — [NEW]
- `backend/app/Http/Resources/CustomerOrderDetailResource.php` — [NEW]
- `backend/tests/Feature/Phase3CriticalContractsTest.php` — [NEW]
- `backend/tests/Feature/AuthorDrmInventoryTest.php` — chỉ khi cần mở rộng test hiện hữu liên quan trực tiếp

### Frontend
- `frontend/src/router/index.js`
- `frontend/src/views/BookDetailView.vue`
- `frontend/src/views/MyLibraryView.vue`
- `frontend/src/views/MyAnnotationsView.vue`
- `frontend/src/views/EbookReaderView.vue`
- `frontend/src/views/OrdersView.vue`
- `frontend/src/views/orders/InvoicePrintView.vue`
- `frontend/src/views/vendor/BookChaptersView.vue`
- `frontend/src/views/vendor/DrmSettingsView.vue`
- `frontend/src/views/vendor/InventoryAuditView.vue`
- `frontend/src/views/vendor/LiveEditorView.vue`
- `frontend/src/views/vendor/MultiDevicePreviewView.vue`
- `frontend/src/views/vendor/StockTransferView.vue`
- `frontend/src/views/vendor/StockTransferPrintView.vue`
- `frontend/src/__tests__/phase3_critical_contracts.spec.js` — [NEW]

### Ranh giới nghiêm ngặt
- Mọi file không nằm trong danh sách trên đều bị cấm sửa.
- Không tạo/sửa bất kỳ file migration nào.
- Không thay đổi dependency, `package.json` hay file lock.
- Không thay đổi semantics, state machine, payment, VNPAY, inventory reservation, fulfillment, ledger hoặc outbox Giai đoạn 2.
- Không sửa/xóa bất kỳ tài liệu untracked nào trong `docs/upgrade` ngoại trừ chính tệp `31-phase-3b1-implementation-plan.md`.
- Không chạm vào `.env`, production config, service, Cloudflare hoặc database.
- Không reset, checkout, stash, clean, commit hoặc push.
- Nếu phát sinh nhu cầu chạm file ngoài danh sách cho phép, dừng ngay lập tức và báo cáo blocker.

---

## Proposed Changes

### 1. Ebook Access Contract & Service

#### [NEW] [EbookAccessService.php](file:///c:/Projects/DoAnTotNGhiep_komibook/backend/app/Services/EbookAccessService.php)
- Nguồn quyết định quyền truy cập ebook dùng chung.
- Một user chỉ có quyền truy cập ebook khi thỏa mãn đồng thời:
  1. `order` thuộc chính user đó (`order->user_id === $user->id`);
  2. `order` chứa đúng book (`orderItems` chứa `book_id`);
  3. `book` có `type === 'ebook'`;
  4. `order` không ở trạng thái `cancelled` hoặc `refunded`;
  5. `order->payment_status === 'paid'` hoặc `order->status === 'completed'`.
- Phương thức `getValidOrder(User $user, int $bookId): ?Order`:
  - Tìm đơn hàng hợp lệ đáp ứng đủ điều kiện trên.
  - Nếu có nhiều order hợp lệ cho cùng ebook, cách chọn order phải xác định và ổn định, ưu tiên order hợp lệ mới nhất (`created_at` desc) rồi đến `id` mới nhất (`id` desc).
- Phương thức `checkAccess(User $user, int $bookId): bool`: kiểm tra xem user có đơn hàng ebook hợp lệ hay không.
- Phương thức `getOwnershipData(User $user, int $bookId): array`:
  - Khi đủ quyền: `{ owned: true, order_id: $order->id, book_id: (int) $bookId }`.
  - Khi không có quyền (unpaid, cancelled, refunded, physical book, không chính chủ): trả về shape chuẩn hóa `{ owned: false, order_id: null, book_id: (int) $bookId }`, tuyệt đối không làm lộ order của user khác.

#### [MODIFY] [BookController.php](file:///c:/Projects/DoAnTotNGhiep_komibook/backend/app/Http/Controllers/Api/BookController.php)
- Refactor `checkOwnership`: sử dụng `EbookAccessService::getOwnershipData`.
- Giữ canonical endpoint `/api/books/{id}/check-ownership`; tuyệt đối không tạo alias `/ownership`.
- Trả về response chuẩn hóa:
  `{ status: "success", data: { owned: bool, order_id: int|null, book_id: int } }`.

#### [MODIFY] [OrderController.php](file:///c:/Projects/DoAnTotNGhiep_komibook/backend/app/Http/Controllers/Api/OrderController.php)
- Refactor `generateEbookLink`:
  - Sử dụng `EbookAccessService::getValidOrder($request->user(), $book_id)`.
  - Fail closed: Nếu không có order hợp lệ hoặc order_id trong URL không khớp với order đủ quyền, trả về HTTP 403 `['message' => 'Đơn hàng chưa được thanh toán hoặc không đủ quyền truy cập.']`.
- Refactor `myLibrary`:
  - Siết contract `myLibrary`: với từng ebook item, bắt buộc dùng `EbookAccessService` để xác định quyền đọc.
  - Chỉ ebook đủ quyền mới được trả về `order_id` hợp lệ và trạng thái cho phép đọc. Ebook unpaid, cancelled, refunded hoặc không thuộc user sẽ không được cấp `order_id` để đọc.
  - Không được chỉ sửa `$order->order_status` thành `$order->status` rồi vẫn trả toàn bộ ebook từ mọi đơn hàng.
  - Physical book tiếp tục xuất hiện nếu có trong đơn hàng để phục vụ điều hướng tracking (`/tracking/{order_id}`), nhưng không được coi physical book là ebook ownership.
  - Tuyệt đối không trả private `file_path` trong response `myLibrary`.

#### [MODIFY] [BookAnnotationController.php](file:///c:/Projects/DoAnTotNGhiep_komibook/backend/app/Http/Controllers/Api/BookAnnotationController.php)
- Kiểm tra quyền truy cập ebook qua `EbookAccessService` nhất quán cho các hành động:
  - `store`: Bắt buộc user có quyền truy cập ebook `book_id` đang lưu annotation. Nếu không đủ quyền, trả HTTP 403.
  - `recent`: Bắt buộc user có quyền truy cập ebook `$bookId`. Nếu không đủ quyền, trả HTTP 403.
  - `index`: Khi lọc theo `book_id`, kiểm tra quyền truy cập ebook đó. Khi lấy toàn bộ danh sách annotation của user, chỉ trả về các annotation thuộc về các ebook mà user hiện tại vẫn đủ quyền truy cập.
- Mỗi annotation trả về trong `index` và `store` phải có `book_id` và `order_id` hợp lệ (lấy từ order hợp lệ xác định từ `EbookAccessService`) để frontend My Annotations điều hướng chính xác đến reader.
- Tránh N+1 query: Eager load `book` và giải quyết `order_id` theo tập hợp sách hoặc truy vấn tối ưu.

#### Frontend Updates (Ebook Access)
#### [MODIFY] [BookDetailView.vue](file:///c:/Projects/DoAnTotNGhiep_komibook/frontend/src/views/BookDetailView.vue)
- Gọi canonical endpoint `/api/books/${bookId}/check-ownership`.
- Điều hướng reader luôn dùng route name `ebook-reader` với params `{ orderId, bookId }`.
#### [MODIFY] [MyLibraryView.vue](file:///c:/Projects/DoAnTotNGhiep_komibook/frontend/src/views/MyLibraryView.vue)
- Sửa nút đọc ebook: điều hướng qua route name `ebook-reader` với `{ orderId, bookId }`, tuyệt đối không dùng `/read/...`.
- Chỉ trao nút "Đọc Ngay" cho ebook khi item có đủ quyền từ backend contract.
- Bỏ `readingProgress = 45` giả định; khi chưa có dữ liệu thật thì ẩn thanh progress.
#### [MODIFY] [MyAnnotationsView.vue](file:///c:/Projects/DoAnTotNGhiep_komibook/frontend/src/views/MyAnnotationsView.vue)
- Nút `goToReader` chỉ thực hiện điều hướng khi annotation có đủ `order_id` và `book_id` hợp lệ.
#### [MODIFY] [EbookReaderView.vue](file:///c:/Projects/DoAnTotNGhiep_komibook/frontend/src/views/EbookReaderView.vue)
- Xử lý khi API từ chối quyền (403): hiển thị error state rõ ràng kèm nút thử lại, không để màn hình treo hoặc giả định thành công.

---

### 2. Customer Order Detail và Invoice

#### [MODIFY] [api.php](file:///c:/Projects/DoAnTotNGhiep_komibook/backend/routes/api.php)
- Đăng ký endpoint `GET /api/my-orders/{order}` trong middleware group `auth:sanctum`.

#### [NEW] [CustomerOrderDetailResource.php](file:///c:/Projects/DoAnTotNGhiep_komibook/backend/app/Http/Resources/CustomerOrderDetailResource.php)
- Transform Order model cho khách hàng xem chi tiết với dữ liệu thật 100%: order code, timestamps (`created_at`), status, payment_status, payment_method, shipping_status, shipping_carrier, shipping_tracking_code, thông tin người mua được phép trả (name, email, phone), shipping_address, totals (`total_amount`) và danh sách `items` (gồm book id, title, cover_image, type, price, quantity).

#### [MODIFY] [OrderController.php](file:///c:/Projects/DoAnTotNGhiep_komibook/backend/app/Http/Controllers/Api/OrderController.php)
- Bổ sung method `myOrderDetail(Request $request, $orderId)`:
  - Chỉ tìm order thuộc về chính user đăng nhập (`where('user_id', $request->user()->id)`).
  - Với order của user khác hoặc không tồn tại: trả HTTP 404 nhất quán `{ status: "error", message: "Đơn hàng không tồn tại." }`, không làm lộ thông tin đơn hàng.
  - Trả về `new CustomerOrderDetailResource($order)`.

#### [MODIFY] [InvoicePrintView.vue](file:///c:/Projects/DoAnTotNGhiep_komibook/frontend/src/views/orders/InvoicePrintView.vue)
- Gọi endpoint mới `/api/my-orders/${orderId}`, không gọi `/api/orders`.
- Xóa toàn bộ fallback invoice giả (`order.value = { order_code: 'ORD-98765'... }`).
- Sử dụng đúng response field `items` (không nhầm với `order_items`).
- Không dựng customer, seller, invoice number, tax, subtotal hay total giả.
- Nếu trường chưa tồn tại trên backend, hiển thị `—` hoặc ẩn section với nhãn trung thực.
- Khi API lỗi: hiển thị error state và nút retry; không bao giờ render hóa đơn mẫu.

#### [MODIFY] [OrdersView.vue](file:///c:/Projects/DoAnTotNGhiep_komibook/frontend/src/views/OrdersView.vue)
- Đảm bảo liên kết mở hóa đơn điều hướng đúng route `/orders/invoice/${order.id}` với order ID hợp lệ.

---

### 3. Vendor Book Selector & Mock Fallback Cleanup

Loại bỏ hoàn toàn việc gọi `/api/books` public trong 7 màn hình vendor; chuyển sang dùng `/api/vendor/books` (hoặc `/api/vendor/books/{id}`); xử lý đúng response phân trang (`res.data.data` / `res.data`); xóa sạch dữ liệu giả/fallback; bổ sung state loading/empty/error/retry.

#### [MODIFY] [BookChaptersView.vue](file:///c:/Projects/DoAnTotNGhiep_komibook/frontend/src/views/vendor/BookChaptersView.vue)
- Lấy thông tin sách qua `/api/vendor/books/${bookId}`.
- Xóa mock chapters fallback (`chapters.value = [...]`).
- Thêm error state & retry button khi API lỗi.
#### [MODIFY] [DrmSettingsView.vue](file:///c:/Projects/DoAnTotNGhiep_komibook/frontend/src/views/vendor/DrmSettingsView.vue)
- Lấy thông tin sách qua `/api/vendor/books/${bookId}`.
- Bổ sung error state & retry button.
#### [MODIFY] [InventoryAuditView.vue](file:///c:/Projects/DoAnTotNGhiep_komibook/frontend/src/views/vendor/InventoryAuditView.vue)
- Lấy danh sách sách physical qua `/api/vendor/books` (với `per_page: 100` hoặc lọc phù hợp).
- Xóa mock audit list fallback và mock detail fallback trong file.
- Bổ sung error state & retry button.
#### [MODIFY] [LiveEditorView.vue](file:///c:/Projects/DoAnTotNGhiep_komibook/frontend/src/views/vendor/LiveEditorView.vue)
- Lấy sách qua `/api/vendor/books/${bookId}`.
- Xóa mock chapters fallback. Bổ sung error state & retry button.
#### [MODIFY] [MultiDevicePreviewView.vue](file:///c:/Projects/DoAnTotNGhiep_komibook/frontend/src/views/vendor/MultiDevicePreviewView.vue)
- Lấy sách qua `/api/vendor/books/${bookId}`.
- Xóa nội dung chương giả khi API lỗi hoặc không có chương. Dùng empty state thật.
#### [MODIFY] [StockTransferView.vue](file:///c:/Projects/DoAnTotNGhiep_komibook/frontend/src/views/vendor/StockTransferView.vue)
- Lấy danh sách sách physical qua `/api/vendor/books`.
- Xóa mock transfer list fallback và detail fallback.
- Bổ sung error state & retry button.
#### [MODIFY] [StockTransferPrintView.vue](file:///c:/Projects/DoAnTotNGhiep_komibook/frontend/src/views/vendor/StockTransferPrintView.vue)
- Xóa mock print fallback (`transfer.value = { transfer_code: 'TRF-...' }`).
- Hiển thị error state và retry button khi API lỗi.

---

### 4. Automated Tests

#### [NEW] [Phase3CriticalContractsTest.php](file:///c:/Projects/DoAnTotNGhiep_komibook/backend/tests/Feature/Phase3CriticalContractsTest.php)
Tạo tệp test tự động backend bằng PHPUnit để kiểm tra:
1. `test_paid_ebook_owner_returns_ownership_true_and_order_id`: Ebook đã trả tiền trả `owned: true` và đúng `order_id`.
2. `test_completed_ebook_owner_can_access`: Order `status = completed` cho phép truy cập ebook.
3. `test_unpaid_or_pending_ebook_returns_owned_false_and_link_generation_forbidden`: Order unpaid/pending trả `owned: false` và `generate-link` trả 403.
4. `test_cancelled_or_refunded_order_denies_ebook_access`: Order cancelled/refunded từ chối truy cập ebook.
5. `test_physical_book_order_does_not_grant_ebook_ownership`: Order chứa sách giấy không được tính là ebook ownership.
6. `test_user_cannot_access_or_leak_other_user_ebook_order`: User B không thể kiểm tra/truy cập ebook/order của User A, response ownership trả `owned: false` không lộ `order_id`.
7. `test_my_library_does_not_grant_invalid_ebook_reading_and_omits_private_filepath`: `my-library` không trao quyền đọc cho ebook chưa trả tiền và không chứa private `file_path`.
8. `test_annotations_index_store_recent_reject_non_owner`: Annotation index/store/recent từ chối non-owner hoặc user chưa đủ quyền.
9. `test_valid_annotation_returns_book_id_and_order_id`: Annotation hợp lệ trả đủ `book_id` và `order_id`.
10. `test_customer_order_detail_returns_own_order`: `/api/my-orders/{id}` trả đúng order của chính mình.
11. `test_customer_order_detail_blocks_other_user_order`: `/api/my-orders/{id}` chặn order của user khác (404/403).

#### [NEW] [phase3_critical_contracts.spec.js](file:///c:/Projects/DoAnTotNGhiep_komibook/frontend/src/__tests__/phase3_critical_contracts.spec.js)
Tạo tệp test tự động frontend bằng Vitest hiện tại:
1. `test_book_detail_calls_canonical_ownership_endpoint`: BookDetailView gọi đúng `/api/books/{id}/check-ownership`.
2. `test_reader_navigation_includes_order_id_and_book_id`: Điều hướng reader dùng route `ebook-reader` kèm `{ orderId, bookId }`.
3. `test_my_library_avoids_legacy_read_route`: MyLibraryView không dùng đường dẫn `/read/...`.
4. `test_invoice_print_calls_my_orders_endpoint`: InvoicePrintView gọi `/api/my-orders/{id}`.
5. `test_api_failure_does_not_generate_mock_data`: Khi API lỗi, invoice, chapter, audit, transfer không tự sinh dữ liệu giả.
6. `test_vendor_screens_use_vendor_books_api`: Các màn hình vendor gọi `/api/vendor/books`, không gọi catalog public `/api/books`.

*Ghi chú*: Chỉ sử dụng Vitest và dependency hiện có. Không cài package mới và không viết test grep source hình thức.

---

## Verification Plan

All verification commands are scoped by working directory as required:

### Backend Commands (chạy từ thư mục `backend`)
- `php vendor/bin/phpunit tests/Feature/Phase3CriticalContractsTest.php`
- `php artisan test`
- `php vendor/bin/pint --test app/Services/EbookAccessService.php app/Http/Controllers/Api/OrderController.php app/Http/Controllers/Api/BookController.php app/Http/Controllers/Api/BookAnnotationController.php app/Http/Resources/CustomerOrderDetailResource.php`

### Frontend Commands (chạy từ thư mục `frontend`)
- `npm.cmd test`
- `npm.cmd run build`
- `npx.cmd --no-install oxlint src/views/BookDetailView.vue src/views/MyLibraryView.vue src/views/MyAnnotationsView.vue src/views/EbookReaderView.vue src/views/OrdersView.vue src/views/orders/InvoicePrintView.vue src/views/vendor/BookChaptersView.vue src/views/vendor/DrmSettingsView.vue src/views/vendor/InventoryAuditView.vue src/views/vendor/LiveEditorView.vue src/views/vendor/MultiDevicePreviewView.vue src/views/vendor/StockTransferView.vue src/views/vendor/StockTransferPrintView.vue`
- `npx.cmd --no-install eslint src/views/BookDetailView.vue src/views/MyLibraryView.vue src/views/MyAnnotationsView.vue src/views/EbookReaderView.vue src/views/OrdersView.vue src/views/orders/InvoicePrintView.vue src/views/vendor/BookChaptersView.vue src/views/vendor/DrmSettingsView.vue src/views/vendor/InventoryAuditView.vue src/views/vendor/LiveEditorView.vue src/views/vendor/MultiDevicePreviewView.vue src/views/vendor/StockTransferView.vue src/views/vendor/StockTransferPrintView.vue`

### Repository Root Command (chạy từ root)
- `git diff --check`

*Lưu ý*: Không chạy command lint có `--fix`, không mass-format và không để `npx` tự động cài đặt package mới (`--no-install`).
