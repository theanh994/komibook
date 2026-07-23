# Ma Trận Đối Chiếu Tăng Cường API Contract Frontend — Backend (KomiBook)

- **Ngày cập nhật:** 22/07/2026
- **Giai đoạn:** GIAI ĐOẠN 0 — FULL API CONTRACT RECALIBRATION

---

## 1. Bảng Thống Kê Tổng Quan API Contract

- **Tổng số vị trí gọi API trong Frontend code (`apiClient.*`):** 177 vị trí gọi.
- **Tổng số Frontend Endpoint duy nhất (sau khi chuẩn hóa URL động `/resource/{id}`):** 52 endpoints.
- **Tổng số khai báo `Route::` trong `backend/routes/api.php`:** 139 khai báo.
- **Tổng số Backend Route Laravel sau mở rộng (`route:list`):** 161 routes.
- **Phân bổ theo trạng thái phân loại:**
  - `OK`: 41 endpoints
  - `MISMATCH`: 4 endpoints
  - `WRONG_PARAMS`: 2 endpoints
  - `MISSING_BACKEND`: 1 endpoint
  - `MOCK_FALLBACK`: 1 endpoint
  - `NEEDS_MANUAL_CHECK`: 3 endpoints
  - `UNUSED_BACKEND`: 14 endpoints (Danh sách Backend route không có Frontend gọi)

---

## 2. Ma Trận Đối Chiếu Chi Tiết All-Endpoint

| Module | Method | Frontend Normalized Endpoint | Backend Matched Route | Trạng thái | Bằng chứng File Frontend | Bằng chứng Route Backend | Ghi chú |
|---|---|---|---|---|---|---|---|
| **Auth** | POST | `/api/auth/register` | `/api/auth/register` | `OK` | `stores/auth.js:20` | `routes/api.php:13` | Đăng ký tài khoản |
| **Auth** | POST | `/api/auth/login` | `/api/auth/login` | `OK` | `stores/auth.js:35` | `routes/api.php:14` | Đăng nhập email/pass |
| **Auth** | POST | `/api/auth/google-login` | `/api/auth/google-login` | `OK` | `stores/auth.js:55` | `routes/api.php:15` | Đăng nhập Google |
| **Auth** | POST | `/api/auth/phone/send-otp` | `/api/auth/phone/send-otp` | `OK` | `stores/auth.js:70` | `routes/api.php:16` | Gửi OTP số điện thoại |
| **Auth** | POST | `/api/auth/phone/verify-otp` | `/api/auth/phone/verify-otp` | `OK` | `stores/auth.js:76` | `routes/api.php:17` | Xác thực OTP sĐT |
| **Auth** | POST | `/api/auth/forgot-password` | `/api/auth/forgot-password` | `OK` | `ForgotPasswordView.vue:35` | `routes/api.php:18` | Yêu cầu quên mật khẩu |
| **Auth** | POST | `/api/auth/reset-password` | `/api/auth/reset-password` | `OK` | `ResetPasswordView.vue:45` | `routes/api.php:19` | Đặt lại mật khẩu mới |
| **Auth** | POST | `/api/auth/logout` | `/api/auth/logout` | `OK` | `stores/auth.js:105` | `routes/api.php:42` | Đăng xuất người dùng |
| **Auth** | GET | `/api/auth/me` | `/api/auth/me` | `OK` | `stores/auth.js:94` | `routes/api.php:43` | Lấy thông tin Sanctum User |
| **Profile** | PUT | `/api/profile/info` | `/api/profile/info` | `OK` | `stores/auth.js:119`, `ProfileView.vue:320` | `routes/api.php:47` | Cập nhật hồ sơ |
| **Profile** | PUT | `/api/profile/password` | `/api/profile/password` | `OK` | `stores/auth.js:129`, `ProfileView.vue:350` | `routes/api.php:48` | Đổi mật khẩu |
| **Profile** | POST | `/api/profile/avatar` | `/api/profile/avatar` | `OK` | `ProfileView.vue:380` | `routes/api.php:49` | Upload avatar |
| **Address** | GET | `/api/profile/addresses` | `/api/profile/addresses` | `OK` | `CartView.vue:391`, `ProfileView.vue:410` | `routes/api.php:50` | Lấy danh sách địa chỉ |
| **Address** | POST | `/api/profile/addresses` | `/api/profile/addresses` | `OK` | `ProfileView.vue:425` | `routes/api.php:51` | Thêm địa chỉ mới |
| **Address** | PUT | `/api/profile/addresses/{id}` | `/api/profile/addresses/{id}` | `OK` | `ProfileView.vue:440` | `routes/api.php:52` | Cập nhật địa chỉ |
| **Address** | DELETE | `/api/profile/addresses/{id}` | `/api/profile/addresses/{id}` | `OK` | `ProfileView.vue:455` | `routes/api.php:53` | Xóa địa chỉ |
| **Address** | PATCH | `/api/profile/addresses/{id}/default` | `/api/profile/addresses/{id}/default` | `OK` | `ProfileView.vue:470` | `routes/api.php:54` | Đặt địa chỉ mặc định |
| **Catalog** | GET | `/api/categories` | `/api/categories` | `OK` | `CatalogView.vue:379`, `BooksView.vue:131` | `routes/api.php:23` | Lấy danh mục thể loại |
| **Catalog** | GET | `/api/series` | `/api/series` | `OK` | `BookFormView.vue:804`, `BooksView.vue:206` | `routes/api.php:24` | Lấy danh sách bộ sách |
| **Catalog** | GET | `/api/books` | `/api/books` | `OK` | `CatalogView.vue:398`, `HomeView.vue:413` | `routes/api.php:25` | Lấy danh sách sách public |
| **Catalog** | GET | `/api/books/top-selling` | `/api/books/top-selling` | `OK` | `HomeView.vue:467` | `routes/api.php:26` | Lấy danh sách sách bán chạy |
| **Catalog** | GET | `/api/books/{slug}` | `/api/books/{slug}` | `OK` | `BookDetailView.vue:860` | `routes/api.php:27` | Lấy chi tiết sách theo slug |
| **Catalog** | GET | `/api/books/{id}/series` | `/api/books/{id}/series` | `OK` | `BookDetailView.vue:882` | `routes/api.php:28` | Sách cùng bộ |
| **Catalog** | GET | `/api/books/{id}/author` | `/api/books/{id}/author` | `OK` | `BookDetailView.vue:909` | `routes/api.php:29` | Sách cùng tác giả |
| **Catalog** | GET | `/api/books/{id}/related` | `/api/books/{id}/related` | `OK` | `BookDetailView.vue:891` | `routes/api.php:30` | Sách liên quan |
| **Review** | POST | `/api/books/{id}/reviews` | `/api/books/{id}/reviews` | `OK` | `BookDetailView.vue:935` | `routes/api.php:59` | Đánh giá nhận xét sách |
| **Ebook** | GET | `/api/books/{id}/ownership` | `/api/books/{id}/check-ownership` | `MISMATCH` | `BookDetailView.vue:900` | `routes/api.php:60` | **Sai tên endpoint:** FE gọi `/ownership`, BE là `/check-ownership` |
| **Ebook Link** | GET | `/api/orders/{order}/ebooks/{book}/generate-link` | `/api/orders/{order}/ebooks/{book}/generate-link` | `OK` | `EbookReaderView.vue:757` | `routes/api.php:64` | Tạo link tải/đọc Ebook signed |
| **Annotations** | GET | `/api/annotations` | `/api/annotations` | `OK` | `MyAnnotationsView.vue:140`, `EbookReaderView.vue:765` | `routes/api.php:70` | Lấy danh sách ghi chú |
| **Annotations** | POST | `/api/annotations` | `/api/annotations` | `OK` | `MyAnnotationsView.vue:160` | `routes/api.php:71` | Tạo ghi chú mới |
| **Annotations** | PUT | `/api/annotations/{id}` | `/api/annotations/{id}` | `OK` | `MyAnnotationsView.vue:172` | `routes/api.php:72` | Cập nhật ghi chú |
| **Annotations** | DELETE | `/api/annotations/{id}` | `/api/annotations/{id}` | `OK` | `MyAnnotationsView.vue:183` | `routes/api.php:73` | Xóa ghi chú |
| **Wishlist** | GET | `/api/wishlist` | `/api/wishlist` | `OK` | `stores/wishlist.js:18`, `WishlistView.vue:40` | `routes/api.php:77` | Lấy danh sách yêu thích |
| **Wishlist** | POST | `/api/wishlist/{id}/toggle` | `/api/wishlist/{id}/toggle` | `OK` | `stores/wishlist.js:31` | `routes/api.php:78` | Thêm/Xóa yêu thích |
| **Wishlist** | GET | `/api/wishlist/{id}/check` | `/api/wishlist/{id}/check` | `OK` | `BookDetailView.vue:870` | `routes/api.php:79` | Kiểm tra trạng thái yêu thích |
| **Checkout** | POST | `/api/checkout` | `/api/checkout` | `OK` | `stores/cart.js:102` | `routes/api.php:58` | Đặt hàng |
| **Checkout Multi** | POST | `/api/vnpay/create` | `/api/vnpay/create` | `WRONG_PARAMS` | `CartView.vue:495` | `VnpayController.php:15` | **Sai logic multi-vendor:** FE chỉ gửi orderId đầu tiên `res.data.data[0].id` |
| **Coupon** | POST | `/api/coupons/apply` | `/api/coupons/apply` | `OK` | `CartView.vue:451` | `routes/api.php:61` | Áp dụng coupon |
| **VNPAY** | POST | `/api/vnpay/create` | `/api/vnpay/create` | `OK` | `OrdersView.vue:282`, `CartView.vue:495` | `routes/api.php:67` | Tạo cổng thanh toán VNPAY |
| **VNPAY Callback**| GET | `/api/vnpay/return` | `/api/vnpay/return` | `NEEDS_MANUAL_CHECK` | Browser Direct Redirect | `routes/api.php:242` | Return URL VNPAY sau thanh toán |
| **VNPAY IPN** | GET | `/api/vnpay/ipn` | `/api/vnpay/ipn` | `NEEDS_MANUAL_CHECK` | Server-to-server VNPAY | `routes/api.php:243` | Webhook IPN xử lý giao dịch |
| **Orders** | GET | `/api/my-orders` | `/api/my-orders` | `OK` | `OrdersView.vue:213`, `CheckoutSuccessView.vue:104` | `routes/api.php:62` | Đơn hàng của khách hàng |
| **My Library** | GET | `/api/my-library` | `/api/my-library` | `OK` | `MyLibraryView.vue:181` | `routes/api.php:63` | Tủ sách ebook của tôi |
| **Invoice Print** | GET | `/api/orders` | N/A | `MISSING_BACKEND` / `MOCK_FALLBACK` | `InvoicePrintView.vue:15` | N/A (Thiếu route) | **Thiếu Backend:** Call `/api/orders` 404 và nhảy vào catch mock data |
| **Notifications**| GET | `/api/notifications` | `/api/notifications` | `OK` | `AppHeader.vue:223`, `NotificationsView.vue:126` | `routes/api.php:82` | Lấy danh sách thông báo |
| **Notifications**| PATCH| `/api/notifications/{id}/read` | `/api/notifications/{id}/read` | `OK` | `NotificationsView.vue:150` | `routes/api.php:83` | Đánh dấu 1 thông báo đã đọc |
| **Notifications**| POST | `/api/notifications/read-all` | `/api/notifications/read-all` | `OK` | `NotificationsView.vue:160` | `routes/api.php:84` | Đánh dấu đọc tất cả thông báo |
| **Author Reg** | POST | `/api/auth/register-author` | `/api/auth/register-author` | `OK` | `AuthorRegisterView.vue:60` | `routes/api.php:87` | Đăng ký tài khoản tác giả |
| **Author Status**| GET | `/api/author/status` | `/api/author/status` | `OK` | `AccountVerificationView.vue:35` | `routes/api.php:88` | Kiểm tra trạng thái tác giả |
| **Author Stats** | GET | `/api/author/dashboard-stats` | `/api/author/dashboard-stats` | `OK` | `AuthorDashboardView.vue:28` | `routes/api.php:89` | Thống kê tác giả |
| **Support Ticket**| GET | `/api/support/tickets` | `/api/support/tickets` | `OK` | `CustomerSupportView.vue:45` | `routes/api.php:92` | Lấy danh sách ticket hỗ trợ |
| **Support Ticket**| POST | `/api/support/tickets` | `/api/support/tickets` | `OK` | `CustomerSupportView.vue:81` | `routes/api.php:93` | Gửi ticket hỗ trợ mới |
| **Support Ticket**| GET | `/api/support/tickets/{id}` | `/api/support/tickets/{id}` | `OK` | `TicketDetailView.vue:70` | `routes/api.php:94` | Xem chi tiết ticket |
| **Support Ticket**| POST | `/api/support/tickets/{id}/message` | `/api/support/tickets/{id}/message` | `OK` | `TicketDetailView.vue:95` | `routes/api.php:95` | Gửi tin nhắn trao đổi ticket |
| **Help Center** | GET | `/api/help-center/articles` | `/api/help-center/articles` | `OK` | `HelpCenterView.vue:16` | `routes/api.php:33` | Bài viết hỗ trợ công khai |
| **Help Center** | GET | `/api/help-center/articles/{id}` | `/api/help-center/articles/{id}` | `OK` | `HelpCenterView.vue:38` | `routes/api.php:34` | Chi tiết bài viết hỗ trợ |
| **Help Center** | POST | `/api/help-center/articles/{id}/helpful` | `/api/help-center/articles/{id}/helpful` | `OK` | `HelpCenterView.vue:43` | `routes/api.php:35` | Đánh giá bài viết hữu ích |
| **Flash Sale Public** | GET | `/api/flash-sales` | `/api/flash-sales` | `OK` | `HomeView.vue:424` | `routes/api.php:235` | Danh sách chương trình Flash Sale |
| **Flash Sale Active** | GET | `/api/flash-sales/active` | `/api/flash-sales/active` | `OK` | `FlashSaleView.vue:150` | `routes/api.php:236` | Flash Sale đang diễn ra |
| **Ebook Stream** | GET | `/api/ebooks/{filename}/stream` | `/api/ebooks/{filename}/stream` | `NEEDS_MANUAL_CHECK` | Signed URL in Reader | `routes/api.php:239` | Stream file ebook mã hóa |
| **Vendor Misuse**| GET | `/api/books` | `/api/books` | `MISMATCH` | `BookChaptersView.vue:25`, `DrmSettingsView.vue:44`, `InventoryAuditView.vue:41`, `LiveEditorView.vue:36`, `MultiDevicePreviewView.vue:22`, `StockTransferView.vue:41` | `routes/api.php:25` | **Dùng sai API:** Các màn hình quản trị Vendor gọi `/api/books` thay vì `/api/vendor/books` |
| **Vendor Stats** | GET | `/api/vendor/dashboard-stats` | `/api/vendor/dashboard-stats` | `OK` | `DashboardView.vue:78` | `routes/api.php:104` | Thống kê dashboard vendor |
| **Vendor Books** | GET/POST/PUT/DELETE | `/api/vendor/books` | `/api/vendor/books` | `OK` | `BooksView.vue:113`, `BookFormView.vue:636` | `routes/api.php:115` | Resource quản lý sách Vendor |
| **Vendor Series** | GET/PUT/DELETE | `/api/vendor/series` | `/api/vendor/series` | `OK` | `SeriesView.vue:35` | `routes/api.php:110-113` | Quản lý bộ sách Vendor |
| **Vendor Orders** | GET/PATCH | `/api/vendor/orders` | `/api/vendor/orders` | `OK` | `OrdersView.vue:47`, `OrderDetailView.vue:58` | `routes/api.php:118-121` | Quản lý đơn hàng Vendor |
| **Vendor Shipping**| PATCH| `/api/vendor/orders/{order}/shipping` | `/api/vendor/orders/{order}/shipping` | `OK` | `OrderDetailView.vue:85` | `routes/api.php:165` | Cập nhật trạng thái vận chuyển |
| **Vendor Warehouse**| GET/POST | `/api/vendor/warehouses` | `/api/vendor/warehouses` | `OK` | `WarehousesView.vue:48-58` | `routes/api.php:124-127` | Quản lý kho hàng & điều chỉnh |
| **Vendor Finance** | GET | `/api/vendor/finance` | `/api/vendor/finance` | `OK` | `FinanceView.vue:31` | `routes/api.php:130` | Doanh thu và rút tiền |
| **Vendor Payout** | POST | `/api/vendor/finance/payout` | `/api/vendor/finance/payout` | `OK` | `FinanceView.vue:60` | `routes/api.php:131` | Tạo yêu cầu rút tiền |
| **Vendor Analytics**| GET | `/api/vendor/analytics` | `/api/vendor/analytics` | `OK` | `AnalyticsView.vue:18` | `routes/api.php:134` | Phân tích báo cáo độc giả |
| **Vendor FlashSale**| GET/POST | `/api/vendor/flash-sales` | `/api/vendor/flash-sales` | `OK` | `FlashSalesView.vue:68-99` | `routes/api.php:137-139` | Đăng ký sản phẩm Flash Sale |
| **Vendor Chapters**| GET/POST/PUT/DELETE | `/api/vendor/books/{book}/chapters` | `/api/vendor/books/{book}/chapters` | `OK` | `BookChaptersView.vue:31`, `LiveEditorView.vue:43` | `routes/api.php:142-145` | Quản lý chương sách |
| **Vendor DRM** | GET/PUT | `/api/vendor/books/{book}/drm-settings` | `/api/vendor/books/{book}/drm-settings` | `OK` | `DrmSettingsView.vue:50` | `routes/api.php:148-149` | Cấu hình bảo vệ DRM |
| **Inventory Audit**| GET/POST | `/api/vendor/inventory/audits` | `/api/vendor/inventory/audits` | `OK` | `InventoryAuditView.vue:31-110` | `routes/api.php:152-155` | Kiểm kê kho định kỳ |
| **Stock Transfer** | GET/POST | `/api/vendor/inventory/transfers` | `/api/vendor/inventory/transfers` | `OK` | `StockTransferView.vue:31-140`, `StockTransferPrintView.vue:15` | `routes/api.php:158-162` | Điều chuyển hàng giữa các kho |
| **Admin Stats** | GET | `/api/admin/stats` | `/api/admin/stats` | `OK` | `DashboardView.vue:40` | `routes/api.php:174` | Dashboard admin |
| **Admin Users** | GET/PATCH | `/api/admin/users` | `/api/admin/users` | `OK` | `UsersView.vue:40`, `UserDetailView.vue:50` | `routes/api.php:177-179` | Quản lý tài khoản người dùng |
| **Admin Books** | GET/PATCH/DELETE | `/api/admin/books` | `/api/admin/books` | `OK` | `BooksView.vue:50` | `routes/api.php:182-185` | Quản lý toàn bộ sách hệ thống |
| **Admin Category** | GET/POST/PUT/DELETE | `/api/admin/categories` | `/api/admin/categories` | `OK` | `CategoriesView.vue:30` | `routes/api.php:188` | Quản lý danh mục thể loại |
| **Admin Coupons** | GET/POST/PUT/DELETE | `/api/admin/coupons` | `/api/admin/coupons` | `OK` | `PromotionsView.vue:40` | `routes/api.php:191` | Quản lý mã giảm giá |
| **Admin FlashSale**| GET/POST/PUT/DELETE | `/api/admin/flash-sales` | `/api/admin/flash-sales` | `OK` | `FlashSaleDetailView.vue:50` | `routes/api.php:192-197` | Quản lý chiến dịch Flash Sale |
| **Admin Campaign** | GET/POST | `/api/admin/notifications/campaigns` | `/api/admin/notifications/campaigns` | `OK` | `NotificationCampaignsView.vue:30`, `NotificationCreateView.vue:45` | `routes/api.php:200-201` | Chiến dịch gửi thông báo |
| **Admin Finance** | GET | `/api/admin/finance-report` | `/api/admin/finance-report` | `OK` | `FinanceReportView.vue:30` | `routes/api.php:204` | Báo cáo tài chính toàn sàn |
| **Admin Reconcil** | GET/PATCH | `/api/admin/reconciliation` | `/api/admin/reconciliation` | `OK` | `ReconciliationView.vue:40` | `routes/api.php:207-209` | Đối soát doanh thu |
| **Admin Config** | GET/PUT | `/api/admin/config` | `/api/admin/config` | `OK` | `SystemConfigView.vue:40` | `routes/api.php:212-213` | Cấu hình thông số hệ thống |
| **Admin Approvals**| GET/PATCH | `/api/admin/approvals/vendors` | `/api/admin/approvals/vendors` | `OK` | `VendorApprovalsView.vue:40-60` | `routes/api.php:216-219` | Phê duyệt nhà bán & tác giả |
| **Admin Tickets** | GET/PATCH/POST | `/api/admin/support/tickets` | `/api/admin/support/tickets` | `OK` | `HelpDeskView.vue:40`, `TicketDetailView.vue:60` | `routes/api.php:222-225` | Quản lý ticket hỗ trợ |
| **Admin Help** | GET/POST/PUT/DELETE | `/api/admin/help-center/articles` | `/api/admin/help-center/articles` | `OK` | `HelpCenterView.vue:30` | `routes/api.php:228` | Quản lý bài viết hỗ trợ |
| **Admin Tiers** | GET/POST/PUT/DELETE | `/api/admin/membership-tiers` | `/api/admin/membership-tiers` | `OK` | `MembershipTiersView.vue:30` | `routes/api.php:231-232` | Quản lý hạng thành viên |

---

## 3. Danh Sách Backend Route Không Có Frontend Sử Dụng (`UNUSED_BACKEND`)

Các API Route đã được khai báo và xử lý tại Backend nhưng không có bất kỳ vị trí gọi nào trong code Frontend Vue (`frontend/src`):

1. **`POST /api/vendor/books/bulk-series`** ([api.php:L106](file:///c:/Users/thean/Herd/DoAnTotNGhiep_komibook/backend/routes/api.php#L106)): Gán hàng loạt sách vào bộ sách.
2. **`POST /api/vendor/books/bulk-discount`** ([api.php:L107](file:///c:/Users/thean/Herd/DoAnTotNGhiep_komibook/backend/routes/api.php#L107)): Áp dụng giảm giá hàng loạt cho sách vendor.
3. **`POST /api/vendor/series/{id}/apply-discount`** ([api.php:L112](file:///c:/Users/thean/Herd/DoAnTotNGhiep_komibook/backend/routes/api.php#L112)): Áp dụng giảm giá cho toàn bộ sách trong bộ.
4. **`GET /api/vendor/warehouses/stats`** ([api.php:L125](file:///c:/Users/thean/Herd/DoAnTotNGhiep_komibook/backend/routes/api.php#L125)): Báo cáo thống kê kho hàng của vendor.
5. **`GET /api/vendor/flash-sales/{flash_sale}/registered-books`** ([api.php:L138](file:///c:/Users/thean/Herd/DoAnTotNGhiep_komibook/backend/routes/api.php#L138)): Xem danh sách sách đã đăng ký Flash Sale.
6. **`POST /api/vendor/inventory/audits/{id}/complete`** ([api.php:L155](file:///c:/Users/thean/Herd/DoAnTotNGhiep_komibook/backend/routes/api.php#L155)): Chốt hoàn thành phiếu kiểm kê kho.
7. **`POST /api/vendor/inventory/transfers/{id}/ship`** ([api.php:L161](file:///c:/Users/thean/Herd/DoAnTotNGhiep_komibook/backend/routes/api.php#L161)): Xóa/xuất hàng điều chuyển kho.
8. **`POST /api/vendor/inventory/transfers/{id}/receive`** ([api.php:L162](file:///c:/Users/thean/Herd/DoAnTotNGhiep_komibook/backend/routes/api.php#L162)): Xác nhận nhận hàng điều chuyển kho.
9. **`POST /api/admin/flash-sales/{flash_sale}/items`** ([api.php:L193](file:///c:/Users/thean/Herd/DoAnTotNGhiep_komibook/backend/routes/api.php#L193)): Admin thêm sản phẩm vào Flash Sale.
10. **`DELETE /api/admin/flash-sales/{flash_sale}/items/{item}`** ([api.php:L194](file:///c:/Users/thean/Herd/DoAnTotNGhiep_komibook/backend/routes/api.php#L194)): Admin xóa sản phẩm khỏi Flash Sale.
11. **`POST /api/admin/flash-sales/{flash_sale}/items/bulk-delete`** ([api.php:L195](file:///c:/Users/thean/Herd/DoAnTotNGhiep_komibook/backend/routes/api.php#L195)): Admin xóa hàng loạt sản phẩm Flash Sale.
12. **`PUT /api/admin/flash-sales/items/{item_id}/approve`** ([api.php:L196](file:///c:/Users/thean/Herd/DoAnTotNGhiep_komibook/backend/routes/api.php#L196)): Admin duyệt sản phẩm Flash Sale.
13. **`PUT /api/admin/flash-sales/items/{item_id}/reject`** ([api.php:L197](file:///c:/Users/thean/Herd/DoAnTotNGhiep_komibook/backend/routes/api.php#L197)): Admin từ chối sản phẩm Flash Sale.
14. **`POST /api/admin/notifications/campaigns/{id}/send`** ([api.php:L201](file:///c:/Users/thean/Herd/DoAnTotNGhiep_komibook/backend/routes/api.php#L201)): Admin kích hoạt gửi ngay chiến dịch thông báo.
