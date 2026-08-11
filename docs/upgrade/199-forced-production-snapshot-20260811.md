# Forced production snapshot — 2026-08-11

## Authorization and boundaries

This release is an explicitly authorized forced snapshot of the current KomiBook
worktree. The source base before the snapshot is
`7614d97f7e85b41a8f590da8a66c8a625ce79107` on `master`.

The release may create and push a dedicated branch, back up production, run
`migrate --force`, publish immutable frontend assets, run readiness, restore the
tunnel, cut over/reload Caddy, and execute smoke tests.

The release must not:

- run any seeder;
- import, restore, or copy the local database into production;
- overwrite production data with local data;
- hide a failed non-waivable security, payment, refund, inventory, migration, or
  data-integrity gate.

The frozen worktree contained 102 status entries before this release record was
added. It includes unfinished Batch 7 and Batch 8 work intentionally accepted for
the forced snapshot. Every file is committed so later corrections remain traceable.

## Gate evidence and waivers

### Backend

Full suite result: **626 passed, 4 failed, 4230 assertions**.

All four failures are compatibility tests superseded by already-approved runtime
contracts:

1. Two checkout duplicate-book tests expect the former exception/success behavior.
   Checkout now rejects duplicate book IDs before writes with a validation error;
   Batch 5 tests cover the new fail-closed behavior.
2. The Phase 3 coupon test omits `items`. Coupon preview now requires server-priced
   items and does not trust a client total; Batch 5 tests cover that contract.
3. The Phase 9 used-book wallet test expects 45,000 VND. The immutable snapshot is
   50,000 VND merchandise + 15,000 VND shipping - 5,000 VND commission = 60,000
   VND vendor net. Current fulfillment and finance contracts intentionally use the
   checkout-session order total and snapshotted commission.

These failures are waived as stale tests. They are not waived as runtime defects and
must be corrected after release without weakening the current contracts.

### Frontend

Full suite result: **189 passed, 17 failed across 8 files**.

The failures are waived for this forced snapshot because the production build is
green and the failures are limited to stale source-contract expectations, unfinished
Batch 7/8 UI acceptance, the known OrderTracking `journeyException` gap, and Node test
environment access to browser `localStorage`. Runtime/browser smoke remains a
mandatory post-cutover gate.

Production build result: **passed** with only the existing Vite timing and chunk-size
warnings.

### Formatting

The whole-worktree `git diff --check` reports trailing whitespace in unfinished
frontend files. Secret scanning and conflict-marker scanning are clean. The whitespace
is waived to preserve the user's current work exactly; it is not bulk-reformatted in
this snapshot.

## Non-waivable release gates

Before cutover, all of the following must pass on the exact snapshot commit:

- secret and conflict-marker checks;
- source syntax and frontend production build;
- production backup creation and verification;
- migration provenance verification;
- `php artisan migrate --force` without seeding;
- immutable frontend asset publication;
- `php artisan production:readiness`;
- Caddy configuration validation and reload;
- origin and public-domain smoke tests for critical read-only and authentication
  boundaries.

If any non-waivable gate fails, cutover stops or rolls back to the prior release.

## Follow-up debt

- Finish and independently accept the remaining Batch 7 and Batch 8 work.
- Update the four stale backend tests without changing the accepted contracts.
- Repair the eight frontend test files and add mounted/browser coverage where static
  source assertions are insufficient.
- Remove the trailing-whitespace waiver in a later reviewed cleanup commit.
