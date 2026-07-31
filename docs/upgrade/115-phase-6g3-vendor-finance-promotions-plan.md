# Giai đoạn 6G.3 — kế hoạch Tài chính và Flash Sale Vendor

Ngày lập: 2026-07-29

## Phạm vi

- `frontend/src/views/vendor/FinanceView.vue`
- `frontend/src/views/vendor/FlashSalesView.vue`

## Bất biến

- Giữ payout ledger/lifecycle, balance và Flash Sale approval/integrity.
- Không tạo payout, đăng ký sale hoặc mutation trong browser smoke.
- Bảng/chart/filter có mobile fallback, target 44px, semantic states, bốn viewport.
- Không commit, push hoặc deploy.

## Prompt dự phòng Antigravity

> Chỉ sửa FinanceView và FlashSalesView Vendor. Giữ API/payout ledger/state machine,
> balance và promotion integrity. Cải thiện responsive 375/768/1024/1440,
> table/card fallback, filter, target 44px và loading/error/empty. Không tạo payout
> hoặc đăng ký Flash Sale trong smoke; không sửa backend, commit hay push. Chạy
> frontend test/build và payout/promotion regression.

