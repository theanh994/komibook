# KomiBook UI/UX master

> Khi triển khai một trang, đọc file tương ứng trong
> `design-system/komibook/pages/` trước. Quy tắc theo trang được phép bổ sung
> hoặc siết chặt tài liệu này, nhưng không được hạ thấp yêu cầu accessibility.

**Trạng thái:** ADR giao diện Giai đoạn 6 đã được chủ dự án phê duyệt
**Ngày chốt:** 2026-07-29
**Nguồn:** ui-ux-pro-max, kiểm kê trực tiếp mã Vue hiện có và nhận xét thao tác
thực tế của chủ dự án.

## 1. Định hướng

KomiBook dùng phong cách **content-first, bình tĩnh, chuyên nghiệp và dễ đọc**.
Nội dung sách, quyết định mua và tác vụ quản lý phải nổi bật hơn phần trang trí.
Giữ nhận diện navy/Material 3 đang có; không thay toàn bộ thương hiệu bằng bảng
màu mẫu do công cụ sinh.

- Không dùng phong cách jelly/chrome/clay, hiệu ứng nảy hoặc biến dạng diện rộng.
- Không thêm GSAP chỉ để tạo hiệu ứng xuất hiện khi cuộn.
- Chuyển động chỉ hỗ trợ phản hồi trạng thái, kéo dài 150–300 ms và tắt theo
  `prefers-reduced-motion`.
- Trang quản lý Tác giả tiếp tục dùng shell và cấu trúc nhiều trang như Vendor.

## 2. Token ngữ nghĩa

| Vai trò | Giá trị chuẩn | Ghi chú |
|---|---:|---|
| Primary | `#002442` | Navy nhận diện, tiêu đề và hành động chính |
| On primary | `#ffffff` | Chữ trên nền primary |
| Primary container | `#d1e4ff` | Nền chọn/nhấn nhẹ |
| Secondary | `#ba0035` | Nhấn thương hiệu, không dùng cho trạng thái lỗi |
| Commerce CTA | `#15803d` | Mua hàng, xác nhận tích cực; đạt tương phản với trắng |
| Surface | `#ffffff` | Card, panel, dialog |
| Background | `#faf8ff` | Nền ứng dụng |
| Text | `#191c20` | Chữ chính |
| Text muted | `#4b5563` | Chữ phụ; không dùng opacity thấp |
| Border | `#c3c7cf` | Viền điều khiển |
| Error | `#b91c1c` | Lỗi/destructive |
| Warning | `#a16207` | Cảnh báo |
| Focus ring | `#2563eb` | Luôn nhìn thấy trên nền sáng và tối |

Màu phải đi qua biến CSS ngữ nghĩa. Không thêm hex tùy ý trong view nếu token
hiện có diễn đạt đúng ý nghĩa.

## 3. Chữ và khả năng đọc

- UI: `Inter`, system sans-serif fallback; không tải font mạng mới.
- Nội dung đọc dài/ebook: `Literata`, Georgia, serif fallback.
- Cỡ chữ nội dung tối thiểu 16 px. Metadata có thể 14 px; tuyệt đối không dùng
  cỡ 8–11 px cho nội dung hoặc điều khiển.
- Line-height nội dung 1.5–1.75; dòng văn bản dài giới hạn khoảng 65–75 ký tự.
- Không dùng chữ hoa toàn bộ cho đoạn dài.

## 4. Không gian, hình dạng và chiều sâu

- Thang khoảng cách: 4, 8, 12, 16, 24, 32, 48, 64 px.
- Bán kính: điều khiển 8 px, card/panel 12 px, dialog 16 px.
- Ảnh bìa dùng đúng tỷ lệ, `object-fit: contain` hoặc `cover` theo ngữ cảnh;
  không kéo nén và không bo tròn quá mức.
- Bóng đổ nhẹ; dùng viền và phân cấp bề mặt trước khi tăng shadow.
- Mục tiêu chạm tối thiểu 44×44 px.

## 5. Component contract

### Nút và liên kết

- Mỗi hành động có nhãn rõ ràng; icon trang trí phải `aria-hidden`.
- Có đủ `hover`, `focus-visible`, `active`, `disabled`, `loading`.
- Không dùng `transition: all`; chỉ chuyển thuộc tính cần thiết.
- Hover không làm dịch bố cục. Active có thể dịch 1 px nếu không gây chóng mặt.

### Form

- Mỗi input có label hiển thị; placeholder không thay label.
- Thông báo lỗi đặt gần trường, nêu cách sửa và được liên kết bằng
  `aria-describedby`.
- Không xóa dữ liệu đã nhập khi lỗi API; nút gửi phản hồi trạng thái chờ.

### Card sách

- Ưu tiên bìa, tên sách, tác giả, loại sản phẩm và giá.
- Ebook luôn ghi phiên bản hiện hành trên thẻ; khách không chọn phiên bản để mua.
- Sách vật lý/ebook/sách cũ có nhãn loại nhất quán.
- Toàn card không được tạo nhiều vùng click chồng nhau.

### Trạng thái dữ liệu

Mọi trang gọi API phải có loading, empty, error và success/ready rõ ràng.
Không dùng skeleton vô hạn; lỗi phải có nút thử lại khi hợp lý.

## 6. Responsive

Kiểm tra bắt buộc ở 375, 768, 1024 và 1440 px.

- Mobile-first; không có cuộn ngang ngoài thành phần chủ ý.
- Navigation mobile phải thao tác bàn phím được và khóa focus khi dùng dialog.
- Dashboard chuyển sidebar thành drawer rõ ràng, không làm mất chức năng.
- Bảng dữ liệu có chiến lược mobile cụ thể: cuộn trong vùng có nhãn hoặc đổi
  thành card; không ép chữ nhỏ.
- Header/footer không che nội dung.

## 7. Accessibility và bàn phím

- Tương phản chữ thường tối thiểu 4.5:1.
- Mọi điều khiển có focus ring nhìn thấy; có skip link đến nội dung chính.
- Dùng landmark và heading theo cấp; chỉ một `h1` mô tả trang.
- Dialog/menu có tên truy cập, trạng thái mở và đóng bằng Escape.
- Không phụ thuộc màu sắc hoặc hover để truyền đạt thông tin.
- Tôn trọng `prefers-reduced-motion`.

## 8. Quy tắc riêng theo bề mặt

- Public/feed: giàu nội dung nhưng có nhịp đọc, CTA không lấn nội dung.
- Customer commerce: ưu tiên độ tin cậy, tổng tiền, điều khoản và trạng thái.
- Dashboard: ưu tiên khả năng tìm chức năng, mật độ vừa phải, tách trang rõ.
- Ebook reader: giảm chrome, tối ưu đọc dài; phiên bản chỉ chọn trong trình đọc
  và không được sớm hơn phiên bản mà khách đã mua.

## 9. Acceptance cho từng trang

- Quan sát trực tiếp ở 375/768/1024/1440 px.
- Không tràn ngang, không nội dung bị header/sidebar che.
- Đi hết tương tác chính bằng bàn phím.
- Kiểm tra loading/empty/error/ready với dữ liệu/API phù hợp.
- Kiểm tra console và lỗi mạng liên quan đến thay đổi.
- Ghi kết quả vào `docs/upgrade/73-phase-6-page-ledger.md`.
