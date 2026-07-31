# KomiBook Phase 5 final acceptance

Date: 2026-07-29  
Checkout: `C:\Projects\DoAnTotNGhiep_komibook`  
Branch: `master`  
HEAD and `origin/master`: `f53d5453ad80f25d20c332cf3538179b604abcd4`

## Final decision

Phase 5 is accepted locally in the approved order: ADR 62, 5A.1, 5A.2, 5B, 5C, 5D, 5E, 5F and Gate 5.

The Author and Vendor boundaries remain independent. Author Commerce uses an internal vendor profile only for the existing commerce ledger and never grants the user a Vendor role or Vendor routes. Product format, provenance, condition, fulfillment mode and return policy are explicit and snapshotted at checkout.

The delivered Phase 5 scope includes:

- a private, encrypted and verified author fulfillment address with one-address inventory rules;
- explicit product taxonomy and safe legacy backfill;
- eight-digit author email OTP with backend-verified Google bypass;
- Author Studio, chapter writing/preview and privacy-thresholded reader analytics;
- mandatory ebook terms, immutable ebook versions and continuing entitlement to purchased and newer versions;
- purchase-time snapshot of the latest ebook version, with the Library showing the purchased and latest version as read-only information;
- a version selector available only inside the ebook reader, limited to the purchased version and newer updates;
- used-book listings, actual-photo/condition/defect/attestation fields, single-location inventory and counterfeit disputes;
- author-scoped coupons and Flash Sale enrollment with ownership, eligibility and stacking enforcement;
- an Author Commerce screen for the private address, used-book inventory, coupons and Flash Sale enrollment.

## Acceptance evidence

- Full backend regression: 342 tests passed with 1,677 assertions.
- Phase 5 focused regression: 17 tests passed with 83 assertions.
- Frontend regression: 11 test files and 67 tests passed.
- Production frontend build passed. The existing large `EbookReaderView` chunk warning remains non-blocking and is not introduced as a functional failure by Phase 5.
- Targeted ESLint and Oxlint passed on the Phase 5 UI, reader, library and router files; Oxlint reported 0 warnings and 0 errors.
- `git diff --check` passed.
- All seven Phase 5 migrations passed a fresh temporary SQLite migration, rollback of the seven migrations and reapply. The migrations use Laravel Schema Builder and were statically checked for MySQL-compatible constructs; no production or shared MySQL database was used.
- Author route inventory confirms the OTP, fulfillment address, Studio/chapter, used-book, coupon and Flash Sale endpoints are protected by Sanctum, with admin-only verification/moderation routes remaining separate.
- Permission tests cover customer/author/vendor/admin, cross-author and cross-vendor denial, address non-disclosure and reader analytics without PII.

## Browser smoke

Browser smoke ran against an isolated temporary SQLite database and local-only servers:

- author login and registration show the eight-digit email OTP flow;
- approved Author can open Author Studio and the create-ebook dialog;
- ebook cart shows the no-return/update-entitlement terms and disables order submission until accepted;
- catalog cards display the latest ebook version and expose no purchase-time version selector;
- My Library displays the purchased and latest version as read-only information and opens the reader without a `version_id`;
- the reader defaults to the latest entitled version and contains the only version selector, limited to the purchased version and newer updates;
- Author Commerce displays the private-address verification state, used-book form and authenticity attestation, inventory controls, author coupon form and Flash Sale enrollment state.

No browser smoke submitted an order, sent a real OTP/email, uploaded a document, created an external campaign or wrote to a real database.

## Safety and repository state

- Existing local modified and untracked work was preserved; no reset, checkout, stash or clean was used.
- Work stayed in `C:\Projects\DoAnTotNGhiep_komibook`; the old Herd checkout was not used.
- Temporary browser and migration SQLite databases and local test servers were removed.
- No production action, credential change, real database write, external service activation or paid/billing action occurred.
- No commit, push or deploy was performed. HEAD remained unchanged and equal to `origin/master`.

This is local engineering acceptance, not a production rollout. Deployment and any isolated MySQL runtime rehearsal remain separate explicitly authorized operations.

## Post-acceptance correction — 2026-07-29

The user requested two corrections before Phase 6:

1. Author management must retain the familiar Vendor-style management shell while keeping Author capability and Vendor permission boundaries independent.
2. Customers always buy the latest ebook version. Version selection is forbidden in catalog, cart, checkout and Library, and is available only in the reader after purchase.

Accepted implementation:

- `/author/manage/*` now uses `AdminLayout` with an Author-specific sidebar.
- Author functions are split into separate pages for overview, works, reader analytics, fulfillment address, used books, promotions, copyright and royalty.
- existing `/author/dashboard`, `/author/studio`, `/author/commerce` and royalty links redirect to the corresponding management pages.
- public ebook resources expose the latest selling version; legacy ebooks without a version row are represented as version 1 until the immutable first version is created at checkout.
- checkout snapshots the latest version and creates the entitlement from that purchase version.
- Library has no version selector.
- reader access defaults to the latest entitled version and rejects versions older than the purchase version or belonging to another ebook.

Correction gates:

- `Phase5EbookRightsTest`: 2 tests passed, 19 assertions.
- Author focused regression: 9 tests passed, 47 assertions.
- Full backend regression: 342 tests passed, 1,677 assertions.
- Frontend regression: 11 files and 67 tests passed.
- targeted ESLint and Oxlint: passed, 0 warnings and 0 errors.
- production frontend build: passed; the pre-existing large reader chunk warning remains non-blocking.
- `git diff --check`: passed.
- local browser smoke confirmed the Author sidebar and all separated routes, ebook version badges, a read-only Library version summary, latest-by-default reader access and switching from update version 2 to purchase version 1 without a URL purchase-selection parameter.

The correction used only the isolated local review SQLite database. It did not submit a real order, access production, change a real credential, send an external message, commit, push or deploy.
