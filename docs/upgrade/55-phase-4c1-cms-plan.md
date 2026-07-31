# Phase 4C.1 — Editorial CMS plan

## Objective

Replace the fail-closed news placeholder with a real, approval-based editorial CMS. No article is seeded or fabricated.

## Scope and invariants

- Lifecycle: `draft -> submitted -> under_review -> approved -> scheduled|published`; `under_review -> changes_requested|rejected`; `published -> unpublished|archived`.
- CMS mutation and review are admin-only until an explicit editor role exists.
- Body is sanitized on write. Media is validated, categories/tags/book links are explicit, and SEO fields are bounded.
- Every content save creates an immutable revision; every state transition creates an append-only audit event.
- Scheduling uses persisted timestamps; public APIs return only `published` articles whose publication time is due.
- `home_featured` never bypasses publication eligibility.

## Antigravity handoff prompt (retained; execution waived by user)

Modify only Phase 4C.1 CMS source described here. Preserve all local changes. Implement permission, sanitization, categories/tags/book links, revisions, scheduling/timezone, home_featured, SEO, audit/notification and published-only public reads. Do not create fake articles, touch real databases/credentials/services, commit, push or deploy. Codex independently reviews and runs gates.

## Acceptance

- Focused permission, sanitization, lifecycle, revision, schedule and public visibility tests pass.
- Frontend tests/build pass and BlogView consumes only the CMS API.
- Migration fresh, rollback and reapply pass on temporary SQLite.
- HEAD remains unchanged; no commit/push/deploy.

## Codex acceptance — 2026-07-26

Accepted locally. Codex performed the source implementation directly under the user's explicit quota override and independently ran the gates.

- Backend focused suite: `Phase4EditorialCmsTest` — 4 tests, 42 assertions passed.
- Public reads are fail-closed to due `published` records; `home_featured` cannot bypass publication state.
- HTML sanitization, explicit taxonomy/book links, immutable revisions, lifecycle audit, notifications, scheduling and operation-key conflict handling are covered.
- Frontend focused suites: 2 files, 5 tests passed; production build passed (existing large-chunk warning only).
- SQLite rehearsal passed full fresh migration, CMS rollback and reapply; the temporary database was removed.
- Pint and `git diff --check` passed. No commit, push, deploy, real-database write, credential, or external-service action was performed.
