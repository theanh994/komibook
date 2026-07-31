# Giai đoạn 6H.2 — kế hoạch Thương mại và Tài chính Admin

Ngày lập: 2026-07-29

## Phạm vi

- `frontend/src/views/admin/PromotionsView.vue`
- `frontend/src/views/admin/FlashSaleDetailView.vue`
- `frontend/src/views/admin/FinanceReportView.vue`
- `frontend/src/views/admin/FeeSchedulesView.vue`
- `frontend/src/views/admin/ReconciliationView.vue`
- `frontend/src/views/admin/MembershipTiersView.vue`
- `frontend/src/views/shared/ReturnManagementView.vue`
- `frontend/src/assets/main.css` chỉ cho lỗi target của nút đóng PrimeVue Toast
  dùng chung đã được đo trực tiếp.

## Bất biến

- Giữ nguyên coupon/Flash Sale integrity, fee history, payout/reconciliation và
  return/refund lifecycle.
- Browser smoke chỉ đọc; không tạo/sửa/xóa coupon, Flash Sale, fee schedule,
  membership, payout, reconciliation hay quyết định trả hàng/hoàn tiền.
- Dữ liệu tài chính không bị thay bằng số minh họa; loading/error/empty phải phân
  biệt rõ.
- Bảng/chart/filter có mobile fallback hoặc vùng cuộn được gắn nhãn; target tối
  thiểu 44px và dùng được ở `375 / 768 / 1024 / 1440`.
- Không sửa backend, commit, push hoặc deploy.

## Prompt dự phòng Antigravity

> Chỉ sửa Promotions, FlashSaleDetail, FinanceReport, FeeSchedules,
> Reconciliation, MembershipTiers và shared ReturnManagement views. Giữ nguyên
> API/state machine, promotion integrity, fee history, payout/reconciliation,
> return/refund truth. Cải thiện responsive bốn viewport, table/card hoặc labeled
> scroll region, chart fallback, semantic loading/error/empty, labels, keyboard
> focus và target 44px. Browser smoke chỉ đọc; không tạo/sửa/xóa/duyệt hay thay
> dữ liệu tài chính. Không sửa backend, commit hoặc push. Báo cáo file thay đổi
> và chạy frontend test/build cùng commerce/finance/return regression.

## Gate

1. Audit trực tiếp bảy nhóm route ở bốn viewport.
2. Frontend tests và production build đạt.
3. Backend Promotion/Fee/Payout/Return regression đạt.
4. `git diff --check` đạt.
5. Cập nhật page ledger và acceptance report.
