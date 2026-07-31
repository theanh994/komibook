# KomiBook — kế hoạch Giai đoạn 6 với UI/UX Pro Max và giám sát từng trang

Ngày: 2026-07-29  
Trạng thái: **đã chốt lại cách chia việc; chưa bắt đầu source work Giai đoạn 6**  
Checkout: `C:\Projects\DoAnTotNGhiep_komibook`

## 1. Quyết định

Giai đoạn 6 không còn giới hạn ở trang chủ, catalog và footer. Phạm vi được mở rộng đúng theo yêu cầu “cải thiện giao diện toàn dự án”, bao gồm:

- public/guest;
- đăng ký, đăng nhập và onboarding;
- hành trình khách hàng;
- Author Panel;
- Vendor Panel;
- Admin Panel;
- footer, trang thông tin và chính sách;
- responsive, accessibility, loading/error/empty state cho toàn bộ các nhóm trên.

Audit hiện tại ghi nhận khoảng **75 Vue view** và **90 khai báo route** (bao gồm child route và redirect). Vì vậy kế hoạch cũ 6A–6D là quá rộng để người dùng theo dõi từng trang một cách an toàn.

Giai đoạn 6 mới gồm:

- **1 batch audit/design không sửa source: 6A.0**;
- **25 source batch**;
- gate sau từng nhóm 6A–6I;
- một Gate 6 cuối cùng.

## 2. Tích hợp UI/UX Pro Max

Skill chính thức:

- repository: `nextlevelbuilder/ui-ux-pro-max-skill`;
- tên: `ui-ux-pro-max`;
- cài tại Codex personal skills, không vendor toàn bộ database của skill vào repository;
- áp dụng cho cấu trúc trang, design system, responsive, accessibility, interaction, form và data visualization;
- không dùng skill để tự quyết định nghiệp vụ backend hoặc thay đổi authorization.

Kết quả phân tích sơ bộ cho KomiBook:

- hướng **content-first**, hiện đại và dễ đọc;
- giữ trục màu indigo + xanh lá cho hành động mua;
- chuyển động mức thấp, ưu tiên phản hồi thao tác 150–300ms;
- density trung bình; dashboard có thể dùng override dày hơn;
- bắt buộc contrast 4.5:1, focus rõ, touch target tối thiểu 44px, hỗ trợ `prefers-reduced-motion`;
- breakpoint kiểm tra chính: 375, 768, 1024 và 1440px.

Đề xuất “Tactile Digital” của skill chỉ được dùng ở mức phản hồi nhấn nhẹ. Không áp dụng jelly/chrome/bounce diện rộng vì không phù hợp với khả năng đọc lâu, accessibility và giao diện hiện có.

## 3. Quy trình giám sát liên tục theo từng trang

Mỗi trang hoặc cụm trang nhỏ phải đi qua vòng lặp sau:

1. **Baseline**
   - mở đúng role và dữ liệu local;
   - chụp/quan sát trạng thái hiện tại;
   - ghi lỗi bố cục, thao tác, responsive, keyboard, focus, loading/error/empty.
2. **Design decision**
   - đọc `design-system/komibook/MASTER.md`;
   - đọc override của trang nếu có;
   - chạy truy vấn UI/UX Pro Max đúng domain/stack khi cần;
   - không tự tạo visual language riêng cho từng trang.
3. **Source update**
   - sửa trong phạm vi batch;
   - giữ nguyên business contract, route guard và quyền actor;
   - hot reload trên local server.
4. **Live observation**
   - giữ trang đang sửa mở trong in-app browser;
   - kiểm tra 375, 768, 1024 và 1440px;
   - thao tác chuột, bàn phím, form, modal, menu, back/deep link;
   - kiểm tra guest/customer/author/vendor/admin theo route.
5. **Page gate**
   - không horizontal overflow;
   - không text/body nhỏ khó đọc trên mobile;
   - touch target và focus đạt yêu cầu;
   - loading/error/empty state không làm vỡ layout;
   - không dùng dữ liệu mock để che lỗi API;
   - lint/test liên quan qua.
6. **Báo cáo**
   - báo cáo ngay sau mỗi trang/cụm trang nhỏ;
   - để lại trang vừa nghiệm thu trong browser cho người dùng kiểm tra;
   - tiếp tục trang kế tiếp nếu người dùng không gửi phản hồi ngắt luồng.

Một ledger riêng sẽ được tạo ở 6A.0, mỗi dòng gồm: route, role, baseline, viewport, interaction, accessibility, API state, result và ghi chú người dùng.

## 4. Phân bổ 25 source batch

### 6A — Design system và nền UI

#### 6A.0 — Audit, page inventory và visual ADR (không sửa source)

- lập route/view/role inventory;
- lập page ledger;
- kiểm kê token, raw hex, font, spacing, radius, shadow, z-index và motion;
- chạy UI/UX Pro Max design-system cho public, dashboard và reader;
- tạo đề xuất `design-system/komibook/MASTER.md` và page override;
- trình visual ADR trước khi thay token diện rộng.

#### 6A.1 — Semantic tokens và primitives

- màu, typography, spacing, radius, elevation, motion, focus;
- button, input, select, dialog, table wrapper, alert, skeleton, empty/error state;
- không đổi business logic.

#### 6A.2 — Responsive application shells

- `AppHeader`, `AppFooter`, public main shell;
- `AdminLayout`, `UserSidebar`;
- skip link, focus-on-route-change, mobile menu, safe area và z-index.

**Gate 6A:** design-system, primitives và shell đạt visual/accessibility gate.

### 6B — Navigation toàn hệ thống

#### 6B.1 — Public navigation

- Trang chủ, Danh mục, Sách cũ, Tin tức, Tủ sách;
- submenu 10 thể loại published nhiều nhất;
- search, user menu, cart, notification;
- desktop/mobile state nhất quán.

#### 6B.2 — Role navigation

- Author/Vendor/Admin/User navigation;
- active state, submenu, breadcrumb, deep link và back behavior;
- không trộn quyền giữa các actor.

**Gate 6B:** navigation smoke cho guest/customer/author/vendor/admin.

### 6C — Trang chủ dạng feed

#### 6C.1 — Hero và recommendation

- CMS/campaign hero carousel;
- “Gợi ý dành riêng cho bạn” với consent/fallback;
- skeleton, empty, error độc lập.

#### 6C.2 — Vendor Feed

- bài viết mới từ NXB/Tác giả đã duyệt;
- card bài viết, metadata, CTA và responsive.

#### 6C.3 — Commerce/content feed

- bán chạy và mới nhất, tách ebook/sách vật lý;
- ebook đọc thử và sách cũ giá tốt;
- giới hạn số lượng, thứ tự feed và failure isolation.

**Gate 6C:** home feed ở guest/customer, dữ liệu API thật, không hardcode production.

### 6D — Catalog và khám phá sách

#### 6D.1 — Catalog filters

- lứa tuổi;
- sáu mốc giá;
- loại sách và Sách cũ;
- URL state, back/forward, keyboard và mobile filter drawer.

#### 6D.2 — Book card và cover system

- không nén/crop bìa;
- ảnh bìa không bo góc;
- nhãn ebook version, used-book condition, price/promotion/status;
- touch/hover/focus và responsive grid.

#### 6D.3 — Detail và discovery pages

- Book Detail, Flash Sale;
- Blog, Article;
- quick view, review, sample và related/series cards.

**Gate 6D:** catalog → detail → cart/sample smoke ở bốn viewport.

### 6E — Hành trình khách hàng

#### 6E.1 — Authentication

- Login, Register, Forgot/Reset Password, Account Verification;
- Author/Vendor registration entry;
- OTP, social completion và form feedback.

#### 6E.2 — Cart và Checkout

- cart/mixed format;
- ebook terms;
- coupon, address, payment, order summary;
- checkout success/failure.

#### 6E.3 — Account và engagement

- Profile, Wishlist, Notifications;
- Help Center và Customer Support.

#### 6E.4 — Orders và after-sales

- Orders, Order Tracking, invoice print;
- Returns và Return Management;
- trạng thái, timeline, table/card responsive.

#### 6E.5 — Reading experience

- My Library, Ebook Reader, My Annotations;
- version selector chỉ trong reader;
- mobile controls, reduced motion, focus và long-reading comfort.

**Gate 6E:** critical customer journey và responsive/accessibility regression.

### 6F — Author Panel

#### 6F.1 — Author dashboard và commerce

- dashboard, analytics, works;
- fulfillment address, used books, promotions;
- copyright và royalty.

#### 6F.2 — Writing and publishing

- Author Studio;
- Live Editor, preview, chapter/revision/publishing flows;
- autosave/loading/error/unsaved-change feedback.

**Gate 6F:** approved Author thao tác toàn bộ chức năng mà không cần route Vendor.

### 6G — Vendor Panel

#### 6G.1 — Dashboard, books và publishing

- dashboard/analytics;
- books, book form, series, chapters;
- publishing workflow và DRM.

#### 6G.2 — Inventory và orders

- warehouses, inventory audit, stock transfer/print;
- orders, order detail và returns.

#### 6G.3 — Finance và promotions

- finance;
- Flash Sale;
- data tables, charts, filters và mobile fallbacks.

**Gate 6G:** Vendor critical journey và cross-role denial smoke.

### 6H — Admin Panel

#### 6H.1 — Core administration

- dashboard, users/detail;
- vendor/author approvals;
- books, categories, publishing/review moderation.

#### 6H.2 — Commerce and finance

- promotions/Flash Sale detail;
- finance report, fee schedules, reconciliation, memberships.

#### 6H.3 — Content and operations

- articles;
- notification create/campaign/analytics;
- help desk/ticket và system config.

**Gate 6H:** dense tables/forms/charts ở desktop và mobile card/scroll fallback.

### 6I — Footer, information và policy

#### 6I.1 — Information pages

- Về KomiBook;
- Dành cho Tác giả;
- Liên hệ;
- FAQ.

#### 6I.2 — Versioned policy pages

- Điều khoản sử dụng;
- Chính sách bảo mật;
- Chính sách ebook;
- sách cũ–trả hàng–hoàn tiền;
- bản quyền/hàng giả;
- footer links và checkout policy version.

**Gate 6I:** nội dung, links, versioning và checkout references.

## 5. Gate hoàn tất Giai đoạn 6

- toàn bộ page ledger không còn P0/P1 UI;
- visual check 375/768/1024/1440; edge check 320 và 1536 cho shell/group gate;
- guest/customer/author/vendor/admin browser smoke;
- keyboard, focus, contrast, semantics và reduced-motion;
- frontend tests/lint/build;
- backend focused tests cho API/feed/policy bị tác động;
- full regression ở Gate 6;
- không còn footer “Chưa có trang”;
- không mock dữ liệu production;
- không commit, push hoặc deploy nếu chưa có yêu cầu riêng.

## 6. Quy tắc phối hợp

- Mỗi source batch có kế hoạch và prompt Antigravity dự phòng, nhưng Codex trực tiếp sửa trong thời gian Antigravity hết quota theo ủy quyền hiện tại.
- Codex độc lập nghiệm thu mọi batch.
- Người dùng có thể ngắt bất kỳ lúc nào để yêu cầu sửa trang đang mở; phản hồi đó được xử lý trước khi tiếp tục.
- Báo cáo từng trang/cụm nhỏ và từng batch; không chờ xác nhận mặc định, ngoại trừ thay đổi visual ADR, kiến trúc/phạm vi, Git history, production, database thật, credential, dịch vụ ngoài hoặc chi phí.

