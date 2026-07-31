# Giai đoạn 6G.2 — nghiệm thu Kho và Đơn hàng Vendor

Ngày nghiệm thu cục bộ: 2026-07-29

**Đạt Gate 6G.2 cục bộ.**

- Bảy route Warehouse/Audit/Transfer/Print/Orders/Order Detail/Returns được kiểm
  tra ở bốn viewport, không overflow toàn document.
- Chi tiết đơn dùng heading cấp một, loading live state, action/select target 44px.
- Phiếu điều chuyển có status/alert semantics và action 44px ở error/print state.
- Không tạo/sửa kho, audit, transfer, order, return hoặc gọi print.
- Frontend `68/68`, build đạt; backend Inventory/Checkout/Return/DRM regression
  `68` tests, `306` assertions đạt; `git diff --check` đạt.
- Không commit, push hoặc deploy.

