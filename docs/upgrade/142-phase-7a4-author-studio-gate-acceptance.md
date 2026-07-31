# Giai đoạn 7A.4 — Nghiệm thu sửa Author Studio

Ngày: 29/07/2026  
Kết quả: accepted-local

## Thay đổi được nghiệm thu

- Quyền Author được xét trước fallback Vendor, nên tài khoản dual Author/Vendor có thể quản lý tác phẩm mà quan hệ tác giả đã được chấp nhận cho phép.
- `AuthorCommerceService` tái sử dụng Vendor hiện hữu của cùng tài khoản thay vì vi phạm ràng buộc duy nhất `vendors.user_id`.
- API chương nhận `order` canonical và tương thích đầu vào `chapter_number`; trạng thái miễn phí dùng `is_free`.
- Trình viết tạo một bản nháp chương đầu hợp lệ khi tác phẩm chưa có chương, không còn index `-1`, không tạo trùng draft và không ghi autosave nhầm chương sau khi đổi lựa chọn.
- Loại bỏ giá theo từng chương khỏi editor vì backend và nghiệp vụ hiện bán ebook theo toàn cuốn.

## Bằng chứng gate

- `Phase5AuthorStudioTest` + `Phase7AuthorStudioFlowTest`: 5 tests passed, 27 assertions.
- `phase7_author_studio.spec.js` + `phase3_critical_contracts.spec.js`: 13 tests passed.
- ESLint target: passed.
- Pint target: passed.
- Vite production build: passed; cảnh báo EbookReader chunk lớn là nợ đã ghi cho 7F.2, không phát sinh từ batch.
- `git diff --check` trong phạm vi batch: passed.

Browser journey cần phiên local hoạt động và phiên đăng nhập phù hợp; gate tự động đã xác nhận create work → create chapter → autosave → reload cùng negative authorization. Preview và submit publishing tiếp tục được nghiệm thu trong batch chuyên biệt, không được tuyên bố hoàn tất tại đây.

Đây là nghiệm thu cục bộ; không có commit, push, deploy, dữ liệu thật hoặc production operation.
