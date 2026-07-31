# Batch 2E.1 — Security and Ledger Correction

## Status

Batch 2E.1 is not accepted yet. The targeted formatting gate passes, but the
targeted feature suite currently reports 6 failures, and static review found
authorization, state-machine, immutable-snapshot, and idempotency gaps.

This correction remains inside Batch 2E.1. It must not add outbox/job work
reserved for Batch 2E.2.

## Direct evidence

- Targeted feature result: 27 passed, 6 failed, 112 assertions.
- `AuthorDrmInventoryTest` passes in the targeted run.
- The bulk route is `PATCH api/vendor/orders/bulk-status`, while the new test
  currently uses `POST`.
- The targeted Pint check for Batch 2E.1 files passes.
- `git diff --check` passes.
- Current ledger tests incorrectly expect gross revenue although the immutable
  checkout snapshot contains a 10 percent commission.

## Required source corrections

### 1. Vendor authorization and BOLA protection

- Every vendor-driven single, bulk, and shipping transition must prove that the
  authenticated actor owns the order's `vendor_id`.
- Do not use `system`/null actor identity for the authenticated vendor shipping
  endpoint.
- The bulk operation must lock rows in deterministic order and remain atomic:
  one foreign or invalid order rolls back the whole batch.
- Add negative tests showing Vendor A cannot mutate Vendor B's order through
  any of the three endpoints and that no order, shipping, operation, ledger,
  balance, points, or stock state changes.

### 2. Strict fulfillment state machine

- Vendor order status supports only `processing -> shipped`.
- Shipping supports only:
  `pending_pickup -> picked_up -> delivering -> delivered`.
- `failed` is terminal in this batch. It must not resurrect and must not trigger
  cancellation, refund, release, or restock behavior.
- Delivery completion is allowed only from `delivering`; direct delivery from
  null, `pending_pickup`, `picked_up`, or `failed` must fail closed.
- An ebook completion entry point must require a non-empty order whose items
  are all ebooks. A physical or mixed order must not bypass shipping.

### 3. Immutable financial snapshot and exact money

- Completion must require and lock the matching `CheckoutSessionOrder`
  snapshot. Remove fallback to mutable `orders.total_amount`.
- Validate snapshot ownership and consistency with the order, vendor, checkout
  session, and VND currency before any write.
- Require and lock the customer and vendor projections needed for completion.
- Gross and commission must be non-negative integers, commission must not
  exceed gross, and net must equal `gross - commission` without clamping.
- Change monetary ledger columns to unsigned 64-bit integers, consistent with
  existing VND amount columns.
- Missing or inconsistent snapshot/projection data must roll back without
  partial transition, ledger, balance, points, or stock mutation.

### 4. Durable idempotency and corruption detection

- A reused operation key is idempotent only when its order, transition kind,
  from/to state, actor identity, and canonical business payload match.
- A conflicting reuse must fail closed without mutation.
- A same-state retry must be backed by the expected durable transition
  operation; do not silently return merely because the order already has the
  destination state.
- Completion retries must verify the complete ledger set and projection
  consistency. Existing earning data must not mask a missing transition or
  loyalty ledger.
- Validate supported actor types and require the actor ID where applicable.

### 5. Correct and strengthen the tests

- Use the actual `PATCH` bulk endpoint.
- Do not use a nonexistent `OrderFactory`; create orders with explicit valid
  fixtures.
- Assert the snapshot net amount (90 percent under the current default
  fixture), not gross revenue.
- Exercise the real fulfillment graph instead of manually forcing `shipped`.
- For the shipping-failure stock assertion, compare stock before and after the
  failed transition or create/commit the reservation through the real flow;
  do not hard-code an unrelated quantity.
- Cover retries (including repeated calls), conflicting keys/payloads,
  transaction rollback, missing/inconsistent snapshots, invalid commission,
  ebook/physical separation, forbidden transition jumps, failed-terminal
  behavior, cross-vendor BOLA, and multi-vendor isolation.
- Include a schema/behavior assertion that values exceeding signed 32-bit range
  are represented safely by the ledger columns.

## Allowed source files

- `backend/database/migrations/2026_07_25_130000_create_order_completion_ledgers.php`
- `backend/app/Models/OrderTransitionOperation.php`
- `backend/app/Models/LoyaltyPointLedger.php`
- `backend/app/Models/VendorEarningLedger.php`
- `backend/app/Services/OrderFulfillmentService.php`
- `backend/app/Http/Controllers/Api/OrderController.php`
- `backend/app/Http/Controllers/Api/Vendor/OrderController.php`
- `backend/app/Http/Requests/Vendor/UpdateOrderStatusRequest.php`
- `backend/tests/Feature/OrderCompletionLedgerTest.php`
- `backend/tests/Feature/AuthorDrmInventoryTest.php`

If a correction genuinely requires another source file, stop and report the
exact dependency before editing it.

## Acceptance gates

Run the narrow gates first:

```powershell
php artisan test tests/Feature/OrderCompletionLedgerTest.php tests/Feature/AuthorDrmInventoryTest.php
.\vendor\bin\pint --test app/Http/Controllers/Api/OrderController.php app/Http/Controllers/Api/Vendor/OrderController.php app/Http/Requests/Vendor/UpdateOrderStatusRequest.php app/Models/OrderTransitionOperation.php app/Models/LoyaltyPointLedger.php app/Models/VendorEarningLedger.php app/Services/OrderFulfillmentService.php database/migrations/2026_07_25_130000_create_order_completion_ledgers.php tests/Feature/OrderCompletionLedgerTest.php tests/Feature/AuthorDrmInventoryTest.php
```

Then report changed files and results. Do not run the full backend/frontend
regression yet; Codex will consolidate those checks after the corrected narrow
gates pass.

Do not commit, push, change production configuration, or perform any production
operation.
