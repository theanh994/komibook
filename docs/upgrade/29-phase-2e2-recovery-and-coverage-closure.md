# Batch 2E.2 — Recovery and Coverage Closure

## Status

The correction narrow gate passes 28 tests with 126 assertions and targeted
Pint. Batch 2E.2 remains unaccepted because the correction replaced the
original outbox tests instead of preserving them, removing several atomicity,
retry, rollback, and payload-safety gates.

## Required implementation corrections

### Crash-safe unique locks

- Add a finite `uniqueFor` to `DeliverOrderSideEffect` that is longer than its
  timeout and stale-claim lease, so a hard worker crash cannot leave the
  recovery scheduler blocked by an orphan cache lock forever.
- Add an appropriate finite `uniqueFor` to `ProcessOrder` as well.
- Test the configured queue identity, queue name, tries, timeout, backoff, and
  finite unique-lock duration for both jobs.

### Atomic terminal validation failure

- When canonical validation fails during claim, persist terminal failed state
  in the same locked transaction before releasing the row.
- Do not leave a race window in which the row remains pending between the
  validation transaction and a second failure-recording transaction.
- Preserve the safe exception for rethrow only after the terminal state commits.

### Deterministic versus retryable delivery failure

- Deterministic integrity failures such as a conflicting existing notification,
  unsupported effect, invalid canonical payload, or recipient mismatch must be
  terminal immediately: set attempts to the limit, clear availability/lock,
  store only the safe failure category, and rethrow.
- Transient transport failures remain retryable with backoff.
- `failed()` must always produce terminal state by setting the attempt floor to
  the limit and clearing `available_at` and `locked_at`.
- Add tests proving deterministic corruption is not redispatched while a
  transient mail transport failure is eligible only after its backoff.

## Restore cumulative test coverage

Keep all current correction tests and restore the missing original gates.
Do not replace one set with the other.

Add focused tests proving:

1. `confirmed -> processing`, inventory commit, and both required outboxes are
   atomic;
2. a user without email creates only the database-notification outbox;
3. `ProcessOrder` itself never sends mail or creates a notification inside its
   transaction;
4. `ProcessOrder` repeated five times does not duplicate inventory deduction,
   transition, or outbox rows;
5. a pre-existing outbox with canonical key but conflicting payload causes the
   whole processing transaction to roll back;
6. injected outbox insertion failure rolls back order status, inventory, and
   every outbox row;
7. outbox payloads contain no credential/token/secret fields or complete user
   objects;
8. delivery failure does not roll back or mutate the already committed
   processing order/inventory state;
9. immediate per-order dispatch independently excludes future, active,
   succeeded, and terminal records, not only the recovery command;
10. the `failed()` callback produces terminal non-eligible state.

The final `OrderSideEffectOutboxTest` must contain both the baseline contract
and the security correction coverage. Test count alone is not acceptance;
assert the database, queue, mail, notification, order, reservation, and stock
states explicitly.

## Allowed source files

- `backend/app/Jobs/ProcessOrder.php`
- `backend/app/Jobs/DeliverOrderSideEffect.php`
- `backend/app/Services/OrderSideEffectOutboxService.php`
- `backend/tests/Feature/OrderSideEffectOutboxTest.php`

Do not modify any other source file.

## Acceptance gates

```powershell
php artisan test tests/Feature/OrderSideEffectOutboxTest.php tests/Feature/InventoryReservationCheckoutIntegrationTest.php
.\vendor\bin\pint --test app/Jobs/ProcessOrder.php app/Jobs/DeliverOrderSideEffect.php app/Services/OrderSideEffectOutboxService.php tests/Feature/OrderSideEffectOutboxTest.php
```

Report changed files, test/assertion counts, and Pint result. Do not run full
regression, commit, push, run production migrations, start workers, or perform
production operations.
