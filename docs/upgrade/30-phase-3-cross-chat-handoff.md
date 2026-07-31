# KomiBook — Handoff sang task mới trước Giai đoạn 3

## 1. Mục đích

Tài liệu này là điểm bắt đầu bắt buộc cho task Codex mới tiếp nhận KomiBook tại:

`C:\Projects\DoAnTotNGhiep_komibook`

Không làm việc trong checkout Herd cũ. Không bắt đầu sửa mã Giai đoạn 3 trước khi hoàn tất preflight read-only, đối chiếu code hiện tại và có phê duyệt phạm vi Giai đoạn 3A của người dùng.

Thứ tự đọc:

1. Tài liệu này.
2. `docs/upgrade/04-cross-account-continuation-handoff.md`.
3. `docs/upgrade/01-api-contract-matrix.md` và `docs/upgrade/02-priority-backlog.md`.
4. Khi có bất kỳ công việc production nào, ưu tiên `docs/upgrade/windows_production_deployment_plan.md`.

Không tin số liệu hoặc issue lịch sử nếu chưa kiểm tra code và trạng thái hiện tại.

## 2. Bằng chứng trực tiếp tại thời điểm bàn giao

Thời điểm kiểm tra: `2026-07-26 01:33:51 +07:00`.

- CWD: `C:\Projects\DoAnTotNGhiep_komibook`.
- Branch: `master`.
- `HEAD`: `f903430bd835cc22f45df28818f0adf2af60ea15`.
- `origin/master`: `f903430bd835cc22f45df28818f0adf2af60ea15`.
- `git diff --check`: sạch.
- Toàn bộ thay đổi Giai đoạn 2 vẫn nằm trong working tree, gồm nhiều file modified/untracked; chưa commit hoặc push.
- Không được reset, checkout, stash, clean hoặc xóa các thay đổi này.

### Cổng kết thúc Giai đoạn 2

- Backend: `226 tests`, `1021 assertions`, pass.
- Frontend Vitest: `11 tests`, pass.
- Frontend production build: pass.
- Batch 2F.1 critical journey: `3 tests`, `97 assertions`, pass.
- Bộ test tích hợp liên quan 2F.1: `108 tests`, `549 assertions`, pass.
- Pint trên file 2F.1 và các file thuộc từng batch đã kiểm tra: pass.
- Pint toàn repository vẫn báo nợ định dạng lịch sử ở nhiều file ngoài Giai đoạn 2. Không được tự động format toàn repository.
- `git diff --check`: pass.
- Cảnh báo không chặn: PHP báo OpenSSL được nạp hai lần; frontend báo một số chunk lớn hơn 500 kB.

### Migration rehearsal

Đã thực hiện trên một SQLite database tạm trong workspace:

1. `migrate:fresh` toàn schema: pass.
2. Rollback đúng năm migration Giai đoạn 2: pass.
3. Áp dụng lại năm migration: pass.
4. `migrate:status`: tất cả migration ở trạng thái `Ran`.
5. Database tạm đã được xóa.

Không có database production hoặc dịch vụ production nào bị tác động.

## 3. Trạng thái Giai đoạn 2

Giai đoạn 2 đã đạt cổng mã nguồn, integration/E2E ở cấp HTTP/API và migration rehearsal:

- 2A: ADR state machine và invariant.
- 2B: checkout session/payment transaction schema và write path.
- 2C: VNPAY gateway, initiation, IPN/return và idempotency.
- 2D: inventory reservation, checkout/fulfillment cutover và lifecycle hết hạn/hủy.
- 2E: order completion, loyalty/vendor ledger và side-effect outbox.
- 2F: critical journey integration tests và migration rehearsal.

Đây không phải bằng chứng deployment production. Browser smoke cho mã Giai đoạn 2 chỉ có ý nghĩa sau khi có staging hoặc deployment được phê duyệt riêng.

## 4. Giai đoạn 3 sẽ có gì

Mục tiêu Giai đoạn 3 là sửa các luồng frontend–backend đang lệch contract hoặc đang che lỗi bằng mock/fallback. Không thiết kế lại Order/Payment/Inventory lần nữa và không kéo nghiệp vụ Giai đoạn 4 vào sớm.

### 3A — Re-audit contract và lập bản đồ khoảng trống

Codex thực hiện read-only:

- Đối chiếu `backend/routes/api.php`, controller/resource với `frontend/src/router/index.js`, store, service và view.
- Kiểm tra lại từng issue `FE-01` đến `FE-05` và phần contract của `EBOOK-01`; đánh dấu issue còn thật, đã được sửa hoặc tài liệu đã stale.
- Lập inventory các màn hình dùng mock, hardcode hoặc biến API failure thành dữ liệu thành công giả.
- Phân loại theo luồng và dependency để tạo các batch nguồn lớn nhưng mạch lạc.
- Chưa giao Antigravity sửa mã cho đến khi người dùng duyệt kế hoạch 3A.

### 3B — Endpoint, ownership và router quan trọng

Phạm vi dự kiến:

- Ebook ownership endpoint và quyền truy cập.
- Chuẩn hóa reader route/params `orderId` và `bookId`.
- Điều hướng từ Book Detail và My Library.
- Customer order detail và Invoice Print contract.
- Vendor UI dùng `/api/vendor/books`, không dùng catalog public.
- Re-audit lỗi runtime/biến chưa khai báo trong LoginView.

Không mặc định các issue lịch sử vẫn còn; phải tái hiện và kiểm tra code trước.

### 3C — Loại bỏ mock/fallback thành công giả

Chia theo nhóm màn hình, không làm tất cả trong một prompt:

- Invoice.
- Chapter editor và multi-device preview.
- User detail.
- Notification analytics.
- Google/OTP production fallback.
- Blog/news hardcode.
- Các màn hình khác đang biến API error thành dữ liệu thành công giả.

Khi API lỗi, UI phải có error state, retry và empty state thật. Nếu backend chưa có endpoint, báo blocker/contract gap; không dựng dữ liệu giả để che lỗi.

### 3D — Chuẩn hóa API contract tăng dần

- Chuẩn response thành công/lỗi, HTTP status và pagination theo từng domain.
- Bổ sung adapter/versioning nếu cần để tránh phá toàn bộ frontend.
- Cập nhật OpenAPI và contract test theo từng endpoint/batch.
- Không thay toàn bộ API trong một batch.

### 3E — Integration/E2E và cổng hoàn thành

Các luồng phải dùng dữ liệu/API thật:

- Đăng nhập.
- Xem/tìm sách.
- Giỏ hàng, checkout và payment.
- Xem order/invoice.
- Thư viện và ebook reader.
- Vendor xem/tạo/sửa sách.
- Author editor/preview ở mức API hiện có.
- Không còn mock che lỗi trong phạm vi đã duyệt.

Cuối Giai đoạn 3 chạy backend regression, frontend tests/build, contract tests và browser smoke trên môi trường được phép.

## 5. Ranh giới với Giai đoạn 4

Không đưa các mục sau vào Giai đoạn 3 nếu chưa có phê duyệt mở rộng:

- Return request và refund transaction.
- VNPAY refund.
- Hoàn tồn kho/điểm/ledger do refund.
- Invoice snapshot bất biến đầy đủ về thuế, phí và thông tin pháp lý.
- Review verified-purchase/moderation hoàn chỉnh.
- Các hệ sinh thái tác giả/vendor khác của Giai đoạn 4.

Giai đoạn 3 chỉ sửa contract và UI/API hiện có cho order/invoice; nghiệp vụ invoice/refund đầy đủ vẫn thuộc Giai đoạn 4.

## 6. Quy tắc phối hợp Antigravity

- Antigravity chỉ tạo hoặc sửa mã nguồn.
- Codex tự làm preflight, audit, kế hoạch, tài liệu, prompt, chạy test, migration rehearsal, browser smoke và nghiệm thu.
- Prompt Antigravity không cần nhắc lại workspace path.
- Mỗi prompt phải có mục tiêu, allowed files, forbidden files, invariant, acceptance gates và câu “không commit hoặc push”.
- Ưu tiên một batch lớn mạch lạc; chỉ dùng vài correction nhỏ sau nghiệm thu.
- Codex có thể sửa trực tiếp khi lỗi nằm trong một file dù có nhiều vấn đề, hoặc tối đa ba file nếu lỗi chỉ giới hạn trong hai phương thức; phải nằm trong objective đã duyệt, không đổi product/design/security/business rule và phải chạy lại gate.
- Nếu vượt giới hạn trên hoặc cần quyết định thiết kế, trả lại source work cho Antigravity hoặc xin người dùng phê duyệt.

## 7. Quy tắc an toàn

- Không commit, push, reset, checkout, stash hoặc clean khi chưa được phê duyệt rõ phạm vi.
- Không xóa repository cũ, active release, rollback hoặc backup.
- Không hiển thị `.env`, token, credential hoặc service PathName chứa token.
- Không đổi Cloudflare/DNS, không reboot, không rollback và không dừng database/dịch vụ.
- DBngin/MySQL vẫn là dependency production được người dùng chấp nhận.
- Không suy luận production readiness chỉ từ test local.
- Mọi kết luận phải tách rõ bằng chứng trực tiếp, lịch sử bàn giao và suy luận/khuyến nghị.

## 8. Việc đầu tiên trong task mới

1. Chỉ chạy preflight read-only: thời gian, CWD, branch/HEAD/origin/status/diff-check.
2. Xác nhận tài liệu handoff này tồn tại và workspace là đường dẫn mới.
3. Re-audit read-only Giai đoạn 3A theo code hiện tại.
4. Báo những issue thực sự còn tồn tại, dependency và đề xuất batch đầu tiên.
5. Xin người dùng phê duyệt trước khi giao bất kỳ source-code task Giai đoạn 3 nào cho Antigravity.

