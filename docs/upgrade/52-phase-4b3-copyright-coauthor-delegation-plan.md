# Phase 4B.3 — Copyright, co-author and delegation plan

Date: 2026-07-26  
ADR: `49-phase-4b-author-vendor-adr.md`  
Execution: Codex direct source edit authorized because Antigravity weekly quota is exhausted.

## Objective and states

Persist explicit book-author relations, co-author acceptance and scoped delegations. Replace simulated copyright strings with private evidence and this state machine:

`draft -> submitted -> under_review -> verified`

Branches: `under_review -> changes_requested -> resubmitted | rejected`; `verified -> disputed | revoked`.

## Invariants

- A book name string, Vendor ownership, or copyright number never proves creative ownership.
- A Vendor may invite an approved Author to its own book; the Author must accept. Co-authors and delegates are explicit and revocable.
- Copyright submit requires an approved Author plus an accepted book-author relation or an active accepted delegation with `manage_copyright` scope.
- Evidence is private; only claim participants and admins may download it.
- Duplicate/overlapping live claims fail closed. Every transition is reasoned where required, append-only audited and idempotently notified.
- `BookDrmSetting` remains technical protection only and cannot publish or verify copyright.

## Migration/backfill

Create relations, delegations, claims, claim participants and audit events. Do not derive any relationship from `books.author`, `books.vendor_id`, or DRM strings. Preserve those legacy display fields, but mark legacy copyright strings non-authoritative. Safe down and temporary SQLite rehearsal are required.

## Scope and gate

Allowed: new copyright/relationship schema, models/services/controllers/resources/routes, technical DRM correction, focused admin/author/vendor UI and tests. Forbidden: book publishing lifecycle and royalties (4B.4), other phases, dependencies, production, real DB, credentials, external services, commit/push.

Gate covers ownership invitation/acceptance, co-author/delegation, transitions, duplicate/overlap, private evidence, cross-account/vendor isolation, dispute/revoke, audit/notification/idempotency, DRM non-authority, migration, frontend tests/build, Pint and diff check.

## Antigravity prompt (retained for auditability; execution waived by user)

Implement only Phase 4B.3 per this plan and the approved ADR. Persist explicit accepted book-author and scoped delegation records; implement the exact copyright state machine, private evidence, duplicate/overlap checks, append-only audit and idempotent notifications. Remove all logic that treats copyright strings or DRM settings as publish authority. Do not infer ownership/backfill relations. Include safe migration/down and focused tests. Do not touch 4B.4+, dependencies, production, commit or push. Report changed files and gates.
