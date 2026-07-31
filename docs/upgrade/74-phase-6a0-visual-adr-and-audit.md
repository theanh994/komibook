# Giai đoạn 6A.0 — Visual ADR và audit

Ngày nghiệm thu: 2026-07-29  
Trạng thái: **đạt 6A.0, cho phép bắt đầu source batch 6A.1**

## Quyết định đã duyệt

- Giữ nhận diện navy/Material 3 hiện tại của KomiBook.
- Hướng thiết kế là content-first, bình tĩnh, chuyên nghiệp, accessible.
- Không áp dụng gợi ý jelly/chrome/clay/bounce diện rộng của bản sinh tự động.
- Không thêm font mạng hay GSAP chỉ cho mục đích trang trí.
- Dùng Inter cho UI, Literata cho nội dung đọc dài.
- CTA thương mại có token xanh lá riêng, không thay primary brand.
- Author Panel dùng shell nhiều trang kiểu Vendor, không quay lại dashboard dồn
  chức năng.
- Viewport acceptance: 375, 768, 1024, 1440 px.

Chi tiết chuẩn: `design-system/komibook/MASTER.md` và các override trong
`design-system/komibook/pages/`.

## Bằng chứng audit trực tiếp

- Khoảng 75 Vue view và 90 khai báo route/child route/redirect.
- Khoảng 837 khai báo visual raw/ad-hoc.
- Khoảng 210 lần dùng cỡ chữ 8–11 px trong source Vue.
- `git diff --check` đạt trước khi bắt đầu.
- Local frontend và backend cô lập chạy tại `127.0.0.1:5173` và
  `127.0.0.1:8000`, dùng SQLite review trong workspace, không đụng database thật.

## Browser baseline đại diện

Trang chủ:

- Desktop hiện tại: không horizontal overflow, có một `h1`.
- Audit DOM ghi nhận 32 text leaf dưới 12 px và 94 control/link có ít nhất một
  chiều nhỏ hơn 44 px.
- Mobile 375 px: không horizontal overflow; shell chuyển sang menu mobile.
- Ebook card đã ghi format và phiên bản hiện hành, đúng quyết định Giai đoạn 5.
- Các con số là tín hiệu audit, không phải khẳng định tất cả đều là lỗi độc lập;
  sẽ được xử lý theo component và xác nhận lại trực quan.

## Ranh giới

6A.0 chỉ tạo tài liệu/design artifacts, không sửa source runtime. Vì Antigravity
hết quota và chủ dự án đã ủy quyền Codex trực tiếp triển khai, không có prompt
source riêng cho 6A.0. Mọi source batch tiếp theo vẫn phải có plan và prompt dự
phòng trước khi sửa.

