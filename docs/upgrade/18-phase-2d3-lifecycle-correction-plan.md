# KomiBook Batch 2D.3 — Lifecycle correction

- **Ngày:** 2026-07-25
- **Trạng thái:** Required after independent acceptance failed
- **Phạm vi:** correction transaction, schema compatibility và test coverage; không
  mở rộng mục tiêu Batch 2D.3.

## 1. Lỗi đã xác minh

1. `VnpayPaymentService` gọi expiry rồi ném HTTP 422 trong cùng outer transaction,
   làm toàn bộ thay đổi lifecycle bị rollback.
2. Lifecycle query dùng `payment_transactions.order_id`, nhưng schema bảng này chỉ
   liên kết bằng `checkout_session_id`. SQLite có thể che giấu lỗi quoted identifier;
   MySQL production sẽ không an toàn.
3. Scheduler test backdate session trước khi tạo attempt nên payment initiation chặn
   test trước khi command được chạy.
4. Callback fixtures dùng amount không khớp checkout nên nhận `RspCode=04`.
5. Late-success callback nuốt exception khi expiry thất bại rồi vẫn trả terminal
   response, có thể ngăn provider retry trong khi cleanup chưa commit.
6. Nhánh `allCancelled` trả về trước khi xác minh/converge payment và reservation,
   có thể để sót pending transaction hoặc active reservation từ đường ghi legacy.

## 2. Correction bắt buộc

1. Kết thúc expired checkout phải commit trước; HTTP 422 chỉ được ném sau khi
   transaction cleanup đã hoàn tất.
2. Chỉ query transaction bằng `checkout_session_id`, khóa theo ID tăng dần.
3. `expireSession` phải từ chối session chưa đến hạn.
4. Late success:
   - session đã cancelled hợp lệ: trả terminal, không hồi sinh;
   - session đến hạn nhưng còn active: chạy expiry atomically;
   - cleanup lỗi: rollback và trả retryable internal response (`99`), không nuốt lỗi.
5. Idempotency phải xác minh trạng thái payment/reservation. Nếu order đã cancelled
   nhưng còn pending transaction/reserved inventory, operation phải hội tụ về trạng
   thái terminal đúng thay vì return sớm.
6. Không đổi chính sách: một failed attempt trước session deadline vẫn được retry.

## 3. Test correction

Sửa fixture amount theo chính snapshot checkout. Tạo attempt trước rồi mới backdate
session trong scheduler test.

Bổ sung/siết test cho:

- expiry từ payment initiation vẫn persisted sau response 422;
- late success trực tiếp khi chưa chạy scheduler tự expiry toàn session;
- callback cleanup failure trả `99` và rollback;
- direct expiry trước deadline fail-closed;
- all-cancelled nhưng còn pending transaction/reserved inventory phải converge;
- online paid/confirmed và legacy/incomplete session không bị mutation.

## 4. Cổng

Codex chạy lại ba feature suites 2D.3/VNPAY, Pint, diff-check, sau đó mới full backend
regression. Không migration production, commit hoặc push.
