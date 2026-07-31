# Kế hoạch tổng thể Giai đoạn 7 — Sửa lỗi nghiệm thu và hardening

Ngày lập: 29/07/2026  
Checkout bắt buộc: `C:\Projects\DoAnTotNGhiep_komibook`

## 1. Mục tiêu và nguyên tắc

Giai đoạn 7 xử lý các phản hồi sau nghiệm thu Giai đoạn 6 và chuẩn bị an toàn cho thay đổi kiến trúc 158; việc tạo tác nhân Quản lý kho được triển khai ở Giai đoạn 8 thay vì trộn vào các batch sửa lỗi hiện tại.

- Không làm việc trong checkout Herd cũ.
- Bảo toàn toàn bộ thay đổi cục bộ; không reset, checkout, stash, clean hoặc reseed phá hủy dữ liệu.
- Không commit, push, deploy, dùng production, database thật, credential, dịch vụ ngoài hoặc phát sinh chi phí nếu chưa có yêu cầu/phê duyệt riêng.
- Sách, ảnh bìa và dữ liệu người dùng tự nhập là dữ liệu ưu tiên bảo toàn. Không dùng `migrate:fresh`, không xóa để “làm sạch”, không ghi đè trường thủ công.
- Mỗi batch source phải có kế hoạch riêng và prompt dự phòng cho Antigravity. Trong thời gian Antigravity hết quota, Codex được người dùng giao quyền sửa source nhưng vẫn phải tự review, chạy gate và báo cáo độc lập.
- Sau mỗi batch sẽ báo cáo nhưng tiếp tục batch kế tiếp; chỉ dừng ở ADR, thay đổi kiến trúc/phạm vi, Git history, production, database thật, credential, dịch vụ ngoài hoặc chi phí.
- Mọi trang UI được kiểm tra liên tục ở 375, 768, 1024 và 1440 px; kiểm tra biên 320 và 1536 px ở gate nhóm.

## 2. Bằng chứng đã xác nhận trước khi triển khai

Các kết luận dưới đây là bằng chứng trực tiếp từ mã/log hiện tại, không phải giả định:

1. Báo cáo tài chính lỗi trên SQLite vì `FinanceReportController` dùng `DATE_FORMAT`, hàm riêng của MySQL.
2. `OrderResource` luôn nối `/storage/` vào bìa, làm hỏng URL đã là `http(s)` hoặc `/storage/...`.
3. Redirect `/dashboard` xét Vendor trước Author; tài khoản Author có Vendor tương thích vì vậy bị đưa sai kênh.
4. Seeder tài chính tạo payout minh họa; đồng thời chỉ số `unreconciled` hiện chưa biểu diễn một sai lệch ledger thực sự.
5. Thẻ sách vẫn có quick view/cart/buy nhưng wishlist phụ thuộc prop, phần category/author và kiểu card mới không khớp mẫu người dùng duyệt.
6. Fallback hero không có ảnh nên tạo mảng nền tối lớn.
7. Trình viết và API chapter đang dùng tên trường thứ tự chương không thống nhất (`chapter_number` và `order`); cần tái hiện thêm để xác định đầy đủ lỗi runtime.
8. Trang Commission & phí và route vẫn tồn tại, nhưng đang tách rời cấu hình hệ thống và chưa giải thích đủ ý nghĩa nghiệp vụ.

## 3. Thứ tự triển khai — 17 batch

### Nhóm 7A — Lỗi chặn thao tác và tính đúng dữ liệu

**7A.1 — Báo cáo tài chính**

- Loại SQL phụ thuộc MySQL; tạo aggregation theo tháng dùng được trên SQLite và MySQL.
- Chuẩn hóa contract doanh thu, phương thức thanh toán, payout và khoảng thời gian.
- Gate: feature test SQLite, test contract API, smoke trang báo cáo không còn lỗi.

**7A.2 — Ảnh bìa đơn hàng**

- Dùng một bộ chuẩn hóa URL cho đường dẫn tương đối, `/storage/...`, `http(s)` và null.
- Audit cùng contract tại đơn khách hàng, đơn Vendor, hóa đơn và thư viện.
- Gate: resource tests đủ bốn dạng URL và browser smoke ảnh thật.

**7A.3 — Định tuyến đúng kênh Author**

- Thay redirect theo vai trò/capability chính; Author vào kênh Author, Vendor vào kênh Vendor.
- Tài khoản hai capability dùng kênh được chọn gần nhất hoặc màn hình chọn kênh, không âm thầm ưu tiên Vendor.
- Gate: route/unit test cho admin, customer, author, vendor và dual-capability.

**7A.4 — Khôi phục Author Studio đầu-cuối**

- Tái hiện lỗi bằng tài khoản Author; sửa create work → chapter đầu → save/autosave → reload → preview → submit.
- Đồng bộ payload `order/chapter_number`, quyền truy cập, trạng thái lỗi và nút lưu chương đầu.
- Gate: backend feature test, frontend unit test và browser journey hoàn chỉnh.

**7A.5 — Đối soát bằng dữ liệu thật**

- Tách fixture/demo khỏi dữ liệu chạy thường; không xóa bản ghi hiện hữu khi chưa có phê duyệt dữ liệu.
- Định nghĩa “chưa đối soát” từ ledger/payout/mismatch thật, kèm audit trail và trạng thái chuyển tiếp.
- Gate: test số liệu từ giao dịch mẫu có kiểm soát, zero-state, mismatch-state và không còn giá trị hardcode.

**Gate 7A:** năm lỗi P0 hết tái hiện; regression auth/order/finance/author studio xanh.

### Nhóm 7B — Hồi quy giao diện công khai

**7B.1 — Khôi phục BookCard dùng chung**

- Bìa hiển thị trọn vẹn, không nén/crop, hai góc trên không bo; bỏ category và author khỏi card.
- Giữ title, giá, giá gốc/giảm giá và phiên bản ebook khi cần.
- Trả lại trái tim icon-only; giữ quick view, thêm giỏ, mua ngay.
- Hover/focus có nâng nhẹ, bóng đổ, scale bìa và hiện action trong 150–300 ms; touch không phụ thuộc hover; hỗ trợ reduced motion và `aria-label`.

**7B.2 — Hero, gợi ý responsive và nút lên đầu trang**

- Dùng ảnh giá sách thuộc tài sản dự án làm fallback/default hero; vẫn giữ nội dung sự kiện thật.
- Gợi ý chỉ lấy tối đa 5 sách; desktop rộng đúng 5, các breakpoint tự giảm theo chiều rộng.
- Nút lên đầu trang dùng toàn site, không che mobile navigation, 44×44 px và tôn trọng reduced motion.

**7B.3 — Catalog và lứa tuổi**

- Khôi phục đúng năm lựa chọn: Nhà trẻ - mẫu giáo (0–6), Nhi đồng (6–11), Thiếu niên (11–15), Tuổi mới lớn (15–18), Tuổi trưởng thành (trên 18).
- Giữ URL state/back-forward và kiểm tra backend hiểu đúng biên tuổi.
- Wishlist ở catalog chỉ hiện biểu tượng tim nhưng giữ vùng chạm tối thiểu 44×44 px.

**Gate 7B:** visual/browser check Home + Catalog ở sáu chiều rộng; keyboard, touch, focus, contrast và ảnh bìa đều đạt.

### Nhóm 7C — Admin intelligence và cấu hình

**7C.1 — Dashboard Admin bằng dữ liệu thật**

- Bổ sung xu hướng doanh thu/đơn hàng, tăng trưởng người dùng/Author/Vendor, phân bố định dạng/trạng thái sách và hàng đợi vận hành.
- Chỉ dùng line/bar/stacked bar/donut phù hợp; có legend, giá trị/tooltip, bảng dữ liệu thay thế và không truyền nghĩa chỉ bằng màu.

**7C.2 — Chiến dịch thông báo**

- Đồng bộ list/create/analytics bằng token KomiBook; bỏ phối màu rời rạc.
- Sửa bố cục bảng/card, trạng thái, form validation, mobile stacking và action dễ đọc.

**7C.3 — Commission & phí trong cấu hình hệ thống**

- Đưa vào tab/nhóm của System Config nhưng giữ deep link cũ.
- Hiển thị rõ: khách trả, doanh thu gộp người bán, commission nền tảng, service fee, người bán nhận ròng, nền tảng nhận ròng, thời điểm hiệu lực và lịch sử.
- ADR bắt buộc trước source: ai chịu service fee, công thức thuế/làm tròn, hiệu lực hồi tố hay bất biến.

**Gate 7C:** dữ liệu chart đối chiếu API/DB; không mock; admin navigation, responsive và accessibility đạt.

### Nhóm 7D — Sách cũ và đóng băng phạm vi Author

**7D.1 — Quản lý nhiều sách cũ không phụ thuộc giao diện Author**

- Tách thành trang quản lý riêng, dùng ngôn ngữ “Người bán sách cũ” trung lập thay vì mở rộng Author.
- Form lặp/queue cho nhiều đầu sách; mỗi listing có số lượng, tình trạng, ảnh, giá và trạng thái riêng.
- Giữ endpoint đơn hiện tại để tương thích; nếu thêm bulk endpoint phải báo lỗi theo từng dòng và không làm mất draft hợp lệ.
- Chưa migrate owner trong Giai đoạn 7; mọi source mới phải có seam để Giai đoạn 8 chuyển sang capability `used_book_seller`.

**7D.2 — Đóng entry point Author và lập manifest chuyển đổi**

- Không triển khai thêm đăng ký Author, Studio, publishing/copyright/royalty hoặc promotion riêng của Author.
- Lập inventory read-only cho user/author/book/listing/entitlement/royalty/copyright và phân loại: giữ hoạt động, archive read-only, chuyển sang used-book seller hoặc chờ xử lý xung đột.
- Chỉ ẩn entry point sau khi có test chứng minh ebook đã mua, sách đã xuất bản và lịch sử tài chính vẫn truy cập đúng; không drop bảng/cột.

**Gate 7D:** quản lý nhiều sách cũ vẫn hoạt động; không tạo thêm phụ thuộc Author; manifest chuyển đổi đầy đủ và không có thao tác dữ liệu thật.

### Nhóm 7E — Vendor parity

**7E.1 — Dashboard, sách, đơn và analytics Vendor**

- Audit contract/cột dữ liệu thật, gồm lỗi analytics đang tham chiếu trường subtotal không tồn tại.
- Đồng bộ bìa, bảng/card responsive và navigation nhưng giữ nghiệp vụ Vendor riêng.

**7E.2 — Inventory, finance, promotion và fee Vendor**

- Dùng primitive/contract chung khi đúng nghĩa; không sao chép workflow Author đã bị retire.
- Kiểm tra tenant isolation, payout/ledger, đăng ký campaign và preview phí.
- Audit toàn bộ kho, stock, transfer, inventory audit và order fulfillment làm baseline cho `warehouse_manager`; chưa cấp quyền actor mới trong Giai đoạn 7.

**Gate 7E:** browser smoke Vendor + negative authorization; không rò dữ liệu chéo tenant.

### Nhóm 7F — Bảo toàn dữ liệu và quality closure

**7F.1 — Bổ sung sách/ảnh theo cơ chế an toàn**

- Lập inventory chỉ đọc và manifest dry-run trước khi ghi.
- Chỉ additive upsert bằng ISBN/slug/khóa ổn định; xung đột title, mô tả, giá, tồn, bìa do người dùng nhập phải dừng để duyệt.
- Không xóa demo finance hoặc dữ liệu sách bằng script diện rộng; việc phân loại/migrate phải có backup và báo cáo từng bản ghi.
- Bổ sung manifest Author legacy, used-book listing, warehouse và liên kết lịch sử để chuẩn bị Giai đoạn 8; không tự động map Author thành Quản lý kho.

**7F.2 — Policy, performance, CI và E2E**

- Hoàn thiện Policy/service boundaries/index, N+1, pagination/cache, ảnh responsive và EbookReader chunk.
- Chạy PHPUnit/Vitest/lint/build/E2E critical journeys; dependency chỉ triage, không auto-fix diện rộng.

**Gate hoàn tất Giai đoạn 7:** toàn bộ ledger đóng hoặc có ngoại lệ được người dùng duyệt; full regression xanh; diff check sạch; UAT theo vai trò và breakpoint đạt; không suy diễn local gate thành production approval.

## 4. Quy trình mỗi batch

1. Preflight read-only: cwd, status, diff stat/check, tài liệu và dữ liệu liên quan.
2. Viết kế hoạch batch + prompt Antigravity dự phòng, ghi allowed/forbidden files, invariants và gate.
3. Triển khai trong phạm vi batch; không trộn refactor ngoài mục tiêu.
4. Codex review diff độc lập, kiểm tra bảo mật/quyền riêng tư/data truth.
5. Chạy targeted test, build và browser smoke tại các breakpoint áp dụng.
6. Cập nhật ledger và báo cáo: file thay đổi, bằng chứng, lỗi còn lại, rủi ro, batch kế tiếp.

## 5. Các điểm phải xin duyệt trước

- ADR service fee/commission ở 7C.3 đã được duyệt tại tài liệu 157.
- ADR promotion/Flash Sale riêng của Author tại 7D.2 đã bị hủy theo thay đổi kiến trúc 158; promotion Vendor hiện có vẫn giữ nguyên.
- Mọi schema/backfill cho `warehouse_manager`, warehouse assignment, phiếu kho và `used_book_seller` chỉ được thực hiện sau ADR 8A.
- Bất kỳ thay đổi schema/phạm vi không nằm trong plan; Git history; database thật; production; credential; dịch vụ ngoài/chi phí.
- Ghi dữ liệu cục bộ ở 7F.1 được phép theo yêu cầu hiện tại, nhưng vẫn phải trình manifest dry-run trước để chứng minh không ghi đè sách người dùng tự nhập.
