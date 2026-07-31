# Giai đoạn 7C.2 — Kế hoạch giao diện chiến dịch thông báo và prompt Antigravity

Ngày: 29/07/2026  
Trạng thái ban đầu: in-progress

## Mục tiêu

- Đồng bộ trang danh sách, tạo chiến dịch và báo cáo theo semantic token KomiBook: navy chủ đạo, crimson cho hành động thương hiệu, commerce cho thành công, warning/error đúng nghĩa.
- Bỏ phối màu Indigo/Purple rời rạc, dark-class không thuộc shell hiện tại và hiệu ứng gây nhiễu.
- Chuyển danh sách từ table-only sang card responsive trên màn hình hẹp nhưng giữ bảng ở desktop.
- Chuẩn hóa touch target, focus, label form, loading/error/empty state và reduced motion.
- Giữ telemetry trung thực: không biến `null` thành 0%; số tổng không được suy ra từ một trang pagination.

## Phạm vi

- Ba view Admin `NotificationCampaignsView.vue`, `NotificationCreateView.vue`, `NotificationAnalyticsView.vue`.
- Chỉ điều chỉnh controller khi cần contract summary phân trang chính xác; không đổi dispatch lifecycle.
- Vitest/feature test chuyên biệt, tài liệu gate và ledger.

Không thay audience policy, queue/job, lịch gửi, idempotency, schema, dữ liệu hay route; không commit/push/deploy.

## Gate

- List/create/analytics có token semantic, mobile stacking, action 44×44, label gắn control và state trung thực.
- Summary phải dùng aggregate API thật thay vì số lượng trang hiện tại.
- Regression Phase 3/4 campaign dispatch; Vitest source/behavior; lint/Pint/build/diff.
- Visual/UAT tại 375/768/1024/1440 ở Gate 7C.

## Prompt Antigravity dự phòng

> Bảo toàn dirty worktree. Batch 7C.2 sửa ba view notification Admin, controller summary chỉ khi cần và test chuyên biệt. Đồng bộ semantic token KomiBook (`primary`, `secondary`, `commerce`, `warning`, `error`, surface/on-surface/outline); bỏ palette indigo/purple/slate rời rạc và gradient trang trí. Danh sách phải đọc được trên mobile (card hoặc responsive table), desktop giữ mật độ hợp lý; mọi action 44×44, form label/control rõ, focus/reduced-motion, loading/error/empty trung thực. Không biến telemetry null thành 0; tổng campaign/sent phải là aggregate thật, không đếm một trang pagination. Giữ nguyên audience, dispatch/retry/idempotency/route/schema. Không commit/push/deploy. Chạy regression campaign, Vitest, lint/Pint/build/diff và báo changed-files.
