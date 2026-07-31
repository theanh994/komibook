# Batch 2E.1 — Snapshot and Idempotency Closure

## Status

The current narrow gates pass (42 tests, 137 assertions; targeted Pint passes),
but Batch 2E.1 remains unaccepted.

The previous correction contract required an immutable snapshot vendor ID while
its file boundary did not permit the schema, model, or checkout write-path
changes needed to implement that invariant. The implementation substituted an
order-item vendor check, which is useful defense in depth but is not an
immutable vendor snapshot.

This closure corrects that file boundary and resolves the remaining
idempotency and actor-validation gaps.

## Required implementation

### Immutable vendor snapshot

1. Add a non-null `vendor_id` foreign key to `checkout_session_orders` in the
   existing Phase 2 schema migration.
2. Add it to the `CheckoutSessionOrder` model's assignable attributes and add
   the appropriate vendor relationship if useful.
3. Persist the grouped vendor ID when `CheckoutService` creates each immutable
   session-order snapshot.
4. In `OrderFulfillmentService`, require the locked snapshot's `vendor_id` to
   equal the locked order's `vendor_id`. Keep the order-item vendor check only
   as additional defense, not as a substitute.
5. Update all direct test fixtures that construct `CheckoutSessionOrder` so the
   new non-null foreign key is populated.
6. Extend schema/write-path tests to prove each multi-vendor session snapshot
   stores the correct vendor and rejects an invalid vendor reference.

### Actor validation

1. Remove actor types that have no approved caller.
2. `vendor` requires a non-null user ID and ownership of the order.
3. If `system` remains for an internal ebook caller, define its ID rule
   explicitly and reject invalid combinations.
4. No arbitrary or under-validated actor type may bypass authorization.

### Exact operation payload and retries

1. Compare the complete canonical metadata object, not merely a subset of
   expected keys. Preserve meaningful null values; do not use `array_filter`
   to erase parts of the business payload.
2. Shipping operation metadata must canonically include order status, carrier,
   and tracking code.
3. Make the default shipping operation key stable across identical retries.
   A retry after the projection reaches the destination must find and validate
   the original transition instead of generating a key from the new current
   state.
4. Validate that an existing operation's stored `from_state -> to_state` is a
   permitted graph edge. Valid identical retries for `picked_up`, `delivering`,
   `failed`, and `delivered` must succeed without new operations or mutations.
5. Reusing the same key with changed carrier/tracking/actor/order/transition or
   extra/missing metadata must fail closed.

### Completion ledger/projection verification

1. Verify exact earning and loyalty ledger contents on retry.
2. Verify the order completion projection.
3. Verify the retry itself does not change vendor balance, user points, order,
   operation count, or ledger counts.
4. Because pre-existing balances/points and future earnings may exist, do not
   claim an exact absolute projection can be reconstructed from one order.
   At minimum, detect a projection below its cumulative durable ledger
   contribution and document this constraint in the test name/assertions.

## Allowed source files

- `backend/database/migrations/2026_07_25_100000_create_phase2_checkout_and_payment_tables.php`
- `backend/app/Models/CheckoutSessionOrder.php`
- `backend/app/Services/CheckoutService.php`
- `backend/app/Services/OrderFulfillmentService.php`
- `backend/tests/Feature/Phase2CheckoutPaymentSchemaTest.php`
- `backend/tests/Feature/Phase2CheckoutSessionWritePathTest.php`
- `backend/tests/Feature/InventoryReservationServiceTest.php`
- `backend/tests/Feature/OrderCompletionLedgerTest.php`

Other tests containing direct snapshot construction may be modified only when
the new non-null `vendor_id` makes their fixture fail. Report each such file and
make only the mechanical fixture addition there. Do not modify unrelated
production source.

## Acceptance gates

Run:

```powershell
php artisan test tests/Feature/Phase2CheckoutPaymentSchemaTest.php tests/Feature/Phase2CheckoutSessionWritePathTest.php tests/Feature/InventoryReservationServiceTest.php tests/Feature/OrderCompletionLedgerTest.php tests/Feature/AuthorDrmInventoryTest.php
.\vendor\bin\pint --test database/migrations/2026_07_25_100000_create_phase2_checkout_and_payment_tables.php app/Models/CheckoutSessionOrder.php app/Services/CheckoutService.php app/Services/OrderFulfillmentService.php tests/Feature/Phase2CheckoutPaymentSchemaTest.php tests/Feature/Phase2CheckoutSessionWritePathTest.php tests/Feature/InventoryReservationServiceTest.php tests/Feature/OrderCompletionLedgerTest.php tests/Feature/AuthorDrmInventoryTest.php
```

Report changed files, test count, assertion count, and formatting result. Codex
will run the consolidated full regression after independent review.

Do not commit, push, perform production operations, or begin Batch 2E.2.
