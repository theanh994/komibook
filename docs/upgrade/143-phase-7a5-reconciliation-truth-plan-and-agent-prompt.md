# Giai đoạn 7A.5 — Kế hoạch dữ liệu thật cho Đối soát và prompt Antigravity

Ngày: 29/07/2026  
Trạng thái ban đầu: in-progress

## Mục tiêu

- Trang Đối soát chỉ hiển thị payout, ledger và transition lấy từ database/API; không suy diễn mọi đơn đã thanh toán là “chưa đối soát”.
- Chỉ số cần kiểm tra được tính từ bất nhất thực tế của payout: thiếu ledger gốc, thiếu transition, trạng thái hiện tại lệch transition cuối, hoặc trạng thái kết thúc thiếu ledger/bằng chứng tương ứng.
- Mỗi dòng cho biết trạng thái toàn vẹn và dấu vết gần nhất để quản trị viên phân biệt payout bình thường với payout cần rà soát.
- Pagination, lọc trạng thái và thao tác chuyển trạng thái tiếp tục dùng contract backend thật.

## Phạm vi

- `PayoutRequest` relations phục vụ audit.
- `Admin/ReconciliationController::index` và helper truy vấn toàn vẹn.
- `ReconciliationView.vue` cho trạng thái đối soát, nhãn KPI đúng nghĩa và phân trang.
- Feature test/Vitest chuyên biệt và tài liệu gate.

Không thay schema, không tạo dữ liệu demo trong runtime, không sửa số dư, không tự động chuyển payout, không truy cập database thật; không commit/push/deploy.

## Invariants

- Payout và số tiền luôn lấy từ `payout_requests`; lịch sử lấy từ `payout_ledger_entries` và `payout_transitions`.
- Payout pending/approved/processing hợp lệ không bị gọi là bất nhất chỉ vì chưa hoàn tất.
- Payout completed phải có ledger `completed`, mã tham chiếu và bằng chứng; rejected phải có ledger hoàn dự phòng.
- Payout phải có ledger nguồn (`reservation` hoặc `legacy_import`) và transition hiện tại khớp trạng thái mới nhất.
- API không sửa dữ liệu trong request GET và không che giấu lỗi bằng số liệu dựng sẵn.

## Gate

- Backend feature: payout lành mạnh, payout bất nhất, KPI, audit summary, filter và pagination.
- Frontend unit: bind contract API, trạng thái rỗng, nhãn toàn vẹn và pagination.
- Regression payout Phase 4A.3; Pint, ESLint, build và `git diff --check`.
- Browser smoke khi local stack có thể truy cập và có phiên admin phù hợp.

## Prompt Antigravity dự phòng

> Bảo toàn dirty worktree trong checkout chính. Sửa batch 7A.5 để trang Admin Đối soát dùng hoàn toàn dữ liệu `payout_requests`, `payout_ledger_entries`, `payout_transitions`. Không được dùng số liệu demo hoặc coi toàn bộ đơn completed/paid là chưa đối soát. Tính mismatch thật: thiếu ledger nguồn, thiếu transition, transition cuối lệch status, completed thiếu completed-ledger/reference/evidence, rejected thiếu release-ledger. Trả audit summary theo từng payout, hiển thị trạng thái toàn vẹn và pagination trên Vue. Không thay schema/số dư/trạng thái trong GET, không seed dữ liệu runtime, không commit/push/deploy. Thêm backend feature test và Vitest, chạy đầy đủ gate và báo changed-files.
