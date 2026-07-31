# Phase 4A.2 — Review, reading and account trust implementation

Date: 2026-07-26  
Status: implemented locally; no commit, push, deployment or real database operation

## Delivered contracts

- Reviews require a completed, paid purchase and use create-or-update semantics.
- The database permits one active review per user/book. Legacy duplicates are preserved as superseded audit rows instead of being deleted.
- Customers can report reviews; admins can moderate them through a dedicated queue. Moderation decisions and idempotency keys are recorded in an append-only event table.
- Public book detail only exposes active, published reviews.
- New annotations must identify a valid page or chapter belonging to the owned ebook. Update/delete re-check both ownership and current ebook access.
- Reading progress is synchronized per user/book with optimistic version checks to detect stale device writes. The reader restores the last valid page and debounces updates.
- Database-backed web sessions can be listed and revoked from the profile security tab.
- Password change preserves the current browser session, revokes every other database session and all API tokens. Password reset revokes all sessions and tokens.
- Password, payout, refund decisions, refund reconciliation, session revocation and review moderation require verified email plus authentication within the previous 15 minutes.
- Email/password, Google, Facebook, registration and phone OTP logins establish recent-auth state. Trusted social email linkage marks the matching email as verified.
- Expired recent-auth returns a machine-readable 423 response. The SPA asks for the current password, confirms identity through a dedicated endpoint and retries the original request once.

## Schema

- Extended `reviews` with verified-purchase, moderation, active-row and supersession fields.
- Added `review_reports` and append-only `review_moderation_events`.
- Extended `book_annotations` with chapter/location/range fields.
- Added `reading_progress` with a unique user/book key and version counter.

## Focused acceptance evidence

- Backend: 33 tests passed, 218 assertions across Phase 4A.2, Phase 4A.1 return/refund regression, security hardening, Google, Facebook and registration email OTP.
- Frontend: 8 Vitest files passed, 59 tests.
- Frontend production build passed. The pre-existing large reader chunk warning remains non-blocking.
- Targeted Laravel Pint passed.
- Migration execution was exercised by the in-memory SQLite `RefreshDatabase` feature suite. The consolidated fresh/rollback/reapply rehearsal remains reserved for Gate 4A.

## Scope boundary

No production, service, Cloudflare, credential, real database, commit, push or deployment action was performed. Phase 3 was not repeated because no regression evidence was found.
