# KomiBook — bàn giao liên tài khoản và kế hoạch tiếp tục

> Ngày lập: 2026-07-24 (Asia/Ho_Chi_Minh)
>
> Mục đích: giúp một tài khoản Codex khác tiếp tục công việc mà không cần lịch sử chat cũ.
>
> Phạm vi: tổng hợp lộ trình nâng cấp ứng dụng, trạng thái triển khai production, cổng nghiệm thu còn lại và thứ tự công việc sau cửa sổ chờ 24 giờ.

## 1. Cách dùng tài liệu này

Đây là bản bàn giao vận hành, không thay thế các tài liệu nguồn. Khi số liệu hoặc trạng thái có khác biệt:

1. Kiểm tra mã nguồn, Git, dịch vụ và production thực tế.
2. Dùng `Tai_lieu/Kế hoạch nâng cấp chất lượng dự án.docx` làm nguồn chiến lược cho cấu trúc Giai đoạn 0–8.
3. Ưu tiên kế hoạch production chi tiết tại `windows_production_deployment_plan.md` cho triển khai Windows, dịch vụ, Cloudflare, rollback và gỡ Herd.
4. Xem `00-baseline.md`, `01-api-contract-matrix.md` và `02-priority-backlog.md` để hiểu kiến trúc, hợp đồng API và backlog kỹ thuật đã kiểm kê.
5. Xem `03-phase-1-security-report.md` như báo cáo lịch sử của Giai đoạn 1; một số số liệu test trong đó thấp hơn kết quả mới nhất.
6. Không đồng nhất mã issue trong bảng lịch sử của tài liệu Word với mã issue hiện hành trong `02-priority-backlog.md`; có các mã trùng tên nhưng khác phạm vi.
7. Không tin báo cáo của agent nếu chưa tự đọc diff/mã và chạy cổng kiểm tra phù hợp.

Tài liệu này không chứa bí mật, mật khẩu, nội dung `.env`, token Cloudflare hoặc thông tin đăng nhập production.

## 2. Hai hệ thống đánh số phải phân biệt

### 2.1. Giai đoạn nâng cấp ứng dụng

- Giai đoạn 0: khảo sát, baseline, hợp đồng API và backlog.
- Giai đoạn 1: khắc phục bảo mật khẩn cấp.
- Giai đoạn 2: thiết kế lại Order, Payment và Inventory.
- Giai đoạn 3: sửa luồng frontend–backend, loại bỏ mock và chuẩn hóa API.
- Giai đoạn 4: hoàn thiện nghiệp vụ cốt lõi, CMS Tin tức, tự xuất bản ebook, onboarding tác giả/người bán, bản quyền, hoa hồng và khuyến mãi.
- Giai đoạn 5: chuẩn hóa kiến trúc, phân quyền và dữ liệu.
- Giai đoạn 6: kiểm thử và tự động hóa chất lượng.
- Giai đoạn 7: hiệu năng và vận hành.
- Giai đoạn 8: UAT và phát hành.

> **Cập nhật sau Giai đoạn 4 (2026-07-27):** yêu cầu sản phẩm mới đã thay đổi Giai đoạn 5–8. `61-phase-5-to-8-revised-roadmap.md` là nguồn hiện hành và thay thế các Mục 7.6–7.11 cùng các bước 17–20 tại Mục 17 của tài liệu này. Tóm tắt mới: Giai đoạn 5 hoàn thiện miền sản phẩm author-first; Giai đoạn 6 làm public experience/responsive; Giai đoạn 7 hợp nhất architecture, quality, performance và operations; Giai đoạn 8 UAT/phát hành.

### 2.2. Batch triển khai production trên Windows

- Batch 1A/1P/1B: chuẩn bị runtime, mã nguồn, build và dịch vụ.
- Batch 2: chuyển ingress public qua Cloudflare Tunnel.
- Batch 3: browser smoke, theo dõi ổn định 24 giờ và kiểm tra sau reboot.
- Batch 4: dừng dev stack cũ và gỡ Herd một cách có kiểm soát.

Batch 3/4 là công việc đưa Giai đoạn 1 lên production an toàn, không phải Giai đoạn 2 của nâng cấp ứng dụng.

## 3. Quy tắc bắt buộc cho tài khoản tiếp nhận

- Repository làm việc hiện tại: `C:\Users\thean\Herd\DoAnTotNGhiep_komibook`.
- Branch hiện tại: `master`.
- Không tự ý `commit`, `push`, `reset`, `checkout`, `stash`, `clean`, xóa file, xóa release hoặc thay đổi dữ liệu.
- Không dùng `git add .`.
- Không chỉnh mã nguồn khi nhiệm vụ chỉ là audit/nghiệm thu.
- Không bắt đầu Giai đoạn 2 của nâng cấp ứng dụng trước khi:
  - Batch 3 vượt qua đủ cửa sổ ổn định 24 giờ;
  - có một lần reboot hệ điều hành do người dùng phê duyệt và kiểm tra sau reboot;
  - Batch 4 được hoàn tất hoặc người dùng quyết định rõ phần nào được giữ lại;
  - người dùng phê duyệt bắt đầu Giai đoạn 2.
- Không reboot máy, rollback production, gỡ Herd, dừng DBngin/MySQL hoặc đổi DNS/Tunnel nếu chưa có chấp thuận rõ ràng của người dùng.
- Mọi thay đổi production phải có preflight, bằng chứng trước/sau và đường rollback.
- Không hiển thị hoặc chép bí mật từ `.env`, credential file hay Cloudflare config vào chat/tài liệu.
- Giữ nguyên các release cũ và cấu hình rollback ít nhất cho đến khi Batch 3, reboot và Batch 4 đều được nghiệm thu.

## 4. Trạng thái Git và release tại thời điểm bàn giao

- Local `HEAD` và `origin/master` đã được xác nhận cùng commit:
  - SHA: `f903430bd835cc22f45df28818f0adf2af60ea15`
  - Subject: `sửa(api): trả về JSON 401 cho các yêu cầu API không xác thực`
- Release production đang active:
  - `C:\komibook_releases\f903430bd835cc22f45df28818f0adf2af60ea15`
- Release rollback gần nhất:
  - `C:\komibook_releases\fcfe5a10a38c368c8ecae34fc87a1887e9e214b4`
- Release cũ hơn:
  - `C:\komibook_releases\c47858d8d553390a83e538d0203921b174e4718b`
- SHA-256 gói source của release active:
  - `DC08C91E0C3DE8C5B76FEE3D4C92C9B4F71FAB210225766F7B7F2EC87F5C02A7`
- Hash cấu hình Caddy active:
  - `83A1EAD7B75A5E388AB45EEA4ACD421F4E406781D9B9CDE10ED1706529A11B6A`
- Cấu hình rollback chuyên dụng:
  - `C:\komibook_shared\config\Caddyfile.rollback.fcfe5a10-to-f903430`
  - SHA-256: `2453BAAF04961D4A3ACA8413E421A74289AE3B3B2189CAAC6B37F310D4DBA0D3`
- Caddyfile active đã được kiểm tra:
  - chứa SHA release mới 3 lần;
  - không còn tham chiếu SHA release trước.

Phải xác nhận lại các giá trị trên trước mọi thao tác sau thời gian chờ vì trạng thái có thể đã thay đổi.

## 5. Kiến trúc production hiện tại

```text
Người dùng
   |
   v
https://komibook.id.vn
   |
   v
Cloudflare Tunnel (dịch vụ Windows: cloudflared)
   |
   v
http://127.0.0.1:8080
   |
   v
FrankenPHP/Caddy (dịch vụ Windows: FrankenPHPService)
   |                                  |
   v                                  v
Laravel API/PHP                 frontend dist + shared assets
   |
   v
Database hiện hữu
```

- `FrankenPHPService`: `Running`, startup `Automatic` tại lần kiểm tra gần nhất.
- `cloudflared`: `Running`, startup `Automatic` tại lần kiểm tra gần nhất.
- Production không cần `php artisan serve` hoặc `npm run dev`.
- Domain public chính: `https://komibook.id.vn/`.
- Origin nội bộ: `127.0.0.1:8080`.
- Cloudflare public mapping chính: `komibook.id.vn → http://127.0.0.1:8080`.
- Mapping legacy cần xử lý trước khi kết thúc Batch 4:
  - `api.komibook.id.vn → localhost:8000`.
- Trạng thái production dùng:
  - release bất biến trong `C:\komibook_releases`;
  - `.env`, Laravel storage và asset dùng chung trong `C:\komibook_shared`;
  - `.env` của release active là hardlink đến bản shared;
  - các thư mục storage cần ghi là junction đến vùng shared.
- Không xóa `C:\komibook_shared`, release active hoặc release rollback trong công việc dọn Herd.

## 6. Bất biến sản phẩm quan trọng

Mục tiêu người dùng đã xác nhận nhiều lần:

> Khách chưa đăng nhập phải mở được trang chủ, xem danh mục/nội dung công khai và điều hướng các trang public bình thường.

Do đó:

- `/`, trang public và API catalog công khai không được yêu cầu đăng nhập.
- 401 từ `/api/auth/me` đối với khách là bình thường và không được làm trắng trang.
- Khách vào route được bảo vệ phải được chuyển tới login và giữ nguyên `redirect`, kể cả query string.
- Người đã đăng nhập vào route guest-only phải được chuyển tới trang phù hợp.
- Logout không được tạo vòng lặp fetch/401.
- Cookie session phải tiếp tục hoạt động sau reload.

Các bất biến sản phẩm được bổ sung:

- Tác giả và người bán là các vai trò/ngữ cảnh nghiệp vụ cần được định nghĩa rõ; không mặc định hai vai trò hoàn toàn giống nhau chỉ vì một số màn hình hiện dùng chung khu vực `vendor`.
- Người dùng phải có luồng đăng ký tác giả và đăng ký người bán riêng, có trạng thái, lý do từ chối, khả năng bổ sung hồ sơ và audit.
- Tác giả có thể tạo ebook bằng trình soạn thảo, lưu draft, xem trước, quản lý chương, khai báo bản quyền, định giá và gửi duyệt trước khi bán.
- Bản quyền phải là quy trình xác minh có hồ sơ và trạng thái; không coi việc nhập một `copyright_number` hoặc bật DRM là bằng chứng bản quyền đã được duyệt.
- Người bán/tác giả có thể soạn bài giới thiệu sách và gửi admin duyệt; chỉ bài được duyệt, còn hiệu lực và được lên lịch mới xuất hiện ở Tin tức hoặc Trang chủ.
- Admin có thể quản lý hoa hồng, phí dịch vụ, coupon và Flash Sale, nhưng mọi thay đổi tài chính phải có thời điểm hiệu lực, audit và snapshot trên giao dịch để không làm thay đổi lịch sử.
- Không dùng nội dung hardcode/mock để làm cho Tin tức, ebook, hóa đơn, analytics hoặc các luồng quản trị trông như đã hoạt động.

## 7. Lộ trình nâng cấp ứng dụng đã đề ra

Roadmap chiến lược giữ nguyên cấu trúc Giai đoạn 0–8 của tài liệu Word. Thời lượng dưới đây là ước lượng cho một người thực hiện; Giai đoạn 4 được tăng vì bổ sung các luồng sản phẩm chưa có trong kế hoạch gốc.

### 7.1. Giai đoạn 0 — ổn định hiện trạng

- **Ưu tiên:** điều kiện bắt đầu.
- **Ước lượng gốc:** 2–3 ngày.
- **Trạng thái:** đã hoàn thành ở mức tạo baseline và tài liệu; phải xác minh lại Git trước mỗi batch.

Công việc và đầu ra:

- Kiểm kê thay đổi, lịch sử Git, runtime, database, Redis và các tiến trình phụ thuộc.
- Ghi baseline test/build/lint/Pint, bundle và API.
- Lập API contract matrix và backlog P0/P1/P2.
- Sao lưu database development trước migration có tác động.
- Tài liệu đầu ra:
  - `00-baseline.md`;
  - `01-api-contract-matrix.md`;
  - `02-priority-backlog.md`;
  - `implementation_plan.md`;
  - các kế hoạch/manifest production Windows.

Sai khác cần ghi nhớ:

- Kế hoạch gốc khuyến nghị tạo nhánh nâng cấp và không sửa trực tiếp nhánh chính.
- Thực tế dự án đã làm việc trên `master`; không tự ý tạo/chuyển nhánh, reset hoặc sắp xếp lại lịch sử để “sửa” sai khác này.
- Baseline phiên bản và số lỗi là dữ liệu lịch sử, không phải số liệu hiện tại.

Điều kiện hoàn thành:

- Mọi thay đổi có nguồn gốc rõ.
- Có baseline và backlog kiểm chứng được.
- Có backup trước thay đổi dữ liệu.
- Có quy tắc commit/review/rollback được người dùng chấp thuận.

### 7.2. Giai đoạn 1 — khắc phục bảo mật khẩn cấp

- **Ưu tiên:** P0.
- **Ước lượng gốc:** 4–6 ngày.
- **Trạng thái ứng dụng:** phạm vi đã chốt trong `implementation_plan.md` đã được kiểm tra và triển khai.
- **Trạng thái production:** đang ở Batch 3; chưa đóng đợt production trước khi đủ 24 giờ và kiểm tra sau reboot.

Đã thực hiện trong phạm vi được duyệt:

- `SEC-01`: bỏ OTP hardcode/debug, không lộ OTP và fail-closed khi gửi lỗi.
- `SEC-02`: làm sạch mô tả sách ở backend/frontend để giảm stored XSS.
- `SEC-03`: chuyển tài liệu định danh tác giả sang private storage.
- `SEC-04`: chuyển attachment ticket sang private storage, kiểm tra quyền và MIME.
- `AUTH-01`: Google login/register dùng verifier; test dùng fake verifier có kiểm soát.
- `AUTH-02`: dùng Sanctum cookie session cho web.
- Xóa dialog cho phép dán Google ID token.
- Tách và test route guard guest/protected/redirect/logout.
- Thêm security headers.

Endpoint private đúng:

- `/api/authors/{id}/identity-document`
- `/api/support/tickets/{ticket}/messages/{message}/attachment`

Diễn giải security header:

- CSP đang là `Content-Security-Policy-Report-Only`, chưa enforcing.
- Clickjacking hiện dựa chủ yếu vào `X-Frame-Options: SAMEORIGIN`.
- Không tuyên bố `frame-ancestors` report-only tự nó đã chặn clickjacking.

Cổng chất lượng gần nhất:

- Backend: 34 tests, 144 assertions, pass.
- Frontend Vitest: 11/11 tests pass.
- Build, Oxlint, targeted ESLint và targeted Pint: pass.
- `git diff --check`: pass ở lần kiểm tra gần nhất.
- Full frontend ESLint còn 102 lỗi nợ cũ.

Các mục rộng hơn trong kế hoạch Word chưa được mặc định coi là đã hoàn thành:

- rate limit OTP chi tiết, hash/reuse policy và SMS provider production;
- quản lý/thu hồi tất cả phiên đăng nhập;
- audit log khi xem/tải tài liệu nhạy cảm;
- test XSS cho chương sách và notification;
- chuyển CSP từ report-only sang enforcing an toàn.

Những mục này phải được re-audit và đưa vào batch phù hợp của Giai đoạn 4–6; không tự ý mở lại kết luận của Giai đoạn 1 nếu chưa phát hiện lỗ hổng P0 mới.

Sai khác tài liệu:

- `03-phase-1-security-report.md` có thể còn 31 tests/136 assertions và `READY_FOR_CODEX_REVIEW`.
- Số liệu bàn giao gần nhất là 34/144 nhưng vẫn phải chạy lại trước tuyên bố mới.

### 7.3. Giai đoạn 2 — thiết kế lại Order, Payment và Inventory

- **Ưu tiên:** P0.
- **Ước lượng:** 10–15 ngày.
- **Trạng thái:** chưa bắt đầu; chỉ bắt đầu sau Batch 4 và phê duyệt của người dùng.
- **Nguyên tắc:** test tái hiện trước, transaction/lock/idempotency rõ, migration có rollback.

#### 2.1. Chuẩn hóa state machine

Tách trạng thái độc lập:

- Order: `pending`, `confirmed`, `processing`, `shipped`, `completed`, `cancelled`, `refunded`.
- Payment: `pending`, `paid`, `failed`, `expired`, `refunding`, `refunded`.
- Shipping: `pending_pickup`, `picked_up`, `delivering`, `delivered`, `failed`, `returned`.

Controller không được tự gán chuỗi trạng thái. Mọi chuyển trạng thái phải qua service/policy, kiểm tra actor, trạng thái trước và side effect.

#### 2.2. Checkout đa nhà bán

- Tạo `checkout_sessions` liên kết nhiều order.
- Tạo `checkout_session_orders`.
- Tạo `payment_transactions` có provider transaction ID, amount, currency, status, payload đã lọc bí mật, timestamp và idempotency key.
- Một giao dịch VNPAY thanh toán toàn bộ checkout session; không chỉ order đầu tiên.
- Snapshot giá, giảm giá, phí và hoa hồng tại thời điểm checkout.

#### 2.3. VNPAY idempotency

- Dùng `hash_equals()` và xác minh merchant, amount, currency, mã giao dịch.
- IPN/webhook là nguồn xác nhận; return URL chỉ hiển thị kết quả.
- Unique provider transaction ID.
- Lưu webhook trước khi xử lý.
- Callback lặp trả kết quả thành công nhưng không lặp side effect.
- Không dispatch xử lý đơn online trước khi thanh toán được xác nhận.

#### 2.4. Inventory reservation

- Tạo `inventory_reservations` với book, checkout session, quantity, status và expiry.
- Khóa bản ghi bằng `lockForUpdate()`.
- Chỉ bán sách `published`, đúng vendor active và còn hàng.
- Thanh toán thành công chuyển reservation thành committed.
- Thất bại/hết hạn giải phóng reservation bằng scheduled job.
- Chỉ sách vật lý dùng tồn kho.
- Database là nguồn tồn kho chuẩn; Redis chỉ hỗ trợ cache/lock.

#### 2.5. Job, điểm và ledger idempotent

- Order có dấu xử lý hoặc bảng operation riêng.
- Retry/backoff/failed-job có kiểm soát.
- Không trừ kho, gửi email, cộng điểm hoặc ghi doanh thu hai lần.
- Tạo point transaction unique theo order/type.
- Tạo vendor ledger entry unique theo order/type.
- Không cập nhật tiền trực tiếp từ model event.

#### 2.6. Test và điều kiện hoàn thành

- Hai người mua cuốn cuối cùng cùng lúc không oversell.
- VNPAY callback lặp 2–5 lần không double charge/side effect.
- Job lặp không trừ kho, gửi mail hoặc cộng điểm lặp.
- Thanh toán thất bại/hết hạn giải phóng kho.
- Không mua được sách draft hoặc vendor inactive.
- Một giỏ ba vendor tạo một giao dịch tổng hợp đúng số tiền.
- Hủy/hoàn tiền phục hồi đúng reservation/tồn kho.
- Có ledger và lịch sử giao dịch để đối soát.

Issue hiện hành liên quan: `PAY-01`, `PAY-02`, `PAY-03`, `INV-01`, `INV-02`, `ORD-01`, `FIN-01`.

### 7.4. Giai đoạn 3 — sửa luồng frontend–backend

- **Ưu tiên:** P0/P1.
- **Ước lượng cập nhật:** 7–10 ngày.
- **Trạng thái:** chưa bắt đầu.

#### 3.1. Đồng bộ endpoint và router

- Sửa ownership ebook.
- Chuẩn hóa route reader với `orderId` và `bookId`.
- Sửa điều hướng từ Book Detail và My Library.
- Tạo/làm đúng API chi tiết order và Invoice Print.
- Màn hình vendor dùng `/api/vendor/books`, không dùng catalog public.
- Re-audit biến không khai báo trong LoginView.

#### 3.2. Loại bỏ mock/fallback giả

Loại bỏ mock khỏi:

- Invoice;
- chapter editor và multi-device preview;
- user detail;
- notification analytics;
- Google/OTP production;
- Tin tức/Blog hardcode;
- mọi màn hình đang biến API failure thành dữ liệu thành công giả.

Khi API lỗi phải có error state, retry và empty state thật.

#### 3.3. Chuẩn hóa API contract

- Chuẩn response thành công/lỗi, pagination và HTTP status.
- Viết OpenAPI cho auth, catalog, checkout, payment, order, vendor, author, ebook, content/news và admin.
- Endpoint mới phải cập nhật OpenAPI và contract test.
- Không thay toàn bộ API trong một batch; có adapter/versioning khi cần.

#### 3.4. Điều kiện hoàn thành

Các luồng sau chạy bằng dữ liệu/API thật:

- đăng nhập;
- xem/tìm sách;
- giỏ hàng/checkout/payment;
- xem order/invoice;
- thư viện và ebook reader;
- vendor xem/tạo/sửa sách;
- author editor/preview ở mức API đã có;
- không còn mock che lỗi trong các màn hình thuộc phạm vi.

Issue hiện hành liên quan:

- `FE-01`: ownership endpoint;
- `FE-02`: EbookReader route/params;
- `FE-03`: InvoicePrint/order API;
- `FE-04`: LoginView;
- `FE-05`: vendor UI dùng sai catalog API;
- phần contract của `EBOOK-01`.

### 7.5. Giai đoạn 4 — hoàn thiện nghiệp vụ cốt lõi và hệ sinh thái tác giả/người bán

- **Ưu tiên:** P1, riêng lỗi sai tiền/quyền/data integrity nâng lên P0.
- **Ước lượng cập nhật:** 24–35 ngày, chia thành 4A/4B/4C.
- **Trạng thái:** chưa bắt đầu.
- **Lý do tăng thời lượng:** bổ sung CMS Tin tức, self-publishing, bản quyền, onboarding, commission và Flash Sale mà kế hoạch gốc chưa đặc tả đủ.

#### 4A. Vòng đời thương mại và tài khoản — 7–10 ngày

##### 4A.1. Hủy, trả hàng và hoàn tiền

- Khách hủy trước ngưỡng giao hàng được phép.
- `return_requests` và `refund_transactions` có trạng thái, actor và lịch sử.
- Hoàn tồn kho, điểm và ledger đúng thời điểm.
- VNPAY refund có transaction/status riêng.

##### 4A.2. Hóa đơn

- Invoice hoặc snapshot bất biến trong order.
- Lưu thông tin người mua, người bán, giá, coupon, phí, thuế và tổng.
- Không tính thuế/giá giả ở frontend.

##### 4A.3. Review và annotation

- Review chỉ dành cho verified purchase, unique user/book và có moderation/report.
- Annotation/progress chỉ cho chủ sở hữu ebook.
- Page/chapter phải thuộc đúng book.

##### 4A.4. Email, phiên và tài khoản

- Email verification cho hành động nhạy cảm.
- Reset password phù hợp email/điện thoại.
- Quản lý và thu hồi session.
- Cân nhắc MFA cho admin.

##### 4A.5. Payout và notification

- Payout có lifecycle, row lock, người duyệt, chứng từ và ledger.
- Chỉ cập nhật total withdrawn khi completed.
- Notification campaign dùng scheduler/batch job, retry và số liệu thật.

#### 4B. Onboarding, self-publishing và bản quyền — 10–15 ngày

Trước khi code phải lập ADR về vai trò:

- Author sở hữu/sáng tác tác phẩm.
- Vendor vận hành gian hàng, giá, đơn hàng, kho và tài chính.
- Một user có thể có một hoặc cả hai vai trò.
- Phải quyết định rõ tác giả tự bán trực tiếp, phải đăng ký thêm vendor, hay bán qua gian hàng nền tảng.
- Không tiếp tục dựa vào việc route có tên `author-*` nhưng middleware chỉ cho `vendor`.

##### 4B.1. `AUTHOR-ONBOARD-01` — đăng ký tác giả

Luồng trạng thái:

`draft → submitted → under_review → approved`

Nhánh khác:

`under_review → changes_requested → resubmitted`

hoặc:

`under_review → rejected`

Yêu cầu:

- hồ sơ, giấy tờ private, chuyên môn/bút danh và điều khoản;
- lý do từ chối/yêu cầu bổ sung;
- admin review, audit và thông báo;
- giới hạn quyền trước khi approved;
- suspend/revoke có lý do;
- không lộ tài liệu định danh.

##### 4B.2. `VENDOR-ONBOARD-01` — đăng ký người bán

- Form đăng ký riêng, thông tin pháp lý/thanh toán và điều khoản.
- Trạng thái giống author nhưng policy riêng.
- Admin approve/reject/request changes/suspend.
- Vendor inactive không được publish/bán/checkout.
- Kiểm tra quyền truy cập chéo giữa vendor.

##### 4B.3. `AUTHOR-PUBLISH-01` — trình viết và tự xuất bản ebook

Vòng đời book:

`draft → submitted_for_review → approved → scheduled/published`

Nhánh sửa:

`submitted_for_review → changes_requested/rejected → draft`

Tính năng cơ bản:

- tạo tác phẩm/metadata/cover;
- editor chương, autosave, version history và phục hồi;
- sắp xếp chương, preview đa thiết bị;
- upload/import định dạng được cho phép;
- giá, sample/preview, ngày phát hành;
- DRM/watermark và signed access;
- gửi duyệt, nhận feedback, sửa và gửi lại;
- không sửa âm thầm bản published; dùng revision/version;
- chỉ tác giả/chủ sở hữu hoặc người được ủy quyền được chỉnh sửa;
- liên kết doanh thu/royalty với ledger;
- ebook chỉ được bán khi author, copyright và book đều hợp lệ.

##### 4B.4. `COPYRIGHT-01` — đăng ký và thẩm định bản quyền

Trạng thái:

`draft → submitted → under_review → verified`

Nhánh:

`under_review → changes_requested/rejected/disputed`

Yêu cầu:

- chủ sở hữu, số đăng ký, loại giấy tờ, phạm vi quyền và thời hạn;
- file chứng minh lưu private;
- admin review và audit log;
- kiểm tra trùng số đăng ký khi phù hợp;
- hỗ trợ đồng tác giả/ủy quyền nếu sản phẩm yêu cầu;
- trạng thái tranh chấp/thu hồi;
- DRM không thay thế việc thẩm định bản quyền;
- bỏ logic “giả lập có số thì coi như đủ điều kiện”.

#### 4C. CMS Tin tức, hoa hồng và khuyến mãi — 7–10 ngày

##### 4C.1. `CMS-01` — bài giới thiệu sách và Tin tức

Đối tượng được phép tạo:

- admin/editor;
- author/vendor đã approved cho sách mà họ có quyền quản lý.

Vòng đời bài:

`draft → submitted → under_review → approved → scheduled/published`

Nhánh:

`under_review → changes_requested/rejected`

Sau phát hành:

`published → archived/unpublished`

Tính năng cơ bản:

- title, slug, excerpt, body đã sanitize, cover/media, category, tags;
- liên kết book/author/vendor;
- preview trước khi gửi;
- admin feedback và lịch sử revision;
- lịch xuất bản theo timezone;
- vị trí `news`, `home_featured` hoặc cả hai;
- thứ tự/khung thời gian nổi bật trên trang chủ;
- SEO metadata;
- audit người tạo, người duyệt, người publish;
- notification cho người gửi;
- API public chỉ trả bài published và còn hiệu lực;
- thay toàn bộ dữ liệu hardcode trong `BlogView.vue`.

##### 4C.2. `COMMISSION-01` — hoa hồng và phí

- Admin cấu hình commission/service fee bằng dữ liệu persistent, không dựa vào JSON rời rạc.
- Xác định commission chung và khả năng override theo vendor/category/campaign nếu thật sự cần.
- Có `effective_from`, người thay đổi, lý do và audit.
- Snapshot rate/amount vào order/ledger tại giao dịch.
- Thay đổi hôm nay không làm đổi doanh thu lịch sử.
- Dùng transaction/ledger; không cộng balance trực tiếp trong model event.
- Có preview tác động và validation 0–100%.

##### 4C.3. `PROMO-01` — Flash Sale và khuyến mãi cơ bản

- Admin tạo/sửa/lên lịch/hủy campaign.
- Vendor đăng ký sách; admin approve/reject có lý do.
- Chỉ sách/vendor hợp lệ được tham gia.
- Giá sale có validation với giá gốc, thời gian và mức giảm.
- Quy định timezone, overlap campaign, coupon stacking và ưu tiên giá.
- Giới hạn số lượng/stock reservation nếu có.
- Giá/discount được snapshot khi checkout.
- Hủy/refund dùng đúng snapshot.
- Public API chỉ trả campaign active hợp lệ.
- Audit thay đổi và notification cho vendor.

##### 4C.4. Điều kiện hoàn thành Giai đoạn 4

- Vòng đời order đến refund/payout khép kín.
- Author/vendor onboarding có approve/reject/resubmit/suspend.
- Tác giả tạo và phát hành ebook bằng dữ liệu thật, có quyền/bản quyền rõ.
- Bài giới thiệu được duyệt mới xuất hiện ở Tin tức/Trang chủ.
- Hoa hồng không làm thay đổi giao dịch lịch sử.
- Flash Sale không tạo giá/tồn kho sai và có phê duyệt.
- Không còn analytics, invoice, blog hoặc copyright “thành công giả” trong phạm vi.
- Có audit và test phân quyền customer/author/vendor/admin.

Issue hiện hành liên quan: `FIN-01`, `FIN-02`, `REV-01`, `EBOOK-01`, `NOTIF-01`; bổ sung `AUTHOR-ONBOARD-01`, `VENDOR-ONBOARD-01`, `AUTHOR-PUBLISH-01`, `COPYRIGHT-01`, `CMS-01`, `COMMISSION-01`, `PROMO-01`.

### 7.6. Giai đoạn 5 — chuẩn hóa kiến trúc và dữ liệu

- **Ưu tiên:** P1.
- **Ước lượng cập nhật:** 7–10 ngày.
- **Trạng thái:** chưa bắt đầu.

Phân quyền:

- Laravel Policy cho Book, Article/Post, AuthorProfile, Vendor, CopyrightRecord, Order, Warehouse, SupportTicket, Annotation và PayoutRequest.
- Middleware kiểm tra author/vendor active.
- Không dùng global scope như lớp bảo mật duy nhất.
- Test tenant isolation và ownership.

Service:

- Controller mỏng.
- Tách Payment, OrderState, Inventory, Pricing, Reward, Payout, Commission, Publishing, Copyright, ContentModeration và Promotion service.
- Không làm nghiệp vụ tiền/ledger trong model event.

Dữ liệu:

- Index cho order, item, stock, notification, payout, book, article và review.
- Unique constraint cho review, point/ledger operation, provider transaction, reservation, slug và copyright number khi phù hợp.
- Foreign key/cascade/retention được thiết kế rõ.
- Migration có backfill, dry-run và rollback.

Cấu hình:

- Chuyển `system_config.json` sang database.
- Cache/invalidate có kiểm soát.
- Audit cấu hình, commission và promotion.
- Maintenance mode, upload size, service fee và commission phải được áp dụng thật, nhất quán khi chạy nhiều process/server.

Điều kiện hoàn thành:

- Policy bảo vệ mọi tài nguyên nhạy cảm.
- Controller không chứa khối nghiệp vụ tài chính/xuất bản lớn.
- Dữ liệu có constraint/index phù hợp.
- Cấu hình hoạt động nhất quán và có audit.

Issue hiện hành liên quan: `ARCH-01`, `FIN-01`, `FIN-02`, `FE-05`.

### 7.7. Giai đoạn 6 — kiểm thử và tự động hóa chất lượng

- **Ưu tiên:** P1.
- **Ước lượng cập nhật:** 10–15 ngày.
- **Trạng thái:** mới có nền Vitest/auth guard; phần lớn còn lại chưa bắt đầu.

Backend:

- auth/rate limit và role matrix;
- tenant isolation;
- checkout đa vendor, VNPAY idempotency và inventory concurrency;
- coupon/Flash Sale/commission;
- refund/payout/ledger;
- author/vendor onboarding;
- self-publishing/copyright;
- CMS submit/review/publish;
- private file, signed ebook, XSS và authorization.

Frontend:

- Vitest + Vue Test Utils cho store, form, router, editor, moderation và error state.
- Playwright hoặc Cypress cho E2E.
- Không dùng mock production để làm E2E pass.

E2E tối thiểu:

- đăng ký/đăng nhập/logout;
- đăng ký author/vendor và admin xét duyệt;
- author tạo ebook, khai báo bản quyền, gửi duyệt và publish;
- author/vendor gửi bài, admin duyệt, bài xuất hiện đúng vị trí;
- COD/VNPAY sandbox đa vendor;
- đọc ebook đã mua;
- vendor xử lý order/Flash Sale;
- admin điều chỉnh commission có hiệu lực đúng;
- refund/payout.

CI:

- Composer validate, targeted/full Pint theo gate;
- PHPUnit;
- ESLint/Oxlint;
- frontend unit test/build;
- dependency audit có triage;
- E2E smoke trên CI/staging;
- không merge khi gate bắt buộc fail.

Nợ chất lượng:

- Triage 102 lỗi ESLint cũ theo module/batch, không mass-fix.
- Đánh giá 2 vulnerability npm; không chạy `npm audit fix` tự động.
- Mục tiêu cuối Giai đoạn 6 là ESLint/Oxlint/Pint đạt theo policy đã thống nhất và test quan trọng không flaky.

Issue hiện hành liên quan: `TEST-01`, `OPS-01` và toàn bộ issue nghiệp vụ cần regression test.

### 7.8. Giai đoạn 7 — hiệu năng và vận hành

- **Ưu tiên:** P2, trừ availability/backup/security nâng mức theo rủi ro.
- **Ước lượng cập nhật:** 7–12 ngày.
- **Trạng thái:** production Windows cơ bản đã có; staging/observability/restore test còn thiếu.

Frontend:

- Lazy-load route lớn.
- Tách PDF/Ebook reader và worker khỏi initial bundle.
- Tối ưu logo/cover WebP/AVIF và responsive image.
- Xóa import/component không dùng.
- Skeleton/error boundary phù hợp.

Backend:

- Sửa N+1 trong BookResource.
- Dùng eager loading/withCount và pagination.
- Không dùng `all=true` không giới hạn.
- Cache catalog/category/series phù hợp.
- Load test catalog, checkout, CMS và notification.

Vận hành:

- Staging gần production.
- Queue worker và scheduler chạy như service, có giám sát failed job.
- Structured log/correlation ID/error tracking.
- Health check database, Redis, queue, storage và tunnel.
- Backup database/private files/shared config và thử restore.
- Secret ngoài Git, HTTPS bắt buộc.
- Tài liệu deploy/rollback/recovery.
- Xử lý cảnh báo PHP CLI duplicate OpenSSL sau khi tách Herd ổn định.

Mục tiêu tham khảo:

- catalog p95 dưới 500 ms;
- API thông thường p95 dưới 300–500 ms;
- checkout nội bộ p95 dưới 1,5 giây, không tính provider;
- không oversell trong concurrent load test;
- EbookReader không nằm trong initial bundle nếu có thể tách.

Issue hiện hành liên quan: `PERF-01`, `PERF-02`, `ENV-01`, `DOC-01`, `OPS-01`.

### 7.9. Giai đoạn 8 — UAT và phát hành

- **Ưu tiên:** release gate.
- **Ước lượng cập nhật:** 5–7 ngày.
- **Trạng thái:** chưa bắt đầu; browser smoke production hiện tại không thay thế UAT toàn sản phẩm.

UAT khách hàng:

- đăng ký email/điện thoại/Google, reset password;
- duyệt catalog, giỏ hàng, COD/VNPAY, hủy/refund;
- invoice, library, ebook reader, annotation/review;
- support ticket.

UAT tác giả/người bán:

- đăng ký và được duyệt/yêu cầu sửa/từ chối;
- tạo draft/published book;
- editor, preview, copyright, submit/publish ebook;
- gửi bài giới thiệu, nhận feedback và xuất bản;
- kho/order/Flash Sale;
- ledger/commission/payout.

UAT admin:

- duyệt author/vendor/copyright/book/article/Flash Sale/payout;
- quản lý user, catalog, category, coupon và commission;
- xử lý ticket;
- đối soát payment/refund/payout;
- audit log và notification campaign.

Go-live gate:

- không còn P0;
- P1 còn lại có owner/deadline/risk acceptance;
- CI xanh và UAT ký nhận;
- migration thử trên bản sao dữ liệu;
- backup/restore đã xác minh;
- secret production đúng, `APP_DEBUG=false`;
- queue/scheduler/health/alert chạy;
- VNPAY IPN public hoạt động;
- chính sách quyền riêng tư, bản quyền và hoàn tiền sẵn sàng;
- rollback đã diễn tập hoặc kiểm chứng.

### 7.10. Issue mới bổ sung và quy tắc ánh xạ

Các ID mới không thay thế issue cũ:

- `AUTHOR-ONBOARD-01`: đăng ký/xét duyệt tác giả.
- `VENDOR-ONBOARD-01`: đăng ký/xét duyệt người bán.
- `AUTHOR-PUBLISH-01`: trình viết và self-publishing ebook.
- `COPYRIGHT-01`: hồ sơ, thẩm định và trạng thái bản quyền.
- `CMS-01`: bài giới thiệu sách, Tin tức và vị trí Trang chủ.
- `COMMISSION-01`: cấu hình hoa hồng/phí có hiệu lực, snapshot và audit.
- `PROMO-01`: vòng đời Flash Sale/khuyến mãi có phê duyệt.

Ánh xạ những mã dễ nhầm giữa tài liệu Word lịch sử và backlog hiện hành:

- Word `FE-01` gộp ownership và ebook router; backlog hiện hành tách thành `FE-01` và `FE-02`.
- Word `FE-02` là Invoice API/mock; backlog hiện hành dùng `FE-03`.
- Word `FE-03` là LoginView; backlog hiện hành dùng `FE-04`.
- Word `AUTH-02` bao gồm email verification/session management; backlog hiện hành `AUTH-02` chủ yếu nói về Sanctum cookie session.
- Word `TEST-01` nói về webhook/job idempotency; backlog hiện hành dùng `TEST-01` cho thiếu frontend test rộng hơn.
- Word `ORD-02` được giữ ở Giai đoạn 4A cho cancel/return/refund.
- Word `QA-01` được triển khai trong Giai đoạn 6.
- Word `CI-01` tương ứng phần lớn với `OPS-01` và Giai đoạn 6.

Khi tạo issue trên tracker phải chọn một namespace/cách đánh mã duy nhất và ghi `legacy_id` để truy vết; không tái sử dụng một mã cho hai phạm vi.

Trước khi triển khai phải thêm từng issue vào backlog chính thức với:

- mức ưu tiên/rủi ro;
- actor và state machine;
- schema/API/UI dự kiến;
- migration/rollback;
- test thành công, thất bại, phân quyền và concurrency nếu có;
- phụ thuộc và tiêu chí nghiệm thu.

### 7.11. Lịch triển khai cập nhật

Nếu một người thực hiện, dự kiến hợp lý hơn là 14–18 tuần sau khi đóng Batch 4:

1. Tuần 1–3: Giai đoạn 2.
2. Tuần 4–5: Giai đoạn 3.
3. Tuần 6–10: Giai đoạn 4A/4B/4C.
4. Tuần 11–12: Giai đoạn 5.
5. Tuần 13–15: Giai đoạn 6.
6. Tuần 16–17: Giai đoạn 7.
7. Tuần 18: Giai đoạn 8/UAT.

Đây là ước lượng, không phải deadline. Không rút ngắn bằng cách bỏ test, migration, audit hoặc rollback.

## 8. Trạng thái triển khai production theo batch

### Đã hoàn tất

- Batch 0/0.x: kiểm kê và quyết định kiến trúc Windows production.
- Batch 1A.1: manifest runtime và hash pin.
- Batch 1A.2: kiểm tra runtime cô lập.
- Batch 1P: trusted proxies và quality gates.
- Batch 1B.1: tạo clean release và frontend build.
- Batch 1B.2: WinSW service và cookie smoke.
- Batch 2: chuyển public ingress qua Cloudflare Tunnel.
- Batch 3 phần đầu: deploy release `f903430...`, HTTP/API smoke và browser smoke guest/customer/logout.

### Đang chờ

- Phần còn lại của Batch 3:
  - đủ ít nhất 24 giờ ổn định liên tục;
  - không có lỗi production mới nghiêm trọng;
  - một lần reboot hệ điều hành do người dùng phê duyệt;
  - kiểm tra auto-start và full smoke sau reboot.
- Batch 4:
  - backup dữ liệu/chat/repository;
  - giải quyết mapping legacy port 8000;
  - xác minh database độc lập;
  - dừng dev stack cũ;
  - gỡ Herd an toàn;
  - kiểm tra lại production và toolchain.

## 9. Bằng chứng browser/production gần nhất

Đã xác minh:

- Trang chủ guest mở được và có dữ liệu.
- Guest mở `/cart` được, hiển thị empty state, không có console error.
- `/profile?tab=security` chuyển chính xác thành:
  - `/login?redirect=/profile?tab=security`
- Đăng nhập bằng tài khoản seed customer quay lại đúng route/profile.
- Customer mở `/cart` được.
- Logout quay về `/`, không có fetch/401 loop và không có console error.
- `/api/books`: 200 JSON.
- `/api/auth/me` không đăng nhập:
  - không có `Accept`: 401;
  - có `Accept: application/json`: 401.
- Một cảnh báo unauthenticated cho `/api/auth/me` ở mỗi full page load của khách là hành vi đã biết; không được biến thành loop hoặc blank page.
- Không có `production.ERROR` mới từ release `f903430...` ở lần kiểm tra gần nhất.
- Lỗi cuối lúc `12:56:40` thuộc baseline cố ý trước khi switch và release cũ `fcfe5a10...`, không được tính nhầm cho release active.

## 10. Cửa sổ ổn định 24 giờ

- Mốc bắt đầu chính thức: `2026-07-24 13:01:00 +07:00`.
- Thời điểm sớm nhất được coi là đủ 24 giờ:
  - `2026-07-25 13:01:00 +07:00`.

Không chỉ dựa vào đồng hồ. “Đủ 24 giờ” còn yêu cầu dịch vụ chạy liên tục, domain hoạt động và không có lỗi mới làm mất tính ổn định. Nếu dịch vụ bị dừng/restart ngoài kế hoạch hoặc production hỏng đáng kể, phải ghi nhận sự kiện và quyết định có bắt đầu lại cửa sổ hay không.

## 11. Checklist bắt buộc ngay sau thời gian chờ

### 11.1. Preflight chỉ đọc

1. Xác nhận thời gian hiện tại đã sau `2026-07-25 13:01:00 +07:00`.
2. Kiểm tra Git local và remote vẫn ở SHA dự kiến; không pull/reset tự động.
3. Kiểm tra release active và Caddyfile vẫn trỏ tới `f903430...`.
4. Kiểm tra hash Caddy config và rollback config.
5. Kiểm tra:
   - `FrankenPHPService`: Running, Automatic;
   - `cloudflared`: Running, Automatic.
6. Kiểm tra port/origin nội bộ `127.0.0.1:8080`.
7. Xác minh các junction/hardlink shared state vẫn hợp lệ.
8. Không in nội dung `.env`.

### 11.2. HTTP/API smoke

Phải ghi lại status code và content type tối thiểu:

- `GET https://komibook.id.vn/` → 200, HTML.
- `GET https://komibook.id.vn/api/books` → 200, JSON.
- Một asset JS/CSS đang được HTML tham chiếu → 200, đúng MIME, không trả HTML fallback.
- `GET https://komibook.id.vn/api/auth/me` khi guest → 401.
- Cùng request với `Accept: application/json` → 401 JSON.
- Không có redirect sang port 5173/8000 hoặc hostname dev.

### 11.3. Log review

1. Đọc log Laravel shared và log dịch vụ từ mốc `2026-07-24 13:01:00 +07:00`.
2. Phân biệt lỗi cũ trước mốc với lỗi mới.
3. Tìm:
   - `production.ERROR`;
   - exception lặp;
   - lỗi database/session/permission;
   - crash/restart dịch vụ;
   - lỗi Cloudflare Tunnel;
   - asset 404/MIME sai.
4. Không kết luận pass nếu chỉ xem vài dòng cuối.

### 11.4. Browser smoke trước reboot

Chạy trên domain public, không dùng dev server:

1. Guest mở `/`, thấy dữ liệu public.
2. Guest mở `/register`, reload và đi từ `/login` sang `/register`.
3. Guest mở `/cart`.
4. Guest mở `/profile?tab=security`, xác nhận redirect được giữ nguyên.
5. Đăng nhập bằng tài khoản test/seed được người dùng cho phép.
6. Xác nhận quay lại `/profile?tab=security`.
7. Mở `/cart` khi đã đăng nhập.
8. Reload để kiểm tra cookie session.
9. Logout.
10. Xác nhận không fetch/401 loop.
11. Kiểm tra console: không runtime error; network không có request dev port.
12. Không tạo/xóa dữ liệu thật nếu chưa được cho phép.

### 11.5. Quyết định trước reboot

Nếu tất cả pre-reboot gate pass:

- báo bằng chứng ngắn gọn;
- yêu cầu người dùng phê duyệt reboot rõ ràng;
- không tự reboot.

Nếu có lỗi:

- dừng gate;
- xác định lỗi code, config, database, tunnel hay môi trường;
- không gỡ Herd;
- không bắt đầu Giai đoạn 2;
- chỉ rollback khi có kế hoạch và thẩm quyền phù hợp.

## 12. Gate reboot hệ điều hành

### Trước reboot

- Ghi lại thời gian, release active, hash config và trạng thái hai dịch vụ.
- Xác minh release rollback vẫn tồn tại.
- Xác minh không có tiến trình cài đặt/build/database migration đang chạy.
- Có sự phê duyệt rõ ràng của người dùng.

### Sau reboot

Không tự khởi động `php artisan serve` hoặc `npm run dev`. Kiểm tra:

1. `FrankenPHPService` tự lên `Running`, startup `Automatic`.
2. `cloudflared` tự lên `Running`, startup `Automatic`.
3. Database dùng bởi production sẵn sàng mà không cần thao tác dev ngoài kế hoạch.
4. `https://komibook.id.vn/` → 200 và có nội dung public.
5. `/api/books` → 200 JSON.
6. `/api/auth/me` guest → 401 đúng định dạng.
7. Asset JS/CSS → 200 đúng MIME.
8. Lặp browser smoke guest/protected/login/session/logout.
9. Xem log phát sinh kể từ thời điểm reboot.

Chỉ khi toàn bộ mục trên pass mới đánh dấu Batch 3 hoàn tất.

## 13. Batch 4 — gỡ phụ thuộc dev/Herd an toàn

Batch 4 chỉ bắt đầu sau Batch 3 pass.

### 13.1. Backup trước mọi gỡ cài đặt

Tạo và xác minh bản sao, không chỉ tạo folder rỗng:

- Toàn bộ Git repository, gồm `.git`, file tracked và file chưa commit.
- Dữ liệu Codex cần bảo toàn, tối thiểu `C:\Users\thean\.codex`.
- Dữ liệu Antigravity IDE trong `%APPDATA%\Antigravity IDE` và vị trí liên quan đã được kiểm kê.
- Tài liệu dự án và các file ngoài Git cần giữ.
- Database backup có thể restore.
- `C:\komibook_shared` và cấu hình production/rollback.
- Danh sách runtime/service/path hiện tại.

Không chép bí mật vào Git. Kiểm tra backup bằng cách liệt kê file, so hash chọn mẫu và thử đọc/restore theo mức an toàn.

### 13.2. Bảo toàn đường dẫn dự án và lịch sử chat

- Việc gỡ Herd không mặc định đồng nghĩa được phép xóa thư mục `C:\Users\thean\Herd`.
- Trước khi gỡ, chọn một đường dẫn nguồn mới ngoài thư mục Herd.
- Nên copy nguyên repository sang đường dẫn mới trước; xác minh `.git`, branch, HEAD, remote và file chưa commit; chỉ chuyển luồng làm việc sau khi bản copy đúng.
- Không xóa bản cũ trong cùng batch.
- Lịch sử Codex/Antigravity nằm trong dữ liệu ứng dụng người dùng, không chỉ trong repo, nên phải backup các thư mục ứng dụng nêu trên.
- Sau khi đổi đường dẫn, các chat cũ có thể vẫn ghi đường dẫn cũ; tài khoản mới phải được cung cấp đường dẫn mới rõ ràng.

### 13.3. Xử lý DNS/Tunnel legacy

Trước khi dừng port 8000:

- xác định ai/luồng nào còn dùng `api.komibook.id.vn`;
- đổi hoặc xóa mapping `api.komibook.id.vn → localhost:8000` theo quyết định người dùng;
- xác nhận frontend production dùng cùng origin `/api/...`;
- xác nhận domain chính và API vẫn pass.

Không dừng tiến trình port 8000 khi mapping legacy chưa được giải quyết.

### 13.4. Database và DBngin

- Gỡ Herd không đồng nghĩa được phép gỡ DBngin.
- Xác định chính xác MySQL/database production đang do DBngin, Windows service hay runtime khác quản lý.
- Nếu production còn phụ thuộc DBngin:
  - giữ DBngin; hoặc
  - lập batch riêng để chuyển MySQL sang dịch vụ độc lập, backup/restore và kiểm tra dữ liệu.
- Chỉ bỏ DBngin sau khi database độc lập auto-start sau reboot và toàn bộ API/database smoke pass.
- Không được xóa database hoặc data directory trong quá trình gỡ Herd.

### 13.5. Dừng dev stack và gỡ Herd

Sau khi các gate trên pass:

1. Xác định đúng process đang giữ port 5173 và 8000.
2. Dừng đúng dev process, không kill theo tên rộng.
3. Kiểm tra production domain vẫn hoạt động.
4. Dùng Windows uninstaller chính thức để gỡ Herd.
5. Chỉ dọn PATH/shortcut/service của Herd sau khi xác minh chúng thuộc Herd.
6. Không xóa thủ công thư mục chứa repository hoặc dữ liệu chưa backup.
7. Không đụng tới portable runtimes đang dùng bởi production.

### 13.6. Nghiệm thu sau gỡ Herd

- Git và repository ở đường dẫn mới hoạt động.
- Codex/Antigravity tiếp tục nhìn thấy task/chat cần thiết hoặc backup đã được xác minh.
- `FrankenPHPService` và `cloudflared` vẫn Running/Automatic.
- Domain, API, auth và asset smoke pass.
- Database đọc/ghi theo smoke được phê duyệt.
- Không cần Herd, `php artisan serve`, `npm run dev` hoặc port 8000/5173 để dùng production.
- Portable Node/Composer/FrankenPHP và toolchain cần thiết vẫn chạy.
- Reboot bổ sung chỉ thực hiện khi người dùng phê duyệt.

## 14. Lưu ý runtime và lệnh dependency

- PHP CLI thông thường từng cảnh báo extension `openssl` được nạp hai lần.
- FrankenPHP direct test từng thiếu PDO SQLite trong ngữ cảnh test.
- Composer script gọi `@php artisan ...` từng bị FrankenPHP hiểu sai thành command `artisan`.
- Quy trình production đã dùng:
  - cài Composer dependency với `--no-scripts`;
  - sau đó chạy thủ công package discovery bằng `frankenphp php-cli artisan package:discover --ansi`.
- Không tự đổi quy trình hoặc chạy install vào release active khi chưa có batch/release mới.
- `npm ci` gần nhất pass nhưng báo 2 vulnerability (1 high, 1 critical). Không tự chạy `npm audit fix`.

## 15. Quy tắc rollback

Rollback là thao tác production có tác động lớn:

- Không rollback chỉ vì một cảnh báo đã biết hoặc lỗi cũ trong log.
- Xác nhận lỗi thuộc release active và ảnh hưởng availability/security/data integrity.
- Giữ nguyên bằng chứng: timestamp, request, log, release SHA và config hash.
- Dùng release/config rollback đã chuẩn bị, không chỉnh chắp vá trực tiếp vào release bất biến.
- Sau rollback phải kiểm tra service, home, API, auth, asset và database.
- Báo rõ release nào active sau thao tác.
- Không xóa release bị rollback để còn điều tra.
- Cần chấp thuận của người dùng trước rollback, trừ khi người dùng đã trao quyền cụ thể cho tình huống khẩn cấp đó.

## 16. Cách chia nhỏ công việc cho Antigravity/Codex sau này

Để tránh hết ngữ cảnh:

1. Mỗi batch chỉ có một mục tiêu nghiệp vụ hoặc một cổng vận hành.
2. Prompt phải ghi:
   - file/module được phép sửa;
   - file/module cấm sửa;
   - test cần thêm;
   - lệnh nghiệm thu;
   - yêu cầu không commit/push.
3. Antigravity triển khai mã nguồn; Codex audit độc lập bằng diff, mã và test.
4. Các việc chỉ đọc/vận hành/nghiệm thu nên để Codex làm trực tiếp để tránh lặp quota.
5. Lệnh cài dependency có thể do người dùng tự nhập nếu agent không kiểm soát chính xác môi trường.
6. Chỉ đề xuất commit khi batch đã pass; người dùng quyết định commit/push.
7. Mỗi lần bàn giao phải ghi:
   - HEAD/release hiện tại;
   - file đã đổi;
   - test pass/fail;
   - blocker;
   - bước tiếp theo duy nhất.
8. Với Giai đoạn 4B/4C, không giao một prompt “làm toàn bộ hệ sinh thái”; tách tối thiểu:
   - ADR vai trò author/vendor;
   - author onboarding;
   - vendor onboarding;
   - copyright;
   - editor/self-publishing;
   - CMS/article moderation;
   - commission;
   - Flash Sale.
9. Mỗi state machine phải được duyệt trước khi tạo migration/controller/UI.
10. Không bắt agent cài package mơ hồ; nếu có dependency mới, agent chỉ nêu package/version/lý do và người dùng quyết định chạy lệnh install.

## 17. Thứ tự tiếp tục được khuyến nghị

1. Chờ đến sau `2026-07-25 13:01:00 +07:00`.
2. Chạy toàn bộ gate ở Mục 11.
3. Nếu pass, xin phép reboot.
4. Chạy toàn bộ gate sau reboot ở Mục 12.
5. Đánh dấu Batch 3 hoàn tất khi có đủ bằng chứng.
6. Chuẩn bị/kiểm tra backup.
7. Giải quyết mapping `api.komibook.id.vn`.
8. Xác định rõ dependency database/DBngin.
9. Di chuyển/copy repository ra khỏi đường dẫn Herd và xác minh lịch sử chat/data ứng dụng.
10. Dừng dev stack cũ, gỡ Herd và nghiệm thu Batch 4.
11. Cập nhật tài liệu production/report bằng một batch tài liệu riêng nếu người dùng cho phép.
12. Xin phê duyệt bắt đầu Giai đoạn 2.
13. Chia Giai đoạn 2 theo thứ tự:
    - 2A: ADR state machine và invariant;
    - 2B: migration checkout session/payment transaction;
    - 2C: VNPAY IPN idempotent;
    - 2D: inventory reservation/concurrency;
    - 2E: job, points và ledger idempotent;
    - 2F: integration/E2E và migration rehearsal.
14. Chỉ sang Giai đoạn 3 sau khi cổng tiền/order/kho của Giai đoạn 2 pass.
15. Giai đoạn 3 sửa contract/mock trước để Giai đoạn 4 không xây trên API giả hoặc endpoint lệch.
16. Giai đoạn 4 thực hiện theo 4A → ADR vai trò → 4B → 4C; có thể đổi thứ tự các batch nhỏ sau khi re-audit dependency nhưng không bỏ gate.
17. Giai đoạn 5 chuẩn hóa Policy/service/schema sau khi state machine nghiệp vụ đã ổn định; phần Policy P0 cần thiết có thể làm sớm trong chính batch nghiệp vụ.
18. Giai đoạn 6 bổ sung CI/E2E theo từng batch, không đợi đến cuối mới viết toàn bộ test.
19. Giai đoạn 7 hoàn thiện staging/observability/backup.
20. Giai đoạn 8 chạy UAT và go-live gate; chỉ phát hành khi không còn P0.

## 18. Điều kiện để tuyên bố hoàn tất đợt production hiện tại

Chỉ được tuyên bố hoàn tất khi:

- cửa sổ 24 giờ hợp lệ;
- pre/post reboot smoke pass;
- hai dịch vụ tự khởi động;
- guest vẫn xem được trang chủ và nội dung public;
- auth/redirect/session/logout đúng;
- không có lỗi production mới chưa giải thích;
- database ổn định;
- rollback còn sử dụng được;
- mapping legacy/dev dependency được xử lý;
- việc gỡ Herd không làm mất repository, dữ liệu, chat hoặc toolchain;
- người dùng đã xem và phê duyệt kết quả.

## 19. Tài liệu nguồn phải đọc khi tiếp nhận

- `Tai_lieu/Kế hoạch nâng cấp chất lượng dự án.docx`
- `docs/upgrade/00-baseline.md`
- `docs/upgrade/01-api-contract-matrix.md`
- `docs/upgrade/02-priority-backlog.md`
- `docs/upgrade/03-phase-1-security-report.md`
- `docs/upgrade/implementation_plan.md`
- `docs/upgrade/production_deployment_plan.md`
- `docs/upgrade/windows_production_deployment_plan.md`
- `docs/upgrade/inventory_decision_record.md`
- `docs/upgrade/runtime_artifact_manifest.md`

Tài khoản tiếp nhận nên bắt đầu bằng audit chỉ đọc, xác nhận trạng thái hiện tại rồi mới đề xuất hành động. Tài liệu này ghi lại trạng thái tại thời điểm lập, không phải bằng chứng thay thế cho kiểm tra live sau 24 giờ.
