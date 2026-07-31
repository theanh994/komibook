# Giai đoạn 6E.3d — nghiệm thu Trung tâm trợ giúp và Hỗ trợ

Ngày nghiệm thu cục bộ: 2026-07-29

## Kết luận

**Đạt Gate 6E.3d cục bộ về contract, accessibility và responsive.**

Không gửi ticket/phản hồi/đánh giá, không chọn hoặc tải tệp, không đổi trạng thái,
không commit, push hoặc deploy.

## Kết quả

- Help Center có vùng tìm kiếm được gắn nhãn, nút tìm kiếm có accessible name,
  article card là button dùng được bằng bàn phím, cùng loading/error/empty semantics.
- Support có heading cấp một, label liên kết đúng với input/select/textarea, ticket
  history dùng button, target chính tối thiểu 44px và bố cục mobile một cột rõ ràng.
- Ticket Detail luôn có heading, nút quay lại có nhãn, error/loading semantics,
  textarea phản hồi có label, nhóm action co dọc trên mobile và thread không ép
  chiều cao 600px trên màn hình nhỏ.
- Cả ba trang tôn trọng reduced motion; endpoint, payload và quyền giữ nguyên.

## Bằng chứng

- Browser `/help-center`, `/support`, `/support/tickets/999999999` ở
  `375 / 768 / 1024 / 1440`: không tràn ngang; vùng điều khiển trong nội dung đạt
  44px. Ticket ID không tồn tại xác nhận trạng thái lỗi trực tiếp.
- Browser không thực hiện bất kỳ mutation hỗ trợ nào.
- Frontend: `11` files, `68` tests đạt; production build đạt.
- `AuthorDrmInventoryTest::test_support_ticket_creation`: `1` test,
  `3` assertions đạt.
- `SecurityHardeningTest::test_sec_03_and_04_private_files_and_migration_commands`:
  `1` test, `20` assertions đạt, gồm phân quyền tệp ticket private.
- `git diff --check`: đạt.

