# Batch 2E.1 — Final Contract Correction

## Status

The first correction passes its narrow tests (37 tests, 122 assertions), its
targeted Pint gate, and `git diff --check`. Batch 2E.1 is still not accepted
because several approved fail-closed and idempotency invariants are not
implemented by the service and are not covered by the tests.

## Required corrections

### `backend/app/Services/OrderFulfillmentService.php`

1. Validate actor identity before authorization:
   - Accept only explicitly supported actor types.
   - A `vendor` actor requires a non-null user ID and must own the order.
   - A trusted internal actor, if retained for ebook completion, must be an
     explicit supported type; arbitrary strings must never bypass
     authorization.
2. Enforce exactly `processing -> shipped` for vendor order fulfillment.
   `confirmed -> shipped` is not allowed by the approved Batch 2E.1 contract.
3. Make operation matching exact:
   - Always compare order, transition kind, `from_state`, `to_state`, actor type,
     actor ID, and the canonical business metadata/payload.
   - Do not skip `from_state` comparison merely because the current projection
     already equals the destination state.
   - Shipping metadata must include and verify the carrier/tracking payload
     relevant to the operation.
   - Completion metadata must be compared exactly on retry.
4. Make immutable snapshot validation fail closed:
   - The referenced checkout session must exist and be locked.
   - Its customer must equal the order customer.
   - Its currency must be exactly `VND`.
   - Snapshot vendor ID must be present and equal the order vendor.
   - Reject every missing or inconsistent relationship before ledger or
     projection writes.
5. On a completion retry, verify exact ledger contents and projections:
   - earning ledger vendor, operation key, gross, commission, net and currency;
   - loyalty ledger user, operation key, type and points (or its required
     absence when points are zero);
   - completed order/payment/shipping projection as appropriate;
   - vendor balance and user points must be consistent with the durable
     operation rather than merely checking that ledger rows exist.
   Any mismatch must fail closed.

### `backend/tests/Feature/OrderCompletionLedgerTest.php`

Add focused negative and retry tests proving:

- unsupported actor types cannot mutate an order;
- `confirmed -> shipped` is rejected without mutation;
- an operation key reused with conflicting `from_state`, carrier/tracking data,
  or completion metadata is rejected;
- a missing checkout session and non-VND checkout session are rejected;
- null/mismatched snapshot vendor is rejected;
- corrupted earning values, loyalty values, vendor balance, user points, or
  completion projection are detected on retry;
- valid identical retries still succeed without duplicate ledgers or projection
  increments.

### `backend/tests/Feature/AuthorDrmInventoryTest.php`

Remove the direct manual assignment to `shipped` and `delivering`. Exercise the
approved fulfillment transitions through `OrderFulfillmentService` or the
actual vendor endpoints before delivery completion.

## File boundary

Only these source files may be modified:

- `backend/app/Services/OrderFulfillmentService.php`
- `backend/tests/Feature/OrderCompletionLedgerTest.php`
- `backend/tests/Feature/AuthorDrmInventoryTest.php`

Stop and report before touching any other source file. Do not modify this plan.

## Narrow acceptance gates

```powershell
php artisan test tests/Feature/OrderCompletionLedgerTest.php tests/Feature/AuthorDrmInventoryTest.php
.\vendor\bin\pint --test app/Services/OrderFulfillmentService.php tests/Feature/OrderCompletionLedgerTest.php tests/Feature/AuthorDrmInventoryTest.php
```

Report changed files, test count, assertion count, and formatting result. Do not
run the full regression, commit, push, or perform any production operation.
