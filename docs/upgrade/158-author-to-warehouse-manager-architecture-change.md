# Thay đổi kiến trúc — Loại bỏ tác nhân Tác giả, bổ sung Quản lý kho

Ngày: 29/07/2026  
Trạng thái: hướng sản phẩm đã được người dùng phê duyệt; triển khai source được xếp vào phần sau của dự án

## 1. Quyết định sản phẩm

- KomiBook ngừng phát triển tác nhân **Tác giả** như một vai trò vận hành.
- Không tiếp tục đăng ký Tác giả, Author Studio, viết/preview/xuất bản tác phẩm tự viết, bản quyền cộng tác, royalty mới hoặc promotion riêng của Tác giả.
- Bổ sung tác nhân **Quản lý kho** (`warehouse_manager`) để vận hành một hoặc nhiều kho thuộc Vendor.
- Vendor vẫn là chủ thể sở hữu gian hàng và kho về mặt nghiệp vụ; Quản lý kho là tài khoản được Vendor mời, phân công và thu hồi quyền theo từng kho.
- Luồng sách cũ, trả hàng và tranh chấp hàng giả được giữ lại nhưng tách khỏi Tác giả thành capability **Người bán sách cũ** (`used_book_seller`) của tài khoản khách hàng.

## 2. Vì sao không đổi tên Author trực tiếp

`authors` hiện liên kết với onboarding/OTP, địa chỉ fulfillment, copyright, coauthor, royalty, Author Studio, ebook version, coupon và used-book listing. `warehouses` lại thuộc `vendors` và đang là nguồn của stock, reservation, transfer, audit và fulfillment.

Đổi tên hoặc tái sử dụng bảng `authors` cho Quản lý kho sẽ:

- trộn PII/bank/copyright với quyền kho;
- có thể cấp nhầm quyền xuyên Vendor;
- làm mất nghĩa lịch sử royalty, ebook entitlement và sách đã bán;
- khiến migration/down/backfill khó kiểm chứng.

Vì vậy dùng mô hình mới, additive trước; archive Author sau.

## 3. Mô hình đích

### 3.1. Quản lý kho

- Tài khoản người dùng có capability `warehouse_manager`, không cần có Vendor profile.
- Bảng assignment mới liên kết `user_id`, `vendor_id`, `warehouse_id`, trạng thái, quyền chi tiết, người mời và thời điểm hiệu lực.
- Một người có thể quản lý nhiều kho; mỗi assignment luôn bị khóa trong một Vendor.
- Vendor có thể mời, duyệt, tạm khóa, thu hồi và xem lịch sử thay đổi quyền.
- Admin chỉ giám sát/audit, không âm thầm trở thành nhân viên của Vendor.

Quyền dự kiến:

- xem tồn kho của kho được giao;
- lập/duyệt phiếu nhập kho;
- lập/duyệt phiếu xuất kho;
- đề xuất/nhận điều chuyển kho;
- kiểm kê, ghi nhận chênh lệch và vị trí kệ;
- xem đơn cần xử lý tại kho;
- không được sửa giá, payout, commission, hồ sơ Vendor hoặc dữ liệu kho khác.

### 3.2. Phiếu kho và audit

- Phiếu nhập, phiếu xuất, điều chuyển và kiểm kê là chứng từ bất biến sau khi hoàn tất.
- Mọi thay đổi tồn kho đi qua ledger/operation key, không cập nhật số lượng rời rạc không có nguồn.
- State machine tối thiểu: `draft → submitted → approved → completed`; có `rejected/cancelled` với lý do.
- Người tạo và người duyệt phải được snapshot; thao tác nhạy cảm hỗ trợ nguyên tắc tách nhiệm vụ khi Vendor bật cấu hình này.

### 3.3. Sách cũ

- Không giao luồng sách cũ cho Quản lý kho, vì đây là marketplace cá nhân chứ không phải nghiệp vụ kho của Vendor.
- Tạo/chuẩn hóa hồ sơ `used_book_seller` cho khách hàng muốn đăng sách cũ.
- Listing, ảnh thật, tình trạng, tồn, địa chỉ fulfillment riêng tư, attestation hàng thật, return và dispute được giữ.
- Listing Author cũ được ánh xạ sang hồ sơ người bán sách cũ theo manifest; không xóa hoặc đổi chủ âm thầm.

### 3.4. Di sản Tác giả

- Dừng entry point đăng ký và tạo dữ liệu Author mới trước; chưa xóa bảng/record.
- Sách/ebook đã xuất bản tiếp tục bán và đọc; entitlement/version đã mua không đổi.
- Copyright, royalty, agreement và lịch sử xuất bản cũ chuyển read-only/audit archive.
- Trường tên tác giả trên sách tiếp tục là metadata thư mục, không còn đồng nghĩa với tài khoản/actor.
- Chỉ drop bảng/cột Author sau khi có inventory, export/archive, zero-reference proof và rollback được duyệt riêng.

## 4. Kế hoạch triển khai mới

### Phần còn lại Giai đoạn 7

- Hoàn tất 7C.3 Commission & phí theo ADR 157 đã duyệt.
- 7D chỉ giữ ổn định marketplace sách cũ và chuẩn bị tách actor; không tiếp tục Author promotion/Studio/copyright source.
- 7E hoàn thiện Vendor parity và audit nghiệp vụ kho hiện có để làm baseline.
- 7F chạy data inventory, regression và lập manifest mọi record Author/used-book/warehouse; không migrate dữ liệu thật.

### Giai đoạn 8 — Chuyển đổi vai trò và vận hành kho

1. **8A — ADR và manifest:** capability matrix, assignment lifecycle, inventory document state machine, mapping Author legacy/used-book.
2. **8B — Identity & assignment:** invitation, activation, suspension/revocation, warehouse-scoped middleware/policies.
3. **8C — Cổng Quản lý kho:** dashboard tách trang, kho được giao, tồn/vị trí kệ, đơn cần xử lý.
4. **8D — Chứng từ kho:** phiếu nhập, phiếu xuất, điều chuyển, kiểm kê, approval/audit/print.
5. **8E — Vendor/Admin control:** Vendor quản lý nhân sự kho; Admin giám sát, tra cứu audit và xử lý ngoại lệ.
6. **8F — Sách cũ và Author retirement:** capability người bán sách cũ, chuyển listing có kiểm soát, ẩn Author entry point, archive lịch sử.
7. **Gate 8:** negative authorization đa Vendor/kho, concurrency/ledger, migration rehearsal, responsive UAT theo vai trò.

### Giai đoạn 9 — UAT và phát hành

- UAT khách hàng, người bán sách cũ, Quản lý kho, Vendor và Admin.
- Không còn Author trong navigation/onboarding hoạt động; dữ liệu lịch sử vẫn truy xuất đúng theo quyền.
- Migration/backup/rollback, queue/scheduler, staging và production vẫn là gate phê duyệt riêng.

## 5. Ranh giới an toàn

- Không rename/drop bảng Author trong batch UI hoặc route.
- Không tự động biến mọi Author thành Quản lý kho.
- Không tự gán Quản lý kho vào tất cả kho của Vendor.
- Không chuyển listing sách cũ nếu chưa có manifest owner mapping và báo cáo xung đột.
- Không xóa ebook version, entitlement, royalty, copyright hoặc sách đã bán.
- Không commit, push, deploy, chạy database thật hoặc thao tác production nếu chưa có yêu cầu riêng.

## 6. Acceptance tổng

- Mọi endpoint kho kiểm tra cả `vendor_id` và `warehouse_id` assignment.
- Quản lý kho không truy cập tài chính/cấu hình/giá hoặc kho ngoài assignment.
- Phiếu nhập/xuất/transfer/audit cân bằng ledger và idempotent.
- Sách cũ vẫn tạo, bán, trả hàng và dispute được dưới actor mới.
- Public books và ebook đã mua không mất quyền truy cập sau khi Author bị retire.
- Tất cả thay đổi dữ liệu đều có dry-run manifest, backup và rollback rehearsal trước production.

## 7. Bổ sung kiến trúc chuỗi trách nhiệm thương mại (2026-07-30)

Giai đoạn 8 không còn giả định `Vendor = Nhà cung cấp` cho mọi sản phẩm. Kế hoạch phải tách rõ Nhà bán, Nhà xuất bản, Nhà cung cấp và Đơn vị chịu trách nhiệm được khai báo; hỗ trợ cả Nhà xuất bản bán trực tiếp và Nhà sách/hiệu sách bán đa nguồn.

- ADR chi tiết: `docs/upgrade/176-phase-8-commercial-party-architecture-adr.md`.
- Kế hoạch 24 batch đã điều chỉnh: `docs/upgrade/177-phase-8-master-plan.md`.
- Trục Warehouse Manager và chứng từ kho vẫn giữ nguyên; commercial-party model bổ sung khả năng truy vết nguồn sách cho kho, listing và order snapshot.
- Không backfill ngầm Vendor thành Publisher/Supplier/Responsible organization; mọi mapping legacy đi qua manifest và review.
