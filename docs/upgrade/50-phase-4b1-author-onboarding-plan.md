# Phase 4B.1 — Author onboarding execution plan

Date: 2026-07-26  
ADR: `49-phase-4b-author-vendor-adr.md`  
Execution: Codex direct source edit, explicitly authorized because Antigravity weekly quota is exhausted.

## Objective

Implement private Author onboarding with this canonical state machine:

`draft -> submitted -> under_review -> approved`

Branches:

- `under_review -> changes_requested -> resubmitted`
- `under_review -> rejected`
- `approved -> suspended | revoked`

Every transition records actor, reason where required, operation key and metadata; it also creates an idempotent in-app system notification. Approval requires verified phone and a complete profile. Approval does not change the user's legacy role and does not create a Vendor.

## Allowed source scope

- Author onboarding migration, enum, model/event model, service and resource.
- Author and admin approval controllers, authenticated/admin routes, Author bootstrap in registration, user capability resource.
- Author registration/dashboard and admin approval UI.
- Focused backend/frontend tests and Phase 4B documentation.

## Forbidden scope

- Vendor onboarding/business rules (4B.2), copyright/delegation (4B.3), publishing/royalty (4B.4).
- Phase 4A behavior, production configuration, real database, credentials, external messaging/payment services.
- Dependency changes, commit or push.

## Migration/backfill contract

- Abort before schema mutation if duplicate `authors.user_id` exists.
- Add uniqueness and canonical onboarding timestamps/status.
- Backfill `active -> approved`, `rejected -> rejected`; complete legacy pending applications -> submitted; placeholder/incomplete pending records -> draft.
- Do not infer creative ownership, vendor status or financial facts.
- Supply a safe `down()` and rehearse on a temporary SQLite database at the group gate.

## Acceptance gate

- Focused tests cover creation as draft, submit/resubmit, invalid transitions, reasons, audit, notification, idempotency, phone/profile approval requirements, suspend/revoke, private-document isolation and no automatic Vendor/role mutation.
- Relevant PHP files pass Pint; changed frontend compiles/tests; `git diff --check` remains clean.
- Codex reviews the complete diff and reports changed files, evidence, remaining issues and confirmation of no commit/push/deploy.

## Antigravity prompt (retained for auditability; execution waived by user)

Implement only Phase 4B.1 Author onboarding under the allowed scope above. Preserve the approved Author–Vendor ADR and all existing local changes. Implement the exact state machine, private fields, transition reasons, append-only audit events, idempotent notifications, resubmission and capability-based authorization. Author approval must never mutate `users.role` or create/activate a Vendor. Include safe migration/down/backfill and focused tests. Do not touch 4B.2–4B.4, Phase 4A, dependencies or production configuration. Report every changed file and gate result. Do not commit or push.
