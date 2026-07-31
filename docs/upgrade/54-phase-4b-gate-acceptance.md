# Phase 4B gate acceptance

Date: 2026-07-26  
Checkout: `C:\Projects\DoAnTotNGhiep_komibook`  
HEAD before/after: `87d578924de8dc8b9afa7266c562b17783d9ccfe`

## Accepted scope

- 4B.1 Author onboarding: private real profile, audited approval/correction/suspend/revoke, independent capability.
- 4B.2 Vendor onboarding: independent legal/payout profile, active-vendor commerce boundary and cross-vendor isolation.
- 4B.3 Copyright: explicit book authors/delegations, private evidence, overlap prevention, audited verification/dispute/revoke; DRM cannot verify rights or publish.
- 4B.4 Self-publishing/editor: gated lifecycle, review feedback, autosave/revisions/restore/order/import, sample preview, signed ebook path retained, published snapshots and versioned multi-author royalty acceptance.

Legacy published books keep their existing public compatibility state through nullable `publishing_status`; no rights, agreement, royalty or financial facts are backfilled or inferred. All newly managed publication paths require the new eligibility workflow.

## Direct evidence

- Full backend: 314 passed, 1,461 assertions.
- Frontend: 8 files, 60 tests passed.
- Production build passed; existing large-chunk warning remains non-blocking.
- Focused 4B.4: 5 passed, 42 assertions. Nearby 4B/DRM regression: 39 passed, 153 assertions.
- 4B.3 and 4B.4 migrations each passed temporary SQLite fresh, isolated rollback and reapply.
- `git diff --check` passed.
- Browser smoke on temporary seeded SQLite:
  - admin dashboard and publishing review loaded;
  - vendor book list and publication workflow loaded;
  - approved author dashboard and royalty agreement list loaded;
  - customer catalog returned 39 results and vendor route guard redirected to home;
  - no target-page console errors.

The browser smoke database and temporary console configuration were deleted after verification. No real database, credential, external service or production environment was used.

## Decision

Gate 4B accepted. Phase 4C may begin. No commit, push or deploy was performed.
