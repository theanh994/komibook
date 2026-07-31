# Giai đoạn 7B.2 — Nghiệm thu tự động trình bày trang chủ

Ngày: 29/07/2026  
Kết quả: implemented; visual pending Gate 7B

## Thay đổi đã kiểm tra

- Fallback hero dùng ảnh giá sách KomiBook mới, không dùng dữ liệu/bìa/ảnh riêng tư; CMS hero có ảnh vẫn được ưu tiên.
- Asset được tạo bằng built-in image generation, sau đó tối ưu từ PNG 1.94 MB thành WebP 133 KB, kích thước 1942×809.
- API recommendation chỉ trả tối đa 5; frontend cũng giới hạn phòng vệ ở 5.
- Grid gợi ý hiển thị lần lượt 2/3/4/5 card tại mobile hẹp/mobile lớn/tablet/desktop, không tạo hàng thứ hai.
- Back-to-top xuất hiện sau 480 px, vùng chạm 44×44, có focus và dùng `auto` khi reduced motion.

## Bằng chứng gate

- `Phase6HomeFeedTest`: 5 tests passed, 20 assertions.
- `phase7_home_presentation.spec.js` + `phase7_book_card.spec.js`: 4 tests passed.
- ESLint target và Pint target: passed.
- Vite production build: passed; asset build 133.13 KB.
- `git diff --check` trong phạm vi batch: passed.

Frontend local đang chạy để UAT trực tiếp. Quan sát tự động URL loopback tiếp tục bị chính sách trình duyệt chặn, nên breakpoint visual được giữ pending đến Gate 7B thay vì suy diễn từ build.

Không có commit, push hoặc deploy.
