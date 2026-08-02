# Kế hoạch lịch sử: thanh toán, thuế doanh thu Nhà bán và ví demo

> Cập nhật 02/08/2026: phần MoMo và payOS trong tài liệu này đã bị hủy. Hệ thống chỉ còn COD, VNPAY Sandbox và Ví KomiBook giả lập. Thiết kế hiện hành được ghi tại `docs/upgrade/195-vnpay-sandbox-and-komibook-wallet.md`; các mục MoMo/payOS bên dưới chỉ được giữ làm biên bản quyết định, không phải cấu hình đang hỗ trợ.

Ngày lập: 02/08/2026
Trạng thái: **đã được phê duyệt và triển khai source cục bộ — chưa commit/push/deploy/production**

Quyết định được duyệt ngày 02/08/2026:

- kỳ tính thuế theo năm dương lịch;
- thuế lũy tiến từng phần theo các bậc do Admin nhập, không gán thuế suất pháp lý mặc định;
- căn cứ tính thuế là `checkout_session_orders.total_amount`, tức tổng tiền khách thanh toán trước commission;
- thuế thực sự được trừ khỏi earning và số dư có thể payout của Nhà bán;
- payOS đã bị loại khỏi hệ thống theo quyết định ngày 02/08/2026 vì không phù hợp yêu cầu không phát sinh phí.
- VNPAY được khôi phục bằng gateway hiện hữu trên production; local dùng VNPAY Demo
  vì VNPAY sandbox từ chối callback localhost với mã 71 “Website chưa được phê duyệt”.
- VNPAY Demo, MoMo và ví KomiBook chạy mô phỏng nội bộ, không gọi provider hoặc phát sinh phí.

## 1. Gate batch giao diện Antigravity

Các lỗi chặn đã được sửa và nghiệm thu lại trước khi triển khai thanh toán/thuế:

1. Production build đang thất bại tại `ArticleEditorView.vue` vì dùng cú pháp CSS
   `var(--color-outline-variant/60)` không hợp lệ. Hai biến thể `/40` và `/50` trong
   cùng khối cũng phải được sửa bằng `color-mix(...)` hoặc token hợp lệ.
2. `NotificationsView.vue` tự tạo các đường dẫn `/books/:slug`, `/vendors/:slug` và
   `/orders/:id`, trong khi router công khai hiện dùng `/book/:slug`, `/shops/:slug`
   và chỉ có `/orders`. Hành động “Chuyển tới trang liên quan” có thể dẫn tới 404.
3. Toàn bộ thẻ thông báo được mở bằng `@click` trên `article` nhưng không có semantics,
   `tabindex` và phím Enter/Space. Người dùng bàn phím không mở được chi tiết.
4. `BookCard.vue` đã bỏ focus ring của nút yêu thích; icon trắng có thể mất tương phản
   trên bìa sáng. `AppFooter.vue` hạ liên kết xuống `text-xs`, bỏ mục tiêu chạm 44 px và
   có metadata 11 px, trái design system KomiBook.
5. `NotificationsView.vue` còn trailing whitespace; `git diff --check` chưa đạt.
6. Bốn ảnh `header_*.jpg` mới (tổng khoảng 2,44 MB) chưa được source tham chiếu. Cần
   xóa khỏi batch hoặc tích hợp có chủ đích và tối ưu WebP/AVIF trước khi phát hành.

Gate trực tiếp đã đạt: oxlint và eslint sạch, 29 file/129 test frontend đạt, production
build đạt (chỉ còn cảnh báo chunk lớn). Browser responsive còn là gate trước deployment.

## 2. Phạm vi sản phẩm

### Trong phạm vi

- Bổ sung thanh toán MoMo demo và khôi phục VNPAY hiện hữu cho checkout nhiều Nhà bán.
- Bỏ hoàn toàn thẻ Credit Card/Stripe khỏi Cấu hình hệ thống.
- Thay danh sách cổng đang hard-code bằng trạng thái khả dụng do backend trả về.
- Bổ sung ví khách hàng demo để thanh toán đơn demo và chạy hết luồng doanh thu.
- Bổ sung hồ sơ thuế Nhà bán, lịch thuế theo ngưỡng doanh thu, ledger và snapshot.
- Hiển thị rõ tiền khách trả, commission, thuế khấu trừ Nhà bán và số tiền Nhà bán
  được ghi nhận/rút.

### Ngoài phạm vi nếu chưa có phê duyệt riêng

- Không đăng ký merchant, nhập khóa thật, gọi API thật, xác nhận webhook thật hoặc
  phát sinh phí từ provider mới.
- Không mở payout thật cho Vendor demo.
- Không tự suy đoán thuế suất pháp lý hay dùng một tỷ lệ mẫu như tỷ lệ chính thức.
- Không sửa ngược invoice/order/earning lịch sử.
- Không lưu secret trong database, giao diện admin, log, payload hoặc Git.

## 3. Quyết định kiến trúc thanh toán đề xuất

### 3.1. Một state machine dùng chung

Giữ `payment_transactions` làm nguồn chuẩn và dùng provider:
`vnpay`, `momo`, `demo_wallet`. Trạng thái vẫn là:

`pending -> paid | failed | expired -> refunding -> refunded`

Retry luôn tạo attempt mới; không hồi sinh attempt thất bại/hết hạn. Một payment
transaction thanh toán toàn bộ checkout session, không thanh toán riêng order đầu tiên.
`orders.payment_method` tiếp tục là loại tổng quát `cod|online`; provider cụ thể nằm ở
payment transaction để tránh mở rộng enum legacy và tránh lệch dữ liệu.

### 3.2. Hợp đồng provider trong phạm vi không phát sinh phí

Lớp MoMo chỉ thực hiện tạo và xác minh HMAC cục bộ. Luồng checkout demo dùng
`SimulatedPaymentService`, không gửi request ra internet. Kết nối gateway live/webhook
thật được giữ ngoài phạm vi và cần phê duyệt mới về credential, chi phí và production.

- `createAttempt(checkout, transaction, context)`;
- `verifyNotification(payload, headers)`;
- `queryStatus(transaction)`;
- `cancel/expire` nếu provider hỗ trợ;
- refund qua registry riêng, chỉ bật khi hợp đồng provider đã được kiểm chứng.

`PaymentOrchestrationService` chịu trách nhiệm khóa checkout/transaction, kiểm tra
buyer, amount nguyên VND, currency, idempotency, expiry và gọi state transition dùng
chung. Gateway chỉ ký/xác minh/giao tiếp giao thức, không tự sửa Order/Inventory/Ledger.

### 3.3. Endpoint chuẩn hóa

- `GET /api/payment-providers`: chỉ trả capability công khai.
- `POST /api/payments/{provider}/attempts`: tạo/reuse attempt còn hiệu lực.
- `POST /api/payments/{provider}/attempts/{transaction}/complete`: xác nhận mô phỏng
  do chính buyer thực hiện; route chỉ khả dụng khi provider ở mode `demo`.
- Giữ route VNPAY thật để tương thích production. Khi capability VNPAY có mode `demo`,
  checkout dùng endpoint mô phỏng chung và tuyệt đối không chuyển hướng ra sandbox.

### 3.4. MoMo

- Dùng thanh toán một lần trên Payment Gateway, không dùng QR tĩnh vì QR tĩnh không
  gắn chắc trạng thái với checkout.
- `requestId` và `orderId` phải duy nhất, khớp payment attempt; ký/xác minh HMAC-SHA256.
- Lưu `payUrl`/deeplink/QR artifact có thời hạn nhưng không lưu access key, secret key
  hoặc raw signature.
- Chỉ IPN hợp lệ, đúng partner, amount, order/request identity và chưa xử lý mới được
  chuyển `pending -> paid`.

### 3.5. VNPAY theo môi trường

- Local/testing: `VNPAY_MODE=demo`; tạo QR demo, buyer xác nhận trong dialog và toàn
  bộ trạng thái payment/order vẫn đi qua transaction/lock/idempotency dùng chung.
- Production: `VNPAY_MODE=existing`; dùng gateway, return URL và IPN đã đăng ký với
  VNPAY. Production không được phép xác nhận thanh toán chỉ từ browser return.
- Lệnh `payments:enable-demo` bật VNPAY, MoMo và ví KomiBook demo chỉ trong
  `local|testing`, đồng thời xóa cấu hình payOS cũ.

### 3.6. payOS/VietQR — đã hủy

Không triển khai, không hiển thị capability, không cho checkout và không lưu cấu hình
payOS mới. Nếu tồn tại giao dịch lịch sử, dữ liệu ledger được giữ để kiểm toán nhưng
không gọi provider; Admin phải xử lý thủ công.

## 4. Cấu hình hệ thống không gây hiểu nhầm

Xóa `Credit Card (Stripe)` và payOS khỏi UI. Không hiển thị MoMo là “đang bật” chỉ
vì một mảng frontend đặt `enabled: true`.

Backend trả mỗi provider với:

- `mode`: `disabled | demo` trong phạm vi đã duyệt;
- `configured`: boolean (không trả tên/giá trị secret);
- `enabled_by_admin`: boolean có audit;
- `available`: chỉ true khi policy + runtime config đều hợp lệ;
- `supports_qr`, `supports_refund`, `supports_reconciliation`;
- thông điệp vận hành an toàn, ví dụ “Chưa cấu hình khóa môi trường”.

Secret chỉ lấy từ environment/secret store. Bật/tắt admin là bản ghi có actor, reason,
operation key và effective time; không phải toggle cục bộ không lưu.

## 5. Ví điện tử demo

### 5.1. Mục đích và ranh giới

Ví demo là sổ cái nội bộ để trình diễn, không đại diện tiền thật, không nạp/rút/chuyển
ra ngoài và không dùng chung với MoMo/VNPAY. Chỉ tài khoản demo được cấp ví; checkout
chỉ cho chọn khi toàn bộ đơn thuộc Vendor demo và feature flag demo đang bật.

### 5.2. Dữ liệu

- `demo_wallet_accounts`: owner, currency VND, status, balance projection.
- `demo_wallet_ledger_entries`: immutable debit/credit, checkout/payment reference,
  operation key unique, amount nguyên VND, balance_after, actor và metadata đã lọc.
- Không cho sửa balance trực tiếp; projection phải khớp tổng ledger.

### 5.3. Luồng hoàn chỉnh

1. Checkout tiếp tục reserve tồn kho; ví được kiểm tra và debit trong transaction xác nhận.
2. Khi xác nhận thanh toán demo, debit đúng một lần và chạy service hoàn tất payment
   hiện có để commit tồn kho/xác nhận orders.
3. Khi order hoàn tất, earning ledger hiện có ghi doanh thu Vendor; Vendor demo thấy
   số dư doanh thu mô phỏng nhưng payout thật tiếp tục bị `demo_disabled`.
4. Hủy/hết hạn giải phóng reservation; refund tạo credit reversal có liên kết nguồn.
5. UI luôn có badge “Dữ liệu mô phỏng — không phải tiền thật”.

## 6. Thuế theo doanh thu Nhà bán

### 6.1. Không trộn thuế Nhà bán với thuế khách hàng

Thuế/khấu trừ của Nhà bán không được cộng thêm vào tiền khách thanh toán. Công thức đã duyệt:

```text
taxable_revenue = tổng tiền khách thanh toán của order trước commission
commission = snapshot theo lịch commission
vendor_tax_withheld = thuế lũy tiến theo doanh thu cộng dồn năm và lịch thuế hiệu lực
seller_net_payable = seller_gross - commission - vendor_tax_withheld
customer_pays = taxable_revenue
```

Nếu commission cộng thuế vượt gross của order, khoản thuế thực thu được giới hạn ở
phần còn lại sau commission và snapshot ghi `uncollected_tax`; không tạo số dư âm.

### 6.2. Schema đã triển khai

- `vendor_tax_schedules`: append-only theo năm, các bậc `up_to/rate_bps`, thời điểm
  hiệu lực, actor, reason và operation key.
- `vendor_tax_ledger_entries`: earning/reversal, doanh thu tính thuế signed, số thuế
  signed, snapshot công thức và operation key duy nhất.
- `vendor_earning_ledgers`: bổ sung tax amount và schedule, net đã trừ thuế.
- `vendor_earning_reversals`: bổ sung tax amount để refund đảo đúng tỷ lệ snapshot.

### 6.3. Quy tắc bắt buộc

- Chỉ dùng lịch có hiệu lực tại thời điểm earning; cấu hình mới không hồi tố.
- Revenue basis đã duyệt: tổng tiền khách thanh toán của order trước commission.
- Làm tròn half-up từng khoản tới VND nguyên; không dùng float để đối soát cuối kỳ.
- Refund tạo reversal theo snapshot gốc, không tính lại bằng lịch hiện hành.
- Payout chỉ dùng `seller_net_payable`; chặn payout nếu ledger thuế không cân.
- Admin được nhập bậc thuế nhưng bắt buộc effective time, reason, preview và xác nhận;
  không cung cấp “thuế suất mặc định chính thức”.

### 6.4. Quyền cấu hình

Chỉ Admin được tạo lịch thuế mới. Lịch sử không sửa/xóa; mọi lần tạo bắt buộc có
effective time, reason và operation key. Nếu chưa có biểu cho năm hiện hành, hệ thống
ghi nhận doanh thu tính thuế với thuế bằng 0 và hiển thị rõ “chưa cấu hình”, không tự
suy đoán thuế suất.

## 7. Chia batch triển khai

### Batch 0 — Sửa và nghiệm thu UI Antigravity — hoàn tất

Sửa sáu mục tại phần 1, bổ sung test route/keyboard/focus/build. Không đổi nghiệp vụ.

### Batch 1 — ADR và provider foundation — hoàn tất source cục bộ

Chốt ADR thanh toán + thuế; tạo provider enum/registry, capability API, generic
orchestrator và adapter VNPAY tương thích. Chưa thêm cổng ngoài.

### Batch 2 — payOS QR demo — đã hủy và gỡ khỏi hệ thống

Gateway, ký/xác minh, create link, webhook, return/cancel UI, expiry/reconciliation,
mock contract tests. Không gọi API thật.

### Batch 3 — MoMo demo — hoàn tất source cục bộ

One-time gateway, IPN/return, deeplink/QR responsive, expiry/reconciliation và mock
contract tests. Không gọi API thật.

### Batch 4 — Demo wallet — hoàn tất luồng debit/payment cục bộ

Schema ledger/reservation, payment adapter, admin seed/reset an toàn cho demo, checkout
UI, refund reversal và khoá payout thật.

### Batch 5 — Vendor revenue tax — hoàn tất engine/earning/refund/payout projection cục bộ

Schema/profile/schedule/ledger, tax engine, earning/refund/payout integration, admin
preview/history, Vendor finance breakdown và invoice/export liên quan.

### Batch 6 — Gate tích hợp và rehearsal — đang thực hiện

Test checkout nhiều Vendor cho COD/VNPAY/MoMo/demo wallet; webhook lặp/sai chữ
ký/sai amount; retry/expiry/refund; thuế vượt ngưỡng và refund qua kỳ; concurrency;
migration up/down trên DB test; frontend lint/test/build; browser responsive. Sau đó mới
xin phê duyệt riêng cho sandbox thật, credential, Git và production.

## 8. Tiêu chí nghiệm thu tối thiểu

- Provider chưa cấu hình không xuất hiện như phương thức khả dụng ở checkout.
- Return URL không thể tự đánh dấu paid; webhook/IPN giả hoặc sai amount luôn fail closed.
- Callback lặp 5 lần chỉ có một payment transition, một inventory commit và một earning.
- Ví demo không thanh toán Vendor thật, không rút tiền thật và không tạo số dư âm.
- Tax schedule append-only; order/earning cũ giữ snapshot; refund đảo đúng snapshot gốc.
- Tổng ledger: tiền khách = seller gross + service fee + thuế khách; payout Vendor =
  gross - commission - thuế Vendor - reversals.
- Không secret/signature/raw credential trong response, log, DB payload hoặc source.
- Toàn bộ test backend/frontend, lint, production build và `git diff --check` đạt.

## 9. Tài liệu giao thức chính thức dùng khi triển khai

- MoMo One-Time Payments: https://developers.momo.vn/v3/docs/payment/api/wallet/onetime/
- MoMo Payment Gateway: https://developers.momo.vn/v3/docs/payment/guides/payment-with-aio/

Mọi chi tiết giao thức phải được kiểm tra lại theo tài liệu chính thức tại thời điểm bắt
đầu batch; không sao chép secret mẫu và không dùng tài liệu bên thứ ba làm nguồn chuẩn.
