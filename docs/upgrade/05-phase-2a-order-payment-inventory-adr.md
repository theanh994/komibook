# ADR 0001: State machine và bất biến cho Order, Payment, Shipping và Inventory

- **Trạng thái ADR:** Accepted — người dùng phê duyệt ngày 2026-07-25
- **Ngày:** 2026-07-25
- **Giai đoạn:** 2A
- **Phạm vi:** thiết kế và ràng buộc nghiệp vụ; chưa tạo migration, controller, UI hoặc thay đổi production
- **Issue liên quan:** `PAY-01`, `PAY-02`, `PAY-03`, `INV-01`, `INV-02`, `ORD-01`, `FIN-01`

## 1. Bối cảnh và bằng chứng trực tiếp

KomiBook hiện tách một checkout đa nhà bán thành nhiều `orders`, nhưng chưa có
thực thể checkout tổng hợp hoặc payment transaction độc lập:

- `CheckoutService` nhóm item theo `vendor_id`, tạo một order cho mỗi vendor và
  dispatch `ProcessOrder` cho tất cả order ngay sau khi commit.
- VNPAY hiện nhận một `order_id`, tạo số tiền từ đúng order đó và giải mã
  `vnp_TxnRef` trở lại một order.
- `ProcessOrder` luôn chuyển order sang `processing`, trừ `books.stock`, trừ
  `warehouse_stocks`, tạo notification và gửi email; job chưa có operation key
  chống chạy lặp.
- VNPAY IPN trực tiếp gán `order.status`, `payment_status`, `payment_method` rồi
  dispatch lại `ProcessOrder`.
- Vendor controller cho phép chọn trạng thái đích mà không kiểm tra đầy đủ cạnh
  chuyển hợp lệ.
- Shipping controller trực tiếp gán `shipping_status`, tự chuyển order sang
  `completed`/`cancelled` và cộng điểm.
- Model `Order` tự đổi payment thành `paid` và cộng balance cho vendor khi order
  chuyển `completed`.
- Tồn kho hiện xuất hiện đồng thời ở `books.stock`, `warehouse_stocks.quantity`
  và Redis `book_stock:{id}`. DB fallback chỉ đọc tồn kho, không khóa bi quan.

Những đường ghi phân tán này khiến trạng thái có thể mâu thuẫn và side effect có
thể chạy nhiều lần khi request, webhook hoặc queue được retry.

## 2. Quyết định

### 2.1. Tách bốn state machine độc lập

Order, Payment, Shipping và Inventory Reservation là bốn vòng đời riêng. Không
dùng một trạng thái để suy ra hoặc thay thế trạng thái khác.

Mọi chuyển trạng thái phải đi qua application service/policy chuyên trách, trong
một database transaction, với:

1. actor và quyền thực hiện;
2. trạng thái nguồn kỳ vọng;
3. trạng thái đích hợp lệ;
4. operation/idempotency key;
5. row lock đối với dữ liệu cạnh tranh;
6. audit event;
7. side effect được ghi nhận để chạy sau commit.

Controller, model event, webhook handler và queue job không được tự gán chuỗi
trạng thái hoặc trực tiếp thực hiện side effect tài chính/tồn kho.

### 2.2. Order state machine

Trạng thái chuẩn:

`pending`, `confirmed`, `processing`, `shipped`, `completed`, `cancelled`,
`refunded`.

| Từ | Đến | Sự kiện/actor hợp lệ | Điều kiện chính |
|---|---|---|---|
| — | `pending` | Checkout service | Order thuộc một checkout session; snapshot tiền và item hợp lệ |
| `pending` | `confirmed` | Payment service hoặc vendor/system COD | Online đã `paid`; COD đã được chấp nhận |
| `pending` | `cancelled` | Customer/vendor/system | Chưa bắt đầu fulfillment; reservation được giải phóng |
| `confirmed` | `processing` | Fulfillment service/vendor | Inventory đã `committed` hoặc order chỉ có ebook |
| `confirmed` | `cancelled` | Customer/vendor/system theo policy | Chưa giao; nếu đã thu tiền phải mở quy trình refund |
| `processing` | `shipped` | Fulfillment/carrier | Order có sách vật lý; kiện hàng đã bàn giao |
| `processing` | `completed` | Fulfillment service | Order chỉ có nội dung số và entitlement đã cấp idempotent |
| `shipped` | `completed` | Shipping service/carrier | Shipping đã `delivered` |
| `cancelled` | `refunded` | Refund service | Khoản tiền đã thu được hoàn toàn |
| `completed` | `refunded` | Refund service | Full refund đã được provider xác nhận |

Các trạng thái `cancelled` và `refunded` là terminal trong Giai đoạn 2. Return,
partial refund và mở lại đơn thuộc Giai đoạn 4; không âm thầm bổ sung cạnh chuyển
trong controller.

Quy tắc bổ sung:

- Không nhảy trực tiếp `pending → processing/shipped/completed`.
- Không cho vendor đặt tùy ý trạng thái đích.
- `completed` chỉ biểu thị fulfillment hoàn tất, không tự động có nghĩa payment
  vừa được thu.
- Order COD có thể `completed` trong khi payment chỉ chuyển `paid` tại sự kiện
  thu tiền được xác nhận; không suy ra payment bằng model event.
- Order có cả ebook và sách vật lý đi theo nhánh physical; entitlement số chỉ
  được cấp theo policy đã xác định và phải idempotent.

### 2.3. Payment state machine

Trạng thái chuẩn cho từng payment transaction:

`pending`, `paid`, `failed`, `expired`, `refunding`, `refunded`.

| Từ | Đến | Sự kiện hợp lệ | Điều kiện chính |
|---|---|---|---|
| — | `pending` | Payment service | Tạo attempt mới với idempotency key |
| `pending` | `paid` | IPN/webhook đã xác minh | Đúng merchant, amount, currency, provider transaction |
| `pending` | `failed` | IPN/webhook đã xác minh | Provider xác nhận thất bại |
| `pending` | `expired` | Scheduler/provider | Quá hạn và chưa thanh toán |
| `paid` | `refunding` | Refund service | Refund request hợp lệ |
| `refunding` | `refunded` | Provider webhook/reconciliation | Provider xác nhận hoàn tiền đầy đủ |
| `refunding` | `paid` | Provider webhook/reconciliation | Refund thất bại; lưu attempt và lý do riêng |

`failed`, `expired` và `refunded` là terminal. Retry thanh toán tạo transaction
mới; không đưa transaction cũ trở lại `pending`.

Return URL chỉ hiển thị kết quả. IPN/webhook hoặc reconciliation là nguồn xác
nhận payment. Callback lặp phải trả kết quả thành công tương thích với provider
nhưng không lặp bất kỳ side effect nào.

### 2.4. Shipping state machine

Trạng thái chuẩn:

`pending_pickup`, `picked_up`, `delivering`, `delivered`, `failed`, `returned`.

| Từ | Đến | Actor hợp lệ |
|---|---|---|
| — | `pending_pickup` | Fulfillment service sau khi order sẵn sàng giao |
| `pending_pickup` | `picked_up` | Carrier/vendor fulfillment |
| `picked_up` | `delivering` | Carrier |
| `delivering` | `delivered` | Carrier |
| `pending_pickup` | `failed` | Carrier/vendor fulfillment |
| `picked_up` | `failed` | Carrier |
| `delivering` | `failed` | Carrier |
| `failed` | `pending_pickup` | Fulfillment service khi tạo lượt giao lại |
| `failed` | `returned` | Carrier/fulfillment xác nhận trả về |
| `delivered` | `returned` | Return service sau yêu cầu trả hàng được duyệt |

Order chỉ có ebook không có shipping lifecycle. Với order có sách vật lý,
`shipping.delivered` mới được phép kích hoạt `order.shipped → completed`.
`returned` không tự động quyết định refund; return và refund phải được đối soát
riêng ở Giai đoạn 4.

### 2.5. Inventory Reservation state machine

Trạng thái chuẩn:

`reserved`, `committed`, `released`, `expired`.

| Từ | Đến | Sự kiện hợp lệ | Hiệu ứng |
|---|---|---|---|
| — | `reserved` | Checkout service | Giữ số lượng khả dụng trong transaction có row lock |
| `reserved` | `committed` | Payment success hoặc COD confirmation | Trừ on-hand đúng một lần |
| `reserved` | `released` | Cancel/payment failure | Trả khả dụng, không trừ on-hand |
| `reserved` | `expired` | Scheduler | Trả khả dụng, không trừ on-hand |

Ba trạng thái đích là terminal. Mỗi order item vật lý phải được bao phủ bởi đúng
tổng số lượng reservation/allocation; ebook không tạo reservation.

## 3. Bất biến bắt buộc

### 3.1. Checkout và tiền

1. Một checkout session thuộc đúng một buyer và liên kết một hoặc nhiều order.
2. Mỗi order thuộc đúng một vendor; mọi order trong session thuộc cùng buyer và
   cùng snapshot checkout.
3. Tổng phải thanh toán của checkout bằng tổng order, phí và giảm giá đã
   snapshot; không đọc lại giá/coupon/commission hiện tại để đối soát lịch sử.
4. Một VNPAY transaction thanh toán toàn bộ checkout session, không thanh toán
   riêng order đầu tiên.
5. Amount dùng số nguyên VND; so sánh chính xác, không dùng float.
6. Provider transaction ID là unique khi có; idempotency key là unique trong
   phạm vi provider và operation.
7. Payload lưu trữ phải loại bỏ secret/chữ ký nhạy cảm theo policy retention.
8. Chỉ payment `paid` mới xác nhận order online.
9. Không dispatch fulfillment cho order online khi payment chưa `paid`.
10. Một payment attempt không được thu/ghi nhận thành công hai lần.

### 3.2. Order và side effect

1. Mỗi transition kiểm tra trạng thái nguồn bằng row lock hoặc compare-and-set.
2. Cùng operation key chỉ tạo một transition/audit event.
3. Notification, email, entitlement, điểm và ledger mỗi loại chỉ chạy một lần
   cho cùng order/operation/type.
4. Hoàn tất order không được trực tiếp cộng vendor balance trong model event.
5. Điểm và vendor earning phải có ledger/transaction unique; aggregate balance
   chỉ là projection.
6. Side effect bên ngoài transaction được ghi bằng outbox hoặc bản ghi operation
   bền vững trước khi queue chạy.
7. Bulk update phải áp dụng transition policy cho từng order; không mass-update
   bỏ qua state machine.

### 3.3. Inventory

1. Database là nguồn tồn kho chuẩn; Redis chỉ là cache/lock hỗ trợ.
2. `warehouse_stocks.quantity` là on-hand theo kho và không bao giờ âm.
3. Available-to-sell bằng on-hand trừ tổng reservation `reserved` chưa hết hạn.
4. `books.stock` chỉ là projection số lượng có thể bán, không phải nguồn chuẩn
   độc lập; mọi cập nhật phải đồng bộ từ cùng transaction/operation.
5. Reservation phải khóa các hàng tồn kho theo thứ tự xác định để tránh deadlock.
6. Hai buyer tranh cuốn cuối cùng chỉ một người tạo được reservation.
7. Commit/release/expire lặp không thay đổi tồn kho lần hai.
8. Payment failed/expired và order cancelled phải giải phóng reservation.
9. Chỉ sách `published`, vendor `active` và sách vật lý còn available mới được
   reservation.
10. Mọi điều chỉnh, kiểm kê, điều chuyển và fulfillment phải ghi inventory
    operation/audit; không sửa một projection đơn lẻ.

## 4. Ranh giới transaction và thứ tự khóa

Để giảm deadlock, service phải khóa theo thứ tự ổn định:

1. checkout session;
2. payment transaction hoặc order theo ID tăng dần;
3. book/warehouse stock theo `(book_id, warehouse_id)` tăng dần;
4. inventory reservation/allocation;
5. operation/ledger/outbox liên quan.

Không giữ database transaction trong lúc gọi VNPAY, gửi email hoặc gọi dịch vụ
ngoài. Các tác vụ đó chạy sau commit và phải retry an toàn.

## 5. Actor và quyền

- **Customer:** tạo checkout; yêu cầu hủy trong cạnh được policy cho phép. Customer
  không trực tiếp xác nhận payment/shipping/refund.
- **Vendor:** xác nhận COD, bắt đầu xử lý và bàn giao hàng cho các order thuộc
  vendor đó; không xác nhận `paid`, `delivered` hoặc `refunded`.
- **Carrier integration/admin vận hành:** cập nhật shipping theo quyền và bằng
  chứng; mọi override cần reason/audit.
- **Payment service:** là đường duy nhất chuyển payment theo webhook,
  reconciliation hoặc refund result đã xác minh.
- **System scheduler/queue:** expire reservation/payment và chạy side effect bằng
  operation key.
- **Admin:** không bypass transition graph; override khẩn cấp phải là command
  riêng, có reason và audit.

## 6. Hệ quả cho các batch sau

### 2B — schema checkout/payment

Dự kiến cần:

- `checkout_sessions`;
- `checkout_session_orders`;
- `payment_transactions`;
- transition/audit hoặc operation records;
- snapshot subtotal, discount, fee, commission, currency và total.

Schema cụ thể và kế hoạch backfill/rollback phải được duyệt riêng trong 2B.

### 2C — VNPAY

- `createPayment` nhận checkout session/payment attempt thay vì order đầu tiên.
- Xác minh signature bằng so sánh constant-time.
- IPN kiểm tra merchant, amount, currency và transaction identity.
- Lưu callback/operation trước khi xử lý và trả response idempotent.

### 2D — inventory

- `inventory_reservations` liên kết checkout/order item, book, quantity, status,
  expiry và operation key.
- Reservation vật lý cần allocation tới `warehouse_stocks`; thiết kế một bảng
  allocation riêng nếu một item lấy từ nhiều kho.
- Backfill và cutover phải xác định cách chuyển `books.stock` thành projection mà
  không làm sai tồn hiện hữu.

### 2E — job, points và ledger

- Thay side effect trong `ProcessOrder` và model event bằng operation/ledger/outbox
  idempotent.
- Mỗi job có retry/backoff và unique business key.

### 2F — kiểm thử và rehearsal

- Concurrency test cuốn cuối cùng.
- Webhook/job lặp 2–5 lần.
- Checkout ba vendor với một tổng thanh toán.
- Payment fail/expire/cancel giải phóng reservation.
- Migration dry-run, backfill reconciliation và rollback rehearsal trên bản sao.

## 7. Ngoài phạm vi 2A

- Không tạo hoặc sửa migration/schema.
- Không sửa controller, service, model, job, frontend hoặc API.
- Không thay đổi VNPAY, Redis, queue, database, production hay dữ liệu.
- Không quyết định chi tiết partial refund/return/payout của Giai đoạn 4.
- Không chạy migration hoặc test ghi dữ liệu production.

## 8. Cổng phê duyệt

Chỉ bắt đầu 2B sau khi người dùng duyệt:

1. danh sách trạng thái và cạnh chuyển;
2. actor/quyền của từng transition;
3. DB là nguồn tồn kho chuẩn và `books.stock` là projection;
4. một payment cho toàn checkout session;
5. các bất biến idempotency, transaction, audit và side effect;
6. các mục cần sửa hoặc quyết định lại trong ADR này.
