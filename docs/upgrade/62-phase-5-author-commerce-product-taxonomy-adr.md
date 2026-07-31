# KomiBook Phase 5 — ADR Author Commerce, product taxonomy và digital rights

Ngày: 2026-07-27  
Trạng thái: **Đã được người dùng phê duyệt ngày 2026-07-29**

## Bối cảnh

ADR Giai đoạn 4 tách Author khỏi Vendor: Author nắm quyền sáng tác, Vendor vận hành thương mại. Yêu cầu sau Giai đoạn 4 cần tác giả tự bán ebook và sách cũ, tự tạo promotion trong phạm vi của mình, nhưng không nên cấp toàn bộ quyền Vendor hoặc làm rò địa chỉ/PII.

Schema hiện tại chỉ phân biệt `ebook|physical`, không biểu diễn nguồn gốc, tình trạng, fulfillment và return policy. Warehouse hiện còn rủi ro tenant isolation; analytics vendor trả dữ liệu người mua không phù hợp cho author.

## Quyết định đề xuất

### 1. Author Commerce là capability giới hạn

Approved Author được cấp capability thương mại cho:

- ebook `self_published` có quan hệ tác giả/bản quyền/publishing hợp lệ;
- listing `used_resale + physical` do chính tác giả tạo.

Capability cho phép quản lý giá, tồn sách cũ, coupon và đăng ký Flash Sale trong scope trên. Nó không cho phép truy cập catalog, kho, order hoặc tài chính của Vendor khác và không thay đổi `users.role` thành vendor.

### 2. Product taxonomy là nhiều chiều độc lập

- `format`: `ebook | physical`;
- `provenance`: `self_published | used_resale | publisher_catalog`;
- `condition`: `new | like_new | good | fair | null`;
- `fulfillment_mode`: `digital | author_registered_address | vendor_warehouse`;
- `return_policy_version_id`: snapshot policy có hiệu lực.

Các combination không hợp lệ bị chặn bằng validation/service invariant và constraint phù hợp. Dữ liệu legacy không đủ bằng chứng không được tự gán `used_resale` hay `self_published`.

### 3. Địa chỉ tác giả là private fulfillment data

- Một Author Commerce profile chỉ có một địa chỉ active đã xác minh.
- Warehouse/listing chỉ tham chiếu địa chỉ này; author không nhập địa chỉ kho tùy ý.
- Customer/public payload không có địa chỉ đầy đủ. Staff/owner access dùng policy, audit và resource riêng.
- Quy trình trả sách dùng hướng dẫn trung gian; không hiển thị trực tiếp địa chỉ tác giả cho khách.

### 4. Ebook không return, nhưng có financial correction

- Ebook không được đưa vào return request.
- Checkout bắt buộc hiển thị điều khoản nội dung số và lưu policy/consent snapshot.
- Thanh toán trùng, lỗi cấp entitlement hoặc quyết định hỗ trợ được xử lý bằng financial correction có audit; không giả thành “trả ebook”.

### 5. Ebook version và entitlement bất biến

- Mỗi publication tạo version/file bất biến và release note.
- Purchase entitlement giữ version tại thời điểm mua và tự động mở version mới.
- Library cho truy cập version cũ/mới; không ghi đè file cũ.
- Takedown do pháp lý/bản quyền cần quyết định, audit, thông báo và policy rõ.

### 6. Sách cũ và counterfeit liability

- Chỉ `physical + used_resale` có tồn kho tác giả và return/refund.
- Listing yêu cầu ảnh thực, tình trạng, khuyết điểm và attestation hàng thật.
- Tố cáo hàng giả/sai mô tả mở dispute có evidence và moderation; có thể hold/reverse/refund và sanction sau quyết định.

### 7. Reader analytics và recommendation theo privacy

- Author analytics chỉ là aggregate theo tác phẩm; không trả tên/email/danh sách độc giả.
- Tuổi/giới tính là tùy chọn tự khai có consent, minimum cohort threshold và nhóm unknown.
- Recommendation có opt-out và fallback không cá nhân hóa.

## Hệ quả

- Cần migration/backfill nhiều bước; không thể chỉ đổi UI hoặc tái dùng `books.type`.
- Checkout/order item cần snapshot product taxonomy, return policy và ebook consent.
- Return, inventory, promotion, analytics, catalog và library đều phải dùng cùng domain service/policy.
- Các màn hình editor hiện có được tái sử dụng trong Author Studio; không xây lại editor core.
- Warehouse isolation/privacy là correction P0 và phải đi trước migration chức năng rộng hơn.

## Điều kiện phê duyệt

Nếu ADR được duyệt, batch source đầu tiên chỉ là **5A.1 Warehouse isolation và private fulfillment address**. Product taxonomy, OTP, Author Studio, ebook versioning, sách cũ và promotion được triển khai ở các batch riêng theo `61-phase-5-to-8-revised-roadmap.md`.
