# Giai đoạn 6H.3 — kế hoạch Nội dung và Vận hành Admin

Ngày lập: 2026-07-29

## Phạm vi

- `frontend/src/views/admin/ArticlesView.vue`
- `frontend/src/views/admin/NotificationCampaignsView.vue`
- `frontend/src/views/admin/NotificationCreateView.vue`
- `frontend/src/views/admin/NotificationAnalyticsView.vue`
- `frontend/src/views/admin/SystemConfigView.vue`
- `frontend/src/views/admin/HelpDeskView.vue`
- `frontend/src/views/admin/TicketDetailView.vue`

## Bất biến

- Giữ nguyên CMS review/publish lifecycle, consented notification audiences,
  telemetry truth, system configuration scope và support workflow.
- Browser smoke chỉ đọc; không tạo/publish/xóa article, gửi campaign, thay config,
  trả lời ticket, đổi trạng thái/ưu tiên hoặc tải tệp.
- Không hiển thị analytics giả khi thiếu nguồn telemetry.
- Form, filter, table/chart có semantic label/state; target tối thiểu 44px; dùng
  được ở `375 / 768 / 1024 / 1440`.
- Không sửa backend, commit, push hoặc deploy.

## Prompt dự phòng Antigravity

> Chỉ sửa Articles, NotificationCampaigns/Create/Analytics, SystemConfig,
> HelpDesk và TicketDetail Admin views. Giữ API/state machine CMS, consent,
> telemetry truth, config scope và support workflow. Cải thiện responsive bốn
> viewport, table/card hoặc labeled scroll, semantic loading/error/empty, form
> labels, keyboard focus và target 44px. Browser smoke chỉ đọc; không tạo/gửi/
> publish/xóa/sửa config/reply/update/download. Không sửa backend, commit hay
> push. Báo cáo file thay đổi và chạy frontend test/build cùng CMS/campaign/
> support/operational-truth regression.

## Gate

1. Audit trực tiếp bảy nhóm route ở bốn viewport.
2. Frontend tests và production build đạt.
3. Backend CMS/Campaign/Support/Operational Truth regression đạt.
4. `git diff --check` đạt.
5. Cập nhật page ledger và acceptance report.
