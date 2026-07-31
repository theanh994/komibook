# Batch 2E.2 — Delivery Security Correction

## Status

The initial Batch 2E.2 narrow gate passes 29 tests with 128 assertions and
targeted Pint. The batch is not accepted because the current tests confirm
queued mail rather than actual outbox-controlled delivery, and the worker does
not fully validate canonical payloads, recipients, effects, claims, or existing
notification rows.

## Required corrections

### 1. The outbox job must own the email attempt

- `OrderSuccessMail` must not queue a second job when
  `DeliverOrderSideEffect` calls `send()`. The delivery job itself is the queue
  boundary.
- Keep the stable Message-ID.
- Mark the email outbox `succeeded` only after the synchronous mail send returns.
- Update tests to assert a sent mailable, not a queued mailable.
- A normal retry of a succeeded outbox must not send again.
- Retain the documented limitation that an ambiguous SMTP acknowledgement may
  still produce an at-least-once duplicate.

### 2. Canonical validation before any side effect

Before claiming or delivering, independently derive and strictly validate:

- supported effect type;
- canonical operation key;
- outbox order ID;
- payload order ID, user ID, order code, payment method and exact allowed keys;
- notification title/content/data/type;
- email recipient against the canonical order/user data selected by the
  approved snapshot policy;
- order/user relationship and required order state.

Unknown effects or statuses must never fall through to `succeeded`.

Do not derive expected values from the outbox fields currently being validated.
Centralize canonical construction/validation in
`OrderSideEffectOutboxService` so recording and delivery use one contract.

### 3. Exactly-once database notification integrity

- `firstOrCreate` by operation key remains the insertion mechanism.
- If a row already exists, verify its user, operation key, title, content, type,
  and canonical data exactly.
- A conflicting existing notification must fail closed; it must not cause the
  outbox to be marked succeeded.

### 4. Claim, retry, and terminal-state handling

- Enforce maximum attempts for pending, failed, and stale-processing records.
- A pending record with future `available_at` is not eligible.
- A non-stale processing claim is not stolen.
- A processing record with null `locked_at`, an invalid status, invalid effect,
  or corrupted canonical relationship must be recorded as a terminal,
  sanitized failure instead of remaining pending forever.
- Stale processing may be reclaimed only below the attempt limit.
- On success clear `last_error`, `available_at`, and `locked_at`.
- On terminal failure prevent scheduler/`ProcessOrder` redispatch loops.
- Claim-validation exceptions must result in durable fail-closed state before
  being rethrown.

### 5. Failure sanitization

- Do not persist any raw exception message.
- Persist only a bounded generic failure category and safe exception class/code
  mapping.
- Never store URLs, addresses, headers, credentials, tokens, secrets, mail
  transport details, or arbitrary exception text.
- Tests must inject secrets in several formats, including text not expressed as
  `key=value`, and prove none appears in `last_error`.

### 6. Recovery and immediate dispatch eligibility

- The recovery command must exclude max-attempt stale records.
- Reject decimal, scientific-notation, signed, whitespace-padded, zero, and
  over-limit values for `--limit`; accept only canonical positive decimal
  integers from 1 through 1000.
- `dispatchOutboxForOrder` must dispatch only currently eligible records and
  must not enqueue future, active-processing, succeeded, or terminal-failed
  records.
- Keep deterministic ID ordering.

## Allowed source files

- `backend/app/Jobs/DeliverOrderSideEffect.php`
- `backend/app/Services/OrderSideEffectOutboxService.php`
- `backend/app/Models/OrderSideEffectOutbox.php`
- `backend/app/Console/Commands/DispatchOrderSideEffectsCommand.php`
- `backend/app/Mail/OrderSuccessMail.php`
- `backend/tests/Feature/OrderSideEffectOutboxTest.php`

Modify `backend/app/Jobs/ProcessOrder.php` only if required to consume the
service's corrected eligible-dispatch API; do not change its business
transition or inventory behavior.

Do not modify any other source file.

## Required correction tests

Add or strengthen tests for:

1. email is actually sent by the delivery job and never merely requeued;
2. successful email and notification retries 2–5 times do not duplicate;
3. unknown effect and invalid status fail closed;
4. every canonical payload/relationship field and extra/missing keys;
5. recipient mismatch;
6. conflicting pre-existing `UserNotification`;
7. pending future, active processing, stale processing, null-lock processing,
   max-attempt pending/failed/stale, and succeeded states;
8. success clears retry/failure fields;
9. raw exception strings and multiple secret formats never reach
   `last_error`;
10. command and immediate-dispatch eligibility;
11. all invalid limit representations;
12. no mutation of order, inventory, notification, or unrelated outbox records
    on fail-closed paths.

## Acceptance gates

```powershell
php artisan test tests/Feature/OrderSideEffectOutboxTest.php tests/Feature/InventoryReservationCheckoutIntegrationTest.php
.\vendor\bin\pint --test app/Jobs/DeliverOrderSideEffect.php app/Services/OrderSideEffectOutboxService.php app/Models/OrderSideEffectOutbox.php app/Console/Commands/DispatchOrderSideEffectsCommand.php app/Mail/OrderSuccessMail.php app/Jobs/ProcessOrder.php tests/Feature/OrderSideEffectOutboxTest.php
```

Report changed files, test/assertion counts, and Pint result. Do not run full
regression, commit, push, run production migrations, start workers, or perform
production operations.
