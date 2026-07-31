# Kế hoạch batch 7C.3 — Commission và phí trong Cấu hình hệ thống

Ngày: 29/07/2026  
Checkout: `C:\Projects\DoAnTotNGhiep_komibook`  
ADR bắt buộc: `docs/upgrade/157-phase-7c3-commerce-fee-adr.md` — đã duyệt

## 1. Mục tiêu

- Đưa Commission và phí dịch vụ thành tab thực sự trong trang Cấu hình hệ thống.
- Giữ `/admin/fee-schedules` làm deep link tương thích và chuyển đúng tới tab phí.
- Giải thích đầy đủ dòng tiền: khách trả, doanh thu gộp người bán, commission, phí dịch vụ, người bán nhận ròng, nền tảng nhận trước thuế và trạng thái thuế.
- Giữ lịch phí append-only, thời điểm hiệu lực, snapshot checkout và các trường API cũ.

## 2. Phạm vi source

Được sửa:

- `backend/app/Services/CommerceFeeService.php`
- `backend/app/Http/Controllers/Api/Admin/CommerceFeeScheduleController.php`
- `backend/tests/Feature/Phase4CommerceFeeHistoryTest.php`
- `frontend/src/views/admin/FeeSchedulesView.vue`
- `frontend/src/views/admin/SystemConfigView.vue`
- `frontend/src/layouts/AdminLayout.vue`
- `frontend/src/router/index.js`
- `frontend/src/__tests__/phase4_fee_history.spec.js`
- test 7C.3 mới nếu cần.

Không được sửa migration, dữ liệu thật, checkout write path, earning, payout, refund, Author, Vendor, warehouse hoặc production.

## 3. Bất biến

- Khách chịu service fee; người bán chịu commission.
- `seller_gross` là giá trị sau giảm giá được đưa vào phép tính.
- Tiền VND làm tròn half-up độc lập theo từng khoản.
- Thuế vẫn bằng 0 và phải ghi rõ chưa cấu hình chính sách thuế.
- Lịch mới chỉ có hiệu lực từ `effective_at`; không update/delete lịch cũ.
- API giữ `base_amount` và `total_amount` để tương thích.
- Không tạo thêm phụ thuộc vào tác nhân Author; kiến trúc Quản lý kho thuộc Giai đoạn 8.

## 4. Acceptance gate

- Backend feature test chứng minh công thức, half-up, tên trường mới, trường tương thích và preview không ghi dữ liệu.
- Checkout snapshot regression vẫn xanh.
- Frontend contract test chứng minh tab phí, deep link, nội dung dòng tiền và menu không còn mục phí rời.
- Build frontend thành công.
- Browser UAT Admin ở 375, 768, 1024 và 1440 px; form, lịch sử, trạng thái tải/lỗi và keyboard focus hoạt động.
- `git diff --check` đạt.

## 5. Prompt dự phòng cho Antigravity

> Chỉ triển khai batch 7C.3 theo `docs/upgrade/159-phase-7c3-commerce-fee-plan-and-agent-prompt.md` và ADR 157 đã duyệt. Chỉ sửa các file trong danh sách cho phép. Giữ lịch phí append-only, snapshot checkout bất biến, service fee do khách chịu, commission do người bán chịu, VND half-up, thuế 0/chưa cấu hình và các trường API cũ. Nhúng quản lý phí vào System Config, giữ deep link cũ, bỏ menu phí rời. Không sửa migration, dữ liệu thật, Author/Vendor/warehouse, không commit/push/deploy. Báo cáo file đã đổi và toàn bộ gate đã chạy để Codex nghiệm thu độc lập.

Antigravity hiện hết quota; người dùng đã giao Codex trực tiếp triển khai nhưng tài liệu giao việc vẫn được duy trì theo quy trình dự án.
