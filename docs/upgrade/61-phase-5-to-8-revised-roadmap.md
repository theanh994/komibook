# KomiBook — roadmap sửa đổi sau Giai đoạn 4

> Cập nhật kiến trúc 29/07/2026: phạm vi Author trong các phần đã hoàn thành được giữ như lịch sử; kế hoạch đang hoạt động từ 7D trở đi được thay bằng tài liệu 158, bổ sung Giai đoạn 8 Quản lý kho và chuyển UAT/phát hành sang Giai đoạn 9.

> Trạng thái 2026-07-29: Giai đoạn 5 đã được nghiệm thu cục bộ; xem `70-phase-5-final-acceptance.md`. Chưa commit, push hoặc deploy.

Ngày lập: 2026-07-27  
Trạng thái: **Đề xuất nghiệp vụ và kế hoạch; chưa bắt đầu source work**  
Phạm vi thay thế: các phần Giai đoạn 5–8 trong kế hoạch gốc và tài liệu bàn giao trước đây.

## 1. Mục tiêu sản phẩm mới

KomiBook chuyển sang mô hình có ba nhánh rõ ràng:

1. **Tác phẩm tự sáng tác:** tác giả viết, quản lý phiên bản, gửi duyệt và phát hành dưới dạng ebook.
2. **Sách cũ do tác giả cung cấp:** hàng vật lý đã qua sử dụng, tồn kho hữu hạn, có tình trạng, giao nhận, trả hàng và trách nhiệm chống hàng giả.
3. **Catalog nhà bán/nhà xuất bản:** tiếp tục dùng quyền Vendor độc lập đã chốt tại ADR Giai đoạn 4.

Không dùng một trường `type` hoặc một role duy nhất để đại diện cho cả ba nhánh.

## 2. Bằng chứng audit trực tiếp

### 2.1. Phần đã có nền nhưng chưa đáp ứng yêu cầu

- `AuthorController::dashboardStats()` trả các tổng số sách, ebook, sách vật lý, số dư và đã rút bằng số `0`; dashboard tác giả mới chỉ có trạng thái hồ sơ và tổng tác phẩm.
- Analytics thuộc `/api/vendor/analytics`, dựa trên đơn hàng vendor và còn trả tên/email của “top readers”; dữ liệu này không phù hợp cho tác giả và không đạt nguyên tắc tối thiểu hóa dữ liệu cá nhân.
- Trình viết/chapter/revision và publishing workflow đã có nền từ Giai đoạn 4B.4, nhưng UI vẫn nằm trong khu vực/route mang nghĩa Vendor.
- Return service đã từ chối ebook, nhưng hiện cho phép mọi dòng hàng `physical`; chưa có khái niệm chỉ sách cũ mới được trả.
- Vendor đã có API đăng ký Flash Sale; tác giả không có capability độc lập. Coupon hiện chỉ có API quản trị viên.
- Đăng ký tài khoản email đã có OTP 8 số, nhưng đăng ký tác giả chưa có challenge OTP riêng; giao diện vẫn hướng người dùng sang “Xác thực Số điện thoại”.
- Trang chủ có hero tĩnh, danh sách sách và Flash Sale, nhưng chưa có feed năm tầng, recommendation cá nhân hoặc Vendor Feed.
- Footer đang hiển thị nhiều mục “Chưa có trang”. Catalog chưa có bộ lọc lứa tuổi và vẫn dùng khoảng giá nhập tay.

### 2.2. Hồi quy/rủi ro phải sửa trước chức năng mới

- `WarehouseController::index()` lấy toàn bộ warehouse mà không lọc `vendor_id`.
- `adjustStock()` chưa chứng minh warehouse nguồn/đích và book cùng thuộc actor hiện tại.
- Tác giả có vendor profile được phép nhập một địa chỉ kho tùy ý; chưa khóa về đúng địa chỉ đã đăng ký và chưa có lớp API resource che địa chỉ khỏi khách hàng.

Các điểm này được xếp P0 authorization/privacy trong Batch 5A.1.

## 3. ADR bắt buộc trước khi triển khai Giai đoạn 5

### 3.1. Phân loại sản phẩm

Mỗi book/listing dùng các chiều độc lập:

- `format`: `ebook | physical`;
- `provenance`: `self_published | used_resale | publisher_catalog`;
- `condition`: `new | like_new | good | fair` — chỉ áp dụng cho `used_resale + physical`;
- `fulfillment_mode`: `digital | author_registered_address | vendor_warehouse`;
- `return_policy`: policy versioned được snapshot vào order item.

Không suy diễn sách cũ chỉ từ `physical`, không suy diễn tự xuất bản chỉ từ `ebook`, và không backfill tình trạng sách khi không có dữ liệu.

### 3.2. Quyền tác giả và Vendor

- Author và Vendor tiếp tục là hai profile/capability độc lập.
- Approved Author có **Author Commerce capability giới hạn** để định giá, tạo coupon và đăng ký Flash Sale cho ebook tự xuất bản có quyền hợp lệ và sách cũ do chính họ khai báo tại địa chỉ đã xác minh.
- Capability này không cấp quyền vận hành catalog, kho nhiều địa điểm hoặc đơn hàng của Vendor khác.
- Vendor vẫn là actor thương mại cho catalog nhà bán/nhà xuất bản và hợp đồng phát hành qua đối tác.

### 3.3. Ebook, điều khoản mua và phiên bản

- Ebook không đi qua luồng trả hàng vật lý và không có “hoàn trả do đổi ý”.
- Trước thanh toán, khách phải thấy và xác nhận điều khoản nội dung số; bằng chứng đồng ý được snapshot bất biến theo checkout/order item.
- Sửa thanh toán trùng, không cấp được nội dung hoặc quyết định hỗ trợ là **financial correction**, không phải return.
- Mỗi lần xuất bản tạo `ebook_version` và file bất biến riêng.
- Entitlement sau mua gắn với book và phiên bản đã mua; người mua tự động nhận phiên bản mới và vẫn truy cập được phiên bản cũ.
- Thu hồi do vi phạm pháp luật/bản quyền phải có quyết định, audit và thông báo; không âm thầm mất quyền.

### 3.4. Sách cũ, kho và trách nhiệm hàng giả

- Chỉ `physical + used_resale` xuất hiện trong “Sách cũ”, dùng tồn kho và được yêu cầu trả hàng/hoàn tiền.
- Tác giả chỉ có một địa chỉ fulfillment đang hiệu lực, lấy từ địa chỉ đăng ký đã xác minh; không cho nhập địa chỉ kho tùy ý.
- Địa chỉ đầy đủ là dữ liệu riêng tư. Public/customer API không trả địa chỉ; khách chỉ thấy vùng/tỉnh nếu cần. Hoàn trả dùng hướng dẫn trung gian do hệ thống/admin phát hành.
- Listing sách cũ bắt buộc có ảnh thực tế, mô tả tình trạng, khuyết điểm, số lượng và cam kết hàng thật.
- Người cung cấp chịu trách nhiệm nếu sách giả/sai mô tả. Quy trình gồm evidence, moderation, hold/reversal, refund, cảnh cáo/suspend/revoke và audit; không tự động kết luận chỉ từ tố cáo.

### 3.5. Phân tích độc giả và recommendation

- Tác giả chỉ xem số liệu tổng hợp theo tác phẩm: lượt xem sample, bắt đầu đọc, hoàn thành, retention theo chương, conversion sample-to-purchase, doanh thu/royalty, rating và xu hướng thể loại.
- Không trả tên, email hoặc danh sách người đọc cho tác giả.
- Nhóm tuổi/giới tính chỉ dùng dữ liệu tự khai có consent, ngưỡng tối thiểu chống nhận diện và bucket “không xác định”.
- Recommendation dùng độ tuổi phù hợp, thể loại yêu thích, lịch sử xem/đọc/mua, tương tác với tác giả và popularity; khách/thiếu consent dùng fallback không cá nhân hóa.
- Có explanation label cơ bản và khả năng tắt cá nhân hóa.

## 4. Giai đoạn 5 — Author-first product domain

Ưu tiên: P0/P1. Không triển khai source trước khi ADR Mục 3 được duyệt.

### 5A.1 — Warehouse isolation và private fulfillment address

- Sửa tenant isolation cho warehouse/stock adjustment.
- Tạo address record riêng tư, versioned/verified; author chỉ dùng address active đã đăng ký.
- Tách public resource khỏi staff/owner resource; test không rò địa chỉ.
- Chặn transfer nhiều kho cho Author Commerce; giữ multi-warehouse cho Vendor hợp lệ.

### 5A.2 — Product taxonomy và migration

- Thêm các chiều `format/provenance/condition/fulfillment_mode/return_policy`.
- Backfill an toàn: chỉ map ebook/physical chắc chắn; provenance/condition không rõ để trạng thái cần phân loại.
- Cập nhật eligibility, cart/order item snapshot và public filters.

### 5B — OTP email khi đăng ký tác giả

- Challenge OTP email 8 số, hash, expiry, rate limit, attempt limit và one-time use.
- Tài khoản Google chỉ được bỏ qua khi identity đã được backend xác minh, email verified và email trùng tài khoản hiện tại.
- Không dùng dữ liệu frontend tự khai để bypass; ghi audit cho challenge/bypass/submit.

### 5C — Author Studio, dashboard và reader analytics

- Khu vực Author Studio độc lập: Tổng quan, Viết tác phẩm, Bản thảo, Phiên bản, Xuất bản, Phân tích độc giả, Marketing và Doanh thu.
- Tái sử dụng chapter editor/revision/publishing workflow Giai đoạn 4B.4; chuyển capability/navigation đúng actor, không viết lại lõi.
- Dashboard dùng dữ liệu thật và có empty/error state; không số 0 hardcode.
- Analytics chỉ tổng hợp, có privacy threshold và không lộ PII.

### 5D — Ebook commerce, terms và version entitlement

- Modal/step điều khoản bắt buộc trước checkout ebook; snapshot policy/consent.
- Không cho tạo return request với ebook; tách financial correction khỏi return.
- Thêm immutable ebook versions và entitlement tới phiên bản mua + phiên bản mới.
- Library/reader cho chọn phiên bản cũ/mới, hiển thị release note và mặc định phiên bản mới nhất.

### 5E — Used-book marketplace, inventory và counterfeit workflow

- Listing sách cũ có ảnh thật, tình trạng, khuyết điểm, số lượng và attestation.
- Kho tác giả trở thành “Sách cũ đang giữ”: một địa chỉ riêng tư, tồn kho từng listing, reserved/available/sold/returned; không có transfer nhiều kho.
- Return/refund chỉ nhận sách cũ đủ điều kiện và dùng policy snapshot.
- Counterfeit/misdescription dispute có evidence, moderation, financial hold/reversal, sanction và audit.

### 5F — Quyền marketing thực sự của tác giả

- Author tạo coupon scope theo book/listing của chính mình, có limit, thời gian, stacking policy và audit.
- Author đăng ký Flash Sale cho book đủ điều kiện; admin approve/reject; price/discount snapshot tại checkout.
- Không cho tác giả tác động campaign, coupon hoặc book của actor khác.

### Gate 5

- Backend/frontend focused tests cho từng batch; full regression tại gate.
- Permission matrix: customer/author/vendor/admin, cross-author và cross-vendor.
- Migration fresh + rollback/reapply trên DB tạm và rehearsal MySQL-compatible.
- Browser smoke: onboarding OTP, Author Studio, ebook terms/version, sách cũ/kho/return, coupon/Flash Sale.
- Không có địa chỉ tác giả hoặc PII độc giả trong public/customer payload.

## 5. Giai đoạn 6 — Public experience và responsive design

> **Phân bổ thực thi đã được mở rộng ngày 2026-07-29.** Giai đoạn 6 hiện bao phủ giao diện toàn dự án, dùng UI/UX Pro Max và giám sát liên tục theo từng trang. Kế hoạch có thẩm quyền là `72-phase-6-ui-ux-pro-max-and-live-page-observation-plan.md`: 6A.0 audit/design, 25 source batch, gate từng nhóm 6A–6I và Gate 6 cuối. Các mục 6A–6D bên dưới vẫn là yêu cầu sản phẩm nhưng không còn là cách chia source batch.

### 6A — Design system và responsive shell

- Audit breakpoint, typography, spacing, focus, contrast, loading/error/empty state.
- Chuẩn hóa header, navigation, dashboard shell và form cho mobile/tablet/desktop/wide.
- Không redesign từng trang riêng lẻ trước khi chốt token/component chung.

### 6B — Trang chủ dạng feed

Thứ tự bắt buộc:

1. Banner Hero Carousel từ CMS/campaign đã duyệt.
2. “Gợi ý dành riêng cho bạn” với consent/fallback.
3. Bản tin/bài viết mới từ NXB và tác giả đã duyệt.
4. Sách bán chạy và sách mới nhất, tách ebook/sách vật lý.
5. Góc ebook đọc thử và sách cũ giá tốt.

Mỗi khối fail independently, có skeleton/empty state và giới hạn rõ; không hardcode dữ liệu production.

### 6C — Navigation, catalog và book card

- Thanh bar thêm “Sách cũ”.
- “Danh mục” có submenu động gồm 10 thể loại có nhiều sách published nhất; tie-break ổn định và cache.
- Catalog thêm lứa tuổi và sáu mốc giá cấu hình rõ; query/filter có URL state và accessibility.
- Book card giữ đúng tỷ lệ bìa bằng `object-contain`/khung tỷ lệ chuẩn, không crop/nén và không bo góc ảnh bìa; card container có thể bo góc.

### 6D — Footer và trang chính sách

- Tạo: Về KomiBook, Dành cho tác giả, Liên hệ, FAQ, Điều khoản sử dụng, Chính sách bảo mật, Chính sách ebook, Chính sách sách cũ–trả hàng–hoàn tiền, Chính sách bản quyền/hàng giả.
- Policy được version hóa; checkout lưu version đã đồng ý.
- Không bật newsletter hoặc liên kết ngoài chưa có backend/địa chỉ chính thức.

### Gate 6

- Visual regression/breakpoint checklist trên mobile, tablet, desktop và wide.
- Keyboard/focus/contrast và semantic checks.
- Feed, catalog, footer pages và book cards dùng API thật, không mock fallback.
- Browser smoke guest/customer/author/vendor/admin.

## 6. Giai đoạn 7 — Sửa lỗi nghiệm thu, đồng bộ nghiệp vụ và hardening

Giai đoạn 7 được phân bổ lại sau đợt kiểm tra giao diện ngày 29/07/2026. Chi tiết thứ tự, phạm vi và gate nằm tại `docs/upgrade/133-phase-7-correction-master-plan.md`; sổ lỗi/trang nằm tại `docs/upgrade/134-phase-7-defect-ledger.md`.

### 7A — Lỗi chặn thao tác và tính đúng dữ liệu

- Sửa báo cáo tài chính tương thích SQLite/MySQL, chuẩn hóa URL bìa đơn hàng, định tuyến đúng kênh Author và khôi phục luồng viết tác phẩm đầu-cuối.
- Thay số liệu đối soát mang tính minh họa bằng trạng thái/ledger thật; tách dữ liệu demo khỏi dữ liệu giao dịch.

### 7B — Hồi quy giao diện công khai

- Khôi phục thẻ sách tối giản, đầy đủ bìa, nút yêu thích và tiện ích khi hover/focus/touch.
- Khôi phục hero có hình giá sách, thêm nút lên đầu trang, giới hạn gợi ý tối đa năm sách trên màn hình rộng và co giãn theo breakpoint.
- Khôi phục năm nhóm lứa tuổi đã được chủ dự án xác nhận.

### 7C — Admin intelligence và cấu hình

- Bổ sung dashboard bằng dữ liệu thật và biểu đồ truy cập được; đồng bộ giao diện chiến dịch thông báo.
- Đưa Commission & phí vào nhóm cấu hình hệ thống, giải thích rõ người trả, cách tính, hiệu lực và ảnh hưởng lịch sử.

### 7D — Sách cũ và đóng băng phạm vi Author

- Giữ marketplace sách cũ nhưng chuẩn bị tách ownership khỏi Author sang capability người bán sách cũ.
- Không triển khai thêm Author Studio, copyright, royalty, đăng ký xuất bản hoặc promotion riêng của Author.
- Entry point Author cũ chỉ được retire sau inventory/manifest; dữ liệu đã có được bảo toàn ở chế độ lịch sử.

### 7E — Vendor parity

- Audit và sửa các trang Vendor tương ứng bằng component/contract dùng chung, vẫn giữ tenant isolation và quyền riêng tư.

### 7F — Dữ liệu, CI/E2E, performance và operations

- Bảo toàn sách người dùng tự nhập; chỉ bổ sung dữ liệu theo manifest dry-run và upsert không ghi đè.
- Hoàn thiện Policy/service boundaries, index/constraint, PHPUnit/Vitest/E2E, CI, N+1, pagination/cache, ảnh responsive và EbookReader chunk.
- Staging, queue, scheduler, email, database thật, credential, dịch vụ ngoài, chi phí và production vẫn là gate phê duyệt riêng.

## 7. Giai đoạn 8 — Quản lý kho và chuyển đổi vai trò

Chi tiết kiến trúc và thứ tự nằm tại `docs/upgrade/158-author-to-warehouse-manager-architecture-change.md`.

- 8A: ADR capability, assignment, chứng từ kho và manifest dữ liệu Author/sách cũ.
- 8B: invitation và assignment Quản lý kho theo từng Vendor/kho.
- 8C: dashboard/cổng Quản lý kho tách trang.
- 8D: phiếu nhập, phiếu xuất, điều chuyển và kiểm kê có state machine/ledger.
- 8E: Vendor/Admin quản lý nhân sự kho và audit.
- 8F: chuyển sách cũ sang `used_book_seller`, retire entry point Author và archive dữ liệu lịch sử.
- Gate 8: authorization đa tenant/kho, concurrency, ledger, migration rehearsal và UAT responsive.

## 8. Giai đoạn 9 — UAT và phát hành

- UAT khách hàng, người bán sách cũ, Quản lý kho, Vendor và Admin.
- Sách/ebook đã xuất bản và entitlement/version đã mua vẫn hoạt động.
- Không còn Author trong navigation/onboarding hoạt động; copyright/royalty lịch sử chỉ đọc đúng quyền.
- Responsive UAT; không còn P0, CI xanh, migration/backup/rollback được diễn tập.

## 9. Issue namespace mới

- `WH-PRIV-01`: tenant isolation và địa chỉ fulfillment riêng tư.
- `BOOK-TAX-01`: taxonomy định dạng/nguồn gốc/tình trạng/fulfillment.
- `AUTHOR-OTP-01` (retired): không phát triển tiếp; chỉ giữ bằng chứng lịch sử trước archive.
- `AUTHOR-STUDIO-01` (retired): không phát triển tiếp; bảo toàn sách/ebook đã tạo.
- `AUTHOR-DASH-01` (retired): được thay bằng `WAREHOUSE-MANAGER-01` ở Giai đoạn 8.
- `READER-ANALYTICS-01`: analytics tổng hợp, consent và privacy threshold.
- `EBOOK-TERMS-01`: điều khoản nội dung số và consent snapshot.
- `EBOOK-VERSION-01`: immutable versions và entitlement lịch sử.
- `USED-BOOK-01`: listing/kho/return sách cũ.
- `COUNTERFEIT-01`: evidence, moderation, liability và sanction.
- `AUTHOR-PROMO-01` (retired): không triển khai; promotion Vendor vẫn giữ.
- `HOME-FEED-01`: feed năm tầng và recommendation.
- `CATALOG-UX-01`: Sách cũ, top-10 category, age/six-price filters.
- `FOOTER-POLICY-01`: footer pages và policy versioning.
- `RESPONSIVE-01`: design system và responsive toàn dự án.
- `WAREHOUSE-MANAGER-01`: invitation, assignment và quyền theo từng kho.
- `WAREHOUSE-DOC-01`: phiếu nhập/xuất/điều chuyển/kiểm kê và ledger.
- `AUTHOR-RETIRE-01`: đóng entry point Author, archive dữ liệu và bảo toàn ebook lịch sử.
- `USED-SELLER-01`: chuyển marketplace sách cũ sang capability người bán độc lập.

## 10. Thứ tự thực hiện và ranh giới

ADR Giai đoạn 5 → 5A.1 → 5A.2 → 5B → 5C → 5D → 5E → 5F → Gate 5 → 6A → 6B → 6C → 6D → Gate 6 → 7A → Gate 7A → 7B → Gate 7B → 7C → Gate 7C → 7D sách cũ/Author freeze → 7E Vendor baseline → 7F manifest/quality → Gate 7 → 8A → 8B → 8C → 8D → 8E → 8F → Gate 8 → UAT/Gate 9.

Mỗi batch source phải có kế hoạch, prompt bàn giao agent nếu agent khả dụng, review độc lập và gate mục tiêu. Không commit, push, migration database thật, gửi email thật, bật scheduler, thay credential/dịch vụ ngoài hoặc deploy production nếu chưa có phê duyệt riêng.

## 11. Điều chỉnh Giai đoạn 8 — Chuỗi trách nhiệm thương mại (2026-07-30)

Giai đoạn 8 được mở rộng từ 18 thành 24 source batch để không còn đồng nhất mọi Vendor với Nhà cung cấp:

- Nhà bán/gian hàng chịu nghiệp vụ thương mại, listing, giá, tồn và fulfillment.
- Nhà xuất bản, Nhà cung cấp và Đơn vị chịu trách nhiệm được khai báo là các vai trò organization độc lập theo từng listing.
- Nhà xuất bản bán trực tiếp có thể giữ cả ba vai trò sau xác minh; Nhà sách/hiệu sách phải khai báo và chứng minh Publisher/Supplier trước publish.
- Trang chi tiết và xem nhanh hiển thị các organization đã duyệt cùng link nhanh gian hàng; thẻ sách mặc định không bị nhồi metadata.
- Order/invoice chụp snapshot các bên để quan hệ thay đổi sau này không sửa lịch sử.
- Sách cũ dùng ngoại lệ riêng; không bắt người bán cá nhân tạo Supplier organization và không công khai địa chỉ fulfillment.

Tài liệu điều khiển mới:

- `docs/upgrade/176-phase-8-commercial-party-architecture-adr.md`
- `docs/upgrade/177-phase-8-master-plan.md`
