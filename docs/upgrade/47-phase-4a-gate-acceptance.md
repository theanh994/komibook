# KomiBook Phase 4A — Consolidated Gate Acceptance

Date: 2026-07-26  
Baseline and final HEAD: `87d578924de8dc8b9afa7266c562b17783d9ccfe`  
Status: accepted locally; no commit, push, deployment or production operation

## Accepted scope

Gate 4A consolidates the accepted contracts from:

- 4A.1 return, refund and immutable invoice snapshot;
- 4A.2 review, annotation, reading progress and account trust;
- 4A.3 payout ledger and consented notification campaign operations.

Antigravity's Gate correction added the now-required `page_number` to the existing valid-annotation regression fixture. Codex independently inspected that change and accepted it. Codex also applied one behavior-neutral import-order correction in `backend/bootstrap/app.php`, within the approved one-file direct-correction boundary.

## Independent automated evidence

- Full backend suite: **283 tests passed, 1309 assertions**.
- Full frontend suite: **8 files passed, 59 tests**.
- Frontend production build: passed.
- Laravel Pint on all changed PHP files: passed after the one-file import-order correction.
- `git diff --check`: passed.
- The existing Vite large-chunk warning for `EbookReaderView` remains non-blocking.
- The local PHP installation still warns that OpenSSL is loaded twice; this does not fail the gates.

## Migration rehearsal

A disposable SQLite database was used. No real database was touched.

- Full `migrate:fresh`: passed through all migrations, including 4A.1, 4A.2 and 4A.3.
- Rollback of the three Phase 4A migrations: passed.
- Reapply of the three Phase 4A migrations: passed.
- The temporary database was deleted afterward.

A separate attempt to roll back the entire historical migration chain exposed an older SQLite-only failure in `2026_07_17_173710_add_profile_fields_to_users_table` around `users_google_id_unique`. This is not a Phase 4 regression and did not affect the Phase 4A rollback/reapply rehearsal.

## Browser smoke evidence

Browser smoke ran against isolated local PHP/Vite servers, a disposable SQLite database, fake users and local-only session settings. The repository `.env`, production services and real data were not changed.

Verified without triggering financial or delivery actions:

- Customer return/refund screen renders the eligible-order and empty-history states.
- Customer Profile renders explicit marketing consent; the consented fixture is checked and remains user-editable.
- Customer security screen lists the current session and exposes session revocation controls.
- Admin payout reconciliation renders pending, approved, processing, completed and rejected filters.
- Admin campaign screen renders empty operational counters and explicitly shows `Chưa có telemetry` for open/click rates.
- Vendor finance renders the disposable `1,000,000 VND` available balance, zero completed withdrawal and empty payout history.
- Vendor return management renders the empty active-return state.

No payout, return transition, refund, campaign send, password change or session revocation was submitted during browser smoke.

## Cleanup and safety

- Antigravity's accidental untracked SQLite artifact `backend/komibook` was deleted after explicit user approval and after verifying its SQLite signature.
- Every local server and disposable database/log created for browser smoke was stopped or removed.
- Ports 8000 and 5173 had no remaining smoke listener at cleanup.
- No documentation or source change was reset, checked out, stashed or cleaned.
- No commit, push, deployment, Cloudflare, credential, paid provider, real email or production database action occurred.

## Decision

**Gate 4A is accepted.** The next required activity is the Author–Vendor role ADR. No 4B source work may begin until that ADR is approved.
