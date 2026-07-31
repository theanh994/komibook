# Phase 4C.3 — Flash Sale and promotion integrity plan

## Objective

Replace date-only promotional display with an auditable campaign/enrollment lifecycle and immutable checkout pricing snapshots.

## Invariants

- Campaign lifecycle is admin-controlled: `draft -> enrollment_open -> active -> ended`; draft/open campaigns may be cancelled. Public reads require active lifecycle and current time.
- Vendors may enroll only their own eligible, published books in an enrollment-open campaign. Admin approval/rejection records actor, reason and event.
- An approved book cannot overlap another non-cancelled campaign interval. Stored sale price, timezone, quantity limit and coupon-stacking policy are explicit.
- Checkout resolves active approved promotion server-side, enforces quantity atomically, and snapshots list price, sale price, promotion discount, campaign/item and stacking policy on the order line/invoice.
- `deny` stacking fails closed when a coupon is supplied; `allow` applies coupon after the flash price. Refunds continue to use immutable invoice/order snapshots, never the mutable catalog.

## Antigravity handoff prompt (retained; execution waived by user)

Modify only Phase 4C.3 promotion-integrity source described here. Preserve all local changes. Implement admin lifecycle, vendor enrollment, approval/rejection eligibility, time/timezone, overlap, stacking policy, atomic quantity limit, public active+approved reads, immutable checkout/refund snapshots and audit/notification. Do not touch real databases/credentials/services, commit, push or deploy. Codex independently reviews and runs gates.

## Acceptance

- Permission, lifecycle, eligibility, overlap, stacking, quantity, public visibility, snapshot and refund regression pass.
- Frontend tests/build and temporary SQLite migration rehearsal pass.
- HEAD remains unchanged; no commit/push/deploy.

## Codex acceptance — 2026-07-26

Accepted locally under the user's direct-edit quota override.

- Focused promotion suite: 4 tests, 44 assertions passed.
- Nearby checkout, inventory, public API and refund/invoice regression: 48 tests, 309 assertions passed.
- Admin lifecycle, vendor ownership/eligibility, approval/rejection audit/notification, overlap rejection, active+approved public reads, allow/deny coupon stacking, atomic quantity limit and immutable order/invoice promotion snapshots are covered.
- Frontend focused suites: 3 files, 7 tests passed; production build passed (existing large-chunk warning only).
- Initial SQLite rollback exposed an index-drop ordering defect in the new migration; Codex corrected the single migration and the complete fresh/rollback/reapply retry passed. Both temporary databases were removed.
- Pint and `git diff --check` passed. No commit, push, deploy, real-database write, credential, service or billing action was performed.
