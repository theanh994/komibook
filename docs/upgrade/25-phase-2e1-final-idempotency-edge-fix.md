# Batch 2E.1 — Final Idempotency Edge Fix

## Status

The current narrow gate passes 49 tests with 160 assertions and targeted Pint.
Batch 2E.1 still has three uncovered retry defects confined to the fulfillment
service and its feature test.

## Required corrections

1. Treat both approved order edges as valid durable operations:
   - `processing -> shipped` for physical/vendor fulfillment;
   - `processing -> completed` for ebook completion.
   A second identical ebook completion call using the same default or explicit
   operation key must succeed without duplicate operations, ledgers, balance,
   or points.
2. Compare canonical metadata with strict value types. An integer and its
   string representation are not the same payload. Add a test that changes a
   stored numeric completion metadata value to a numeric string and proves the
   retry fails closed.
3. The durable vendor ship operation records
   `shipping_status = pending_pickup`. Repeating that same ship operation after
   shipping has legitimately progressed to `picked_up` or `delivering` must
   recognize the original operation and return idempotently without trying to
   compare its historical payload against the later projection. It must still
   detect a corrupted original operation, wrong actor, wrong order, or wrong
   operation key.
4. Add focused tests for the ebook retry and the late vendor-ship retry. Assert
   operation/ledger counts and balance/points are unchanged.

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
