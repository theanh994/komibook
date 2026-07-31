# Kế hoạch tổng Giai đoạn 8 — Quản lý kho và chuỗi trách nhiệm thương mại

Ngày lập: 2026-07-30  
Trạng thái: Đã hoàn tất triển khai và nghiệm thu cục bộ; chưa commit/push/deploy  
Tiền đề: Gate 7 accepted-local; production và database thật là gate riêng

## 1. Mục tiêu

Giai đoạn 8 đồng thời hoàn thành hai trục nghiệp vụ có liên hệ nhưng không đồng nhất:

1. Thay actor Author bằng Warehouse Manager theo từng kho của Vendor, xây chứng từ nhập/xuất/điều chuyển/kiểm kê.
2. Tách Seller, Publisher, Supplier và Responsible organization để Nhà xuất bản bán trực tiếp lẫn Nhà sách bán đa nguồn đều hoạt động đúng và truy vết được.

ADR chi tiết: `docs/upgrade/176-phase-8-commercial-party-architecture-adr.md`.

## 2. Quy mô và thứ tự

Kế hoạch được điều chỉnh từ 18 thành **24 source batch**, chưa tính các gate. Thứ tự bắt buộc:

`8A → Gate 8A/duyệt ADR → 8B → Gate 8B → 8C → Gate 8C → 8D → Gate 8D → 8E → Gate 8E → 8F → Gate 8F → Gate hoàn tất Giai đoạn 8`

### 8A — ADR, contract và manifest (3 batch)

- **8A.1:** re-audit actor/capability/route/policy và ranh giới Warehouse Manager, Vendor, Admin, Used-book Seller.
- **8A.2:** chốt ADR Seller–Publisher–Supplier–Responsible organization, onboarding và public disclosure.
- **8A.3:** legacy manifest, catalog commercial-party manifest, conflict report, migration/down/backfill rehearsal plan.

Không triển khai schema/source 8B trước khi Gate 8A được người dùng duyệt.

### 8B — Organization, quan hệ và định danh (5 batch)

- **8B.1:** organization schema/model/status/audit và public/private field contract.
- **8B.2:** Vendor business model cùng wizard đăng ký nhiều bước.
- **8B.3:** partner relationship, evidence, effective dates, approval/revocation lifecycle.
- **8B.4:** Warehouse Manager invitation, activation, suspension/revocation và warehouse assignment.
- **8B.5:** middleware/policy/capability; negative authorization chéo Vendor, kho và organization.

### 8C — Cổng Warehouse Manager (3 batch)

- **8C.1:** layout/navigation/dashboard tách trang, assignment switcher và trạng thái công việc.
- **8C.2:** kho được giao, tồn, vị trí kệ, cảnh báo và đơn cần xử lý.
- **8C.3:** responsive/accessibility/loading-empty-error-forbidden và UAT từng trang.

### 8D — Chứng từ kho và ledger (5 batch)

- **8D.1:** phiếu nhập kho và receipt lines.
- **8D.2:** phiếu xuất kho gắn fulfillment/order.
- **8D.3:** điều chuyển kho với hai đầu giao/nhận.
- **8D.4:** kiểm kê, chênh lệch, approval và adjustment ledger.
- **8D.5:** idempotency, concurrency, audit, print/export và gate cân bằng ledger.

### 8E — Vendor/Admin control và catalog parties (4 batch)

- **8E.1:** Vendor quản lý nhân sự kho, assignment và giới hạn quyền.
- **8E.2:** Vendor quản lý organization, đối tác Publisher/Supplier và hồ sơ chứng minh.
- **8E.3:** Admin review queue cho organization, relationship và listing commercial parties.
- **8E.4:** Book form/publish gate/default theo mô hình Direct Publisher, Bookstore, Distributor, Mixed.

### 8F — Public discovery, lịch sử và retirement (4 batch)

- **8F.1:** BookResource/order snapshot cho Seller–Publisher–Supplier–Responsible organization.
- **8F.2:** trang chi tiết sách, xem nhanh, hồ sơ organization và link nhanh gian hàng.
- **8F.3:** used-book exception, capability người bán sách cũ và controlled listing cutover.
- **8F.4:** ẩn Author entry point, archive lịch sử, compatibility/regression/data reconciliation.

## 3. Ràng buộc UI/UX

- Tiếp tục dùng design system KomiBook và `ui-ux-pro-max` cho mọi page batch.
- Management UI chia trang; một primary action ở page header; target tối thiểu 44x44 px.
- Bảng desktop chuyển thành card hoặc labeled scroll ở mobile, không thu nhỏ đến mất khả năng thao tác.
- Public book card không thêm metadata gây rối; commercial parties nằm trong quick view/detail.
- UAT liên tục từng trang tại 375, 768, 1024, 1440 px và đủ trạng thái loading/empty/error/ready/forbidden.

## 4. Gate theo nhóm

Mỗi group gate gồm:

- targeted backend tests và frontend component/contract tests;
- policy/tenant negative tests;
- migration `up/down` rehearsal khi có schema;
- `git diff --check`, build và regression liên quan;
- browser smoke theo page ledger và breakpoint;
- báo cáo changed files, dữ liệu thử, lỗi còn lại và rủi ro.

Gate hoàn tất Giai đoạn 8 bổ sung:

- Warehouse Manager không xem tài chính, giá, cấu hình hoặc kho ngoài assignment.
- Chứng từ kho cân bằng ledger, idempotent và không oversell.
- Direct Publisher và Bookstore đều publish đúng contract commercial parties.
- Order/invoice giữ snapshot sau khi relationship bị sửa/thu hồi.
- Public detail hiển thị đúng organization đã duyệt, không lộ evidence/địa chỉ kho.
- Dữ liệu Author/ebook/used-book lịch sử vẫn truy cập đúng quyền.

## 5. Quy trình mỗi source batch

1. Lập plan và contract/gate cụ thể.
2. Nếu agent ngoài khả dụng, tạo prompt giới hạn file/phạm vi; hiện tại Codex có thể trực tiếp triển khai theo quyền người dùng đã giao.
3. Nghiệm thu độc lập bằng code review, targeted tests, build và browser smoke.
4. Báo cáo sau batch rồi tiếp tục; chỉ dừng ở ADR, thay đổi kiến trúc/phạm vi, Git history, production, database thật, credential, dịch vụ ngoài hoặc chi phí.

Không commit, push, deploy hoặc thao tác database thật nếu chưa có yêu cầu rõ ràng tại thời điểm thực hiện.
