# Page override: management dashboard

Áp dụng cho Admin, Vendor và Author management.

- Author dùng cùng ngôn ngữ shell với Vendor nhưng menu và quyền theo vai trò.
- Mỗi nhóm nghiệp vụ là một route/trang riêng; không dồn mọi chức năng vào một
  dashboard dài.
- Desktop: sidebar cố định đủ rộng để đọc nhãn; content không bị che.
- Mobile/tablet: sidebar thành drawer có nút mở 44×44 px, overlay, Escape và
  focus management.
- Thanh tiêu đề trang chứa breadcrumb ngắn, `h1` và tối đa một primary action.
- KPI dùng số + nhãn + khoảng thời gian; biểu đồ có bảng/tóm tắt thay thế.
- Bảng phải có tiêu đề cột rõ; ở mobile dùng vùng cuộn có nhãn hoặc card.
- Không giảm chữ dưới 14 px để “nhét” thêm dữ liệu.
