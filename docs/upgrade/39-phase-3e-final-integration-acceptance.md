# Phase 3E — Final integration and acceptance

## Objective

Close Phase 3 with current-workspace evidence across backend, frontend, and
browser-visible critical journeys.

## Scope

1. Remove frontend lint debt exposed by a full no-cache run.
2. Run the complete frontend automated test suite and production build.
3. Run the complete backend automated test suite.
4. Run PHP formatting and repository diff-integrity checks.
5. Exercise public and guest browser journeys against local services when the
   local runtime is available.
6. Record direct results separately from historical handoff statements.

## Browser journeys

- Home, catalog, book detail, flash sale, blog unavailable state, and help
  center render without a blank page.
- Guest navigation does not loop and expected unauthenticated requests do not
  cause repeated fetch storms.
- Login/register render the eight-cell OTP flow when phone authentication
  reaches the verification step.
- Authenticated-only journeys are tested only if a safe local account/session
  is already available; no account or database records are created merely for
  acceptance.

## Boundaries

- Local verification only.
- No production, Cloudflare, tunnel, service, or production database changes.
- No commit, push, reset, checkout, stash, or clean.
- Existing user work in the dirty worktree remains intact.

## Acceptance

- No-cache frontend lint passes.
- Complete frontend tests pass.
- Frontend production build passes.
- Complete backend tests pass, or every failing test is triaged with direct
  evidence and no Phase 3 regression is left unresolved.
- Touched PHP files pass Pint and `git diff --check` passes.
- Browser smoke has no blank page, route loop, or uncaught application error in
  the scoped guest journeys.

## Direct acceptance evidence — 2026-07-26

- Backend: 250 tests passed with 1,123 assertions.
- Frontend: 55 tests passed across 7 files.
- Oxlint: 0 warnings and 0 errors.
- ESLint: full repository pass with cache disabled.
- Production frontend build: passed; the existing large-chunk advisory remains
  non-blocking.
- Browser smoke: home, catalog, flash sale, blog, help center, login, and
  register rendered meaningful content with no visible error and no console
  error. Each forced full-page guest load performed the expected single
  unauthenticated session probe; no loop or repeated-fetch storm was observed.
- OTP browser check: local log-only sender accepted a synthetic phone number and
  rendered exactly eight numeric one-character inputs, labelled from digit 1 to
  digit 8. No OTP verification, account creation, or production request was
  performed.
- Full-repository Pint still reports historical formatting debt in files outside
  the Phase 3 change set. All touched Phase 3 PHP files pass the scoped Pint
  gate; no bulk reformat was applied.
