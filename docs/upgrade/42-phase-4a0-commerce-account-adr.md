# Phase 4A.0 — Commerce lifecycle and account trust ADR

Date approved: 2026-07-26  
Status: Approved  
Implementation order: `4A.1 -> 4A.2 -> 4A.3 -> Gate 4A`

## 1. Decision

Phase 4A extends the existing Phase 2 order, payment, inventory, loyalty, and
vendor earning foundations. It must not replace those foundations or move
financial side effects back into controllers or model events.

The authoritative aggregate boundaries are:

- `Order` owns fulfilment. `cancelled` is a pre-fulfilment terminal state.
- `ReturnRequest` owns the physical-return state machine and returned items.
- `RefundTransaction` owns refund attempts, provider state, amount, evidence,
  failure details, and idempotency.
- the original `PaymentTransaction` remains the immutable charge record;
  refunds reference it instead of rewriting it;
- `InvoiceSnapshot` owns the immutable buyer-facing transaction document;
- append-only inventory, loyalty, vendor earning, payout hold, and transition
  records are the financial and audit source of truth. Mutable balances and
  statuses are projections.

## 2. Cancellation and return policy

### 2.1 Cancellation

- An unpaid online checkout remains session-scoped because one payment
  transaction covers every vendor order in that checkout session.
- A COD order may be cancelled independently by its buyer before it reaches
  `shipped`.
- A paid or shipped/completed order is not directly cancelled. It must enter
  the return/refund workflow.
- A committed physical inventory allocation restored by cancellation must be
  restored exactly once through a durable operation key.

### 2.2 Return eligibility

- A physical item may be requested for return within seven calendar days of
  its recorded `delivered` transition.
- A request belongs to one vendor order and may contain some or all physical
  order items.
- Active requests may not overlap the same returned quantity.
- E-books do not enter the physical-return path. An exceptional digital refund
  requires an explicit admin decision, reason, and audit record.

### 2.3 State machine

Primary path:

`requested -> under_review -> approved -> item_received -> refund_processing -> refunded`

Branches:

`requested/under_review -> rejected`

`refund_processing -> refund_failed -> refund_processing`

Every transition records the actor, reason, timestamp, from/to states, metadata,
and an idempotent operation key. Controllers may request transitions but may
not assign state strings directly.

## 3. Side-effect timing

- Inventory is restored at `item_received`, after the returned goods are
  accepted, exactly once per return item/allocation.
- A refund hold is created at `approved` so disputed money is no longer
  withdrawable by the vendor.
- Loyalty points and vendor earning are reversed only after the refund reaches
  `refunded`.
- Existing completion ledgers are never edited or deleted. Refunds append
  signed reversal entries referencing the originating order and return.
- A failed refund keeps the financial hold and may be retried safely.
- Rejection releases any hold that was created, but normally rejection occurs
  before approval and therefore before a hold exists.

## 4. COD and VNPAY refunds

- A COD refund is a manual settlement. Completion requires an actor, payment
  evidence/reference, settled amount, and settlement time.
- A VNPAY refund uses a dedicated gateway abstraction. Each provider attempt is
  persisted before/with processing and retains sanitized request/response data.
- Missing provider configuration fails closed. Tests use a fake gateway and
  never call a real provider.
- Retry reuses the logical refund idempotency key and cannot duplicate stock,
  points, ledger, balance, or provider amount.
- The sum of successful and in-flight refunds for a checkout payment may never
  exceed the original paid amount, including partial refunds across vendors.

## 5. Payment projection

Order fulfilment state is not overloaded with partial-refund information.
Buyer-facing payment projection supports:

`unpaid -> paid -> refunding -> partially_refunded/refunded`

The projection is derived or updated only by the refund service and must agree
with durable refund transactions.

## 6. Immutable invoice snapshot

One snapshot is created for each vendor order in the checkout transaction. It
contains:

- a unique document number, issue time, currency, and order reference;
- buyer name, email, phone, and address as purchased;
- vendor/store identity and available legal/contact fields as purchased;
- line-item title/type, quantity, unit price, and line total;
- subtotal, coupon discount, membership discount, shipping fee, service fee,
  tax basis/rate/amount, and final total;
- source identifiers needed for reconciliation without depending on mutable
  catalog/profile/configuration data.

The snapshot is immutable after creation. API resources and print UI read it
without recalculating from current Book, User, Vendor, Coupon, membership, or
configuration records.

Until a valid tax/e-invoice policy and legal integration exist, `tax_amount` is
zero and the UI describes the output as a transaction/order invoice snapshot,
not a statutory VAT e-invoice.

## 7. Payout decision for 4A.3

- Available vendor funds equal durable earnings minus refund holds, payout
  holds, completed payouts, and other durable reversals.
- Creating a payout locks the relevant vendor/ledger projection and creates a
  hold; it does not merely decrement an unlocked mutable balance.
- Lifecycle:
  `pending -> approved -> processing -> completed` or `pending -> rejected`.
- `total_withdrawn` increases only at `completed`.
- Review actor, reason, timestamps, transfer evidence, and idempotent transition
  history are mandatory.

Batch 4A.1 implements only the refund hold/reversal compatibility required to
prevent a refund from racing a payout. The complete payout lifecycle remains
Batch 4A.3.

## 8. Account trust decision for 4A.2

- Password reset remains email-based and free; paid SMS is not introduced.
- Password reset and password change revoke every other web session and API
  token.
- Users can list and revoke current/other sessions.
- Password change, bank/payout changes, refund decisions, and session
  revocation require a verified email and authentication within the preceding
  15 minutes.

## 9. Batch boundaries and verification reuse

### Batch 4A.1

Return/refund/invoice schema, state services, authorization, stock/point/earning
reversal, refund gateway abstraction, invoice-backed API/UI, focused tests, and
temporary-database migration rehearsal.

### Batch 4A.2

Review, annotation, reading progress, session management, email verification
guards, and recent-authentication enforcement. It reuses the account decisions
in this ADR and does not redesign commerce.

### Batch 4A.3

Payout lifecycle/ledger and scheduled notification campaign processing. It
reuses the payout decisions in this ADR and the refund holds from 4A.1.

### Gate 4A

Full backend tests, full frontend tests, production build, consolidated
migration rehearsal, and browser smoke run once after 4A.1–4A.3. Individual
batches run only their focused gates unless a direct regression requires a
broader check.

## 10. Safety

- No real database, payment provider, email recipient, production service,
  Cloudflare configuration, credential, deployment, commit, or push is part of
  this ADR.
- Every migration must have a safe `down()` and be rehearsed on a temporary
  database.
- No existing Phase 3 behaviour is reopened without direct regression evidence.
