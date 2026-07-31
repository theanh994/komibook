# Batch 3C.1 — Authenticated Operational Truth

## 1. Mục tiêu

Loại bỏ dữ liệu giả và trạng thái “thành công giả” trong các màn hình vận hành đã có API thật, đồng thời khép contract báo cáo chiến dịch thông báo:

1. Admin/customer support ticket.
2. Admin user detail, vendor/author approval và membership tiers.
3. Author dashboard.
4. Notification analytics và quá trình gửi campaign không tự tạo số liệu hoặc mở rộng sai audience.

Đây là một batch lớn nhưng cùng một invariant: **UI và API vận hành chỉ hiển thị dữ liệu đã tồn tại thật; lỗi/rỗng/không có telemetry phải được biểu diễn trung thực.**

## 2. Baseline đã được chấp nhận — không chạy lặp

Batch 3B.1 đã được Codex nghiệm thu:

- Backend full regression trước correction: `237 tests`, `1048 assertions`, pass.
- Backend delta access/annotation: `32 tests`, `96 assertions`, Pint pass.
- Frontend correction cuối: `12 tests`, pass.
- Frontend production build: pass.
- Oxlint, ESLint trên delta và `git diff --check`: pass.

Antigravity chỉ chạy gate theo delta ở mục 8. Không chạy lại toàn bộ backend/frontend regression. Codex sẽ chạy full regression tại mốc đóng nhóm 3C hoặc khi batch thay đổi hạ tầng dùng chung.

## 3. Bằng chứng trực tiếp và contract cần sửa

### Notification backend

`NotificationCampaignController::show` hiện:

- thay `sent_count = 0` bằng số giả;
- tự ghi các số giả vào database trong một GET request;
- tạo hourly trend, device distribution và segment distribution giả.

`dispatchCampaign` hiện:

- coi `fiction_enthusiasts` gần như toàn bộ customer;
- nếu target query rỗng thì fallback gửi cho toàn bộ customer;
- tự suy diễn `opened_count` và `click_count` từ tỷ lệ giả.

### Frontend vận hành

Các view sau vẫn giữ object/list mẫu trong state hoặc trong `catch`:

- Admin User Detail.
- Admin Help Desk và Ticket Detail.
- Customer Support.
- Vendor/Author approvals.
- Author Dashboard.
- Membership Tiers.

Notification Analytics chưa phân biệt rõ campaign chưa gửi, không có telemetry và lỗi API.

## 4. Allowed files

### Backend

- `backend/app/Http/Controllers/Api/Admin/NotificationCampaignController.php`
- `backend/tests/Feature/Phase3OperationalTruthTest.php` — **NEW**

### Frontend

- `frontend/src/views/admin/UserDetailView.vue`
- `frontend/src/views/admin/NotificationAnalyticsView.vue`
- `frontend/src/views/admin/HelpDeskView.vue`
- `frontend/src/views/admin/TicketDetailView.vue`
- `frontend/src/views/CustomerSupportView.vue`
- `frontend/src/views/admin/VendorApprovalsView.vue`
- `frontend/src/views/vendor/AuthorDashboardView.vue`
- `frontend/src/views/admin/MembershipTiersView.vue`
- `frontend/src/__tests__/phase3_operational_truth.spec.js` — **NEW**

Mọi file khác đều bị cấm sửa. Nếu contract hiện có không đủ để hoàn thành, dừng và báo blocker thay vì mở rộng file.

## 5. Backend changes

### NotificationCampaignController::show

- GET phải read-only, tuyệt đối không `update`, `save` hoặc tạo record.
- Trả đúng counter đã lưu:
  - `sent_count`;
  - `opened_count`;
  - `click_count`.
- Chỉ tính `open_rate` và `click_rate` từ counter thật; tránh chia cho zero.
- Không giả lập delivery rate, hourly trends, devices hoặc segments.
- Với telemetry chưa được hệ thống thu thập:
  - trả mảng rỗng hoặc `null`;
  - có cờ/field rõ ràng để frontend hiển thị “chưa có dữ liệu theo dõi”;
  - không dùng số mẫu.
- Campaign chưa gửi trả analytics trung thực, không giả lập trạng thái sent.

### dispatchCampaign/send/update

- Không fallback từ audience rỗng sang toàn bộ customer.
- `all`: chỉ toàn bộ customer.
- `active_readers`: chỉ customer đáp ứng query thật hiện có.
- `lapsed_users`: chỉ customer đáp ứng query thật hiện có.
- `fiction_enthusiasts`: nếu chưa có contract dữ liệu đủ tin cậy để xác định, fail closed bằng HTTP 422 trước khi gửi; không diễn giải thành `all`.
- Audience không có người nhận là kết quả hợp lệ với `sent_count = 0`; không gửi cho nhóm khác.
- Chỉ `sent_count` được cập nhật từ số notification thực sự tạo.
- Không tự suy diễn `opened_count` hoặc `click_count`; giữ counter thật hiện có.
- Không để campaign chuyển sang `sent` nếu việc xác định audience hoặc transaction tạo notification thất bại.
- Không thay đổi kiến trúc mail/queue ngoài phần cần thiết để loại bỏ fake success.

## 6. Frontend changes

Áp dụng chung cho tám view:

- State khởi tạo không chứa tên, email, số tiền, ticket, tier, stats hoặc record mẫu.
- `catch` không tạo dữ liệu fallback.
- Có loading, error, retry và empty state phù hợp.
- Khi refresh lỗi, xóa dữ liệu có thể gây hiểu nhầm là kết quả mới.
- Không hiển thị placeholder giống dữ liệu thật; dùng `—`, “Chưa có dữ liệu” hoặc ẩn section.
- Action thất bại không cập nhật local state như đã thành công.

### UserDetailView

- `user` khởi tạo `null`, không giữ hồ sơ Nguyễn Văn A hoặc thông tin mẫu.
- Chỉ hiển thị membership/order/spending fields backend thực sự trả.
- Các thống kê chưa có contract như reading history/reviews/total books phải hiển thị unavailable/empty, không tự gán số hoặc tier.
- 404/403/API error có error state và retry/back.

### HelpDeskView, TicketDetailView, CustomerSupportView

- Xóa ticket/message fallback.
- Admin/customer chỉ thấy dữ liệu API trả về.
- Ticket detail phân biệt loading, not-found/forbidden và empty conversation.
- Reply/status/assign thất bại không tạo message hoặc đổi trạng thái cục bộ giả.
- Giữ đúng authorization/endpoint hiện có; nếu endpoint không đáp ứng vai trò, báo blocker.

### VendorApprovalsView

- Xóa vendor/author mẫu.
- Error và empty state tách biệt; retry không tự mở dialog/action.
- Không hiển thị bank/identity data mẫu.

### AuthorDashboardView

- Xóa earnings/book mẫu.
- Counter mặc định `0` chỉ được dùng như giá trị trung tính khi API success thiếu field tùy chọn; API error phải có error state, không render dashboard như success.

### MembershipTiersView

- Xóa tier mẫu.
- API error không cho edit/delete trên fallback data.
- Empty state cho phép mở create action thật nếu API success trả rỗng.

### NotificationAnalyticsView

- Dùng counter/rate thật từ backend.
- Campaign chưa gửi, sent_count bằng 0 hoặc telemetry breakdown chưa được thu thập phải có trạng thái trung thực.
- Không vẽ chart/device/segment giả khi mảng rỗng.
- Có error, retry và empty/unavailable state.

## 7. Automated tests

### Backend — Phase3OperationalTruthTest.php

Ít nhất kiểm tra:

1. GET campaign analytics không mutate campaign/database.
2. Counter zero không được thay bằng số giả.
3. Rate được tính từ counter thật.
4. Hourly/device/segment telemetry không bị dựng giả.
5. Audience query rỗng không fallback sang toàn bộ customer.
6. `fiction_enthusiasts` fail closed 422 khi chưa có contract thật.
7. Dispatch thành công cập nhật đúng `sent_count`, không tự tạo opened/clicked.
8. Dispatch failure không để campaign ở trạng thái sent giả.

### Frontend — phase3_operational_truth.spec.js

- Dùng test hành vi/state với mock `apiClient`; không dùng `fs.readFileSync` hoặc source grep.
- Kiểm tra tối thiểu:
  1. mỗi nhóm view không sinh fallback record khi API reject;
  2. error và retry;
  3. API success trả rỗng tạo empty state;
  4. User Detail không có hồ sơ mẫu trước/sau lỗi;
  5. Notification Analytics không render chart/breakdown giả khi backend báo unavailable;
  6. action thất bại không đổi local state thành success.
- Không cài dependency mới. Có thể dùng test helper trong chính file test.

## 8. Delta acceptance gates

Từ `backend`:

- `php vendor/bin/phpunit tests/Feature/Phase3OperationalTruthTest.php`
- `php vendor/bin/pint --test app/Http/Controllers/Api/Admin/NotificationCampaignController.php tests/Feature/Phase3OperationalTruthTest.php`

Từ `frontend`:

- `npm.cmd test -- --run src/__tests__/phase3_operational_truth.spec.js`
- `npm.cmd run build`
- `npx.cmd --no-install oxlint src/views/admin/UserDetailView.vue src/views/admin/NotificationAnalyticsView.vue src/views/admin/HelpDeskView.vue src/views/admin/TicketDetailView.vue src/views/CustomerSupportView.vue src/views/admin/VendorApprovalsView.vue src/views/vendor/AuthorDashboardView.vue src/views/admin/MembershipTiersView.vue src/__tests__/phase3_operational_truth.spec.js`
- `npx.cmd --no-install eslint src/views/admin/UserDetailView.vue src/views/admin/NotificationAnalyticsView.vue src/views/admin/HelpDeskView.vue src/views/admin/TicketDetailView.vue src/views/CustomerSupportView.vue src/views/admin/VendorApprovalsView.vue src/views/vendor/AuthorDashboardView.vue src/views/admin/MembershipTiersView.vue src/__tests__/phase3_operational_truth.spec.js`

Từ repository root:

- `git diff --check`

## 9. Forbidden scope

- Không sửa migration/schema hoặc thêm analytics-event infrastructure.
- Không thay Order/Payment/Inventory/VNPAY/ledger/outbox Giai đoạn 2.
- Không thêm endpoint, dependency, package hoặc lockfile.
- Không đưa Google/OTP, Blog/news hoặc Help Center vào batch này; chúng thuộc batch 3C tiếp theo.
- Không chạm `.env`, production, service, Cloudflare hoặc database ngoài test database.
- Không reset, checkout, stash, clean, commit hoặc push.

## 10. Báo cáo

Báo:

- file đã sửa;
- fake/fallback đã loại bỏ;
- contract analytics/audience sau sửa;
- test mới và kết quả từng delta gate;
- blocker còn lại.

Không commit hoặc push.
