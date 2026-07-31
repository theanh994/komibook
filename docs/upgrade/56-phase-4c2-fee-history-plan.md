# Phase 4C.2 — Commission and service-fee history plan

## Objective

Move financial rate selection out of mutable JSON into append-only, effective-dated database schedules. Every checkout must persist the selected schedule, rates and calculated amounts so later configuration changes cannot alter order, invoice, earning or refund history.

## Scope and invariants

- A schedule contains commission and service-fee percentages in the inclusive range 0–100, an effective timestamp, actor and mandatory reason.
- Schedule rows are immutable; new policy is a new effective-dated row. No historical financial backfill is inferred.
- Selection is deterministic: latest effective schedule at the checkout timestamp; when no row exists, the documented compatibility default is commission 10% and service fee 0% with no fabricated DB history.
- Preview uses the same calculator as checkout and performs no writes.
- Checkout stores schedule ID (nullable only for the compatibility default), both rates, fee amount and commission amount in its immutable financial snapshot.
- Configuration creation and audit event are one transaction; any failure rolls back both.

## Antigravity handoff prompt (retained; execution waived by user)

Modify only Phase 4C.2 fee-history source described here. Preserve all local changes. Add persistent effective-dated immutable rates, actor/reason audit, validation, read-only preview, deterministic checkout selection and immutable order/ledger snapshots. Do not backfill financial history, touch real databases/credentials/services, commit, push or deploy. Codex independently reviews and runs gates.

## Acceptance

- Permission, range validation, effective selection, preview, immutability, snapshot/history and rollback tests pass.
- Existing checkout/completion/refund contracts remain green.
- Frontend tests/build and temporary SQLite fresh/rollback/reapply pass.
- HEAD remains unchanged; no commit/push/deploy.

## Codex acceptance — 2026-07-26

Accepted locally under the user's direct-edit quota override.

- Focused fee suite: 3 tests, 39 assertions passed.
- Nearby checkout, completion-ledger and refund/invoice regression: 66 tests, 338 assertions passed.
- Admin-only append-only schedules, 0–100 validation, effective-date selection, read-only preview, actor/reason audit, operation conflict rollback and immutable checkout snapshots are covered.
- Mutable JSON financial inputs were removed from general configuration; the admin UI now uses the versioned fee API and displays history/preview.
- Frontend focused suites: 2 files, 5 tests passed; production build passed (existing large-chunk warning only).
- SQLite full fresh migration, fee migration rollback and reapply passed; the temporary database was removed.
- Pint and `git diff --check` passed. No financial history was backfilled; no commit, push, deploy, real-database write, credential, service or billing action was performed.
