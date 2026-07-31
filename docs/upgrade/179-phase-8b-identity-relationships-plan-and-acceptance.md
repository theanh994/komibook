# Giai đoạn 8B — Organization, quan hệ và định danh

Ngày: 2026-07-30  
Trạng thái: Accepted-local

## Kế hoạch và prompt từng source batch

Ràng buộc chung cho 8B: chỉ sửa schema/model/service/controller/resource/route/test và wizard Nhà bán liên quan; không sửa dữ liệu thật, không tự backfill `vendor_id`, không commit/push/deploy.

- **8B.1:** tạo `organizations`, trường public/private, trạng thái và audit. Prompt: triển khai migration `up/down`, model và public resource; không trả mã số pháp lý, evidence, địa chỉ kho; gate bằng migration và negative disclosure test.
- **8B.2:** thêm `business_model` và organization chính cho Vendor, bổ sung bước chọn Direct Publisher/Bookstore/Distributor/Mixed. Prompt: giữ tương thích hồ sơ cũ, không tự xác minh; gate bằng onboarding regression và frontend build.
- **8B.3:** tạo quan hệ Vendor–Organization cùng event bất biến. Prompt: mọi chuyển trạng thái phải có actor, reason, operation key và kiểm tra ngày hiệu lực; Vendor không tự duyệt; gate bằng tenant/transition/idempotency tests.
- **8B.4:** tạo Warehouse Manager assignment theo user–vendor–warehouse. Prompt: dùng capability assignment, không thêm role enum mới; invitation/accept/suspend/revoke có audit; gate bằng self-accept và cross-tenant tests.
- **8B.5:** áp policy/capability lên API. Prompt: Warehouse Manager không được xem tài chính/giá/organization; assignment chỉ cấp đúng kho và capability; gate bằng negative authorization.

## Kết quả

- Có Organization đa loại, quan hệ đối tác có vòng đời và lịch sử.
- Vendor có mô hình hoạt động và organization chính.
- Warehouse Manager là capability theo assignment, không phá enum role cũ.
- API public chỉ trả trường an toàn; Vendor và assignment chéo bị từ chối.

## Gate

`Phase8IdentityAndCommercialPartiesTest`: 3 tests, 18 assertions, pass.

