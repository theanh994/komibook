# Giai đoạn 6E.3b — Đơn hàng, theo dõi, hóa đơn và trả hàng

## Mục tiêu

- Danh sách đơn phân biệt loading/error/empty/filter-empty, trạng thái đơn và
  thanh toán dễ đọc, cover không méo và thao tác đạt 44px.
- Chỉ hiện CTA trả hàng khi đơn có sách cũ vật lý đủ điều kiện; ebook không có
  đường yêu cầu trả hàng.
- Trang theo dõi không bịa carrier, mã vận đơn, ETA hoặc timeline.
- Hóa đơn đọc được trên mobile, bảng cuộn trong container và bản in không chứa
  control UI.
- Trang trả hàng giải thích rõ chỉ áp dụng sách cũ vật lý, form có label/target
  và không trình bày lỗi API như trạng thái rỗng.

## Phạm vi source

- `frontend/src/views/OrdersView.vue`
- `frontend/src/views/OrderTrackingView.vue`
- `frontend/src/views/ReturnsView.vue`
- `frontend/src/views/orders/InvoicePrintView.vue`

Không thay đổi backend contract, payment, fulfillment, return workflow,
database hoặc production.

## Invariants

- Không nhấn thanh toán ngay, gửi yêu cầu trả hàng, in thật, hủy đơn hoặc thay
  đổi trạng thái trong browser gate.
- Payment/return status chỉ lấy từ API; query redirect không đủ để tự đánh dấu
  đơn đã paid.
- Ebook không hoàn trả; return CTA chỉ cho item `physical` có provenance
  `used_resale`, theo đúng backend policy.
- Không commit, push hoặc deploy.

## Prompt Antigravity dự phòng

> Thực hiện Phase 6E.3b tại checkout chính, chỉ sửa OrdersView.vue,
> OrderTrackingView.vue, ReturnsView.vue và InvoicePrintView.vue. Giữ nguyên API
> và workflow. Thêm loading/error/retry/empty trung thực; filter semantics;
> cover 2:3 object-contain/no radius; target 44px; responsive 375/768/1024/1440.
> Chỉ cho phép return CTA với sách cũ vật lý đủ điều kiện, tuyệt đối không ebook.
> Tracking không tạo timeline/ETA/carrier giả; invoice mobile table cuộn nội bộ
> và print CSS sạch. Không click pay/return/print trong browser gate. Chạy
> frontend tests/build, focused backend return/order tests và diff check; không
> commit/push/deploy.

## Gate

- Bốn route đạt keyboard, semantic states, 44px và bốn viewport.
- Return eligibility hiển thị khớp policy backend.
- Behavioral tests tracking và backend return/order tests đạt.
- Frontend tests/build và `git diff --check` đạt.

