# Page override: Newsroom

Áp dụng cho trang tin tức công khai, trang đọc bài, Newsroom Admin/Nhà bán,
trình biên tập, kiểm duyệt bình luận và review cộng đồng.

- Giữ phong cách content-first, navy/Material 3 và token ngữ nghĩa của `MASTER.md`.
- Trang đọc dài giới hạn nội dung chính ở 65–75 ký tự mỗi dòng; dùng Literata cho
  thân bài, Inter cho điều hướng và metadata.
- Desktop có thể dùng sidebar bài liên quan; dưới 1024 px sidebar chuyển xuống sau
  nội dung, không tạo vùng cuộn lồng.
- Trang quản lý, trình viết, hàng đợi review và bình luận là các route riêng.
- Trình viết phải có nhãn trường hiển thị, tự lưu cục bộ, cảnh báo rời trang, xem
  trước, trạng thái lưu và lịch sử phiên bản.
- Nhà bán chỉ sửa nội dung thuộc gian hàng và chỉ được gửi duyệt; Admin chịu trách
  nhiệm phê duyệt, lên lịch, xuất bản và kiểm duyệt.
- Review cộng đồng được chuyển thành bản nháp biên tập, không xuất bản trực tiếp.
- Bình luận mới fail-closed ở trạng thái chờ duyệt; email khách không hiển thị.
- Tất cả điều khiển đạt tối thiểu 44×44 px, có focus-visible và không phụ thuộc
  hover hoặc màu sắc để truyền đạt trạng thái.
- Kiểm tra bắt buộc tại 375, 768, 1024 và 1440 px với loading, empty, error và ready.
