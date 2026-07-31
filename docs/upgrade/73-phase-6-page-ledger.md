# KomiBook — sổ nghiệm thu giao diện Giai đoạn 6

Ngày mở sổ: 2026-07-29  
Design source of truth: `design-system/komibook/MASTER.md`

## Cách ghi kết quả

- `—`: chưa đến batch.
- `B`: đã ghi baseline trước khi sửa.
- `P`: đạt page gate.
- `F`: chưa đạt, ghi lỗi ở cột Ghi chú.
- Viewport bắt buộc: `375 / 768 / 1024 / 1440`.
- Mỗi dòng chỉ được chuyển sang `P` sau khi đã kiểm tra layout, bàn phím,
  trạng thái API và console liên quan.

## Public, authentication và customer

| Batch | Route | View/chức năng | Role | Baseline | 375 | 768 | 1024 | 1440 | Keyboard/API | Kết quả | Ghi chú |
|---|---|---|---|---|---|---|---|---|---|---|---|
| 6C | `/` | Home feed | guest/customer | B | P | P | P | P | P | P | Feed 5 tầng, API thật, fallback/empty trung thực, không overflow |
| 6D | `/catalog` | Catalog | public | B | P | P | P | P | P | P | Bộ lọc URL/deep link/back-forward; thẻ dùng chung; quick/cart/buy/wishlist |
| 6D | `/flash-sale` | Flash Sale | public | B | P | P | P | P | P | P | Empty API state trung thực; bìa contain; không external fallback |
| 6D | `/blog` | Blog list | public | B | P | P | P | P | P | P | API hiện rỗng; loading/empty state và retry error đạt |
| 6D | `/blog/:slug` | Article detail | public | B | P | P | P | P | P | P | Local chưa có bài published; error route, breadcrumb và back link đạt |
| 6D | `/book/:slug` | Book detail | public | B | P | P | P | P | P | P | Bìa contain, version-only label, review modal, detail → cart smoke đạt |
| 6E | `/login` | Login | guest | B | P | P | P | P | P | P | Labels, password toggle, social/error state; không gửi production auth |
| 6E | `/register` | Register | guest | B | P | P | P | P | P | P | Role, email OTP dialog, terms và social entry responsive |
| 6E | `/forgot-password` | Forgot password | guest | B | P | P | P | P | P | P | Không gửi email thật; form semantics/target đạt |
| 6E | `/reset-password` | Reset password | guest | B | P | P | P | P | P | P | Invalid-token warning và password-strength UI đạt |
| 6E | `/author/register` | Author registration | customer | B | P | P | P | P | P | P | Email OTP/Google bypass; không còn phone verification CTA |
| 6E | `/author/verify` | Author email OTP | customer | B | P | P | P | P | P | P | Seed local hiển thị masked email; không gửi OTP thật |
| 6E | `/vendor/register` | Vendor registration | customer | B | P | P | P | P | P | P | Labels pháp lý/ngân hàng; không gửi hồ sơ/file thật |
| 6E | `/cart` | Cart | customer | B | P | P | P | P | P | P | Ebook quantity 1; terms bắt buộc; ebook-only không cần shipping; không checkout/VNPAY thật |
| 6E | `/checkout/success` | Checkout result | customer | B | P | P | P | P | P | P | Invalid-ID trực tiếp; paid/pending/error qua behavioral tests, không tạo order giả |
| 6E | `/profile` | Profile | customer | B | P | P | P | P | P | P | Mobile account nav thu gọn; tab/form/address/security semantics và target đạt |
| 6E | `/wishlist` | Wishlist | customer | B | P | P | P | P | P | P | Shared BookCard; local empty state; error/retry code path; không toggle dữ liệu |
| 6E | `/notifications` | Notifications | customer | B | P | P | P | P | P | P | Local empty state; semantic unread; không mark-read dữ liệu |
| 6E | `/orders` | Orders | customer | B | P | P | P | P | P | P | Local empty; filters semantic; payment query không tự xác nhận paid |
| 6E | `/tracking/:orderId` | Order tracking | customer | B | P | P | P | P | P | P | 404 trực tiếp; truth behavior test; không bịa ETA/carrier/timeline |
| 6E | `/orders/invoice/:id` | Invoice/print | customer | B | P | P | P | P | P | P | 404 trực tiếp; responsive error; snapshot/print qua backend tests |
| 6E | `/returns` | Returns | customer | B | P | P | P | P | P | P | Chỉ sách cũ vật lý; local empty; không submit return |
| 6E | `/my-library` | Library | customer | B | P | P | P | P | P | P | Empty local; version labels; touch actions; không mở ebook thật |
| 6E | `/reader/:orderId/:bookId` | Ebook reader | customer | B | P | P | P | P | P | P | Access denied trực tiếp; versions từ purchase trở đi qua backend test |
| 6E | `/annotations` | Annotations | customer | B | P | P | P | P | P | P | Local empty; search/error/actions semantic; không xóa ghi chú |
| 6E | `/help-center` | Help center | public/customer | B | P | P | P | P | P | P | Search/article keyboard; loading/error/empty semantic; không gửi helpful rating |
| 6E | `/support` | Support | customer | B | P | P | P | P | P | P | Form labels, target 44px, local empty history; không gửi ticket/file |
| 6E | `/support/tickets/:id` | Ticket detail | customer | B | P | P | P | P | P | P | Invalid-ID error trực tiếp; không reply/download/update |
| 6E | `/dashboard` | Role landing | authenticated | B | P | P | P | P | P | P | Customer seed local redirect đúng về `/` |

## Author panel

| Batch | Route | Chức năng | Baseline | 375 | 768 | 1024 | 1440 | Keyboard/API | Kết quả | Ghi chú |
|---|---|---|---|---|---|---|---|---|---|---|
| 6F | `/author/manage/dashboard` | Dashboard | B | P | P | P | P | P | P | Shell kiểu Vendor; trạng thái tổng quan và quyền tách biệt |
| 6F | `/author/manage/books` | Works/books | B | P | P | P | P | P | P | Empty/error/retry; dialog ebook; không tạo tác phẩm |
| 6F | `/author/manage/analytics` | Reader analytics | B | P | P | P | P | P | P | Mobile cards/desktop table; privacy cohort giữ nguyên |
| 6F | `/author/manage/fulfillment-address` | Fulfillment address | B | P | P | P | P | P | P | Không lộ địa chỉ cho khách; không lưu trong smoke |
| 6F | `/author/manage/used-books` | Used books | B | P | P | P | P | P | P | Cam kết sách thật; không upload/tạo listing/đổi tồn |
| 6F | `/author/manage/promotions` | Flash sale/coupons | B | P | P | P | P | P | P | Quyền thật; không tạo coupon/đăng ký sale trong smoke |
| 6F | `/author/manage/copyright` | Copyright works | B | P | P | P | P | P | P | Loading/error/retry/empty; không sửa hồ sơ |
| 6F | `/author/manage/royalty-agreements` | Royalty agreements | B | P | P | P | P | P | P | Không mutate acceptance trong smoke |
| 6F | `/author/studio/books/:bookId/write` | Writing studio | B | P | P | P | P | P | P | Invalid-ID error; mobile chapter layout; không lưu/xóa |
| 6F | `/author/studio/books/:bookId/preview` | Author preview | B | P | P | P | P | P | P | Invalid-ID error; route về editor đã sửa |
| 6F | `/author/books/:bookId/copyright` | Copyright detail | B | P | P | P | P | P | P | Form labels; không upload/submit |
| 6F | Author legacy redirects | dashboard/studio/commerce/royalty | B | P | P | P | P | P | P | Deep links chuyển đúng `/author/manage/...` |

## Vendor panel

| Batch | Child route dưới `/vendor` | Chức năng | Baseline | 375 | 768 | 1024 | 1440 | Keyboard/API | Kết quả |
|---|---|---|---|---|---|---|---|---|---|
| 6G | `dashboard` | Dashboard | B | P | P | P | P | P | P |
| 6G | `analytics` | Analytics | B | P | P | P | P | P | P |
| 6G | `books` | Books | B | P | P | P | P | P | P |
| 6G | `series` | Series | B | P | P | P | P | P | P |
| 6G | `books/create` | Create book | B | P | P | P | P | P | P |
| 6G | `books/:id/edit` | Edit book | B | P | P | P | P | P | P |
| 6G | `books/:bookId/publishing` | Publishing | B | P | P | P | P | P | P |
| 6G | `books/:bookId/chapters` | Chapters | B | P | P | P | P | P | P |
| 6G | `books/:bookId/drm` | DRM | B | P | P | P | P | P | P |
| 6G | `live-editor/:bookId` | Live editor | B | P | P | P | P | P | P |
| 6G | `preview/:bookId` | Multi-device preview | B | P | P | P | P | P | P |
| 6G | `warehouses` | Warehouses | B | P | P | P | P | P | P |
| 6G | `inventory/audits` | Inventory audit | B | P | P | P | P | P | P |
| 6G | `inventory/transfers` | Stock transfer | B | P | P | P | P | P | P |
| 6G | `inventory/transfers/print/:id` | Transfer print | B | P | P | P | P | P | P |
| 6G | `orders` | Orders | B | P | P | P | P | P | P |
| 6G | `orders/:id` | Order detail | B | P | P | P | P | P | P |
| 6G | `returns` | Returns | B | P | P | P | P | P | P |
| 6G | `finance` | Finance | B | P | P | P | P | P | P |
| 6G | `flash-sales` | Promotions | B | P | P | P | P | P | P |

## Admin panel

| Batch | Child route dưới `/admin` | Chức năng | Baseline | 375 | 768 | 1024 | 1440 | Keyboard/API | Kết quả |
|---|---|---|---|---|---|---|---|---|---|
| 6H | `dashboard` | Dashboard | B | P | P | P | P | P | P |
| 6H | `users`, `users/:id` | Users/detail | B | P | P | P | P | P | P |
| 6H | `approvals` | Vendor/Author approvals | B | P | P | P | P | P | P |
| 6H | `books` | Books | B | P | P | P | P | P | P |
| 6H | `books/categories`, `categories` | Categories | B | P | P | P | P | P | P |
| 6H | `reviews/moderation` | Review moderation | B | P | P | P | P | P | P |
| 6H | `publishing-reviews` | Publishing review | B | P | P | P | P | P | P |
| 6H | `coupons` | Coupons | B | P | P | P | P | P | P |
| 6H | `flash-sales/:id` | Flash sale detail | B | P | P | P | P | P | P |
| 6H | `finance-report` | Finance report | B | P | P | P | P | P | P |
| 6H | `fee-schedules` | Fee schedules | B | P | P | P | P | P | P |
| 6H | `reconciliation` | Reconciliation | B | P | P | P | P | P | P |
| 6H | `membership-tiers` | Memberships | B | P | P | P | P | P | P |
| 6H | `returns` | Return management | B | P | P | P | P | P | P |
| 6H | `articles` | Articles | B | P | P | P | P | P | P |
| 6H | `notifications` | Campaign list | B | P | P | P | P | P | P |
| 6H | `notifications/create` | Create notification | B | P | P | P | P | P | P |
| 6H | `notifications/:id/analytics` | Campaign analytics | B | P | P | P | P | P | P |
| 6H | `support/tickets` | Help desk | B | P | P | P | P | P | P |
| 6H | `support/tickets/:id` | Ticket detail | B | P | P | P | P | P | P |
| 6H | `system-config` | System configuration | B | P | P | P | P | P | P |

## Footer và policy pages cần bổ sung ở 6I

| Batch | Route dự kiến | Nội dung | Trạng thái |
|---|---|---|---|
| 6I | `/about` | Về KomiBook | P — 375/768/1024/1440 |
| 6I | `/for-authors` | Dành cho Tác giả | P — 375/768/1024/1440 |
| 6I | `/contact` | Liên hệ | P — 375/768/1024/1440 |
| 6I | `/faq` | Câu hỏi thường gặp | P — 375/768/1024/1440 |
| 6I | `/terms` | Điều khoản sử dụng | P — 375/768/1024/1440 |
| 6I | `/privacy` | Chính sách bảo mật | P — 375/768/1024/1440 |
| 6I | `/policies/ebooks` | Ebook và phiên bản | P — API policy v1 |
| 6I | `/policies/used-books` | Sách cũ, trả hàng, hoàn tiền | P — API policy v1 |
| 6I | `/policies/copyright` | Bản quyền và hàng giả | P — 375/768/1024/1440 |

## Kết quả Gate toàn cục

- `6A–6I`: đạt.
- Edge audit `320 / 1536`: đạt, không tràn ngang ở các shell/route đại diện.
- Frontend test/lint/build và backend full regression: đạt.
- Gate hoàn tất Giai đoạn 6: xem `132-phase-6-final-acceptance.md`.

## Bổ sung giám sát Giai đoạn 7

| Batch | Route | Bề mặt | Vùng nhìn đã quan sát | Trạng thái |
|---|---|---|---:|---|
| 7C.3 | `/admin/system-config?section=fees` | Commission và phí | 1256 px | Đạt ở cửa sổ hiện tại; 375/768/1024/1440 còn giám sát |
| 7D.1 | `/used-books/manage` | Người bán sách cũ trung lập | 970 px | Không tràn ngang; add/remove queue và error-state đạt; ready-data chờ runtime backend mới |
| 7D.2 | `/for-authors`, `/author/register` | Thông tin đóng entry point và guard legacy | 1280 px | Không tràn ngang; nội dung chuyển đổi rõ; deep link tài khoản không có Author chuyển đúng `/for-authors` |
| 7E.1 | `/vendor/dashboard`, `/vendor/analytics`, `/vendor/orders` | Vendor core parity | 970 px | Không tràn ngang; dashboard tương thích API cũ; analytics báo lỗi thật của origin cũ; orders bỏ thao tác giả |
| 7E.2 | `/vendor/finance`, `/vendor/flash-sales`, `/vendor/warehouses`, `/vendor/inventory/audits`, `/vendor/inventory/transfers` | Vendor operations parity | 970 px | Không tràn ngang; finance giải thích fallback của origin cũ; Flash Sale/kho/kiểm kê/điều chuyển hiển thị ổn định; browser smoke chỉ đọc |
| 7F.2 | Guest `/`, `/catalog`, `/for-authors`; Customer `/orders`, `/my-library`, `/wishlist`, `/used-books/manage`; Vendor core/operations; Admin core/finance/config/campaign | E2E bốn vai trò | 970 px | Đúng route/guard, không tràn ngang; lỗi origin 8080 cũ hiển thị trung thực; không thao tác ghi; browser không hỗ trợ ép breakpoint |
