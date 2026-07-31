# Giai đoạn 6F.2 — nghiệm thu Viết sách và Xuất bản

Ngày nghiệm thu cục bộ: 2026-07-29

**Đạt Gate 6F.2 cục bộ.**

- Trang Tác phẩm/Bản thảo nằm riêng trong Author Panel; dialog có form semantics,
  loading/error/retry/empty và target 44px.
- Live Editor dùng danh sách chương phía trên ở mobile, sidebar ở desktop; chapter
  row dùng button bàn phím, input nội dung có label và trạng thái lỗi semantic.
- Sửa lỗi hai tên route không tồn tại: editor → preview và preview → editor nay dùng
  `author-studio-preview` / `author-studio-write`.
- Device Preview responsive, device switch có pressed semantics và action 44px.
- Copyright detail có label/date semantics; không upload hoặc submit hồ sơ.
- Browser bốn route ở `375 / 768 / 1024 / 1440`: không overflow. Writer/preview
  dùng ID không tồn tại để kiểm tra error state, không tạo/lưu/xóa dữ liệu.
- Frontend `68/68`, build đạt; backend `16` tests, `105` assertions đạt trong batch;
  `git diff --check` đạt.

