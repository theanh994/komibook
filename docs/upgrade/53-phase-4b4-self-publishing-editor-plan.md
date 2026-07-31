# Phase 4B.4 — Self-publishing and editor plan

## Objective

Replace direct `draft/published` writes with an audited publication workflow and make the existing chapter editor durable. Publication must be based on explicit Author–Vendor relations, verified copyright, an active vendor, a complete book, and an accepted versioned royalty agreement.

## Scope and invariants

- Lifecycle: `draft -> submitted_for_review -> approved -> scheduled|published`; correction branch `submitted_for_review|resubmitted -> changes_requested -> draft -> resubmitted`.
- `books.status` remains the public compatibility flag. New workflow state is stored separately; no legacy copyright or financial facts are inferred.
- New publication requires an approved author, accepted primary/coauthor relation, live verified copyright claim, active vendor, complete metadata/cover, valid price and physical inventory or private ebook file.
- Chapter autosave creates immutable revisions; restore creates another revision. Ordering is transactional and cross-book/cross-vendor safe.
- Preview is restricted to free sample chapters or entitled readers. Ebook delivery remains signed and watermarked through the existing access service.
- Published snapshots, publishing events, royalty agreement versions, and royalty ledger rows are append-only. Publishing creates no historical royalty money.

## Planned source

- Add publication status enum, migration, models, eligibility/transition services, vendor/admin controllers and routes.
- Extend chapter controller/model for autosave, revision restore, ordering, import and preview.
- Remove direct `published` input from vendor create/update requests and update the vendor UI to submit for review explicitly.
- Add focused transition, permission, audit, revision, snapshot, eligibility and isolation tests.

## Antigravity handoff prompt (retained; execution waived by user)

Antigravity source delegation is unavailable because its weekly quota is exhausted. If resumed, modify only the Phase 4B.4 source listed in this plan. Preserve all existing local changes. Implement the lifecycle and invariants above; do not infer legacy rights or royalties, call external services, change real databases/credentials, commit, push, or deploy. Run focused tests, migration rehearsal and frontend build, then report exact changed files and evidence. Codex performs independent acceptance.

## Acceptance

- Focused backend tests cover transitions, eligibility, corrections, publication snapshots, chapter revisions/restore/order/import, cross-account/vendor denial, immutable audit and royalty records.
- Focused frontend tests and production build pass.
- New migration passes fresh, rollback and reapply on temporary SQLite.
- `git diff --check` passes; HEAD is unchanged; no commit/push/deploy.
