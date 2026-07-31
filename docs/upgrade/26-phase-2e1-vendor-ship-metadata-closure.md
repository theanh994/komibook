# Batch 2E.1 — Vendor Ship Metadata Closure

## Status

The current narrow gate passes 52 tests with 176 assertions and targeted Pint.
One fail-closed defect remains in the late vendor-ship retry.

## Required correction

- The canonical payload of `processing -> shipped` is
  `shipping_status = pending_pickup`.
- Do not construct the expected retry payload from the existing operation's own
  metadata. That makes corrupted durable metadata self-validating.
- Always compare the stored operation against the independently derived
  canonical value `pending_pickup`.
- Add a test that:
  1. performs the vendor ship operation;
  2. progresses shipping to `picked_up` or `delivering`;
  3. corrupts the original order operation metadata to another shipping status;
  4. retries the original vendor ship operation;
  5. proves the retry fails closed without changing order, shipping,
     operation/ledger counts, vendor balance, or user points.
- Keep the existing valid late retry test passing.

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
