# KomiBook Phase 4A Gate — Antigravity correction and Codex acceptance plan

Date: 2026-07-26  
Baseline HEAD: `87d578924de8dc8b9afa7266c562b17783d9ccfe`  
Status: approved workflow plan; no commit, push, deployment or production operation

## 1. Objective

Close Gate 4A across the already implemented 4A.1, 4A.2 and 4A.3 scope without repeating their implementation work. Antigravity may correct only reproducible integration defects inside the Phase 4A source boundary. Codex owns the independent audit, consolidated gates, acceptance decision and documentation.

If the supplied gates expose no source defect, Antigravity must make no speculative change and report that no source correction was required.

## 2. Responsibility boundary

### Antigravity

- Work on source code and tests only within the allowed boundary below.
- Reproduce a failure before changing behavior.
- Make the smallest correction that preserves the approved Phase 4A contracts.
- Run the focused test first, then the relevant consolidated gate.
- Report commands, results, changed files and any unresolved issue.
- Do not edit plans/reports, commit, push, deploy or touch real infrastructure/data.

### Codex

- Capture the preflight and current diff before handoff.
- Independently inspect every Antigravity change rather than accepting its report.
- Run the full backend suite, full frontend suite, production build, temporary-database migration rehearsal and browser smoke.
- Directly correct a defect only when it remains within the approved objective and either:
  - affects one file; or
  - affects at most three files and no more than two methods in total.
- Rerun the failed gate after any direct correction.
- Return broader, architectural, schema-policy or security-policy corrections to Antigravity or the user.

## 3. Approved Phase 4A invariants

- Return/refund/invoice remains consistent with ADR 4A.0 and uses immutable financial snapshots and idempotent reversal operations.
- Review requires verified purchase and one user/book review; moderation/reporting remains audited.
- Annotation and reading progress remain owner-scoped and validate the correct book/chapter relationship.
- Sensitive actions retain verified-email and recent-authentication protection; no paid SMS dependency is introduced.
- Payout remains `pending -> approved -> processing -> completed` or `pending -> rejected`; reserved funds and ledger are idempotent; `total_withdrawn` changes only at completion.
- Campaign dispatch remains scheduled, chunked, retryable and consent-scoped; no fake delivery/open/click telemetry is introduced.
- Existing Phase 2 and Phase 3 behavior is not rewritten unless a Gate 4A regression directly proves a defect.

## 4. Source boundary

Allowed only when required by a reproduced Gate 4A defect:

- `backend/app/Console/Commands/DispatchDueNotificationCampaigns.php`
- `backend/app/Enums/InventoryReservationStatus.php`
- Phase 4A controllers, middleware, requests and resources under `backend/app/Http/`
- Phase 4A jobs, models and services under `backend/app/Jobs/`, `backend/app/Models/` and `backend/app/Services/`
- Phase 4A routes/scheduling in `backend/routes/api.php`, `backend/routes/console.php`, `backend/bootstrap/app.php` and `backend/app/Providers/AppServiceProvider.php`
- the three Phase 4A migrations dated `2026_07_26_150000`, `160000` and `170000`
- Phase 4A tests plus directly failing regression tests under `backend/tests/`
- Phase 4A frontend views/components/services/router integration under `frontend/src/`
- existing frontend tests directly covering an affected Phase 4A flow

Forbidden:

- all files under `docs/` and `Tai_lieu/`
- `.env*`, credentials, secrets and real data
- package manifests and lockfiles unless separately approved
- unrelated Phase 1/2/3 rewrites or refactors
- production services, Cloudflare, schedulers/daemons, email delivery and external providers
- Git reset, checkout, stash, clean, commit, push, merge or history rewriting

No new migration is allowed at Gate 4A. A correction to one of the three existing uncommitted Phase 4A migrations is allowed only when the temporary migration rehearsal proves the existing migration is defective, and the report must explain the defect and rollback behavior.

## 5. Execution and acceptance gates

1. Antigravity records `git status --short` and `git diff --check` without cleaning existing work.
2. Run the consolidated gates. Reproduce and isolate any failure before editing.
3. Apply the smallest in-scope correction, if needed.
4. Run the focused failing test, followed by the affected consolidated gate.
5. Return a changed-file report. Do not commit or push.
6. Codex independently audits the diff and reruns:
   - all backend tests;
   - all frontend tests;
   - frontend production build;
   - Laravel Pint and `git diff --check`;
   - fresh migration, rollback and reapply using a disposable SQLite database;
   - browser smoke for return/refund/invoice, review/reading/session trust, payout and campaign administration.

Gate 4A is accepted only when financial invariants remain consistent, all automated gates pass and browser smoke exposes no blocking defect.

## 6. Prompt for Antigravity

Use the following prompt without adding a workspace path because the Antigravity chat is already scoped to the repository:

```text
KomiBook — Gate 4A source integration correction.

Objective:
Close reproducible source-code defects found by the consolidated Gate 4A across the already implemented batches 4A.1 Return/Refund/Invoice, 4A.2 Review/Reading/Account Trust, and 4A.3 Payout/Campaign. Do not reimplement completed batches and do not make speculative refactors. If all gates pass and no source defect is reproducible, make no source change and report that result.

Before editing:
1. Read docs/upgrade/42-phase-4a0-commerce-account-adr.md and implementation records 43, 44 and 45.
2. Record git status --short and git diff --check. Preserve every existing local change and untracked file.
3. Reproduce and isolate a failing Gate 4A assertion before changing behavior.

Approved invariants:
- Return/refund/invoice uses immutable snapshots and idempotent inventory, points, ledger and gateway reversal operations.
- Review requires verified purchase and remains unique per user/book; moderation/reporting is audited.
- Annotation/reading progress is owner-scoped and validates the correct book/chapter relationship.
- Sensitive actions keep verified-email and recent-authentication gates. Do not introduce paid SMS.
- Payout remains pending -> approved -> processing -> completed or pending -> rejected; funds reservation/release and ledger are idempotent; total_withdrawn changes only once at completed.
- Campaign dispatch remains scheduled, chunked, retryable and explicit-consent scoped. Never fabricate delivery/open/click telemetry.
- Do not rewrite Phase 1/2/3 behavior unless a Gate 4A regression directly proves a defect.

Allowed files, only when required by a reproduced defect:
- backend/app/Console/Commands/DispatchDueNotificationCampaigns.php
- backend/app/Enums/InventoryReservationStatus.php
- Phase 4A controllers, middleware, requests and resources under backend/app/Http/
- Phase 4A jobs, models and services under backend/app/Jobs/, backend/app/Models/ and backend/app/Services/
- Phase 4A integration in backend/routes/api.php, backend/routes/console.php, backend/bootstrap/app.php and backend/app/Providers/AppServiceProvider.php
- existing Phase 4A migrations 2026_07_26_150000, 160000 and 170000 only when temporary migration rehearsal proves a defect
- Phase 4A tests and directly failing regression tests under backend/tests/
- affected Phase 4A frontend views/components/services/router integration and their existing tests under frontend/src/

Forbidden:
- docs/, Tai_lieu/, .env files, credentials, secrets and real data
- new migrations
- package manifests/lockfiles or dependency changes
- unrelated refactors or broad formatting
- production database, real email, paid providers, Cloudflare, services or deploy
- reset, checkout, stash, clean, commit, push, merge or history rewrite

Verification:
- Run the focused failing test before and after a correction.
- Then run the affected consolidated gate: full backend tests, full frontend tests, frontend production build, targeted Pint and git diff --check as applicable.
- Migration validation must use only a disposable SQLite database and must cover fresh, rollback and reapply. Never use the real database.
- Tests must fake queues/mail/external gateways; never send real email or call a real payment/refund service.

Completion report:
- State whether a defect was reproduced.
- List every changed file and why it changed.
- List exact test/gate commands and pass/fail totals.
- Explain preserved invariants and any unresolved issue.
- Confirm no docs, real data/infrastructure, commit or push were touched.
- Do not commit or push.
```

## 7. Next transition

After Gate 4A is independently accepted, Codex prepares the mandatory Author–Vendor role ADR for user approval. No 4B source work begins before that ADR is approved.
