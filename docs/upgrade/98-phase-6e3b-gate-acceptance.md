# Giai đoạn 6E.3b — nghiệm thu Đơn hàng, Theo dõi, Hóa đơn và Trả hàng

Ngày nghiệm thu cục bộ: 2026-07-29

## Kết luận

**Đạt Gate 6E.3b cục bộ.**

Không thanh toán, gửi yêu cầu trả hàng, in, hủy đơn, thay đổi trạng thái,
commit, push hoặc deploy.

## Kết quả chính

- Orders có filter group/pressed semantics, loading/error/retry/empty/filter-empty
  riêng, target 44px và bìa 2:3 `object-contain`.
- Query `payment=success` không còn tự tuyên bố đơn đã thanh toán thành công;
  giao diện chỉ báo đã nhận phản hồi và chờ trạng thái API đối chiếu.
- CTA trả hàng và danh sách eligible chỉ chấp nhận item `physical` có
  `provenance=used_resale`; ebook không có đường return.
- Returns nêu rõ phạm vi sách cũ vật lý và ebook không hoàn trả; API error không
  bị trình bày thành empty state.
- Tracking giữ đúng contract không bịa ETA/carrier/timeline, chuẩn hóa error
  state, cover và reduced motion.
- Invoice có error/retry semantics, control 44px, bảng cuộn trong container và
  print CSS tiếp tục ẩn controls.

## Bằng chứng

- Browser `/orders`, `/returns`, `/tracking/999999999`,
  `/orders/invoice/999999999`: `375 / 768 / 1024 / 1440` không overflow, không
  có visible control dưới 44px.
- Local user chưa có order/return: kiểm tra trực tiếp empty state. Tracking và
  invoice kiểm tra 404/error state bằng ID không tồn tại; không tạo dữ liệu giả.
- Frontend behavioral suite: `11` files, `68` tests đạt; build đạt.
- `Phase4ReturnRefundInvoiceTest`: `10` tests, `79` assertions đạt.
- `git diff --check`: đạt.
- Cảnh báo bundle EbookReader/logo được chuyển tới gate hiệu năng cuối kỳ.

