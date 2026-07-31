# Giai đoạn 6I.3 — nghiệm thu Hiệu chỉnh mép toàn cục

Ngày nghiệm thu cục bộ: 2026-07-29

**Đạt Gate 6I.3 cục bộ.**

- Search input desktop đo trực tiếp đạt `44px`; mobile giữ vùng thao tác tối thiểu
  `44px`.
- Edge audit tại `320px` phát hiện `html { min-width: 20rem; }` làm document rộng
  hơn vùng nội dung 15px khi thanh cuộn dọc hiện diện. Quy tắc ép chiều rộng đã
  được bỏ; các route đại diện `/`, `/catalog`, `/about`, `/policies/ebooks`,
  `/cart`, `/admin/dashboard` đều đo lại `scrollWidth - clientWidth = 0`.
- Cùng các route trên đạt không tràn ngang tại `1536px`.
- Trang chủ được tải độc lập sau correction ở `320px` và `1440px`; shared
  `BookCard` vẫn hiển thị đúng các tiện ích `Xem nhanh`, `Thêm vào giỏ`, `Mua ngay`.
  Lần tải desktop có `30` nút cho mỗi loại và không tràn ngang.
- Thẻ ebook hiển thị phiên bản, không cung cấp lựa chọn phiên bản để mua.
- Xóa bốn hàm cũ không còn được gọi trong Home và Admin Reconciliation sau khi
  ESLint phát hiện; luồng payout đang dùng duy nhất API transition có
  idempotency/audit.
- Oxlint: `0` warning, `0` error. ESLint: đạt.
- Frontend: `68/68` tests đạt; production build đạt; `git diff --check` đạt.
- Browser smoke chỉ đọc, không đặt hàng, thay đổi payout hoặc dữ liệu nghiệp vụ.
- Không commit, push hoặc deploy.

