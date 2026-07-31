# Giai đoạn 8D — Chứng từ kho và ledger

Ngày: 2026-07-30  
Trạng thái: Accepted-local

## Kế hoạch và prompt từng source batch

Ràng buộc chung: mọi mutation nằm trong transaction, khóa dòng tồn, operation key duy nhất, event/ledger bất biến; không cho tồn âm; không đụng database thật.

- **8D.1:** phiếu nhập. Prompt: draft → submitted → approved → posted; receipt tăng tồn kho đích và đồng bộ shadow stock của Book; gate bằng idempotency test.
- **8D.2:** phiếu xuất. Prompt: kiểm tra tồn trước khi ghi sổ, rollback toàn bộ nếu thiếu; gate bằng oversell test.
- **8D.3:** điều chuyển. Prompt: một chứng từ tạo hai ledger cân bằng và yêu cầu quyền ở cả kho nguồn/đích; gate bằng cross-assignment test.
- **8D.4:** kiểm kê. Prompt: lưu expected/actual, chỉ adjustment khi posted, bắt buộc audit; gate bằng state-machine test.
- **8D.5:** tích hợp UI/API và migration. Prompt: Vendor có quyền duyệt, Manager chỉ theo capability; endpoint Vendor và Manager phải tách đúng; gate bằng API contract, Pint, migration down/up và regression.

## Kết quả

- Có `warehouse_documents`, lines, events và immutable stock ledger.
- Receipt idempotent; dispatch thiếu tồn rollback; transfer yêu cầu quyền hai kho.
- Giao diện dùng đúng `/api/vendor/warehouse-documents` và `/api/warehouse-manager/documents`.
- Migration Phase 8 rollback và áp lại thành công trên SQLite tạm.

## Gate

- `Phase8WarehouseDocumentsTest`: 3 tests, 23 assertions, pass.
- `Phase8MigrationReversibilityTest`: 1 test, 11 assertions, pass.
- Pint targeted: pass.

