# Phase 4A.3 — Payout and notification campaign operations

Date: 2026-07-26  
Status: implemented locally; no commit, push, deployment or real database operation

## Payout contract delivered

- Payout creation locks the vendor row, subtracts active refund holds from availability and prevents overdraw.
- Creation is idempotent and records an explicit reservation ledger entry plus an initial transition event.
- State machine: `pending -> approved -> processing -> completed`, or `pending -> rejected`.
- Approval/rejection records reviewer, timestamp and reason. Processing requires a bank transfer reference. Completion requires both transfer reference and evidence.
- Rejection releases the reserved balance exactly once. `total_withdrawn` is incremented exactly once and only at `completed`.
- Every transition has an idempotency key and an append-only transition record.
- Sensitive transition endpoints retain the verified-email and recent-authentication gates introduced in 4A.2.
- Legacy payout rows are preserved and receive explicit `legacy_import` ledger/transition records without fabricating unavailable historical balance snapshots.

## Campaign contract delivered

- Scheduled campaigns are selected each minute by `campaigns:dispatch-due` with bounded selection and overlap protection.
- HTTP and scheduler paths only create recipient chunks of at most 200 users and enqueue jobs; they do not load the full audience into a single request transaction.
- Dispatch keys, unique campaign/chunk rows and unique per-user notification operation keys make scheduling and retry idempotent.
- Jobs retry with backoff, record per-chunk success/failure, sanitize terminal errors and aggregate partial-failure state for admin retry.
- Audience eligibility is rechecked inside each job. Only customers with active explicit marketing consent are eligible; opt-out is available from Profile.
- Unsupported `fiction_enthusiasts` segmentation fails closed until a consented data source exists.
- Sent campaigns and campaigns with dispatch history cannot be edited/deleted, preserving operational evidence.
- Open/click/delivery rates are `null` while no real consented tracking source exists. The UI no longer shows hard-coded marketing percentages.

## Focused acceptance evidence

- Backend gate: 24 tests passed, 129 assertions across new payout/campaign tests, Phase 3 operational-truth regression and Phase 4A.1 return/refund regression.
- New 4A.3 coverage includes reservation idempotency, overdraw, ledger completion/release, admin authorization, due-scheduler idempotency, 201-recipient chunking, consent recheck, per-user idempotency and sanitized partial failure.
- Frontend: 8 Vitest files passed, 59 tests.
- Frontend production build passed. The existing large EbookReader chunk warning remains non-blocking.
- Targeted Laravel Pint passed.
- New migrations were exercised through SQLite `RefreshDatabase`; the consolidated fresh/rollback/reapply rehearsal remains reserved for Gate 4A.

## Scope boundary

No real email was sent by the new queue-fake acceptance tests. No production, service, scheduler daemon, Cloudflare, credential, real database, commit, push or deployment action was performed.
