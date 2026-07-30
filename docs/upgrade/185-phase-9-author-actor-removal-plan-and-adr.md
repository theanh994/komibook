# Giai đoạn 9 — Loại bỏ actor Author trước phát hành

Ngày phê duyệt: 2026-07-30
Trạng thái: **ADR APPROVED / IMPLEMENTED-LOCAL**

## 1. Quyết định kiến trúc

KomiBook không còn actor, loại tài khoản hay kênh quản lý **Tác giả/Author**.

Các khái niệm được giữ lại:

- `books.author` và nhãn “Tác giả/Người viết” là dữ liệu thư mục mô tả ấn phẩm, không phải tài khoản hay quyền hệ thống;
- khách hàng có thể bán sách cũ qua capability trung tính `used_book_seller`;
- Nhà bán chịu trách nhiệm đăng sản phẩm, xuất bản, thương mại và quan hệ Publisher/Supplier/Responsible organization;
- Quản kho hoạt động theo assignment từng kho của Nhà bán;
- quyền đọc ebook, phiên bản ebook, đơn hàng và snapshot lịch sử tiếp tục được bảo toàn.

Các khái niệm bị loại bỏ khỏi runtime:

- role và đăng ký Author;
- onboarding/xác minh Author;
- trang quản lý, studio viết tác phẩm và route `/author/*`;
- copyright claim, đồng tác giả, ủy quyền tác giả;
- royalty agreement, acceptance và ledger theo actor Author;
- kho, coupon, article và listing sách cũ phụ thuộc hồ sơ Author.

## 2. Nguyên tắc dữ liệu

- Không xóa mù dữ liệu cũ.
- Migration đầu tiên chuyển người bán sách cũ sang hồ sơ và địa chỉ trung tính.
- Migration tiếp theo lưu snapshot các bảng actor đã nghỉ vào `retired_actor_archives`, đổi role cũ về `customer`, rồi mới loại bỏ schema cũ.
- `down()` tái tạo schema và phục hồi snapshot để rehearsal có thể đảo chiều.
- Kho lưu trữ này là dữ liệu kỹ thuật nội bộ cho rollback/audit, không được công bố qua API hoặc giao diện.
- Migration lịch sử vẫn giữ nguyên tên cũ để chuỗi migrate có thể chạy lại từ đầu; đây không phải runtime contract.

## 3. Các batch triển khai

### 9A — Preflight, ADR và inventory

- Bảo toàn worktree hiện hữu; không reset, checkout, stash hoặc clean.
- Xác nhận checkout chính `C:\Projects\DoAnTotNGhiep_komibook`.
- Phân loại rõ actor Author và metadata người viết.
- Đóng băng phạm vi migration và rollback trước khi sửa runtime.

### 9B — Capability người bán sách cũ trung tính

- Tạo `used_book_seller_profiles`.
- Tạo `seller_fulfillment_addresses` có dữ liệu liên hệ riêng tư.
- Chuyển listing sang `seller_user_id` và địa chỉ giao nhận đã xác minh.
- Cho phép khách hàng quản lý nhiều sách cũ mà không cần role Author.

### 9C — Migration nghỉ actor

- Chuyển ownership và dữ liệu liên quan sang contract trung tính.
- Snapshot dữ liệu actor cũ.
- Đổi role Author cũ về Customer.
- Loại bỏ bảng và khóa ngoại actor sau khi đã bảo toàn dữ liệu.
- Chứng minh fresh/rollback/reapply trên SQLite dùng một lần.

### 9D — Backend cutover

- Xóa controller, model, service, resource, enum và command của actor.
- Xóa route đăng ký, onboarding, copyright và royalty.
- Chuyển bài viết sang `created_by`.
- Giới hạn chỉnh sửa tác phẩm/chương cho Nhà bán.
- Gate xuất bản dựa trên chuỗi commercial parties đã xác minh.
- Dashboard dùng chỉ số người bán sách cũ, không còn hàng chờ Author.

### 9E — Frontend cutover

- Xóa route, guard, store state, menu và màn hình Author.
- Đăng ký chỉ còn Khách hàng hoặc Nhà bán.
- Trang sách cũ bổ sung form địa chỉ giao nhận riêng tư, trạng thái và validation.
- Giữ metadata người viết trên form, chi tiết sách và trình đọc.
- Các thay đổi UI tuân thủ token hiện hữu, nhãn rõ nghĩa, vùng tương tác tối thiểu 44 px và trạng thái responsive.

### 9F — Gate tích hợp

- Quét route và source runtime cho định danh actor.
- Test backend, frontend, build production cục bộ.
- Rehearsal migration fresh/rollback/reapply.
- Kiểm tra format và whitespace diff.

## 4. Acceptance criteria

- Không còn role, API, route, navigation, dashboard hoặc quyền Author đang hoạt động.
- Không còn runtime relation tới model Author.
- Người bán sách cũ tiếp tục tạo nhiều listing bằng capability trung tính.
- Địa chỉ người bán không lộ qua public resource.
- Nhà bán, Quản kho, commercial parties, ebook và lịch sử giao dịch không hồi quy.
- Metadata người viết vẫn hiển thị đúng trên catalog.
- Migration có thể đảo chiều trên database thử nghiệm.
- Không chạm database thật, credential, Cloudflare, production hoặc lịch sử Git.
