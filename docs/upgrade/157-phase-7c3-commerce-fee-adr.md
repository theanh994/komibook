# ADR Giai đoạn 7C.3 — Commission, phí dịch vụ, thuế và tính bất biến

Ngày: 29/07/2026  
Trạng thái: approved — người dùng phê duyệt ngày 29/07/2026

## 1. Bằng chứng re-audit hiện tại

1. `CommerceFeeService` đang mặc định commission 10%, service fee 0% khi chưa có lịch cấu hình.
2. Cả hai khoản được tính trên giá trị sau coupon và ưu đãi thành viên, theo từng đơn Vendor trong checkout nhiều Vendor.
3. Service fee đang cộng vào tổng khách trả; commission không cộng vào hóa đơn mà được khấu trừ khi tạo earning cho người bán.
4. Mỗi khoản làm tròn độc lập về VND nguyên bằng `round` của PHP; với số dương, phần lẻ `.5` làm tròn lên.
5. Thuế trong invoice snapshot đang cố định `tax_rate = 0`, `tax_amount = 0`; chưa có tax engine hay căn cứ pháp lý theo loại hàng/người bán.
6. Lịch phí append-only; checkout snapshot schedule ID, rate và amount. Thay lịch về sau không viết lại đơn cũ.
7. Hoàn tiền sách cũ đang phân bổ tổng invoice theo tỷ lệ giá trị dòng, vì vậy phân bổ cả service fee đã snapshot; reversal commission dựa trên snapshot earning.
8. Trang cấu hình hệ thống đã có link sang trang phí, nhưng menu vẫn tách thành mục riêng và trang phí chưa giải thích dòng tiền.

## 2. Quyết định đề xuất

### D1 — Người chịu phí

- **Khách hàng chịu service fee** như phụ phí nền tảng hiển thị riêng.
- **Người bán chịu commission** qua khấu trừ doanh thu.
- Không cộng commission vào giá khách trả và không trừ service fee lần hai từ người bán.

Công thức cho mỗi đơn Vendor, sau giảm giá:

```text
seller_gross = max(0, subtotal - coupon_discount - membership_discount)
service_fee = round_half_up(seller_gross × service_fee_rate / 100)
commission = round_half_up(seller_gross × commission_rate / 100)
customer_pays = seller_gross + service_fee + tax
seller_net = seller_gross - commission
platform_net_before_tax = service_fee + commission
```

### D2 — Thuế

- 7C.3 **không tự đặt thuế**: giữ `tax_rate = 0`, `tax_amount = 0` và hiển thị “Chưa cấu hình chính sách thuế”.
- Tax engine là ADR/schema riêng sau khi xác định nghĩa vụ theo pháp nhân, loại sách và hóa đơn; không dùng `service_fee_rate` thay thuế.

### D3 — Làm tròn và nhiều Vendor

- Mọi tiền tệ là VND nguyên, không lưu phần lẻ.
- Làm tròn half-up cho từng khoản của từng đơn Vendor; tổng checkout là tổng snapshot đơn Vendor. Điều này bảo toàn ledger và tránh phân bổ chéo tenant.

### D4 — Hiệu lực và lịch sử

- Schedule chỉ có hiệu lực từ `effective_at`; không hồi tố.
- Snapshot tại lúc checkout là bất biến và là nguồn sự thật cho invoice, earning, payout, return/refund.
- Không update/delete lịch cũ; thay đổi luôn tạo phiên bản mới có actor, reason, operation key.

### D5 — Hoàn tiền

- Trong phạm vi hiện tại, sách cũ đủ điều kiện được hoàn theo tỷ lệ trên tổng invoice snapshot; commission reversal dùng earning snapshot tương ứng.
- UI 7C.3 phải giải thích đây là hành vi hiện tại. Mọi thay đổi “service fee không hoàn” hoặc phí cố định cần ADR riêng vì ảnh hưởng số tiền khách/người bán.

## 3. Hệ quả triển khai sau phê duyệt

- Đưa Commission & phí thành tab/khối thực sự trong `SystemConfigView`, giữ `/admin/fee-schedules` làm deep link tương thích.
- Bỏ mục menu rời hoặc chuyển nó thành link con của Cấu hình hệ thống.
- Trang/khối phí hiển thị sơ đồ dòng tiền và preview đủ: khách trả, gross người bán, commission, service fee, seller net, platform net, tax 0/chưa cấu hình.
- API preview bổ sung các tên trường rõ nghĩa nhưng giữ trường cũ để tương thích.
- Không cần migration/backfill nếu giữ quyết định này; chỉ source/test/docs.

## 4. Phương án không khuyến nghị

- Người bán chịu cả commission và service fee: thay đổi bản chất service fee hiện có và payout.
- Chia service fee giữa khách/người bán: cần thêm cấu hình bearer/split, migration và snapshot mới.
- Áp thuế phần trăm giả định: rủi ro pháp lý và tạo hóa đơn sai.
- Hồi tố lịch phí: phá snapshot, đối soát và khả năng kiểm toán.

## 5. Acceptance sau ADR

- Contract preview và checkout test công thức/rounding/snapshot.
- System Config chứa tab phí; deep link cũ hoạt động.
- Dòng tiền và ý nghĩa từng số được giải thích bằng tiếng Việt, có ví dụ VND.
- Regression checkout/refund/earning/payout và browser UAT Admin.

Không có source 7C.3 nào được sửa trước khi ADR này được phê duyệt.
