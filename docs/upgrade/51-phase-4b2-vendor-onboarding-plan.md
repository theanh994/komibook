# Phase 4B.2 — Vendor onboarding execution plan

Date: 2026-07-26  
ADR: `49-phase-4b-author-vendor-adr.md`  
Execution: Codex direct source edit, explicitly authorized because Antigravity weekly quota is exhausted.

## Objective

Implement independent Vendor onboarding with canonical states:

`draft -> submitted -> under_review -> approved`

Branches: `under_review -> changes_requested -> resubmitted | rejected`; `approved -> suspended | revoked`.

The legal, representative and payout profile is private. Every transition is reasoned where required, append-only audited and idempotently notified. Vendor approval may set the temporary compatibility role to `vendor`, but it never grants authorship.

## Invariants

- Only an approved, active Vendor can access commerce operations, publish/sell, or pass checkout eligibility.
- Vendor ownership remains isolated by `vendor_id`; no cross-vendor read/write is introduced.
- Suspension/revocation immediately removes commerce capability without deleting historical orders or financial records.
- No legal, payout or tax facts are inferred. Existing active Vendors are grandfathered as approved; inactive/incomplete legacy profiles become draft.

## Allowed scope

Vendor onboarding migration/enum/event/service/resource/controller/middleware, admin review integration, authenticated routes, compatibility registration, capability resource, public catalog eligibility, focused UI/tests and this document.

## Forbidden scope

Author changes beyond shared capability presentation; copyright/publishing/royalty; Phase 4A; dependencies; real database, production, credentials, paid/external services; commit or push.

## Migration and gate

- Abort before mutation on duplicate `vendors.user_id`; add uniqueness, nullable draft fields, canonical timestamps and append-only events.
- Safe down/backfill; temporary SQLite fresh + rollback/reapply.
- Test transitions, private document isolation, no authorship grant, inactive/suspended commerce denial, active checkout/catalog eligibility, cross-vendor isolation and idempotency.
- Pint, frontend tests/build and `git diff --check` must pass.

## Antigravity prompt (retained for auditability; execution waived by user)

Implement only Phase 4B.2 Vendor onboarding under this plan and the approved ADR. Preserve existing changes. Add the exact lifecycle, private legal/payment profile, reasons, append-only audit, idempotent notifications, resubmission and active-vendor commerce guard. Do not infer missing legal/payment data. Approval must not grant authorship. Deny inactive/suspended publish, sell, checkout and cross-vendor access. Include safe migration/down/backfill and focused tests. Do not touch 4B.3+, dependencies, production, commit or push. Report changed files and gate results.
