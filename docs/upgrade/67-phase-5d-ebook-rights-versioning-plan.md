# KomiBook Phase 5D — Ebook terms, versions và entitlement

Ngày: 2026-07-29

## Mục tiêu

- Checkout có ebook bắt buộc xác nhận điều khoản nội dung số và lưu snapshot.
- Ebook không return; lỗi thanh toán/cấp quyền dùng luồng correction riêng.
- Mỗi lần xuất bản tạo version bất biến.
- Người mua giữ version lúc mua và được mở mọi version mới, không ghi đè bản cũ.

## Prompt giao Antigravity

> Triển khai 5D theo ADR 62. Thêm policy/consent snapshot ở order item, immutable ebook version và entitlement. Hook publication tạo version, checkout chụp version hiện hành, fulfillment cấp entitlement. Library phải liệt kê version cũ/mới mà không lộ file path; signed link nhận version cụ thể sau khi kiểm tra entitlement. UI checkout phải hiện điều khoản và bắt buộc tick khi có ebook. Không biến financial correction thành return ebook. Không commit/push/deploy.

Antigravity hết quota; Codex trực tiếp thực hiện.

## Gate

- Tests checkout không consent bị chặn, snapshot bất biến.
- Tests purchaser giữ version cũ và nhận version mới.
- Ebook return vẫn bị chặn.
- Frontend build và focused checkout tests.
