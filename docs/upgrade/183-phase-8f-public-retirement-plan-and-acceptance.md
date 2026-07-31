# Giai đoạn 8F — Public discovery, lịch sử và retirement

Ngày: 2026-07-30  
Trạng thái: Accepted-local

## Kế hoạch và prompt từng source batch

Ràng buộc chung: không thêm commercial metadata lên thẻ sách; không xóa dữ liệu Author/ebook/royalty/copyright; không đổi lịch sử đơn; không phá marketplace sách cũ.

- **8F.1:** snapshot giao dịch. Prompt: checkout chụp Seller/Publisher/Supplier/Responsible organization và version/verified time; quan hệ bị thu hồi không đổi order cũ; gate bằng snapshot stability test.
- **8F.2:** public detail/organization. Prompt: thêm khối xuất bản–cung ứng và link gian hàng, chỉ trả organization verified/public; legacy có trạng thái đang đối chiếu; gate bằng resource test và browser smoke.
- **8F.3:** used-book exception. Prompt: cá nhân bán lại không phải tạo Supplier organization, vẫn giữ cam kết nguồn gốc và địa chỉ riêng tư; gate bằng publication eligibility/regression Phase 7.
- **8F.4:** retirement Author. Prompt: bỏ CTA/kênh sáng tác đang hoạt động, ưu tiên Vendor/Quản kho, Author legacy hạ cánh về Sách cũ; giữ deep history/API tương thích để không xóa quyền ebook; gate bằng auth guard, retirement contract và full regression.

## Kết quả

- Order item giữ snapshot commercial parties độc lập với quan hệ hiện tại.
- Trang chi tiết có khối “Thông tin xuất bản và cung ứng”, organization link và shop link.
- Thẻ sách vẫn chỉ hiển thị dữ liệu mua sắm đã duyệt.
- Header/sidebar không còn entry “Kênh sáng tác Tác giả”; có “Không gian Quản kho”.
- Author legacy không còn thắng default dashboard và được đưa về trang quản lý sách cũ.

## Gate

- `Phase8CommercialPartiesTest`: pass.
- `phase7_author_retirement.spec.js`, `auth_guard.spec.js`, `phase8_warehouse_commercial_parties.spec.js`: pass.
- Browser smoke detail legacy: section + shop link present; mobile không tràn ngang.

