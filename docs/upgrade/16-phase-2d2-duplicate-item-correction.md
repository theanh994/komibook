# Batch 2D.2 correction — Duplicate book lines and formatting

- **Ngày:** 2026-07-25
- **Trạng thái:** Required before acceptance
- **Phạm vi:** giữ nguyên thiết kế 2D.2; không mở sang lifecycle 2D.3

## Bằng chứng

- Targeted 2D.2 đạt `61 tests / 264 assertions`.
- `CheckoutRequest` không yêu cầu `items.*.book_id` distinct.
- `InventoryReservationService::reserve()` tính available từ DB cho từng order
  item nhưng chưa trừ allocation đang được lập trong cùng lời gọi.
- Pint fail ở `ProcessOrder`, integration test và hai VNPAY test.

## Correction

1. Trong một lần reserve, theo dõi tổng allocation đã lên kế hoạch theo từng
   `warehouse_stock_id`.
2. Available cho item sau phải bằng:
   `on_hand - active_reserved_in_db - planned_in_current_operation`.
3. Hai dòng cùng book có tổng demand vượt available phải rollback toàn bộ.
4. Hai dòng cùng book có tổng demand hợp lệ được allocation đúng tổng, không sửa
   on-hand khi reserve và commit chỉ trừ đúng tổng một lần.
5. Không chỉ dựa vào request validation; service phải tự giữ invariant vì có thể
   được gọi từ job/test/internal code.
6. Thêm test ở service và checkout integration cho cả trường hợp đủ/không đủ.
7. Sửa toàn bộ lỗi Pint trong các file được phép của 2D.2, không thay đổi hành vi
   ngoài correction.

Không sửa schema/migration, callback/payment service, controller, request,
frontend hoặc dependency. Không commit hoặc push.
