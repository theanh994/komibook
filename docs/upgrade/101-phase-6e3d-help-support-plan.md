# Giai đoạn 6E.3d — kế hoạch Trung tâm trợ giúp và Hỗ trợ

Ngày lập kế hoạch: 2026-07-29

## Mục tiêu

Hoàn thiện khả năng sử dụng, bàn phím, trạng thái tải/lỗi/rỗng và responsive cho
Trung tâm trợ giúp, trang tạo/theo dõi yêu cầu hỗ trợ và chi tiết phiếu hỗ trợ.
Không thay đổi contract API hay thực hiện thao tác làm biến đổi dữ liệu khi nghiệm thu.

## Phạm vi source

- `frontend/src/views/HelpCenterView.vue`
- `frontend/src/views/CustomerSupportView.vue`
- `frontend/src/views/admin/TicketDetailView.vue`

Không mở rộng sang quản trị danh sách ticket, backend, migration hoặc nghiệp vụ
gửi/đính kèm/tải tệp.

## Bất biến

- Giữ nguyên endpoint, payload, quyền truy cập và hành vi phân quyền hiện có.
- Không gửi ticket, phản hồi, đánh giá hữu ích, tải tệp đính kèm hay đổi trạng thái
  trong browser smoke.
- Mọi điều khiển chính đạt tối thiểu 44px, có nhãn truy cập và dùng được bằng bàn phím.
- Trạng thái tải/lỗi/rỗng có ngữ nghĩa phù hợp; bố cục không tràn ngang ở
  `375 / 768 / 1024 / 1440`.
- Không commit, push hoặc deploy.

## Prompt dự phòng giao Antigravity

> Mục tiêu: cải thiện UI/UX cho HelpCenterView, CustomerSupportView và
> admin/TicketDetailView theo kế hoạch 6E.3d. Chỉ được sửa đúng ba file nêu trong
> phạm vi. Bảo toàn toàn bộ API, payload, phân quyền và nghiệp vụ; không sửa backend,
> route, test hay file ngoài phạm vi. Bổ sung semantic heading/main, label và id cho
> form, keyboard-accessible card/row, loading/error/empty live semantics, vùng bấm
> tối thiểu 44px, responsive 375/768/1024/1440 và reduced motion. Không gửi ticket,
> phản hồi, đánh giá, tải file, đổi trạng thái; không commit hoặc push. Sau khi sửa,
> báo cáo file đã đổi và chạy frontend test, build cùng các test backend hỗ trợ/bảo
> mật được chỉ định.

## Gate

1. Frontend unit test và production build.
2. Backend test tập trung cho tạo ticket và quyền truy cập tệp đính kèm.
3. Browser smoke ba route ở bốn viewport, chỉ dùng trạng thái không biến đổi dữ liệu.
4. `git diff --check`.

