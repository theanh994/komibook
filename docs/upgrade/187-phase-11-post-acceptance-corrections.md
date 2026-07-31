# Phase 11 — Post-acceptance corrections

Date: 2026-07-30

## Scope

This correction batch addresses the ten issues reported after the Newsroom acceptance:

1. Vendor navigation freezing after Warehouse Personnel.
2. Missing Vendor Articles API at runtime.
3. Overflow in Warehouse Documents forms.
4. Vendor Flash Sale request and voucher creation workflows.
5. Vendor logout freezing on management pages.
6. Admin sidebar scrollbar presentation.
7. Missing Admin Potential Reviews API at runtime.
8. Unified Admin review and article-comment moderation.
9. Public Newsroom search, searchable review book selection, and rich-text review editing.
10. Vendor registration form discoverability.

## Implemented corrections

- Force role-layout route remounting and deterministic navigation; logout clears local authentication first and hard-navigates home.
- Add Newsroom endpoints and tenant-scoped Vendor Article management.
- Keep Warehouse Documents fields inside responsive columns.
- Add Vendor Flash Sale requests that are independent of active campaigns.
- Add tenant-scoped Vendor vouchers with a mandatory Admin approval state.
- Add Admin review/reject actions for Flash Sale requests and voucher status control.
- Hide the Admin sidebar scrollbar while preserving scrolling.
- Merge review moderation and article-comment moderation under `/admin/moderation`; legacy URLs redirect there.
- Redesign public Newsroom filters as a compact right-aligned search panel.
- Replace the review book picker with search suggestions and add a Quill rich-text toolbar.
- Expose the existing Vendor application path to every authenticated account without an active Vendor profile.

## Acceptance evidence

- Backend targeted regression: 28 tests passed, 205 assertions.
- Frontend full regression: 25 files passed, 105 tests.
- Scoped ESLint and Oxlint: 0 warnings, 0 errors.
- Frontend production build: passed.
- Fresh migration rehearsal using isolated in-memory SQLite: passed through migrations `190000` and `191000`.
- `git diff --check`: passed.

## Runtime acceptance

- With explicit approval, migrations `2026_07_30_190000_create_newsroom_tables.php` and `2026_07_30_191000_add_vendor_promotion_workflows.php` were applied successfully to the real database as batch 15.
- The FrankenPHP workers were reloaded successfully through the local Admin API. `FrankenPHPService` and `cloudflared` remained `Running` and `Automatic`; the Cloudflare mapping was not modified.
- Origin `127.0.0.1:8080` intentionally remains on committed release `4da2c423a42e7948765f53f7758bd40777bd5851`, so it cannot serve the uncommitted correction source.
- For local acceptance only, `frontend/.env.local` now targets the already-running checkout backend at `127.0.0.1:8000`. Vite `127.0.0.1:5173` was restarted.
- Through `5173`, public Articles and Books return HTTP 200; protected Vendor Articles, Admin Article Submissions, and Admin Flash Sale Requests return HTTP 401 before authentication instead of HTTP 404. This proves that the new routes are active in the local acceptance runtime.

No commit, push, production release deployment, or Cloudflare configuration change was performed.

## Follow-up correction batch — 2026-07-31

### Accepted scope

This follow-up implements the six issues reported during the next Admin/Vendor review:

1. Structured Admin user management with search, role filter, explicit sort order, and table/card display modes.
2. Removal of the duplicate Vendor Profile entry from the Admin sidebar.
3. Compact book-review moderation with rating, date, shop, and reported-content filters; ten reviews per page.
4. Search-first article product selection, series selection, and inline product/series links in rich text. Vendor articles require a related product, while Admin system/event news may omit one. Article taxonomy is derived from the selected books.
5. Approved Flash Sale campaign materialization, Vendor/follower notifications, shop follow/visit tracking, and Home sections ordered as active Flash Sale, upcoming countdown, recommendations, featured books, used books, then news. Upcoming campaigns expose at most four shops ordered by visit count.
6. Deterministic role-layout navigation and normalized warehouse book-cover URLs with a local fallback image.

### Data design

- Migration `2026_07_31_193000_create_vendor_follows_and_visit_counter.php` adds the indexed Vendor visit counter and the unique customer-to-Vendor follow relation.
- The migration was rehearsed only on an isolated SQLite file: fresh migrate, rollback, reapply, and status checks passed.
- The real local and production databases were not changed in this batch.
- Public read paths include schema-compatibility fallbacks until the migration is explicitly approved and applied. Follow mutation remains unavailable until its table exists.

### Acceptance evidence

- Backend full regression: 339 tests passed, 1833 assertions.
- Backend Phase 11 targeted regression: 4 tests passed, 46 assertions.
- Frontend full regression: 25 files passed, 107 tests.
- Frontend production build: passed; only the existing large-chunk advisory remains.
- Target PHP formatting and syntax checks: passed.
- `git diff --check`: passed.

### Browser acceptance

- Home: no broken cover images, no horizontal overflow, and the retired Ebook sample section is absent. The upcoming countdown remains hidden when the current database has no eligible campaign.
- Admin Users: table/card modes work, each mode presents ten records, and only one Vendor approval/profile entry remains in the sidebar.
- Content Moderation: rating/shop/date/reported filters are visible and the compact layout has no overflow at desktop or 375 px.
- Article Editor: searchable product selection, series selection, selected-product chips, and the inline link helper are visible without horizontal overflow at desktop or 375 px.
- Shared Admin navigation was exercised from Users to Content Moderation without a stuck overlay or stale page.
- A direct Vendor-authenticated Warehouse Personnel browser run was not available in the retained Admin session; the shared navigation fix is covered by source review, regression tests, and the Admin role-layout navigation smoke.

No commit, push, production deployment, real-database migration, credential use, external-service change, or Cloudflare mapping change was performed.

## Storefront access correction — 2026-07-31

This focused correction closes four remaining review defects:

1. Management navigation now relies on the native RouterLink transition only. Warehouse Personnel also cancels its outstanding API requests when it is unmounted, preventing stale page state from surviving navigation.
2. The collapsed sidebar stacks the KomiBook logo and collapse control instead of forcing both controls into the same 72 px row.
3. Seller registration is now a discoverable desktop/mobile navigation destination. Guests are returned to the dedicated application form after login; the form is explicitly titled “Đăng ký trở thành Nhà bán”.
4. A public Vendor storefront now provides shop information, book discovery, follow/unfollow, follower count, visit count, login return, wishlist, cart, and buy-now paths. Book details and Flash Sale shop links lead to this storefront.

Acceptance:

- Backend full regression: 340 tests passed, 1848 assertions.
- Phase 11 focused backend regression: 5 tests passed, 61 assertions.
- Frontend full regression: 25 files passed, 109 tests.
- Focused frontend contract: 9 tests passed.
- Frontend production build and PHP formatting/syntax checks passed.
- Public local storefront API returned the real development Vendor, 12 books, and follower state successfully.
- Migration `2026_07_31_193000_create_vendor_follows_and_visit_counter.php` was applied only to the approved local development database so the follow flow can be inspected. Migration `192000`, production, Git history, credentials, external services, and Cloudflare were not changed.

The local frontend and backend were left running on `127.0.0.1:5173` and `127.0.0.1:8000`. Automated in-app-browser navigation to the local address was blocked by the browser URL policy, so the final visual interaction must be performed manually in the already-open local tab.
