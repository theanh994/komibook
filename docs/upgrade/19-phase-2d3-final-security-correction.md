# KomiBook Batch 2D.3 — Final security correction

- **Ngày:** 2026-07-25
- **Trạng thái:** Required after targeted tests passed but security audit failed

## Lỗi còn lại

1. `VnpayPaymentService` pre-check expiry và mutate session trước ownership check.
   Người dùng khác có thể kết thúc checkout hết hạn không thuộc mình.
2. Dùng exception sentinel để thoát outer transaction tạo thêm khoảng race và nhánh
   cleanup khó kiểm soát. Cleanup cần hoàn tất trong transaction thành công, rồi mới
   ném HTTP 422 sau commit.
3. Late callback gặp order cancelled nhưng transaction vẫn pending đang trả `02`;
   đây là partial state bất hợp lệ, không được xác nhận terminal.
4. Nhánh convergence chỉ tìm `reserved`, chưa fail-closed rõ ràng với reservation
   `committed`.
5. Test chưa chứng minh expired foreign checkout không bị mutate và chưa đủ coverage
   cho paid/confirmed, committed reservation và legacy order.

## Correction

- Bỏ mọi mutation trước ownership validation.
- Trong transaction đã khóa session và xác minh owner, nếu hết hạn thì chạy lifecycle
  cleanup, trả internal sentinel/result bình thường để transaction commit; chỉ ném
  HTTP 422 sau khi `DB::transaction` đã trả về.
- Callback chỉ trả `02` cho cancelled checkout đã terminal nhất quán. Cancelled order
  đi cùng pending transaction hoặc active reservation phải trả `99` hoặc được lifecycle
  hội tụ atomically; không nuốt lỗi.
- Lifecycle phải từ chối reservation `committed` trong cancel/expiry.
- Khôi phục log an toàn khi scheduler bỏ qua session lỗi, chỉ chứa session ID và loại
  exception, không chứa payload hoặc secret.

## Test bắt buộc

- foreign user + expired session trả 403 và không đổi order/payment/reservation;
- owner + expired session vẫn cleanup persisted rồi trả 422;
- partial cancelled + pending callback không được trả terminal giả;
- paid/confirmed, committed inventory và legacy order fail-closed, không mutation;
- các test 2D.3 hiện có tiếp tục đạt.

Không mở rộng source ngoài correction và không thay đổi policy retry attempt.
