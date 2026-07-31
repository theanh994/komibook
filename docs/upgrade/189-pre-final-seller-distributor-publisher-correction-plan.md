# Phụ lục kiến trúc trước phát hành — Nhà bán phân phối, NXB đối tác và dòng tiền

Ngày lập: 2026-07-31
Trạng thái: Đã được duyệt và hoàn tất triển khai source cục bộ; chờ phát hành/đổi dữ liệu production riêng
Quan hệ tài liệu: bổ sung và thay thế phần phân loại demo/dòng tiền tương ứng trong ADR 176 và kế hoạch 177; không sửa ngược lịch sử nghiệm thu Giai đoạn 8.

## 1. Làm rõ mô hình nghiệp vụ

KomiBook xác định **Nhà bán đứng tên listing** là bên trực tiếp vận hành bán hàng và nhận doanh thu sau phí. Nhà xuất bản của cuốn sách không mặc nhiên là Nhà bán.

- IPM, Hikari - Thái Hà Books và Fahasa là `Distributor + Supplier + Vendor/Seller`: trực tiếp mở gian hàng, nhập sách từ nhiều NXB, truyền thông, quản lý giá/tồn/đơn và nhận dòng tiền.
- NXB Lao Động và NXB Hà Nội là `Publisher organization`: liên kết với Nhà bán phân phối; không cần kênh bán hoặc ngân hàng KomiBook khi không trực tiếp bán.
- NXB Kim Đồng, NXB Trẻ và NXB Giáo Dục là `Direct Publisher + Supplier + Vendor/Seller`: tự mở gian hàng, tự bán và nhận dòng tiền; đồng thời có thể cấp quyền cho nhà phân phối khác bán catalog của mình.

Một tài khoản người dùng có thể quản trị organization mà không có quyền Nhà bán. Quyền truy cập phải dựa trên capability/trạng thái hồ sơ, không chỉ dựa trên `users.role`.

## 2. Ma trận demo mục tiêu

| Đơn vị | Organization types | Có gian hàng | Cần ngân hàng | Bên nhận doanh thu |
|---|---|---:|---:|---|
| IPM | distributor, supplier, bookstore | Có | Có | IPM |
| Hikari - Thái Hà Books | distributor, supplier, bookstore | Có | Có | Hikari - Thái Hà Books |
| Fahasa | distributor, supplier, bookstore | Có | Có | Fahasa |
| NXB Lao Động | publisher | Không mặc định | Không | Nhà bán của listing, ví dụ IPM |
| NXB Hà Nội | publisher | Không mặc định | Không | Nhà bán của listing, ví dụ IPM/Hikari |
| NXB Kim Đồng | publisher, supplier | Có | Có | NXB Kim Đồng |
| NXB Trẻ | publisher, supplier | Có | Có | NXB Trẻ |
| NXB Giáo Dục | publisher, supplier | Có | Có | NXB Giáo Dục |

## 3. Quy tắc listing và chuỗi cung ứng

Mỗi listing sách mới phải lưu độc lập:

1. `seller/vendor`: gian hàng đứng tên bán và thực hiện đơn;
2. `publisher organization`: NXB của ấn phẩm;
3. `supplier/distributor organization`: đơn vị cung ứng sách cho gian hàng;
4. `responsible organization`: đơn vị chịu trách nhiệm được khai báo theo hồ sơ đã duyệt;
5. thỏa thuận phân phối còn hiệu lực, có phạm vi bao phủ listing.

Ví dụ sách NXB Lao Động do IPM bán:

- `books.vendor_id` thuộc gian hàng IPM;
- Publisher = NXB Lao Động;
- Supplier/Distributor = IPM;
- doanh thu sau phí ghi vào ledger và số dư IPM;
- đơn hàng chụp snapshot đầy đủ Seller–Publisher–Supplier–Responsible organization.

Ví dụ sách Kim Đồng tự bán:

- `books.vendor_id` thuộc gian hàng Kim Đồng;
- Publisher, Supplier và Responsible organization có thể cùng là organization Kim Đồng sau khi xác minh;
- doanh thu sau phí ghi vào ledger và số dư Kim Đồng.

## 4. Dòng tiền và ngân hàng

Trong phạm vi này không tự động chia tiền cho NXB/Supplier:

`Khách hàng → KomiBook → trừ commission/phí → seller_net → số dư Nhà bán → ngân hàng Nhà bán đã xác minh`.

- Quan hệ Publisher/Distributor chứng minh nguồn gốc và quyền phân phối, không tự tạo quyền nhận payout.
- Thỏa thuận thanh toán giữa NXB và nhà phân phối nằm ngoài KomiBook trong phiên bản này.
- Nếu sau này cần chia doanh thu nhiều bên, phải có ADR riêng về allocation, thuế, hóa đơn, hoàn tiền và đối soát.
- Yêu cầu rút tiền không được nhận số tài khoản tùy ý. Nó phải snapshot tài khoản payout đã xác minh trong hồ sơ Nhà bán.
- Thay đổi ngân hàng cần recent-auth, audit và duyệt lại; Nhà bán thiếu ngân hàng không được rút tiền.

## 5. Chuyển đổi tài khoản demo

### 5.1. Sửa trạng thái sai hiện tại

- Hạ NXB Lao Động và NXB Hà Nội từ `role=vendor` về tài khoản cơ bản có capability quản trị organization; không tạo Vendor profile.
- NXB Trẻ và NXB Giáo Dục trở về tài khoản cơ bản trước, sau đó tạo Vendor draft bằng luồng đăng ký direct publisher chuẩn; chỉ đổi sang vendor khi hồ sơ được duyệt.
- Frontend `/vendor/*` bắt buộc `active_vendor`; tài khoản chưa có hồ sơ hoặc hồ sơ chưa duyệt phải chuyển về trang đăng ký/trạng thái hồ sơ, không được render dashboard rồi lỗi API.

### 5.2. IPM, Hikari - Thái Hà Books và Fahasa

- Tạo email demo `ipm.demo@komibook.id.vn`, `hikari.thaihabooks.demo@komibook.id.vn` và `fahasa.demo@komibook.id.vn` đã xác minh.
- Tạo Vendor profile ở trạng thái `draft`, business model `distributor`; không cấp `role=vendor` hoặc `active_vendor` trước khi hoàn tất ngân hàng, pháp nhân, điều khoản và được duyệt.
- Tạo organization tương ứng và membership owner; organization chỉ được verified sau quy trình Admin.
- Người dùng tự điền thông tin ngân hàng và hồ sơ còn thiếu; mật khẩu demo lưu ở private storage, bị Git ignore.

### 5.3. Kim Đồng, Trẻ và Giáo Dục

- Không xóa/tạo lại vì đã có dữ liệu liên kết.
- Đổi business model Kim Đồng từ `bookstore` sang `direct_publisher` sau kiểm tra dữ liệu; Trẻ và Giáo Dục đi qua onboarding direct publisher chuẩn.
- Tạo/gắn primary organization và self relationship cho cả ba NXB.
- Yêu cầu bổ sung ngân hàng; khóa payout cho tới khi tài khoản nhận tiền được xác minh.
- Giữ nguyên sách, tồn, đơn hàng, ledger và lịch sử.
- Cho phép cả ba NXB tạo/cấp thỏa thuận phân phối catalog cho IPM, Hikari, Fahasa hoặc nhà phân phối đã xác minh khác.

## 6. Kế hoạch triển khai điều chỉnh — 7 batch + 1 acceptance gate

### PF.1 — Channel/capability hotfix

- Sửa route frontend dùng `active_vendor`.
- Chuẩn hóa redirect cho `no profile`, `draft`, `submitted`, `changes_requested`, `rejected`.
- API không dùng `firstOrFail` để biến trạng thái onboarding bình thường thành lỗi tải dữ liệu.
- Chuyển vai trò sai của bốn tài khoản NXB theo ma trận mục tiêu.

### PF.2 — Organization membership

- Thêm `organization_memberships` và capability `organization_manager`.
- Policy tách quyền quản trị organization khỏi quyền vận hành gian hàng.
- Admin có thể mời, đình chỉ và thu hồi thành viên organization.

### PF.3 — Distributor/Seller onboarding

- Bổ sung wizard cho `direct_publisher`, `distributor`, `bookstore`, `mixed`.
- Distributor phải khai pháp nhân, ngân hàng, kho và phạm vi hoạt động như Nhà bán.
- Organization và Vendor được liên kết nhưng không đồng nhất.

### PF.4 — Distribution agreement

- Thêm quan hệ Publisher–Distributor/Supplier có chứng từ, hiệu lực, trạng thái duyệt và phạm vi sách/bộ sách/thể loại/catalog.
- Admin review/revoke; mọi thay đổi có audit trail.

### PF.5 — Listing commercial-party enforcement

- Book form tìm/chọn Publisher, Supplier và thỏa thuận áp dụng.
- Publish gate kiểm tra Vendor active, organization/relationship verified và agreement còn hiệu lực.
- Checkout giữ snapshot bất biến.

### PF.6 — Finance/payout hardening

- Doanh thu chỉ ghi cho Seller/Vendor của listing.
- Payout chỉ dùng ngân hàng đã xác minh trong Vendor profile.
- Mask dữ liệu ngân hàng, recent-auth khi đổi, audit và trạng thái duyệt.
- Không triển khai split settlement trong batch này.

### PF.7 — Demo data conversion

- Sao lưu production.
- Tạo IPM/Hikari/Fahasa theo luồng draft an toàn.
- Sửa Kim Đồng, Trẻ, Lao Động, Hà Nội, Giáo Dục theo ma trận.
- Gắn một tập sách demo đại diện cho direct publisher và distributor seller; không thay/xóa sách người dùng đã nhập ngoài mapping được duyệt.

### Gate PF — Acceptance và phát hành

- Migration fresh, rollback/reapply và production rehearsal trên bản sao.
- Backend authorization/finance/checkout tests; frontend route/onboarding tests.
- UAT từng loại tài khoản: Publisher-only, Distributor-Seller, Direct Publisher-Seller, Admin.
- Kiểm tra ledger/payout/snapshot, không rò rỉ ngân hàng/evidence.
- Chỉ commit, push và deploy sau yêu cầu riêng; production write có backup và manifest trước/sau.

## 7. Acceptance bắt buộc

- NXB Lao Động/Hà Nội đăng nhập không thấy kênh bán nếu chưa mở gian hàng, nhưng quản lý được organization và quan hệ phân phối.
- IPM/Hikari/Fahasa chưa duyệt chỉ thấy wizard/trạng thái hồ sơ; sau duyệt mới vào dashboard Nhà bán.
- Sách Lao Động do IPM bán hiển thị đúng gian hàng IPM, Publisher Lao Động và Supplier IPM; tiền về IPM.
- Sách Kim Đồng/Trẻ/Giáo Dục tự bán hiển thị direct publisher; tiền về chính gian hàng tương ứng.
- Khi Fahasa bán sách được một trong ba NXB cấp quyền, gian hàng và doanh thu thuộc Fahasa nhưng Publisher vẫn hiển thị đúng NXB gốc.
- Không endpoint nào cho phép vendor chưa active truy cập dữ liệu gian hàng hoặc gây lỗi tải dữ liệu.
- Không payout nào dùng tài khoản ngân hàng nhập tự do tại thời điểm rút.
- Lịch sử đơn cũ không đổi khi quan hệ phân phối bị sửa hoặc thu hồi.

## 8. Kết quả triển khai cục bộ ngày 2026-07-31

- PF.1: route quản lý Nhà bán yêu cầu capability `active_vendor`; tài khoản chưa có/ chưa được duyệt được đưa về onboarding thay vì render dashboard lỗi.
- PF.2: đã tách `organization_memberships` và capability `organization_manager` khỏi vai trò Nhà bán.
- PF.3: onboarding hỗ trợ `direct_publisher`, `distributor`, `bookstore`, `mixed`; hồ sơ draft không tự nhận quyền bán.
- PF.4: đã có thỏa thuận Publisher–Distributor, chứng từ riêng tư, thời hạn, phạm vi catalog/sách, duyệt/đình chỉ/thu hồi và event audit.
- PF.5: việc gán Publisher–Supplier khác nhau bị chặn nếu không có thỏa thuận đã xác minh còn hiệu lực; checkout tiếp tục chụp snapshot thương mại bất biến.
- PF.6: payout chỉ snapshot tài khoản ngân hàng đã xác minh trong hồ sơ Nhà bán; dữ liệu nhập tự do ở yêu cầu rút tiền bị bỏ qua.
- PF.7: đã có lệnh chuyển đổi an toàn/idempotent cho IPM, Hikari–Thái Hà Books, Fahasa và 5 NXB; mật khẩu chỉ ghi vào private storage, không in ra terminal. Chưa chạy lệnh này trên production.
- Gate cục bộ: backend `349 tests / 1917 assertions`; frontend `115 tests`; lint và production build đạt; migration fresh, rollback và reapply đạt trên SQLite tạm.
- Smoke trình duyệt: route `/organization-portal` của khách chưa đăng nhập chuyển đúng tới `/login?redirect=/organization-portal`, không có lỗi console.

Phần chưa được phép thực hiện trong batch source này: commit, push, deploy, migration production và chuyển đổi tài khoản/dữ liệu production. Các thao tác đó cần một yêu cầu phát hành riêng cùng backup production mới.
