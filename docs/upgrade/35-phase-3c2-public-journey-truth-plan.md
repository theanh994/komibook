# Batch 3C.2 — Public Journey Truth

## 1. Mục tiêu

Loại bỏ dữ liệu mẫu, trạng thái thành công giả và các suy diễn vận hành không có contract thật trong những hành trình công khai/khách hàng:

1. Help Center.
2. Flash Sale.
3. Checkout Success.
4. Order Tracking.
5. Thông điệp OTP trên Login/Register.

Invariant chung: **frontend chỉ xác nhận hoặc hiển thị dữ liệu mà API thực sự trả về; lỗi, rỗng và dữ liệu chưa được hệ thống thu thập phải được biểu diễn trung thực.**

Đây là batch frontend lớn cuối của nhóm 3C hiện đã có backend contract. Blog/news chưa có API contract được phê duyệt và không thuộc batch này.

## 2. Baseline đã được chấp nhận — không chạy lặp

Batch 3B.1 đã được Codex nghiệm thu:

- Backend full regression: `237 tests`, `1048 assertions`, pass.
- Backend access/annotation delta: `32 tests`, `96 assertions`, pass.
- Frontend correction delta: `12 tests`, pass.

Batch 3C.1 đã được Codex nghiệm thu sau correction:

- Backend operational-truth delta: `7 tests`, `26 assertions`, pass.
- Frontend operational-truth delta: `10 tests`, pass.
- Pint, frontend production build, Oxlint, ESLint và `git diff --check`: pass.

Antigravity chỉ chạy các gate delta ở mục 8. Không chạy lại full backend/frontend regression. Codex sẽ chạy full regression tại mốc đóng nhóm 3C hoặc khi batch thay đổi hạ tầng dùng chung.

## 3. Bằng chứng trực tiếp và contract hiện có

### Help Center

- `HelpCenterView` tạo ba FAQ mẫu khi request thất bại.
- Khi đánh dấu hữu ích thất bại, UI vẫn tăng `helpful_count` và thông báo như đã nhận phản hồi.
- Request xem chi tiết bị bỏ qua lỗi và view dùng record danh sách thay vì kết quả thật từ endpoint detail.
- Backend đã có các endpoint public list, detail và helpful.

### Flash Sale

- Khi `sold_quantity = 0`, `getSoldPercent` trả giả `12%`.
- Khi API lỗi, màn hình đang biểu diễn giống trạng thái “không có Flash Sale” thay vì lỗi.
- Backend đã trả active sale và items thật; batch không được tạo thêm doanh số hoặc tồn kho.

### Checkout Success

- Màn hình tải toàn bộ `/api/my-orders` rồi tìm record, dù đã có `/api/my-orders/{order}`.
- Không có error/not-found/retry state; lỗi tải có thể để lại trang trống.
- Nội dung luôn tuyên bố đơn “đặt hàng thành công”, đang được xử lý và hóa đơn đã gửi email chỉ vì có record, không kiểm tra trạng thái thanh toán/đơn hàng hoặc bằng chứng gửi email.
- Backend detail endpoint đã trả đúng order thuộc user hiện tại.

### Order Tracking

- Màn hình tải toàn bộ danh sách thay vì endpoint detail.
- Tự dựng timestamp, địa điểm kho/bưu cục, lịch sử đóng gói/giao hàng và giờ giao.
- Tự cộng bốn ngày để tạo ngày giao dự kiến.
- Tạo hãng vận chuyển `Komi Express`, mã vận đơn `VN-{id}-EXP`, tên khách, số điện thoại và các nhãn “Freeship” khi dữ liệu thật thiếu.
- Nút liên hệ tài xế không có contract/action thật.
- Backend detail hiện chỉ bảo đảm trạng thái hiện tại, hãng vận chuyển, mã vận đơn, địa chỉ và `created_at`; không có shipment-event timeline hay ETA contract.

### OTP

- Login/Register ghi “SMS giả lập” dù backend production đã bind `ProductionOtpSender`; fake sender chỉ dành cho test và log sender chỉ dành cho local.
- Hành vi hiện tại chỉ chuyển `otpSent` sau khi request thành công; không cần đổi backend hoặc auth store trong batch này.

## 4. Allowed files

- `frontend/src/views/HelpCenterView.vue`
- `frontend/src/views/FlashSaleView.vue`
- `frontend/src/views/CheckoutSuccessView.vue`
- `frontend/src/views/OrderTrackingView.vue`
- `frontend/src/views/auth/LoginView.vue`
- `frontend/src/views/auth/RegisterView.vue`
- `frontend/src/__tests__/phase3_public_journey_truth.spec.js` — **NEW**

Mọi file khác đều bị cấm sửa. Nếu contract hiện có không đủ, dừng và báo blocker thay vì mở rộng file hoặc backend.

## 5. Thay đổi bắt buộc

### Quy tắc chung

- Không tạo record, counter, timestamp, địa điểm, trạng thái, tên người/hãng hoặc mã nghiệp vụ mẫu.
- API error phải xóa dữ liệu có thể gây hiểu nhầm, hiện error state và cho phép retry phù hợp.
- API success trả rỗng/not-found phải khác API error.
- Action thất bại không được mutate local state như đã thành công.
- Không hiển thị nội dung khẳng định một side effect đã xảy ra nếu response không có bằng chứng.

### HelpCenterView

- Xóa toàn bộ FAQ fallback.
- Có loading, error, retry và empty/search-empty state.
- Khi chọn bài viết, gọi endpoint detail và chỉ hiển thị detail khi request thành công; lỗi detail không tăng lượt xem cục bộ và có đường quay lại/retry.
- Helpful chỉ tăng counter sau response success; request thất bại giữ nguyên counter và báo lỗi trung thực.
- Không nuốt lỗi bằng `.catch(() => {})`.

### FlashSaleView

- `sold_quantity = 0` phải hiển thị `0%`.
- Chỉ tính phần trăm từ `sold_quantity` và `max_quantity`, hoặc từ `sold_quantity + stock` khi contract thật cho phép; clamp về `0..100`.
- Không dùng giá trị giả để “đẹp mắt”.
- Tách loading, API error với retry, success-empty và active-sale states.
- Khi refresh lỗi, không giữ active sale cũ như dữ liệu mới.
- Countdown hết hạn chuyển sang success-empty; không tự tạo sale khác.

### CheckoutSuccessView

- Dùng `/api/my-orders/{orderId}` thay vì tải và dò toàn bộ danh sách.
- Validate `order_id`; thiếu hoặc sai phải hiện trạng thái không hợp lệ/not-found có đường về Orders/Home, không redirect âm thầm trước khi người dùng hiểu lý do.
- Có loading, error, retry và not-found/forbidden state.
- Chỉ dùng nội dung “thành công” khi trạng thái order/payment thực sự phù hợp với contract hiện có. Nếu order còn chờ xác nhận/thanh toán, dùng wording trung tính đúng trạng thái.
- Không khẳng định hóa đơn đã được gửi email nếu response không có field xác nhận.
- Nút đọc ebook/theo dõi vận chuyển chỉ xuất hiện khi item/order thực sự phù hợp.

### OrderTrackingView

- Dùng `/api/my-orders/{orderId}` và không tải/dò danh sách.
- Có loading, error, retry và not-found/forbidden state; không redirect ngay khi lỗi.
- Xóa toàn bộ timeline dựng từ `created_at`, timestamp giả, địa điểm giả và giờ giao giả.
- Không tạo ETA bằng phép cộng ngày. Khi không có ETA contract, hiển thị “Chưa có dự kiến giao hàng” hoặc ẩn section.
- Stepper chỉ phản ánh trạng thái order/shipping thật; xử lý rõ `picked_up`, `delivering`, `delivered`, `failed`, `cancelled`, và không gọi `processing` là đã vận chuyển nếu `shipping_status` chưa xác nhận.
- Chỉ hiển thị `shipping_carrier` và `shipping_tracking_code` khi có giá trị thật; không fallback sang hãng/mã mẫu.
- Không fallback tên khách thành tên thương hiệu. Field thiếu dùng `—` hoặc “Chưa cập nhật”.
- Xóa/disable có giải thích cho nút liên hệ tài xế khi không có contact contract.
- Không gắn nhãn “Freeship” nếu API không trả bằng chứng.
- `created_at` có thể hiển thị đúng là thời điểm đặt đơn, không được diễn giải thành shipment event.

### LoginView và RegisterView

- Thay “SMS giả lập” bằng wording trung tính: OTP được gửi qua SMS tới số điện thoại đã nhập.
- Không mô tả implementation test/local cho người dùng production.
- Giữ nguyên nguyên tắc: chỉ hiện bước nhập OTP sau khi `sendPhoneOtp` resolve; failure giữ `otpSent = false`.
- Không thay Google GIS, redirect, registration contract hoặc auth store.

## 6. Automated tests

Tạo `phase3_public_journey_truth.spec.js` bằng test hành vi/state với mock `apiClient`/store; không dùng `fs.readFileSync`, source grep hoặc assertion dựa trên chuỗi source.

Ít nhất kiểm tra:

1. Help Center list reject không sinh FAQ và hiện error; retry success cập nhật list.
2. Helpful reject không tăng counter; success mới tăng.
3. Flash Sale `sold_quantity = 0` là `0%`; API reject khác success-empty.
4. Checkout Success gọi detail endpoint theo ID; success pending không bị mô tả như completed/paid.
5. Checkout detail 404/error tạo state phù hợp và retry hoạt động.
6. Order Tracking gọi detail endpoint, không sinh timeline/ETA/carrier/tracking code khi backend không trả.
7. Shipping stepper phản ánh ít nhất `pending_pickup`, `delivering`, `delivered`, `failed/cancelled`.
8. OTP send reject ở cả Login/Register không mở bước verify; resolve mới mở.

Có thể dùng test helper trong chính file test. Không cài dependency mới.

## 7. Invariants không được phá vỡ

- Không thay authorization hoặc endpoint backend.
- Guest `/api/auth/me` trả 401 vẫn là hành vi hợp lệ; batch không được tạo loop/repeated fetch.
- Không thay contract Order/Payment/Inventory/VNPAY/ledger/outbox của Giai đoạn 2.
- Không hiển thị thành công dựa trên HTTP resolve nếu payload/status nghiệp vụ nói ngược lại.
- Không thêm telemetry/shipping provider giả.

## 8. Delta acceptance gates

Từ `frontend`:

- `npm.cmd test -- --run src/__tests__/phase3_public_journey_truth.spec.js`
- `npm.cmd run build`
- `npx.cmd --no-install oxlint src/views/HelpCenterView.vue src/views/FlashSaleView.vue src/views/CheckoutSuccessView.vue src/views/OrderTrackingView.vue src/views/auth/LoginView.vue src/views/auth/RegisterView.vue src/__tests__/phase3_public_journey_truth.spec.js`
- `npx.cmd --no-install eslint src/views/HelpCenterView.vue src/views/FlashSaleView.vue src/views/CheckoutSuccessView.vue src/views/OrderTrackingView.vue src/views/auth/LoginView.vue src/views/auth/RegisterView.vue src/__tests__/phase3_public_journey_truth.spec.js`

Từ repository root:

- `git diff --check`

Không chạy full backend regression, full frontend test suite hoặc các gate 3B/3C.1 đã được chấp nhận.

## 9. Forbidden scope

- Không sửa backend, route, store, migration/schema, dependency, package hoặc lockfile.
- Không thêm Blog/news API hay hardcode Blog content.
- Không thay Google GIS, OTP service binding hoặc security flow.
- Không sửa thiết kế tổng thể ngoài phần cần thiết cho truthful states.
- Không chạm `.env`, production, service, Cloudflare hoặc database ngoài test environment.
- Không reset, checkout, stash, clean, commit hoặc push.

## 10. Báo cáo

Báo:

- file đã sửa;
- fake/fallback/false-success đã loại bỏ;
- trạng thái loading/error/retry/empty/not-found sau sửa;
- test mới và kết quả từng delta gate;
- blocker còn lại.

Không commit hoặc push.
