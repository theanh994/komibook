# Phase 4C gate acceptance

Date: 2026-07-26  
Checkout: `C:\Projects\DoAnTotNGhiep_komibook`  
HEAD before/after: `87d578924de8dc8b9afa7266c562b17783d9ccfe`

## Accepted scope

- 4C.1 Editorial CMS: admin lifecycle, sanitized content, categories/tags/book links, scheduled publication, immutable revisions, public published-and-due visibility, audit and notification.
- 4C.2 Commerce fees: immutable effective-dated commission/service-fee schedules, preview and history, deterministic checkout resolution, and order snapshots. Mutable fee fields were removed from the system-configuration API/UI; no financial history was invented or backfilled.
- 4C.3 Promotion integrity: campaign lifecycle, vendor enrollment and ownership checks, audited approval/rejection, interval overlap prevention, timezone and stacking policy, atomic promotional quantity, public active-and-approved visibility, and immutable order/invoice pricing snapshots used by refunds.

## Direct evidence

- Full backend: 325 passed, 1,586 assertions.
- Full frontend: 11 files, 67 tests passed.
- Production frontend build passed after the configurable local backend proxy change; the existing large-chunk warning remains non-blocking.
- Targeted Pint check passed for all Phase 4C PHP production, migration, route and test files.
- `git diff --check` passed.
- Temporary SQLite integration rehearsal passed: full `migrate:fresh`, rollback of the three Phase 4C migrations, and reapply of all three migrations.
- The initial isolated 4C.3 rehearsal found an index-drop ordering defect. The same migration was corrected and its complete retry passed before this integrated gate.
- Browser smoke on temporary SQLite passed for:
  - admin dashboard, CMS article management, effective fee schedules and promotion management;
  - vendor dashboard and Flash Sale enrollment;
  - customer home, blog, Flash Sale and catalog public paths;
  - customer access to the admin article URL failed closed and did not render the protected component.

The browser and migration SQLite files were removed after verification. No real database, credential, external service, billing action or production environment was used.

## Formatting baseline note

A repository-wide Pint diagnostic still reports pre-existing style violations in legacy files outside the Phase 4C gate (including `scratch/test_mail.php`). It was not used to rewrite unrelated user changes. The one reported Phase 4C migration was formatted, then the complete Phase 4C target set passed Pint.

## Decision

Gate 4C accepted locally. No commit, push or deploy was performed.
