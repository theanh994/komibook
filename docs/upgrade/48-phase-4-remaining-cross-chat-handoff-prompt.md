# KomiBook — Prompt bàn giao phần còn lại của Giai đoạn 4

Ngày tạo: 2026-07-26  
Repository đang làm việc: `C:\Projects\DoAnTotNGhiep_komibook`  
Trạng thái: Gate 4A đã nghiệm thu cục bộ; chưa commit, push hoặc deploy

Sao chép nguyên khối prompt dưới đây vào chat Codex mới.

```text
Tiếp nhận và hoàn thành toàn bộ phần còn lại của KomiBook Giai đoạn 4 trong checkout chính `C:\Projects\DoAnTotNGhiep_komibook`. Không làm việc trong checkout Herd cũ. Người dùng cho phép chỉnh sửa trực tiếp mã nguồn chính; không cần tạo worktree. Chỉ dùng worktree nếu thực sự làm tăng an toàn và phải giải thích trước, nhưng ưu tiên tiếp tục trực tiếp trong checkout chính để tránh phát sinh công việc chuyển đổi.

Mục tiêu của task mới:

1. Chốt ADR quan hệ Author–Vendor bắt buộc trước 4B.
2. Triển khai và nghiệm thu tuần tự 4B.1, 4B.2, 4B.3, 4B.4, Gate 4B.
3. Triển khai và nghiệm thu tuần tự 4C.1, 4C.2, 4C.3, Gate 4C.
4. Chạy Gate hoàn tất Giai đoạn 4 và lập báo cáo/handoff cuối.

Không lặp lại Gate 4A hoặc làm lại Giai đoạn 3 nếu không có test/hồi quy trực tiếp chứng minh lỗi liên quan.

## A. Thứ tự đọc bắt buộc

Đọc đầy đủ, không chỉ skim, theo thứ tự:

1. `docs/upgrade/48-phase-4-remaining-cross-chat-handoff-prompt.md`
2. `docs/upgrade/41-phase-4-cross-chat-handoff-and-implementation-plan.md`
3. `Tai_lieu/Kế hoạch nâng cấp chất lượng dự án.docx`
4. `docs/upgrade/04-cross-account-continuation-handoff.md`
5. `docs/upgrade/30-phase-3-cross-chat-handoff.md`
6. `docs/upgrade/39-phase-3e-final-integration-acceptance.md`
7. `docs/upgrade/40-phase-3f-social-auth-production-readiness.md`
8. `docs/upgrade/01-api-contract-matrix.md`
9. `docs/upgrade/02-priority-backlog.md`
10. `docs/upgrade/42-phase-4a0-commerce-account-adr.md`
11. `docs/upgrade/43-phase-4a1-return-refund-invoice-implementation.md`
12. `docs/upgrade/44-phase-4a2-review-reading-account-trust-implementation.md`
13. `docs/upgrade/45-phase-4a3-payout-campaign-operations-implementation.md`
14. `docs/upgrade/46-phase-4a-gate-antigravity-acceptance-plan.md`
15. `docs/upgrade/47-phase-4a-gate-acceptance.md`

Nếu tài liệu và mã khác nhau, luôn tách rõ:

- Bằng chứng trực tiếp vừa kiểm tra từ workspace/code/test.
- Lịch sử bàn giao chưa mặc nhiên là trạng thái hiện tại.
- Khuyến nghị/thiết kế chưa phải chức năng đã tồn tại.

## B. Preflight bắt buộc trước mọi thay đổi

Chỉ chạy read-only:

- thời gian hiện tại;
- CWD;
- nếu ở worktree thì `git rev-parse --git-common-dir` và xác nhận trỏ về repository nguồn;
- branch;
- HEAD;
- `origin/master`;
- `git status --short`;
- `git diff --check`.

Mốc lịch sử lúc bàn giao, phải xác minh lại:

- CWD: `C:\Projects\DoAnTotNGhiep_komibook`
- branch: `master`
- HEAD: `87d578924de8dc8b9afa7266c562b17783d9ccfe`
- `origin/master`: `87d578924de8dc8b9afa7266c562b17783d9ccfe`
- toàn bộ source Phase 4A và nhiều tài liệu `docs/upgrade/` đang modified/untracked hợp lệ;
- `git diff --check` sạch;
- chưa có commit hoặc push cho Phase 4;
- artifact SQLite ngoài ý muốn `backend/komibook` đã được xóa;
- không còn server smoke trên port 8000/5173.

Mọi thay đổi cục bộ hiện có đều thuộc người dùng. Không reset, checkout, stash, clean, ghi đè hoặc xóa chúng. Đặc biệt bảo toàn toàn bộ `docs/` và `Tai_lieu/`.

## C. Bằng chứng Gate 4A đã được chấp nhận

Tại task trước, Codex đã kiểm tra độc lập:

- backend: 283 tests đạt, 1309 assertions;
- frontend: 8 test files, 59 tests đạt;
- frontend production build đạt;
- Pint trên toàn bộ PHP đang thay đổi đạt;
- `git diff --check` sạch;
- full `migrate:fresh` trên SQLite tạm đạt;
- rollback/reapply đúng ba migration 4A đạt;
- browser smoke trên SQLite/users/session tạm đạt cho:
  - customer return/refund, marketing consent, session listing;
  - admin payout reconciliation và campaign không giả telemetry;
  - vendor payout/finance và return management;
- mọi server/database/log tạm đã được dọn;
- không có production/real database/email/provider action.

Antigravity chỉ correction một fixture test annotation bằng `page_number: 1`. Codex correction một file `backend/bootstrap/app.php` để sắp xếp import, không đổi hành vi.

Không coi kết quả trên là production approval. Chỉ chạy lại Gate 4A nếu code 4B/4C tác động trực tiếp các invariant tài chính/tài khoản hoặc regression chứng minh lỗi.

Issue lịch sử không chặn 4A:

- rollback toàn bộ chuỗi migration trên SQLite vấp migration cũ `2026_07_17_173710_add_profile_fields_to_users_table` quanh index `users_google_id_unique`; riêng migration 4A rollback/reapply đạt. Không tự ý sửa Giai đoạn 3 chỉ vì issue này nếu chưa chứng minh nó chặn batch hiện tại.
- cảnh báo PHP nạp OpenSSL hai lần và cảnh báo chunk lớn `EbookReaderView` hiện không chặn gate.

## D. Cách phối hợp Codex và Antigravity

Từ đây dùng Antigravity cho mọi batch cần tạo/sửa source:

- Codex chịu trách nhiệm re-audit, ADR, kế hoạch, prompt, tài liệu, kiểm tra diff/code độc lập, chạy gate và quyết định accept/reject.
- Antigravity chỉ tạo/sửa source và test trong prompt đã khóa phạm vi.
- Prompt Antigravity không lặp lại đường dẫn workspace vì chat Antigravity đã được scope sẵn.
- Mỗi prompt phải ghi rõ: một mục tiêu nghiệp vụ, file/module được phép, file/module cấm, state machine/invariant, migration/down/backfill, test bắt buộc, lệnh gate, changed-files report và câu `không commit hoặc push`.
- Không chấp nhận báo cáo Antigravity nếu chưa tự đọc diff và chạy lại gate.
- Codex được sửa trực tiếp nếu mọi lỗi còn lại vẫn trong objective đã duyệt và:
  - chỉ nằm trong một file; hoặc
  - tối đa ba file nhưng tổng cộng không quá hai phương thức.
- Sau correction trực tiếp phải đọc lại diff và chạy lại gate đã fail.
- Nếu lỗi chạm file thứ tư, hơn hai phương thức trên nhiều file, đổi architecture/schema/business/security policy hoặc cần quyết định thiết kế, trả lại Antigravity bằng corrective prompt hoặc xin người dùng duyệt.

Người dùng muốn thực hiện tuần tự toàn bộ phần còn lại và nhận báo cáo sau mỗi batch. Không yêu cầu phê duyệt lặp lại cho source work đã nằm trong ADR/batch được duyệt; nhưng ADR vai trò bắt buộc phải được người dùng duyệt trước khi giao 4B.1.

## E. Bước đầu tiên — ADR vai trò bắt buộc

Chưa giao source cho Antigravity ngay. Trước tiên re-audit trực tiếp model, migration, middleware, route, controller và UI hiện tại liên quan User, AuthorProfile, Vendor, Book, ownership và publish/sell.

Soạn ADR và trình người dùng duyệt, tối thiểu chốt:

- User có thể đồng thời có AuthorProfile và Vendor profile.
- Author sở hữu/tạo tác phẩm; Vendor vận hành gian hàng, giá, kho, đơn và tài chính.
- Không dùng `role:vendor` làm đại diện cho quyền tác giả.
- Hỗ trợ cả hai cách bán:
  1. author đăng ký thêm vendor để tự bán;
  2. author xuất bản qua gian hàng nền tảng/đối tác;
  nhưng quyền sáng tác/xuất bản phải tách khỏi quyền vận hành thương mại.
- permission matrix customer/author/vendor/admin;
- ownership, coauthor và delegation;
- quan hệ book–author–vendor;
- điều kiện publish và sell;
- royalty source/ledger;
- migration/backfill cho dữ liệu hiện hữu, không bịa dữ liệu quyền sở hữu hoặc tài chính.

Khuyến nghị lịch sử là hỗ trợ cả hai mô hình bán và tách quyền tác giả khỏi vendor; đây vẫn là khuyến nghị cho đến khi người dùng duyệt ADR.

Sau khi ADR được duyệt, Codex lập plan + prompt Antigravity cho 4B.1.

## F. Phần còn lại phải triển khai theo đúng thứ tự

### 4B.1 — Author onboarding

State machine:

`draft -> submitted -> under_review -> approved`

Nhánh:

`under_review -> changes_requested -> resubmitted`
`under_review -> rejected`
`approved -> suspended/revoked`

Bao gồm hồ sơ riêng tư, reason, audit, notification, resubmit và quyền trước/sau phê duyệt.

### 4B.2 — Vendor onboarding

State machine tương tự nhưng policy/hồ sơ pháp lý/thanh toán độc lập.

Invariant:

- inactive vendor không được publish/sell/checkout;
- cross-vendor isolation;
- suspend/revoke;
- audit và notification;
- không suy diễn hồ sơ thanh toán/pháp lý không tồn tại.

### 4B.3 — Copyright

`draft -> submitted -> under_review -> verified`

Nhánh:

`under_review -> changes_requested/rejected`
`verified -> disputed/revoked`

Bao gồm owner, loại đăng ký, phạm vi, thời hạn, bằng chứng riêng tư, duplicate/overlap, coauthor/delegation theo ADR, audit/dispute/revoke. Xóa hoàn toàn logic “có copyright_number là được publish”.

### 4B.4 — Self-publishing và editor

Book lifecycle:

`draft -> submitted_for_review -> approved -> scheduled/published`

Nhánh:

`submitted_for_review -> changes_requested -> draft -> resubmitted`

Bao gồm metadata/cover, chapter editor/autosave, version/restore, chapter ordering, preview, import, price/sample/release, DRM/watermark/signed access, review feedback, published revision, ownership/delegation, royalty ledger và publish eligibility dựa trên author + copyright + book.

### Gate 4B

- transition/permission/audit tests cho author/vendor/copyright/book;
- private-document access;
- cross-account/cross-vendor;
- migration fresh + rollback/reapply trên database tạm;
- full backend, frontend, build;
- browser smoke từng vai trò.

Không mở 4C trước khi Gate 4B được Codex chấp nhận.

### 4C.1 — CMS bài viết/tin tức

`draft -> submitted -> under_review -> approved -> scheduled/published`

Nhánh:

`under_review -> changes_requested/rejected`
`published -> unpublished/archived`

Bao gồm permission, sanitized body/media/category/tags/book links, revisions, scheduling/timezone, `home_featured`, SEO, audit/notification và public API chỉ trả published hợp lệ. Thay BlogView fail-closed bằng CMS thật, không tạo nội dung giả.

### 4C.2 — Commission/service fee có lịch sử

Bao gồm DB config bền vững, effective date, actor/reason/audit, validation 0–100, preview, immutable snapshot vào order/ledger, thay đổi mới không sửa lịch sử cũ và rollback transaction an toàn.

Không bật dịch vụ trả phí hoặc billing.

### 4C.3 — Flash Sale và promotion integrity

Bao gồm admin lifecycle, vendor enrollment, approve/reject, eligibility, price/time/timezone, overlap, coupon stacking/priority, stock/reservation nếu áp dụng, immutable price/discount snapshot, refund theo snapshot, public API chỉ trả active+approved và audit/notification.

### Gate 4C

- CMS permission/sanitization/scheduling/revision;
- commission effective-date/snapshot/history;
- promotion overlap/stacking/snapshot/refund;
- migration rehearsal tạm;
- full backend/frontend/build;
- browser smoke admin/vendor/customer.

### Gate hoàn tất Giai đoạn 4

Xác nhận:

- order -> return/refund -> payout khép kín;
- invoice, commission, promotion dùng snapshot bất biến;
- onboarding author/vendor đủ approve/reject/resubmit/suspend/revoke;
- self-publishing yêu cầu author và copyright hợp lệ;
- article phải được duyệt mới public;
- không còn analytics/invoice/blog/copyright giả;
- transition quan trọng có audit;
- permission tests phủ customer/author/vendor/admin và cross-account;
- full backend/frontend/build đạt;
- migration fresh + rollback/reapply đạt trong phạm vi hợp lệ;
- browser smoke đạt;
- lập báo cáo cuối Giai đoạn 4.

## G. Chiến lược gate không lặp

- Mỗi batch chỉ chạy tests/static checks mục tiêu của batch.
- Correction nhỏ chỉ chạy lại test mục tiêu và gate vừa fail.
- Full regression chỉ chạy ở Gate 4B, Gate 4C và Gate cuối Giai đoạn 4.
- Migration rehearsal hợp nhất ở gate nhóm; batch có migration vẫn cần focused migration tests.
- Browser checklist tăng dần; không lặp toàn bộ Giai đoạn 3 nếu vùng liên quan không đổi.
- Báo cáo sau mỗi batch: objective, HEAD đầu/cuối, changed files, state/invariant, tests, migration, issue, direct evidence, inherited history, recommendation/next step và xác nhận chưa commit/push/deploy.

## H. Ranh giới an toàn

Không tự ý:

- làm việc trong checkout Herd cũ;
- reset, checkout, stash hoặc clean;
- xóa/ghi đè tài liệu hoặc thay đổi local hiện hữu;
- tạo dependency/package change nếu chưa nêu package, version, lý do và xin duyệt;
- commit/push/merge/rewrite history nếu chưa có phê duyệt riêng;
- deploy production;
- thay đổi Cloudflare, Windows service, credential hoặc `.env` thật;
- ghi database thật;
- gửi email thật trong test;
- gọi gateway/payment/refund thật;
- bật SMS trả phí, pay-as-you-go, trial-credit dependency hoặc dịch vụ phát sinh phí.

Mọi migration mới phải có `down()` an toàn, backfill strategy, rehearsal trên database tạm và không suy diễn dữ liệu tài chính/bản quyền không tồn tại.

## I. Hành động ngay khi tiếp nhận

1. Đọc đủ tài liệu theo mục A.
2. Chạy preflight read-only theo mục B.
3. Re-audit trực tiếp phạm vi vai trò/ownership/publish.
4. Tách direct evidence, handoff history và recommendation.
5. Soạn ADR Author–Vendor và trình người dùng duyệt.
6. Chưa sửa source và chưa giao Antigravity trước khi ADR được duyệt.

Sau khi người dùng duyệt ADR, tiếp tục chủ động tuần tự toàn bộ 4B và 4C; báo cáo sau mỗi batch và dùng Antigravity cho source theo quy trình trên.
```
