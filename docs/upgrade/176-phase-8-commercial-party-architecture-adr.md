# ADR bổ sung Giai đoạn 8 — Nhà bán, Nhà xuất bản, Nhà cung cấp và Đơn vị chịu trách nhiệm

Ngày lập: 2026-07-30  
Trạng thái: Đã được người dùng duyệt ngày 2026-07-30; source Giai đoạn 8 đã triển khai cục bộ  
Phạm vi: kiến trúc dữ liệu, đăng ký Nhà bán, duyệt quan hệ thương mại, catalog công khai và snapshot giao dịch

## 1. Vấn đề cần giải quyết

KomiBook hiện dùng `Vendor` vừa là gian hàng bán sản phẩm, vừa bị hiểu ngầm là nhà cung cấp chịu trách nhiệm. Cách hiểu này chỉ đúng với một số đơn vị tự xuất bản và tự bán. Nó không đủ cho các tình huống:

- Nhà xuất bản không trực tiếp mở gian hàng nhưng liên kết với nhà sách/hiệu sách để truyền thông và bán hàng.
- Nhà sách bán sách của nhiều nhà xuất bản và nhận hàng từ nhiều nhà cung cấp/nhà phân phối.
- Đơn vị xuất bản, đơn vị cung ứng hàng cho gian hàng và đơn vị được khai báo chịu trách nhiệm về nguồn gốc có thể là ba pháp nhân khác nhau.
- Quan hệ hợp tác có thể hết hạn hoặc bị thu hồi nhưng đơn hàng lịch sử vẫn phải giữ đúng thông tin tại thời điểm mua.

Đây là thay đổi kiến trúc. Không được triển khai bằng chuỗi tên tự do trên `books` hoặc suy diễn từ `vendor_id`.

## 2. Quyết định thuật ngữ

1. **Nhà bán (`Vendor/Seller`)**: chủ gian hàng và bên thực hiện nghiệp vụ thương mại trên KomiBook. Nhà bán sở hữu listing, giá, tồn, thực hiện đơn, hỗ trợ khách hàng, hoàn trả và đối soát.
2. **Nhà xuất bản (`Publisher organization`)**: tổ chức xuất bản của đầu sách/phiên bản được khai báo và xác minh.
3. **Nhà cung cấp (`Supplier organization`)**: tổ chức cung ứng hàng hóa/quyền phân phối cho chính Nhà bán đối với listing đó. Nhà cung cấp có thể là Nhà xuất bản, nhà phân phối hoặc pháp nhân của Nhà bán.
4. **Đơn vị chịu trách nhiệm được khai báo (`Responsible organization`)**: tổ chức gắn với trách nhiệm nguồn gốc/xác thực/lưu thông của listing theo hồ sơ được duyệt. Tên nhãn pháp lý cuối cùng phải qua legal review; hệ thống không tự gán trách nhiệm pháp lý chỉ từ một quan hệ thương mại.
5. **Gian hàng (`Shop`)**: hồ sơ công khai của Nhà bán. Gian hàng không đồng nghĩa với Nhà xuất bản.

Nhà bán vẫn chịu trách nhiệm vận hành giao dịch với khách hàng trên KomiBook, kể cả khi listing có Nhà xuất bản hoặc Nhà cung cấp khác.

## 3. Mô hình tổ chức và quan hệ

### 3.1. `organizations`

Một pháp nhân/tổ chức dùng chung cho Publisher, Supplier, Distributor và Bookstore:

- tên pháp lý, tên hiển thị, slug công khai;
- loại tổ chức có thể nhiều giá trị: `publisher`, `supplier`, `distributor`, `bookstore`;
- mã số/giấy phép cần cho xác minh, nhưng trường nhạy cảm không công khai mặc định;
- hồ sơ công khai, logo, mô tả, liên kết chính thức;
- trạng thái `draft`, `pending_review`, `verified`, `rejected`, `suspended`, `archived`;
- audit người duyệt, thời điểm duyệt và lý do thay đổi.

### 3.2. `vendor_organization_relationships`

Liên kết Nhà bán với tổ chức:

- vai trò: `self_legal_entity`, `publisher_partner`, `supplier_partner`, `authorized_distributor`;
- loại quan hệ, phạm vi quyền, ngày hiệu lực/hết hạn;
- hồ sơ chứng minh riêng tư;
- trạng thái duyệt và lịch sử thu hồi;
- không cho Nhà bán tự chuyển thành `verified`.

### 3.3. `book_commercial_parties`

Liên kết theo từng listing/sản phẩm, không chỉ theo đầu sách:

- `book_id`, `organization_id`, `role` gồm `publisher`, `supplier`, `responsible_organization`;
- tham chiếu quan hệ đã duyệt và bằng chứng áp dụng;
- thời gian hiệu lực, trạng thái xác minh;
- mỗi role chỉ có một bản ghi đang hiệu lực cho một listing;
- thay đổi role tạo phiên bản mới, không ghi đè lịch sử.

### 3.4. Snapshot giao dịch

Khi checkout, order item/invoice phải chụp tối thiểu:

- Nhà bán/gian hàng;
- Nhà xuất bản;
- Nhà cung cấp;
- Đơn vị chịu trách nhiệm được khai báo;
- mã phiên bản quan hệ và thời điểm xác minh.

Việc quan hệ bị sửa hoặc thu hồi sau này không được làm đổi chứng từ và lịch sử đơn cũ.

## 4. Phân loại Nhà bán và quy tắc publish

### 4.1. Nhà xuất bản bán trực tiếp

- Hồ sơ pháp nhân của Nhà bán phải được xác minh có loại `publisher`.
- Hệ thống cho phép đề xuất cùng một organization làm Publisher, Supplier và Responsible organization.
- Ba vai trò vẫn được lưu rõ ràng; không suy diễn ngầm từ `vendor_id`.
- Giao diện công khai hiển thị nhãn “Nhà xuất bản đồng thời là nhà cung cấp” khi cả ba quan hệ đã được duyệt.

### 4.2. Nhà sách/hiệu sách bán nhiều loại sách

- Đăng ký theo mô hình `bookstore` hoặc `mixed`.
- Trước khi listing được publish, bắt buộc chọn Publisher và Supplier từ quan hệ đã duyệt hoặc gửi hồ sơ quan hệ mới để Admin duyệt.
- Bắt buộc khai báo Responsible organization theo chính sách áp dụng cho loại sản phẩm.
- Không cho nhập tên tự do để né bước xác minh.
- Có thể lưu default Supplier theo kho/danh mục, nhưng mỗi listing vẫn phải xác nhận lại.

### 4.3. Nhà phân phối bán trực tiếp

- Phải khai báo Publisher của đầu sách.
- Supplier có thể là pháp nhân của chính Nhà bán nếu quan hệ phân phối được xác minh.
- Responsible organization được xác định từ hồ sơ đã duyệt, không mặc định luôn là Publisher.

### 4.4. Sách cũ

- Giữ marketplace sách cũ và actor người bán sách cũ độc lập.
- Publisher gốc có thể hiển thị nếu biết và được đối chiếu từ metadata sách.
- Không bắt buộc cá nhân bán lại một cuốn sách cũ phải tạo Supplier organization.
- Người bán sách cũ vẫn phải cam kết nguồn gốc/xác thực; địa chỉ fulfillment không được công khai.

## 5. Nâng cấp đăng ký Nhà bán

Luồng đăng ký được chia trang/bước để dễ thao tác:

1. Danh tính pháp lý và hồ sơ gian hàng.
2. Chọn mô hình hoạt động: Publisher trực tiếp, Bookstore, Distributor hoặc Mixed.
3. Khai báo organization của chính Nhà bán và các vai trò.
4. Khai báo ít nhất một quan hệ xuất bản/cung ứng cùng chứng từ phù hợp với mô hình.
5. Kho, địa chỉ fulfillment và phạm vi vận hành.
6. Thanh toán/đối soát, cam kết thông tin và gửi duyệt.

Nhà bán có thể được duyệt tài khoản trước nhưng không được publish listing thiếu commercial parties đã xác minh. Admin có hàng đợi riêng cho hồ sơ pháp nhân, quan hệ đối tác và ngoại lệ listing.

## 6. Catalog và giao diện công khai

### 6.1. Trang chi tiết sách

Thêm khối **Thông tin xuất bản và cung ứng**:

- gian hàng đang bán và nút truy cập nhanh gian hàng;
- Nhà xuất bản và liên kết hồ sơ tổ chức;
- Nhà cung cấp và liên kết hồ sơ tổ chức;
- Đơn vị chịu trách nhiệm được khai báo và trạng thái xác minh;
- nhãn quan hệ như “NXB bán trực tiếp”, “Nhà sách đối tác” hoặc “Nhà phân phối được xác minh”.

Không công khai chứng từ, địa chỉ kho riêng tư, thông tin thanh toán hoặc mã số pháp lý đầy đủ.

### 6.2. Hồ sơ tổ chức và gian hàng

- Hồ sơ organization công khai: giới thiệu, loại tổ chức, trạng thái xác minh, sách liên quan và các gian hàng đối tác.
- Gian hàng công khai: thông tin Nhà bán, mô hình hoạt động, đối tác đã xác minh và sản phẩm đang bán.
- Bài viết/feed của Publisher phải thuộc organization và có quan hệ quản trị nội dung đã duyệt; chưa tạo actor Publisher độc lập trong Giai đoạn 8.

### 6.3. Trang chủ và thẻ sách

- Không nhồi thêm Publisher/Supplier vào thẻ sách mặc định.
- Khi người dùng mở xem nhanh hoặc trang chi tiết từ trang chủ, khối thông tin thương mại mới phải xuất hiện.
- Thẻ vẫn giữ ảnh bìa đầy đủ, giá, phiên bản và các tiện ích hover đã được duyệt.

## 7. Quyền và audit

- Vendor chỉ quản lý organization/relationship thuộc hồ sơ của mình.
- Warehouse Manager không được sửa Publisher, Supplier, Responsible organization, giá hoặc quan hệ pháp lý.
- Admin duyệt, từ chối, đình chỉ và xem audit; mọi quyết định bắt buộc có lý do.
- Quan hệ hết hạn chặn publish/update liên quan nhưng không phá order snapshot cũ.
- API public chỉ trả trường đã duyệt và được phép công khai.
- Negative authorization phải bao phủ Vendor chéo, organization chéo và listing chéo.

## 8. Migration và tương thích

- Không tự backfill `vendor_id` thành cả ba commercial party.
- Dry-run phân loại: `verified_direct_publisher`, `bookstore_requires_review`, `used_book_exception`, `conflict_review`, `insufficient_evidence`.
- Listing hiện có tiếp tục đọc được; publish/update nhạy cảm đi qua reconciliation gate.
- Không đổi/xóa lịch sử Author, ebook entitlement, royalty, copyright hoặc used-book trong migration này.
- Mọi migration cần `up/down`, rehearsal SQLite/MySQL phù hợp, manifest và báo cáo chênh lệch trước database thật.

## 9. Acceptance bắt buộc

- Một organization có thể làm nhiều vai trò nhưng mỗi vai trò listing được lưu rõ ràng.
- Publisher bán trực tiếp được hiển thị đúng là Publisher đồng thời Supplier sau xác minh.
- Bookstore không thể publish nếu thiếu Publisher/Supplier/Responsible organization bắt buộc.
- Thu hồi quan hệ chặn listing mới nhưng order cũ giữ nguyên snapshot.
- Trang chi tiết đi từ homepage hiển thị đủ ba đơn vị và link nhanh gian hàng.
- Không lộ hồ sơ chứng minh hoặc địa chỉ kho.
- Sách cũ vẫn hoạt động theo ngoại lệ đã định nghĩa.
- Responsive UAT tại 375, 768, 1024 và 1440 px; đủ loading, empty, error, ready, forbidden.

## 10. Ghi chú pháp lý

Thiết kế này phục vụ khả năng truy vết và xác minh, không thay thế tư vấn pháp lý. Trước khi chốt nhãn “đơn vị chịu trách nhiệm” và bộ chứng từ production, cần legal review dựa trên Luật Xuất bản và quy định thương mại điện tử hiện hành. Không đưa credential, dịch vụ trả phí, database thật hoặc production vào batch kiến trúc.

Nguồn đối chiếu chính thức ban đầu:

- Luật Xuất bản số 19/2012/QH13: https://vbpl.vn/FileData/TW/Lists/vbpq/Attachments/113345/VanBanGoc_Luat_xuat_ban_bw.pdf
- Luật Thương mại điện tử số 122/2025/QH15 có hiệu lực từ 01/07/2026: https://moit.gov.vn/tin-tuc/ke-hoach-trien-khai-thi-hanh-luat-thuong-mai-dien-tu.html
- Tổng quan trách nhiệm xác thực và quản lý thông tin người bán theo Luật/Nghị định mới: https://moit.gov.vn/tin-tuc/bo-cong-thuong-pho-bien-luat-thuong-mai-dien-tu-va-nghi-dinh-so-248-2026-nd-cp.html
