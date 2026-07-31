# Sổ vấn đề và trang Giai đoạn 7

Ngày khởi tạo: 29/07/2026  
Trạng thái ban đầu: planned

| ID | Phản hồi | Mức | Batch | Bằng chứng hiện tại | Acceptance chính |
|---|---|---:|---|---|---|
| P7-01 | BookCard lệch mẫu, mất tim/hover | P1 | 7B.1 — implemented, visual pending Gate 7B | Đã khôi phục card và 4 action; gate tự động tại tài liệu 147 | Bìa trọn, top square, không category/author, đủ 4 action |
| P7-02 | Hero thiếu ảnh giá sách; thiếu back-to-top | P1 | 7B.2 — implemented, visual pending Gate 7B | Hero asset WebP + back-to-top; gate tự động tại tài liệu 149 | Hero có asset dự án; nút hoạt động toàn site |
| P7-03 | Gợi ý responsive, tối đa 5 | P1 | 7B.2 — implemented, visual pending Gate 7B | API giới hạn 5; CSS 2/3/4/5 theo viewport; gate tại tài liệu 149 | 5 ở desktop rộng, tự giảm theo viewport |
| P7-04 | Sai nhóm lứa tuổi; tim catalog quá nặng | P1 | 7B.3 — implemented, visual pending Gate 7B | Năm nhãn chuẩn + alias dữ liệu cũ; gate tự động tại tài liệu 151 | Đúng 5 nhãn/biên; icon tim đơn |
| P7-05 | Dashboard Admin thiếu biểu đồ/thông tin | P1 | 7C.1 — implemented, visual pending Gate 7C | KPI + xu hướng/phân bổ/hàng đợi dữ liệu thật; gate tại tài liệu 154 | Chart dữ liệu thật, có bảng/a11y |
| P7-06 | Báo cáo tài chính lỗi | P0 | 7A.1 — accepted-local | Log xác nhận `DATE_FORMAT` trên SQLite; gate tại tài liệu 136 | API và trang chạy trên SQLite/MySQL |
| P7-07 | Đối soát dùng dữ liệu giả | P0 | 7A.5 — accepted-local | Đã thay KPI đơn hàng suy diễn bằng kiểm tra ledger/transition thật; gate tại tài liệu 144 | Ledger/provenance thật, zero/mismatch state đúng |
| P7-08 | Chiến dịch thông báo khó nhìn/lệch tông | P1 | 7C.2 — implemented, visual pending Gate 7C | Card responsive + token semantic + aggregate thật; gate tại tài liệu 156 | List/create/analytics đồng bộ token và responsive |
| P7-09 | Bìa đơn hàng không hiện | P0 | 7A.2 — accepted-local | Đã chuẩn hóa contract; gate tại tài liệu 138 | 4 dạng URL qua test và browser smoke |
| P7-10 | Author đăng nhập bị đưa vào Vendor | P0 | 7A.3 — accepted-local; legacy slated for Phase 8 retirement | Resolver hiện không đưa sai kênh trong giai đoạn chuyển tiếp | Không hồi quy trước khi Author entry point được retire an toàn |
| P7-11 | Trang viết tác phẩm không hoạt động | P0 | 7A.4 — accepted-local; sản phẩm bị retire theo tài liệu 158 | Journey cũ đã ổn định để bảo toàn dữ liệu trước archive | Không phát triển thêm; sách/ebook đã có vẫn truy cập được |
| P7-12 | Form sách cũ chỉ thuận tiện một sách | P1 | 7D — accepted-local | API trung lập + hàng đợi nhiều dòng + lỗi từng dòng; Gate 7D tại tài liệu 165 | Quản lý nhiều đầu sách; không tạo thêm phụ thuộc Author |
| P7-13 | Promotion/coupon riêng của Author | retired | Hủy theo thay đổi kiến trúc 158 | Vendor promotion vẫn giữ; Author actor sẽ bị loại bỏ | Không triển khai source Author promotion mới |
| P7-14 | Commission & phí không rõ/mất khỏi config | P1/ADR | 7C.3 — implemented, current-viewport UAT passed | Tab System Config, deep link cũ, preview tương thích API cũ; gate tại tài liệu 160 | Tích hợp System Config; preview/lịch sử rõ nghĩa |
| P7-15 | Các trang Vendor có vấn đề tương tự | P1 | 7E — accepted-local | Dashboard/analytics/books/orders tenant-safe; kho/kiểm kê/điều chuyển dùng đúng quantity và tenant; phí/Flash Sale thật; Gate 7E tại tài liệu 170 | Parity có chọn lọc, dữ liệu thật, tenant-safe |
| P7-16 | Bảo toàn/bổ sung sách người dùng nhập | P0-data | 7F.1 — accepted-local, import held | Manifest kiểm kê 55 sách, giữ nguyên toàn bộ; 43 stock mismatch dừng review; không có nguồn import nên không upsert | Dry-run manifest đạt; mọi import sau additive và phải so fingerprint, không ghi đè |
| P7-17 | Nợ kiến trúc/quality/performance Phase 7 cũ | P2 | 7F.2 — accepted-local | Bỏ N+1 kho, thêm index, lazy PDF; 377 backend + 102 frontend test và browser role E2E đạt; logo/Pint lịch sử ghi baseline | Full tests/E2E/performance gate đạt với baseline công khai |
| P7-18 | Thay Author bằng Quản lý kho | Architecture | Phase 8A–8F | 32+ route/capability Author; warehouse hiện chỉ thuộc Vendor, chưa có assignment user–warehouse | Actor mới warehouse-scoped; Author archive; không rò tenant |
| P7-19 | Giữ sách cũ khi retire Author | P0-data | 7D/7F.1 — accepted-local; 8F thực hiện migration actor | Hai manifest read-only phân loại owner và liên kết catalog; used-book API trung lập vẫn giữ FK legacy | Owner mapping có manifest; listing/return/dispute không mất |

## Ma trận trang cần người dùng giám sát

| Gate | Trang chính |
|---|---|
| 7A | Finance report, reconciliation, orders, login/channel switch, Author Studio/editor |
| 7B | Home, Catalog, quick view, cart/buy-now, wishlist |
| 7C | Admin dashboard, notification campaigns/create/analytics, system config/fees |
| 7D | Quản lý nhiều sách cũ, compatibility người bán sách cũ, manifest đóng Author |
| 7E | Vendor dashboard, analytics, books, orders, inventory, finance, promotions |
| 7F | Critical journeys guest/customer/used-book compatibility/vendor/admin và manifest Author/warehouse |

## Quy ước trạng thái

- `planned`: đã định phạm vi, chưa sửa source.
- `in-progress`: batch đang triển khai.
- `accepted-local`: targeted gate và browser smoke đã đạt cục bộ.
- `blocked-approval`: chờ ADR hoặc quyền ngoài phạm vi.
- `closed`: đã qua gate nhóm và regression liên quan.

Không dùng `accepted-local` hoặc `closed` để ngụ ý đã commit, push, deploy hay nghiệm thu production.
