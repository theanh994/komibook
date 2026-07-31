# Batch 2D.1 final correction — Explicit targets and lock order

- **Ngày:** 2026-07-25
- **Trạng thái:** Required before acceptance
- **Không mở rộng:** schema và Batch 2D.2

## Bằng chứng

- Targeted suite: `17 passed / 1 failed`, `68 assertions`.
- Integer `123` bị PHP weak-coerce thành string `"123"` trong union type hiện tại,
  nên không bị từ chối như contract.
- Lookup operation multi-item vẫn dùng `LIKE`; operation key chưa cấm `%`, `_`
  hoặc delimiter nội bộ.
- Pint vẫn fail ở service, `Book`, `OrderItem`, `WarehouseStock`.

## Correction

1. Public transition API phải tách rõ:
   - session target;
   - reservation target;
   - operation-key target.
   Generic method không được nhận union có string để integer bị weak-coerce thành
   operation key.
2. Operation key:
   - validate bằng allowlist an toàn;
   - cấm SQL wildcard và delimiter nội bộ;
   - exact base match hoặc exact namespace `base#item:<numeric-id>`;
   - không cho prefix/wildcard collision.
3. Mọi transition phải giữ lock order tương thích với reserve:
   - checkout session;
   - warehouse stocks theo `(book_id, warehouse_id, id)` nếu commit;
   - reservations/allocation theo ID.
   Không khóa reservation trước warehouse stock trong commit.
4. Giữ toàn bộ allocation-integrity checks đã bổ sung.
5. Test:
   - integer target bị từ chối thực sự;
   - `%`, `_`, delimiter và key không hợp lệ bị từ chối;
   - operation multi-item chỉ tác động đúng namespace;
   - semantic methods session/reservation/operation vẫn idempotent.
6. Sửa toàn bộ lỗi Pint trong phạm vi file 2D.1.

Giữ fixture `orders.phone` và snapshot đầy đủ của `checkout_session_orders`.
Không sửa migration/schema, checkout, payment, callback, job, frontend hoặc
dependency. Không commit hoặc push.
