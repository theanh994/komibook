# Batch 2E.2 — Order Side-Effect Outbox and Job Hardening

## Status

Batch 2E.1 is accepted after:

- targeted gate: 53 tests, 184 assertions;
- full backend regression: 199 tests, 807 assertions;
- targeted Pint: pass;
- `git diff --check`: pass;
- frontend production build: pass.

Batch 2E.2 moves the database notification and order-confirmation email side
effects out of `ProcessOrder`'s transaction into a durable, retry-safe outbox.
It does not change payment, inventory, fulfillment, production services, or
notification campaigns.

## Business invariants

1. `confirmed -> processing`, inventory commit, and creation of required outbox
   records are atomic.
2. `ProcessOrder` never sends mail or creates a user notification while holding
   the business transaction.
3. Each `(order, effect_type)` has exactly one durable outbox record and one
   canonical operation key.
4. Repeating `ProcessOrder` or a delivery job 2–5 times does not duplicate
   outbox records, database notifications, normal email sends, inventory
   commits, or order transitions.
5. Reusing an operation key with another order, effect type, actor, or canonical
   payload fails closed.
6. A dispatch failure never rolls back an already committed order/inventory
   transaction. The durable outbox remains recoverable.
7. Queue dispatch happens after commit. A scheduled command can redispatch
   pending, retryable, and stale-claimed records after a process crash.
8. Jobs have explicit queue name, tries, timeout, backoff, unique business ID,
   and sanitized failure recording.
9. Outbox payload and logs contain no credentials, mail transport secrets,
   signatures, cookies, tokens, or raw exception messages.
10. Database notification delivery is exactly-once through a unique operation
    key.
11. SMTP cannot guarantee true exactly-once delivery after an ambiguous network
    failure. Email delivery must use a stable Message-ID/business key,
    durable status, and at-least-once retry semantics without falsely claiming
    provider-level exactly-once behavior.

## Schema

Create
`backend/database/migrations/2026_07_26_140000_create_order_side_effect_outboxes.php`.

### `order_side_effect_outboxes`

- ID;
- restricted foreign key to order;
- globally unique canonical `operation_key`;
- `effect_type`: `database_notification` or `order_success_email`;
- canonical filtered JSON payload;
- status: `pending`, `processing`, `succeeded`, or `failed`;
- unsigned attempt count;
- `available_at`, `locked_at`, `processed_at`;
- sanitized `last_error`;
- timestamps;
- unique `(order_id, effect_type)`;
- dispatch index on `(status, available_at)`.

The same migration adds a nullable unique `operation_key` to
`user_notifications` so existing rows remain compatible while outbox-created
notifications are exactly-once.

`down()` must remove the notification column/index safely before dropping the
outbox table and must pass isolated SQLite migration tests.

## Outbox recording

Create `OrderSideEffectOutbox` model and
`OrderSideEffectOutboxService`.

Inside the same transaction that changes the order to `processing`,
`ProcessOrder` records:

- one database-notification effect;
- one email effect only when the order user has a non-empty email address.

Use stable keys such as:

- `order-processing:{order_id}:database-notification`;
- `order-processing:{order_id}:order-success-email`.

The canonical payload may contain only identifiers and immutable display
fields needed for delivery, such as order/user ID, order code, and payment
method. Do not store an auth token, credential, complete user object, mail
configuration, or raw request.

If the order is already `processing`, retry must verify or safely restore the
canonical outbox set before dispatch. Existing rows with conflicting payloads
must fail closed.

## Delivery job

Create `DeliverOrderSideEffect`:

- implements the appropriate queue uniqueness contract;
- stable unique ID based on outbox ID/operation key;
- explicit queue, tries, timeout, and backoff;
- atomically claims an eligible row;
- returns immediately for `succeeded`;
- does not steal a non-stale active claim;
- permits recovery of a stale `processing` claim;
- validates the outbox/order/user relationship and canonical payload before
  delivery;
- performs the external side effect outside a database transaction;
- marks success in a short transaction;
- records a sanitized retryable failure and rethrows;
- marks terminal failure through `failed()` without storing raw secrets.

For `database_notification`, use the unique outbox operation key with
`firstOrCreate`/equivalent so a crash between notification creation and outbox
success marking cannot duplicate the notification.

For email, resolve the current order/user safely, send
`OrderSuccessMail` outside the transaction, and give the message a stable
Message-ID derived from the non-secret operation key. A completed outbox retry
must not send again.

## Recovery command and scheduler

Create a bounded command such as
`order-side-effects:dispatch --limit=100` that dispatches:

- eligible pending records;
- retryable failed records whose `available_at` has arrived;
- stale `processing` records after the documented lease.

Process rows in deterministic ID order. Reject invalid limits. Schedule it once
per minute with `withoutOverlapping`. Do not change production service
configuration or start a worker.

## Existing source cutover

- Remove direct `UserNotification::create`, `Mail::send`, and their swallowed
  catch blocks from `ProcessOrder`.
- Add explicit retry/backoff/timeout and stable uniqueness to `ProcessOrder`
  itself.
- Keep inventory commit and `confirmed -> processing` semantics unchanged.
- Update `UserNotification` for the nullable operation key.
- Update `OrderSuccessMail` only as needed for a stable Message-ID.

## Allowed source files

Created:

- `backend/database/migrations/2026_07_26_140000_create_order_side_effect_outboxes.php`
- `backend/app/Models/OrderSideEffectOutbox.php`
- `backend/app/Services/OrderSideEffectOutboxService.php`
- `backend/app/Jobs/DeliverOrderSideEffect.php`
- `backend/app/Console/Commands/DispatchOrderSideEffectsCommand.php`
- `backend/tests/Feature/OrderSideEffectOutboxTest.php`

Modified:

- `backend/app/Jobs/ProcessOrder.php`
- `backend/app/Models/UserNotification.php`
- `backend/app/Mail/OrderSuccessMail.php`
- `backend/routes/console.php`
- `backend/tests/Feature/InventoryReservationCheckoutIntegrationTest.php`

If another existing test requires only a mechanical expectation/fixture update
because `ProcessOrder` now dispatches delivery jobs, report that file and make
only that narrow test change. Stop before modifying any other production file.

## Required tests

At minimum cover:

1. atomic transition/inventory/outbox creation;
2. notification-only behavior for a user without email;
3. no mail or notification delivery inside the `ProcessOrder` transaction;
4. `ProcessOrder` retries 2–5 times without duplicate transition, inventory, or
   outbox;
5. canonical operation/payload conflict fails closed;
6. database notification delivery repeated 2–5 times creates one row;
7. successful email delivery retry does not send twice and uses stable
   Message-ID;
8. delivery exception records sanitized retry state and does not mutate the
   order transaction;
9. succeeded, active-processing, stale-processing, retryable-failed, and
   terminal-failed behavior;
10. command limit, deterministic order, eligibility, and invalid input;
11. corrupted cross-order/outbox/user links fail closed;
12. rollback injection leaves no partial outbox/order/inventory mutation;
13. isolated SQLite migration up/down;
14. payload and failure records contain no secrets.

## Acceptance gates

Antigravity runs the narrow gates:

```powershell
php artisan test tests/Feature/OrderSideEffectOutboxTest.php tests/Feature/InventoryReservationCheckoutIntegrationTest.php
.\vendor\bin\pint --test app/Jobs/ProcessOrder.php app/Jobs/DeliverOrderSideEffect.php app/Models/OrderSideEffectOutbox.php app/Models/UserNotification.php app/Services/OrderSideEffectOutboxService.php app/Console/Commands/DispatchOrderSideEffectsCommand.php app/Mail/OrderSuccessMail.php database/migrations/2026_07_26_140000_create_order_side_effect_outboxes.php routes/console.php tests/Feature/OrderSideEffectOutboxTest.php tests/Feature/InventoryReservationCheckoutIntegrationTest.php
```

Codex will independently inspect the diff, rerun the narrow gates, and then
consolidate the full backend/frontend regression if the implementation passes.

Do not commit, push, run production migrations, change queue/database/service
configuration, start workers, or perform any production operation.
