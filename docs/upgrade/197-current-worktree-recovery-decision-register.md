# Decision register phục hồi worktree và contract kiểm thử

Ngày: 2026-08-08

Trạng thái: **Đã chốt cho chuỗi batch sửa lỗi cục bộ — chưa commit, push, deploy hoặc tác động database thật**

## 1. Mục tiêu và ranh giới

Tài liệu này khóa các quyết định nghiệp vụ và kỹ thuật cần thiết để sửa worktree đang dở dang theo từng batch nhỏ. Nó không xác nhận các thay đổi hiện có là hoàn tất và không thay thế kết quả kiểm thử thực tế.

Ranh giới bắt buộc:

- Giữ nguyên mọi thay đổi tracked và untracked thuộc người dùng; không `reset`, `checkout`, `stash`, `clean`, xóa hoặc ghi đè thay đổi ngoài file ownership của batch.
- Không commit, push, tạo PR, deploy, chạy migration trên database thật, dùng credential hoặc gọi dịch vụ ngoài khi chưa có phê duyệt riêng.
- Kết quả test/build/release trong tài liệu cũ chỉ là bằng chứng lịch sử cho đến khi được chạy lại trên diff hiện tại.
- Mỗi batch phải ghi manifest file trước/sau, chạy gate mục tiêu, được primary task kiểm tra diff thực tế và chỉ chuyển batch khi đã nghiệm thu.
- Khi tài liệu mới thay đổi một chính sách cụ thể, chỉ phần chính sách được nói rõ mới thay thế ADR cũ; các invariant khác của ADR cũ vẫn còn hiệu lực.

Baseline lúc bắt đầu Batch 0:

- Branch `master`, HEAD `1ce6f8240dbcff34369bfae1b3ba808075a30523`, không ahead/behind `origin/master`.
- 111 file tracked đã đổi và 28 file untracked.
- Diff tracked: 16.180 dòng thêm, 3.087 dòng xóa.
- Full backend: 447 pass, 4 fail, 2.525 assertions.
- Frontend: 139 pass, 17 fail; production build pass nhưng còn cảnh báo chunk lớn.
- `git diff --check`, Pint, ESLint và Oxlint chưa đạt.

## 2. Decision register

### D1 — Recent authentication phải fail closed

- Request được xác thực bằng token nhưng không có session không được tự động vượt qua `recent-auth`.
- Endpoint thay đổi mật khẩu, payout account, withdrawal, refund, reconciliation và quyết định quản trị nhạy cảm phải chứng minh step-up authentication bằng cơ chế được hỗ trợ rõ ràng.
- Không dùng việc thiếu session làm tín hiệu tin cậy. Nếu token flow chưa có proof phù hợp, trả lỗi yêu cầu xác thực lại.

### D2 — Seller không có quyền xác nhận thay buyer

- Seller chỉ được cập nhật các mốc do seller/carrier sở hữu: chuẩn bị, đã lấy hàng, đang vận chuyển và đã tới nơi/chờ khách xác nhận.
- Chỉ buyer sở hữu order mới được xác nhận đã nhận hàng. Admin/system chỉ được can thiệp qua một transition riêng có actor, reason và audit.
- Không được truyền `order->user_id` vào customer-confirmation service để giả lập actor.
- Trạng thái lạ, `failed`, `delivered` hoặc transition lặp phải fail closed hoặc idempotent theo đúng operation key; không đi vào một nhánh `else` tổng quát để giải ngân.

### D3 — Paid order đi qua return/refund workflow; tiền hoàn vào Ví KomiBook

Quyết định này hợp nhất ADR Phase 4A với kế hoạch Ví KomiBook mới hơn:

- `cancelled` chỉ là terminal state trước fulfilment. Paid order không được hủy trực tiếp từ endpoint buyer cancellation.
- Paid order phải tạo/đi qua `ReturnRequest` và `RefundTransaction`; original `PaymentTransaction` vẫn là charge record bất biến và refund phải tham chiếu nó.
- Sau khi refund được duyệt và đạt điều kiện hoàn tất, settlement được ghi có vào Ví KomiBook đúng một lần, không gọi cổng thanh toán ngoài trong luồng hiện hành.
- Trạng thái order/payment/refund chỉ đổi sau khi wallet ledger và các reversal tồn kho, điểm, earning được ghi idempotent trong transaction phù hợp.
- VNPAY Sandbox vẫn không được gọi bằng credential thật. Dữ liệu/provider lịch sử được giữ để audit, không bị viết lại thành giao dịch ví.

### D4 — Vendor approval và legal/commercial verification là hai trust domain độc lập

- GET/read endpoint không tạo hoặc cập nhật Organization, membership, relationship hay agreement.
- Duyệt vendor không tự động biến Organization hoặc relationship thành `verified`/`demo_accepted`.
- `verified` bắt buộc có `verified_at`, actor/reason/audit hợp lệ. `demo_accepted` bắt buộc `is_demo=true`, không được trình bày như legal verification.
- Agreement `submitted` không tạo verified relationship và không được dùng để mở quyền publish/supply-chain.
- Hồ sơ live phải có evidence theo contract; evidence, tax code đầy đủ, địa chỉ kho và dữ liệu thanh toán không được công khai.
- Mọi mutation phải có negative authorization cho vendor chéo, organization chéo và listing chéo.

### D5 — Backfill tồn kho phải bảo toàn sự thật dữ liệu

- Quantity 0 vẫn là 0; không dùng `max(1, quantity)` và không tạo khả năng bán hàng từ bản ghi hết tồn.
- Backfill phải deterministic, idempotent, chunked và warehouse-specific; không chọn stock đầu tiên chỉ bằng `book_id`.
- Migration dữ liệu không gọi application service có thể tự tạo Vendor/Profile/Warehouse hoặc phát sinh side effect ngoài manifest.
- Bản ghi thiếu owner/vendor/warehouse phải được phân loại vào manifest để review, không bỏ qua im lặng.
- `down()` phải có chiến lược rõ ràng. Nếu dữ liệu không thể đảo an toàn, migration phải được ghi là forward-only có manifest/reconciliation thay vì gọi đó là rollback hoàn chỉnh.

### D6 — Giữ tỷ lệ 1 KomiPoint trên 10.000 VND

- Tỷ lệ hiện hành cho chuỗi corrective batch là `floor(order snapshot total / 10000)`.
- Không thay đổi thành 1/1.000 nếu chưa có ADR kinh tế, effective date, backfill/reconciliation và phê duyệt riêng.
- Service, Membership API, RAG/help copy, frontend và test phải dùng cùng một contract.
- Điểm chỉ được ghi sau completion hợp lệ, qua append-only ledger và idempotency key.

### D7 — AI provider phải được allowlist, giới hạn chi phí và bảo vệ dữ liệu

- External AI mặc định tắt nếu thiếu explicit enablement và credential; không có fallback model hard-code ngoài allowlist cấu hình.
- Rate limit không được nhân số request qua một danh sách model không được duyệt. Mỗi request phải có giới hạn attempt, timeout và telemetry không chứa nội dung nhạy cảm.
- Attachment private và lịch sử chat chỉ được gửi ra provider khi có policy/consent rõ; không mặc định gửi toàn bộ ảnh base64 hoặc lịch sử đa lượt.
- RAG no-match trả trạng thái no-match rõ ràng, không lấy record mới nhất để tạo ngữ cảnh giả.
- Session queued/assigned không tự mất ownership. Sau phản hồi của nhân viên, phiên `waiting_customer + human` có deadline 30 phút gắn với đúng tin nhắn phản hồi; scheduler chỉ chuyển về `open + ai` khi tuple, owner, assignee, vendor và anchor vẫn hợp lệ, tăng `lock_version`, ghi actor hệ thống/policy/audit và không tự gọi Gemini. Khách hàng có thể phản hồi hoặc chọn tiếp tục chờ để hủy/gia hạn deadline.

### D8 — Pricing/reporting đọc từ nguồn chuẩn, không suy đoán bằng chuỗi hoặc tỷ lệ giả

- Coupon dùng `coupon_type` đã chuẩn hóa; không suy loại bằng việc code chứa `SHIP`.
- Ngưỡng freeship và phí vận chuyển hiện tại được giữ tương thích trong corrective batch, nhưng phải có một nguồn policy duy nhất và được snapshot vào checkout/order.
- Coupon không tăng `used_count` nếu không tạo discount áp dụng thực tế.
- Finance report đọc immutable checkout/earning/refund snapshots. Không tự bịa commission 10% khi thiếu ledger.
- GET/export báo cáo là read-only. Refresh/rebuild snapshot là command hoặc mutation endpoint riêng, có actor và audit.

## 3. Contract-to-test map

| Quyết định | Test hiện có phải giữ | Negative/closure test bắt buộc | Batch |
|---|---|---|---|
| D1 recent-auth | `Phase4ReviewReadingAccountTrustTest`, `WalletWithdrawalTest`, `DemoEvidenceModeTest` | Token không session bị chặn; session stale bị chặn; proof hợp lệ pass trên từng nhóm endpoint nhạy cảm | 1 |
| D2 buyer receipt | `Phase9UsedBookFulfillmentWalletTest`, `OrderCompletionLedgerTest` | Seller không thể complete/release wallet; buyer chéo bị 403; failed/unknown state không complete; retry idempotent | 1 |
| D3 paid refund | `Phase4ReturnRefundInvoiceTest`, `CheckoutSessionLifecycleTest`, `OrderCancellationTest` | Paid cancel bị 422 và không đổi state; approved refund credit wallet đúng một lần; original payment immutable; reversal failure rollback toàn bộ | 2 |
| D4 trust domains | `Phase8IdentityAndCommercialPartiesTest`, `DemoEvidenceModeTest`, `DistributionAgreementDemoTest`, `PublicOrganizationShowcaseTest` | GET không ghi DB; vendor approval không legal-verify; submitted agreement không mở publish; cross-tenant mutation bị chặn | 3 |
| D5 inventory truth | `Phase5WarehousePrivacyTest`, `Phase8UsedBookCheckoutPaymentTest`, `Phase7UsedBookAdminModerationTest` | Zero quantity giữ 0; multiwarehouse không cập nhật chéo; missing owner vào manifest; rerun không tạo trùng | 4 |
| D6 KomiPoint | `OrderCompletionLedgerTest`, membership/profile contract tests | 9.999/10.000 boundary; retry không cộng trùng; RAG/API copy thống nhất; corrupted projection fail closed | 5 |
| D7 AI boundary | `ChatSupportTest`, frontend chat tests | Model ngoài allowlist không được gọi; 429 có giới hạn; private attachment không gửi khi thiếu consent; no-match không fallback; assigned session không tự mất owner | 6 |
| D8 pricing/reporting | `Phase10FinancialReconciliationTest`, commerce fee/coupon/checkout tests | Code chứa `SHIP` không tự thành shipping coupon; zero-discount không tăng usage; GET/export không write; thiếu ledger không bịa commission | 5 |

Test đang pass nhưng mã hóa behavior trái decision register phải được sửa có chủ đích; không được dùng việc test pass làm lý do giữ behavior sai.

## 4. Thứ tự và ownership của các batch

1. Batch 1: recent-auth, order/used-book authority và test trực tiếp.
2. Batch 2: cancellation, return/refund, wallet settlement, checkout state và migration `draft` liên quan.
3. Batch 3: Organization/commercial-party trust và authorization.
4. Batch 4: used-book inventory, warehouse và backfill migration.
5. Batch 5: points, coupon, finance/reporting truth.
6. Batch 6: Gemini/RAG, privacy/cost và AI-to-human lifecycle.
7. Batch 7: frontend correctness/accessibility theo module tuần tự.
8. Batch 8: publisher tooling, tài liệu, formatter và full acceptance.

Batch có file chung hoặc phụ thuộc state machine phải chạy tuần tự. Mỗi worker chỉ sở hữu file được liệt kê trong packet của batch và phải thích nghi với thay đổi hiện hữu thay vì hoàn nguyên chúng.

## 5. Gate và rollback contract

Mỗi batch phải có:

1. `git status --short`, manifest file ownership và diff baseline trước khi sửa.
2. Targeted tests cho positive, negative authorization, idempotency và rollback/failure injection phù hợp.
3. PHP syntax/Pint hoặc ESLint/Oxlint/Vitest trên phạm vi đã đổi.
4. Primary task đọc toàn bộ diff thực tế và xác nhận không có file ngoài scope bị đổi.
5. Với native implementation: fresh Sol review sau khi primary verification đạt.
6. Nếu gate fail: dừng batch, gửi correction về đúng worker, rồi chạy lại cùng gate; không chuyển sang batch phụ thuộc.

Rollback trong chuỗi sửa lỗi là reverse patch chỉ giới hạn file ownership của batch hoặc sửa tiến về phía trước trong cùng batch. Không dùng Git reset/checkout/stash/clean và không xóa dữ liệu người dùng.

## 6. Tiêu chí hoàn tất Batch 0

- Decision register giải quyết được xung đột giữa cancellation workflow và wallet settlement.
- Mỗi P0/P1 nghiệp vụ có contract và test closure tương ứng.
- Batch order, ownership, gate, correction và rollback boundary đã rõ.
- Chỉ tài liệu này được thêm bởi Batch 0; runtime, migration và test không thay đổi.
