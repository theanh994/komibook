# Giai đoạn 6G.2 — kế hoạch Kho và Đơn hàng Vendor

Ngày lập: 2026-07-29

## Phạm vi

Warehouses, Inventory Audit, Stock Transfer/Print, Orders, Order Detail và
Return Management dưới `/vendor`.

## Bất biến

- Giữ inventory reservation/fulfillment/return state machine và phân quyền.
- Không tạo/sửa kho, điều chuyển, audit, cập nhật đơn/return hoặc in chứng từ
  trong browser smoke.
- Bảng dài có mobile card/overflow nội bộ; document không overflow; target 44px,
  loading/error/empty semantics, bốn viewport.
- Không commit, push hoặc deploy.

## Prompt dự phòng Antigravity

> Chỉ sửa các view Vendor 6G.2: Warehouses, InventoryAudit, StockTransfer,
> StockTransferPrint, Orders, OrderDetail và shared ReturnManagement khi có bằng
> chứng UI. Giữ API/payload/state machine/inventory/return contract và phân quyền.
> Làm responsive 375/768/1024/1440, mobile table fallback, target 44px, semantic
> states. Không tạo/sửa/in/mutate dữ liệu trong smoke; không sửa backend, không
> commit/push. Chạy frontend test/build và regression inventory/order/return.

