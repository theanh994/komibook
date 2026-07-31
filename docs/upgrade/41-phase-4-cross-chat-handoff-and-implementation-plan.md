# KomiBook — Kế hoạch triển khai và bàn giao Giai đoạn 4

Ngày lập: 2026-07-26  
Repository nguồn: `C:\Projects\DoAnTotNGhiep_komibook`  
Task mới được phép dùng Codex worktree tạo từ working tree này; tuyệt đối không làm việc trong checkout Herd cũ.

## 1. Mục đích tài liệu

Tài liệu này thay thế phần bàn giao Giai đoạn 4 trước đó. Nó hợp nhất:

- phạm vi Giai đoạn 4 trong kế hoạch nâng cấp gốc;
- lộ trình mở rộng đã được ghi nhận trong tài liệu bàn giao xuyên tài khoản;
- trạng thái mã nguồn được kiểm tra trực tiếp tại thời điểm bàn giao;
- thứ tự batch, ranh giới trách nhiệm, cổng nghiệm thu và điều kiện triển khai.

Tài liệu này là kế hoạch và handoff, không phải bằng chứng rằng bất kỳ batch Giai đoạn 4 nào đã hoàn tất.

## 2. Thứ tự đọc bắt buộc ở task mới

Đọc đầy đủ theo thứ tự:

1. `docs/upgrade/41-phase-4-cross-chat-handoff-and-implementation-plan.md`
2. `Tai_lieu/Kế hoạch nâng cấp chất lượng dự án.docx`
3. `docs/upgrade/04-cross-account-continuation-handoff.md`
4. `docs/upgrade/30-phase-3-cross-chat-handoff.md`
5. `docs/upgrade/39-phase-3e-final-integration-acceptance.md`
6. `docs/upgrade/40-phase-3f-social-auth-production-readiness.md`
7. `docs/upgrade/01-api-contract-matrix.md`
8. `docs/upgrade/02-priority-backlog.md`

Nếu tài liệu và mã nguồn khác nhau, phải phân biệt:

- **Bằng chứng trực tiếp:** trạng thái vừa kiểm tra từ workspace/mã/test.
- **Lịch sử bàn giao:** kết quả được ghi từ task trước, chưa mặc nhiên là trạng thái hiện tại.
- **Khuyến nghị:** quyết định thiết kế hoặc thứ tự triển khai, chưa phải chức năng đã tồn tại.

## 3. Preflight tại thời điểm bàn giao

Kiểm tra read-only lúc `2026-07-26 14:52:27 +07:00`:

- CWD nguồn: `C:\Projects\DoAnTotNGhiep_komibook`
- Branch: `master`
- HEAD: `87d578924de8dc8b9afa7266c562b17783d9ccfe`
- `origin/master`: `87d578924de8dc8b9afa7266c562b17783d9ccfe`
- Commit: `Hoàn thiện đăng ký OTP email và đăng nhập mạng xã hội`
- `git diff --check`: sạch
- `backend/` và `frontend/`: không có thay đổi chưa commit
- `docs/upgrade/`: có tài liệu kế hoạch đã sửa/tạo cục bộ; đây là tài sản của người dùng, không được reset, checkout, stash hoặc clean.

Task mới phải chạy lại preflight read-only trước khi làm việc. CWD hợp lệ là repository nguồn trên hoặc Codex worktree được tạo trực tiếp từ working tree của repository này:

```powershell
Get-Date -Format "yyyy-MM-dd HH:mm:ss zzz"
Get-Location
git branch --show-current
git rev-parse HEAD
git rev-parse origin/master
git status --short
git diff --check
```

Nếu CWD là đường dẫn Herd cũ, dừng ngay. Nếu là Codex worktree, phải xác nhận `git rev-parse --git-common-dir` trỏ về repository nguồn và tài liệu `41-...md` có mặt trước khi tiếp tục.

## 4. Điểm xuất phát từ Giai đoạn 3

### 4.1 Lịch sử bàn giao

Giai đoạn 3 đã đóng các nhóm chính:

- frontend build/runtime và luồng công khai trung thực;
- hợp đồng customer order detail/library/ebook access;
- quyền sở hữu annotation;
- loại bỏ hoặc fail-closed dữ liệu giả;
- đăng nhập Google/Facebook và đăng ký bằng OTP email 8 số;
- kiểm thử tích hợp cuối giai đoạn.

Kết quả lịch sử gần nhất:

- backend: `256 passed`, `1167 assertions`;
- frontend: `59 passed`;
- frontend build: đạt;
- commit và push: `87d578924de8dc8b9afa7266c562b17783d9ccfe`.

Task mới phải coi các con số trên là lịch sử cho đến khi chạy lại cổng phù hợp.

### 4.2 Ngoài phạm vi Giai đoạn 4

Không làm lại social login, email OTP hoặc các correction Giai đoạn 3 trừ khi:

- test hồi quy chứng minh lỗi;
- lỗi chặn trực tiếp Giai đoạn 4;
- thay đổi được ghi rõ là correction có bằng chứng.

## 5. Phạm vi Giai đoạn 4 theo kế hoạch nâng cấp

Giai đoạn 4 đóng toàn bộ phần nghiệp vụ còn thiếu theo ba tuyến liên tiếp:

1. **4A — Vòng đời thương mại và độ tin cậy tài khoản**
2. **4B — Onboarding, tự xuất bản và bản quyền**
3. **4C — CMS, hoa hồng và khuyến mãi**

Thứ tự bắt buộc:

`4A → ADR vai trò → 4B → 4C → nghiệm thu tích hợp`

Không mở 4B trước khi chốt ADR vai trò. Không mở 4C trước khi các điều kiện xuất bản/quyền sở hữu của 4B ổn định.

## 6. Re-audit trực tiếp trước Giai đoạn 4

### 6.1 Những gì đã có khung

- Người mua có thể hủy checkout online/VNPAY khi đơn còn `pending`, `unpaid` và kho chưa commit.
- `PaymentTransactionStatus` đã có trạng thái liên quan refund, nhưng chưa tạo thành luồng refund hoàn chỉnh.
- Trang chi tiết đơn/hóa đơn phía khách hàng đã có dữ liệu thật từ Giai đoạn 3.
- Review có model, bảng và API tạo mới.
- Annotation đã kiểm tra quyền sở hữu ebook và giới hạn theo người dùng.
- Author/Vendor đã có hồ sơ đăng ký, trạng thái sơ bộ và màn hình quản trị phê duyệt.
- Editor chương sách, DRM settings và Flash Sale đã có khung ban đầu.
- Notification Campaign có model, CRUD và khả năng gửi thủ công.
- Payout có yêu cầu rút tiền và thao tác approve/reject.
- Blog hiện hiển thị trạng thái “chưa có nguồn nội dung xác minh”, không còn giả dữ liệu.

### 6.2 Issue thực sự còn tồn tại

#### Thương mại, hoàn tiền và hóa đơn

- Chưa có `return_requests` và `refund_transactions`.
- Chưa có workflow khách yêu cầu trả hàng, vendor/admin xét duyệt, nhận lại hàng và xác nhận hoàn tiền.
- Chưa có nghiệp vụ phục hồi tồn kho, đảo điểm thưởng và đảo ledger khi hoàn trả được xác nhận.
- Chưa có tích hợp/state history hoàn tiền VNPAY hoàn chỉnh.
- Chưa có snapshot hóa đơn bất biến gồm số hóa đơn, thông tin người mua/người bán, các thành phần giảm giá/phí/thuế và tổng cuối.

#### Review, annotation và tài khoản

- Review chưa chứng minh verified purchase.
- Bảng review chưa có unique constraint `user_id + book_id`.
- Chưa có luồng sửa review, report abuse và moderation.
- Annotation đã kiểm tra sở hữu ebook nhưng chưa chứng minh chapter/page thuộc đúng sách.
- Frontend có chỗ hiển thị reading progress nhưng backend chưa có nguồn tiến độ đọc bền vững.
- Email OTP đã xác minh email khi đăng ký, nhưng chưa có chính sách hạn chế sensitive action, quản lý phiên đăng nhập và thu hồi phiên.
- Password reset bằng điện thoại trong kế hoạch gốc không còn phù hợp với quyết định bỏ SMS trả phí; cần ADR giữ email reset miễn phí hoặc chỉ demo, không tái đưa SMS production.

#### Payout và notification campaign

- Payout hiện trừ balance ngay lúc tạo request nhưng thiếu row lock/ledger giữ tiền rõ ràng.
- Admin approve đang cộng `total_withdrawn` ngay ở trạng thái `approved`; kế hoạch yêu cầu chỉ cộng khi `completed`.
- Thiếu `processing`, bằng chứng chuyển khoản, người duyệt, thời điểm xử lý và transition guard đầy đủ.
- Campaign hiện tải toàn bộ audience rồi tạo thông báo trong một request/giao dịch.
- `scheduled_at` chưa có scheduler dispatch tương ứng.
- Thiếu batch job, retry/failure report và telemetry có consent; các chỉ số open/click chưa có nguồn tracking thật.

#### Onboarding, vai trò, xuất bản và bản quyền

- Author chỉ có trạng thái sơ bộ `pending/active/rejected`, chưa có state machine draft/submitted/under_review/changes_requested/resubmitted/approved/suspended/revoked.
- Vendor onboarding chưa có state machine, hồ sơ pháp lý/thanh toán tách biệt và guard “inactive không được publish/sell/checkout”.
- Dashboard author đang phụ thuộc vendor; mô hình vai trò Author–Vendor chưa được quyết định chính thức.
- Các route quản lý sách/chương vẫn chủ yếu nằm trong nhóm middleware `role:vendor`.
- Copyright vẫn là mô phỏng: có `copyright_number` thì cho phép tiếp tục; chưa có hồ sơ, bằng chứng riêng tư, review, tranh chấp hoặc thu hồi.
- Editor chưa có đầy đủ version history/restore, review feedback/resubmit, scheduled publish, delegation và royalty ledger.

#### CMS, commission và promotion

- Chưa có Article/News CMS; Blog chỉ đang fail-closed.
- Commission/service fee nằm trong file JSON cấu hình, chưa có bản ghi DB theo effective date, audit và lịch sử bất biến.
- Flash Sale có CRUD/đăng ký/approve sơ bộ nhưng thiếu kiểm soát overlap, timezone, stacking với coupon, ưu tiên áp dụng, snapshot giá/giảm giá và quy tắc refund theo snapshot.
- Chưa có audit/notification đầy đủ cho các transition Flash Sale.

## 7. Kiến trúc batch Giai đoạn 4

Mục tiêu là batch đủ lớn để giảm lặp lại, nhưng không trộn các state machine độc lập. Mỗi batch chỉ chạy lại test mục tiêu; full regression chỉ chạy ở cổng cuối của 4A, 4B, 4C và cuối Giai đoạn 4.

### 4A.0 — ADR vòng đời thương mại và tài khoản

Loại: tài liệu, Codex thực hiện; không sửa source.

Chốt trước khi giao code:

- state machine return/refund;
- ranh giới Order, ReturnRequest, RefundTransaction và PaymentTransaction;
- khi nào phục hồi kho/đảo điểm/đảo ledger;
- quy tắc refund COD và VNPAY;
- invoice snapshot và quy tắc thuế/phí;
- payout reservation/ledger;
- chính sách reset mật khẩu miễn phí bằng email;
- chính sách session và sensitive action.

Đầu ra: ADR được người dùng phê duyệt.

### 4A.1 — Return, refund và invoice snapshot

Mục tiêu:

- khách hủy trước giao theo policy;
- khách tạo yêu cầu trả hàng;
- vendor/admin xử lý theo state machine;
- hoàn kho, điểm và ledger idempotent;
- refund VNPAY có lịch sử trạng thái, retry an toàn;
- hóa đơn lấy từ snapshot bất biến, không tính lại từ dữ liệu hiện tại.

State machine đề xuất:

`requested → under_review → approved → item_received → refund_processing → refunded`

Nhánh:

`requested/under_review → rejected`  
`refund_processing → refund_failed → refund_processing`

Cổng mục tiêu:

- tests transition hợp lệ/không hợp lệ;
- ownership và cross-vendor authorization;
- idempotency/concurrency;
- inventory, points, ledger reversal;
- VNPAY failure/retry;
- invoice snapshot không đổi khi book/vendor/config thay đổi;
- migration fresh + rollback/reapply trên DB tạm.

### 4A.2 — Review, annotation, reading progress và account trust

Mục tiêu:

- review chỉ cho verified purchase;
- unique user/book, create-or-update hoặc edit rõ ràng;
- report/moderation có audit;
- annotation tham chiếu vị trí hợp lệ của đúng ebook;
- reading progress đồng bộ theo user/book;
- session listing/revocation;
- sensitive actions yêu cầu email verified hoặc recent authentication;
- password reset qua email, không phát sinh dịch vụ SMS trả phí.

Cổng mục tiêu:

- verified/unverified purchase;
- duplicate review/concurrency;
- owner/non-owner, book/chapter mismatch;
- session revoke current/other session;
- unverified email bị chặn đúng chỗ;
- regression Google/Facebook/email OTP.

### 4A.3 — Payout và notification campaign vận hành thật

Mục tiêu payout:

`pending → approved → processing → completed`  
`pending → rejected`

- lock dữ liệu và chống overdraw;
- giữ/giải phóng tiền có ledger;
- reviewer/time/reason/evidence;
- `total_withdrawn` chỉ tăng ở `completed`;
- transition idempotent.

Mục tiêu campaign:

- scheduler chọn campaign đến hạn;
- job theo batch/chunk, không tải cả audience trong HTTP request;
- retry/failure report;
- số liệu chỉ hiển thị khi có telemetry thật và consent phù hợp;
- không giả open/click/delivery.

Cổng mục tiêu:

- payout transition/concurrency/ledger;
- scheduler idempotency;
- chunk dispatch, retry, partial failure;
- audience authorization/privacy;
- queue fake tests, không gửi email thật trong test.

### Gate 4A

Chạy:

- toàn bộ backend tests;
- toàn bộ frontend tests;
- frontend production build;
- migration rehearsal;
- browser smoke các luồng thương mại/tài khoản mới.

Không sang 4B nếu return/refund/payout chưa nhất quán tài chính.

## 8. ADR vai trò bắt buộc trước 4B

Phải được người dùng duyệt một trong các chính sách:

- Author tạo/sở hữu tác phẩm; Vendor vận hành gian hàng, giá, kho, đơn và tài chính.
- Một User có thể đồng thời có AuthorProfile và VendorProfile.
- Chọn cách author bán sách:
  1. author đăng ký thêm vendor để tự bán;
  2. author xuất bản qua gian hàng nền tảng;
  3. hỗ trợ cả hai với quyền và hợp đồng rõ ràng.

Khuyến nghị: hỗ trợ cả hai, nhưng tách quyền sáng tác/xuất bản khỏi quyền vận hành thương mại; không dùng `role:vendor` làm đại diện cho quyền tác giả.

ADR phải chốt:

- permission matrix;
- ownership/delegation;
- quan hệ book–author–vendor;
- điều kiện publish và sell;
- royalty source và ledger;
- migration/backfill cho dữ liệu hiện hữu.

## 9. Các batch 4B

Theo yêu cầu handoff cũ, các state machine sau không được gộp vào một prompt lớn.

### 4B.1 — Author onboarding

`draft → submitted → under_review → approved`  
`under_review → changes_requested → resubmitted`  
`under_review → rejected`  
`approved → suspended/revoked`

Bao gồm hồ sơ riêng tư, lý do, audit, notification, resubmit và quyền trước/sau phê duyệt.

### 4B.2 — Vendor onboarding

State machine tương tự nhưng policy/hồ sơ pháp lý/thanh toán độc lập.

Bao gồm:

- inactive vendor không được publish/sell/checkout;
- cross-vendor isolation;
- suspend/revoke;
- audit và notification.

### 4B.3 — Copyright

`draft → submitted → under_review → verified`  
`under_review → changes_requested/rejected`  
`verified → disputed/revoked`

Bao gồm:

- chủ sở hữu, loại đăng ký, phạm vi, thời hạn;
- bằng chứng riêng tư;
- duplicate/overlap checks;
- coauthor/delegation nếu ADR yêu cầu;
- audit, dispute, revoke;
- xóa hoàn toàn logic “có copyright number là được publish”.

### 4B.4 — Self-publishing và editor

Book lifecycle:

`draft → submitted_for_review → approved → scheduled/published`

Nhánh sửa:

`submitted_for_review → changes_requested → draft → resubmitted`

Bao gồm:

- metadata/cover;
- chapter editor, autosave;
- version history và restore;
- sắp xếp chương;
- preview đa thiết bị;
- import;
- price/sample/release date;
- DRM/watermark/signed access;
- review feedback/resubmit;
- published revision;
- ownership/delegation;
- royalty ledger;
- publish eligibility dựa trên author + copyright + book.

### Gate 4B

- transition/permission/audit tests cho author, vendor, copyright và book;
- private document access tests;
- cross-account/cross-vendor tests;
- migration rehearsal;
- full backend/frontend/build;
- browser smoke từng vai trò.

## 10. Các batch 4C

### 4C.1 — CMS bài viết/tin tức

State machine:

`draft → submitted → under_review → approved → scheduled/published`

Nhánh:

`under_review → changes_requested/rejected`  
`published → unpublished/archived`

Bao gồm:

- admin/editor hoặc author/vendor đã duyệt viết cho nội dung được phép;
- sanitized body/media/category/tags/book links;
- revisions;
- scheduling/timezone;
- `home_featured`;
- SEO;
- audit/notification;
- public API chỉ trả bài published hợp lệ;
- thay BlogView fail-closed bằng dữ liệu CMS thật.

### 4C.2 — Commission và service fee có lịch sử

Bao gồm:

- cấu hình bền vững trong DB;
- effective date;
- người thay đổi, lý do và audit;
- validation 0–100;
- preview;
- snapshot vào order/ledger;
- thay đổi mới không sửa lịch sử cũ;
- transaction và rollback an toàn.

### 4C.3 — Flash Sale và promotion integrity

Bao gồm:

- admin lifecycle;
- vendor đăng ký sách;
- approve/reject;
- book/vendor eligibility;
- sale price/time/timezone;
- overlap;
- coupon stacking/priority;
- stock limit/reservation nếu áp dụng;
- snapshot price/discount;
- refund dựa trên snapshot;
- public API chỉ trả campaign/item đang active và approved;
- audit/notification.

### Gate 4C

- CMS permission/sanitization/scheduling/revision tests;
- commission effective-date/snapshot/history tests;
- promotion overlap/stacking/snapshot/refund tests;
- migration rehearsal;
- full backend/frontend/build;
- browser smoke CMS/admin/vendor/customer.

## 11. Gate hoàn tất Giai đoạn 4

Giai đoạn 4 chỉ hoàn tất khi:

- vòng đời order → return/refund → payout đóng kín;
- hóa đơn, commission và promotion dùng snapshot bất biến;
- onboarding author/vendor hỗ trợ approve/reject/resubmit/suspend/revoke;
- self-publishing yêu cầu quyền tác giả và bản quyền hợp lệ;
- article được duyệt mới xuất bản;
- không còn analytics/invoice/blog/copyright giả;
- tất cả transition quan trọng có audit;
- permission tests bao phủ customer/author/vendor/admin và cross-account;
- backend full suite đạt;
- frontend full suite đạt;
- production build đạt;
- migration fresh + rollback/reapply đạt;
- browser smoke đạt;
- production chỉ triển khai sau phê duyệt riêng.

## 12. Phân công Codex và Antigravity

### Codex

- lập/duy trì ADR và kế hoạch;
- re-audit trước từng batch;
- tạo prompt code có ranh giới;
- nghiệm thu độc lập diff và code;
- chạy cổng test;
- sửa trực tiếp lỗi nhỏ trong giới hạn người dùng đã cho phép;
- quản lý commit/push/deploy khi được phê duyệt;
- cập nhật handoff.

### Antigravity

- chỉ tạo/sửa source theo prompt được duyệt;
- không sửa tài liệu kế hoạch;
- không tự mở rộng phạm vi;
- không commit, push, deploy hoặc thay đổi production/database thật.

Mỗi prompt Antigravity phải có:

- một mục tiêu nghiệp vụ;
- danh sách file/module được phép;
- file/module cấm;
- state machine và invariant;
- migration/rollback;
- test bắt buộc;
- lệnh gate;
- yêu cầu báo changed files;
- câu “không commit hoặc push”.

## 13. Tối ưu nghiệm thu, tránh lặp

- Mỗi batch chạy static check và tests mục tiêu của chính batch.
- Không chạy full regression sau mọi correction nhỏ.
- Correction chỉ chạy lại test mục tiêu và gate đã thất bại.
- Full regression chạy một lần tại Gate 4A, Gate 4B, Gate 4C và gate cuối.
- Migration rehearsal chỉ cần chạy khi batch có migration; chạy hợp nhất ở gate nhóm.
- Browser smoke dùng checklist tăng dần; không kiểm lại toàn bộ Giai đoạn 3 trừ các luồng bị tác động.
- Mỗi batch kế thừa bằng chứng gate trước nếu HEAD không đổi ở vùng liên quan, nhưng phải ghi rõ đó là bằng chứng kế thừa.

## 14. Ranh giới an toàn

Không được tự ý:

- làm việc trong đường dẫn Herd cũ;
- reset, checkout, stash hoặc clean các tài liệu cục bộ;
- commit/push khi chưa có phê duyệt tên commit;
- thay đổi package/dependency mà chưa nêu package, version và lý do;
- dùng dịch vụ SMS hoặc dịch vụ trả phí;
- gửi email thật trong test;
- ghi vào production database;
- thay đổi Cloudflare, service hoặc credential;
- deploy production.

Mọi migration phải:

- có `down()` an toàn;
- được rehearsal trên database tạm;
- có chiến lược backfill;
- không suy diễn dữ liệu tài chính/bản quyền không tồn tại.

## 15. Bước đầu tiên của task mới

Task mới chỉ thực hiện:

1. đọc đủ tài liệu bắt buộc;
2. chạy preflight read-only;
3. xác nhận HEAD và dirty docs;
4. re-audit trực tiếp phạm vi 4A.0/4A.1;
5. tách bằng chứng trực tiếp, lịch sử và khuyến nghị;
6. trình ADR 4A.0 cùng đề xuất ranh giới Batch 4A.1;
7. xin người dùng phê duyệt ADR/batch trước khi sửa source.

Không được bắt đầu source work chỉ dựa trên tài liệu bàn giao này.

## 16. Mẫu báo cáo cuối mỗi batch

- Batch và mục tiêu.
- HEAD đầu/cuối.
- Changed files.
- State transitions/invariants đã triển khai.
- Tests mục tiêu và kết quả.
- Gate nhóm nếu có.
- Migration rehearsal nếu có.
- Issue còn lại.
- Bằng chứng trực tiếp.
- Lịch sử kế thừa.
- Khuyến nghị/bước duy nhất tiếp theo.
- Xác nhận chưa commit/push/deploy nếu chưa được phép.
