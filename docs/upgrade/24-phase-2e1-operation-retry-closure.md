# Batch 2E.1 — Operation Retry Closure

## Status

The current closure passes 88 tests with 334 assertions and targeted Pint, but
Batch 2E.1 is not accepted until the remaining authorization and retry defects
are closed.

## Required corrections

### Actor boundary

- `updateOrderStatusByVendor` and `updateShippingStatus` must accept only a
  `vendor` actor with a non-null authenticated user ID that owns the order.
- A `system` actor must not be able to call either vendor method.
- `system` with null ID may remain explicitly supported only by
  `completeEbookOrder`.
- Add direct service tests proving `system/null` cannot ship or update shipping
  for any vendor order.

### Exact operation verification

- Remove the projection-based `fromStateMatches` bypass. Operation verification
  must never accept an arbitrary stored `from_state` merely because the order
  projection already equals `to_state`.
- Compare canonical metadata exactly, including types and meaningful nulls.
- For physical delivery completion, canonical operation metadata must include
  the effective shipping carrier and tracking code. Retrying the same operation
  key with different carrier/tracking must fail closed.
- Validate every stored shipping `from_state -> to_state` against the approved
  graph before accepting a retry.

### Stable retries for every shipping destination

- Make the default operation identity stable for identical requests without
  relying on a newly generated key derived from the post-transition current
  state.
- Valid identical retries must work for:
  - `pending_pickup -> picked_up`;
  - `picked_up -> delivering`;
  - each allowed transition into terminal `failed`;
  - `delivering -> delivered`.
- A retry of `failed` may return idempotently only when its original durable
  operation and payload match. It must remain impossible to transition from
  `failed` to another state.
- Add explicit tests for all four destinations; do not label a test as covering
  terminal states when it only exercises `picked_up`.
- Mutate a stored operation to an invalid `from_state` and prove its retry fails
  closed.

### Cumulative projection floor

- Compare vendor balance against the sum of durable earning-ledger net amounts
  for that vendor, not only the current order's earning.
- Compare user points against the sum of durable loyalty-ledger points for that
  user.
- Add separate tests for corrupted vendor balance and corrupted user points,
  including more than one ledger contribution.

## Allowed source files

- `backend/app/Services/OrderFulfillmentService.php`
- `backend/tests/Feature/OrderCompletionLedgerTest.php`

Do not modify any other source file.

## Acceptance gates

```powershell
php artisan test tests/Feature/OrderCompletionLedgerTest.php tests/Feature/AuthorDrmInventoryTest.php
.\vendor\bin\pint --test app/Services/OrderFulfillmentService.php tests/Feature/OrderCompletionLedgerTest.php
```

Report test/assertion counts and Pint result. Do not run full regression, begin
Batch 2E.2, commit, push, or perform production operations.
