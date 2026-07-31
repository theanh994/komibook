# KomiBook Phase 4A.1 — Return, Refund and Invoice Snapshot

Date: 2026-07-26  
Repository: `C:\Projects\DoAnTotNGhiep_komibook`  
Base commit: `87d578924de8dc8b9afa7266c562b17783d9ccfe`

This document records local implementation evidence for Batch 4A.1. It is not
a production deployment or approval to use real credentials, VNPAY, services,
or databases.

## Implemented boundary

- Immutable per-order invoice snapshot created inside the checkout transaction.
- Physical return window of seven days after the recorded delivery transition.
- Explicit return state machine with actor, reason, timestamp, and idempotent
  transition keys.
- Vendor/admin authorization and customer ownership enforcement.
- Inventory restoration at `item_received`, exactly once per reservation
  allocation.
- Refund hold at approval; append-only loyalty and vendor earning reversals only
  after confirmed settlement.
- COD cancellation before carrier pickup restores committed stock exactly once.
- COD refund completion requires manual evidence.
- VNPAY full/partial refund request signing, signed response verification,
  durable attempt history, safe retry, pending states, and signed `querydr`
  reconciliation before financial finalization.
- Customer, vendor, and admin return/refund interfaces sharing one status/action
  contract.
- Invoice print view reads only the immutable snapshot and identifies itself as
  a transaction snapshot rather than a statutory VAT e-invoice.

The VNPAY request fields, full/partial transaction types, checksum order,
response states, and 32-character request identifier were checked against the
official sandbox documentation:
`https://sandbox.vnpayment.vn/apis/docs/truy-van-hoan-tien/querydr%26refund.html`.

## Local acceptance evidence

- Focused backend and related Phase 2 regression:
  `61 tests, 394 assertions` — passed.
- Frontend Vitest: `8 files, 59 tests` — passed.
- Frontend production build — passed. The existing large-chunk warning remains
  informational and is not introduced as a correctness failure by this batch.
- Targeted Laravel Pint — passed.
- Temporary SQLite migration rehearsal:
  `migrate:fresh -> rollback latest migration -> re-apply -> status` — passed.
  The temporary database was removed afterward.
- API route audit: 11 customer/vendor/admin return and refund routes — passed.
- `git diff --check` — passed.

No real VNPAY request, production database mutation, deployment, commit, push,
credential change, Cloudflare operation, or service operation was performed.

## Deferred by the approved sequence

- Full backend regression and browser-role smoke are consolidated at Gate 4A
  after Batches 4A.2 and 4A.3, avoiding repeated gates between batches.
- Complete payout reservation/transition ledger remains Batch 4A.3. Batch 4A.1
  adds only the refund hold and locked availability compatibility needed to
  prevent a refund/payout race.
