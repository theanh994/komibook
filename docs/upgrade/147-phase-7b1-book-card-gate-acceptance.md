# Giai đoạn 7B.1 — Nghiệm thu tự động BookCard dùng chung

Ngày: 29/07/2026  
Kết quả: implemented; visual pending Gate 7B

## Thay đổi đã kiểm tra

- Bìa dùng `object-contain`, không padding, hai góc trên vuông và chân thẻ bo nhẹ.
- Bỏ thể loại và tác giả; giữ title, giá, giá gốc/giảm giá và phiên bản ebook.
- Trái tim icon-only cùng xem nhanh, thêm giỏ, mua ngay có vùng chạm 44×44 px và nhãn hỗ trợ.
- Home truyền wishlist state/event cho cả khối gợi ý và các nhóm commerce, không làm mất ba tiện ích đã có.
- Hover/focus có lift, shadow, cover scale và action reveal; touch/coarse pointer luôn thấy action; reduced motion tắt transform.

## Bằng chứng gate

- `phase7_book_card.spec.js`: 2 tests passed.
- ESLint target: passed.
- Vite production build: passed.
- `git diff --check` trong phạm vi batch: passed.

Đã khởi động lại frontend local tại `http://127.0.0.1:5173/` để người dùng có thể kiểm tra. Công cụ quan sát giao diện bị chính sách trình duyệt chặn khi điều khiển URL loopback, vì vậy không tuyên bố visual breakpoint đã đạt; phần này được giữ lại cho Gate 7B/UAT trực tiếp.

Không có commit, push hoặc deploy.
