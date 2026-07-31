# Giai đoạn 6F.1b — kế hoạch Commerce của Tác giả

Ngày lập: 2026-07-29

## Mục tiêu và phạm vi

Hoàn thiện ba route độc lập dùng chung
`frontend/src/views/author/AuthorCommerceView.vue`: địa chỉ giữ/gửi sách riêng tư,
sách cũ đang giữ, và mã giảm giá/đăng ký Flash Sale.

## Bất biến

- Không tiết lộ địa chỉ fulfillment ra public/customer.
- Chỉ sách cũ do tác giả thực sự giữ dùng kho/return; giữ cam kết sách thật.
- Giữ quyền coupon/Flash Sale thực và mọi API/validation hiện có.
- Không lưu địa chỉ, đăng listing, đổi tồn, tạo coupon hay đăng ký sale trong browser.
- Form label rõ, target 44px, trạng thái semantic, responsive bốn viewport.
- Không commit, push hoặc deploy.

## Prompt dự phòng Antigravity

> Chỉ sửa AuthorCommerceView.vue. Giữ ba route thành ba trang riêng trong shell
> Author kiểu Vendor. Không đổi API/payload/validation/privacy; không làm lộ địa
> chỉ; giữ cam kết chống sách giả và quyền coupon/Flash Sale. Thêm label/id/aria,
> loading/error/notice semantics, empty states, target 44px và responsive
> 375/768/1024/1440. Không submit form, chọn file, đổi kho, tạo coupon/sale trong
> smoke; không sửa backend/test/route, không commit hoặc push. Chạy frontend
> test/build, backend Phase5 Warehouse/UsedBook/Promotion và diff check.

