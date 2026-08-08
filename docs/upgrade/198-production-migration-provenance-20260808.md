# Production migration provenance gate - 2026-08-08

This evidence applies to the selective production release. It is a pre-migrate
check: run it against the exact candidate backend before any `migrate --force`.
It does not write to the database.

## Required migration file baseline

Each candidate must contain these exact files with these SHA-256 values. A
mismatch is a release blocker; do not edit a migration to make this check pass.

| Migration | SHA-256 |
| --- | --- |
| `2026_08_06_030000_add_coupon_type_to_coupons.php` | `A75FA0F90587A7AD05B4D08F8A42E8CA07F592EE58466616C9A75C04B90CFA6E` |
| `2026_08_06_040000_add_draft_to_orders_status.php` | `F7360DF0C41CBCCFBECF2FF65E646D1625EBE8A5B47C2B228314B684D349403F` |
| `2026_08_07_000001_add_rejection_reason_to_used_book_listings_table.php` | `DCA6C2E977ED36D888A4718619ABE28B7D5795A36FBC0021E52E7C4B0CADB8B9` |
| `2026_08_07_000002_backfill_used_book_warehouse_stocks_table.php` | `542CD84A122BBCF6F856132E6F6F63B905A2F359A22C4553A081A75F7E0AB5C4` |

The production migration ledger already records all four migrations as `Ran`.
In particular, `2026_08_07_000002_backfill_used_book_warehouse_stocks_table`
must be reported as `Ran`; it is never safe to assume it can be reapplied.

## Gate invocation

Use the runtime that will execute the candidate. Supplying it explicitly is
preferred:

```powershell
& .\tools\production\Test-KomiBookMigrationProvenance.ps1 `
  -CandidateBackend 'C:\komibook_releases\<FULL_SHA>\backend' `
  -PhpExecutable 'C:\runtimes\frankenphp\frankenphp.exe'
```

For a PHP executable rather than FrankenPHP, pass that executable with the same
parameter. If omitted, the script uses the known FrankenPHP path when present,
then a `frankenphp` or `php` application found on `PATH`; it fails if none is
available. The script runs only `php artisan migrate:status --no-ansi` (or the
FrankenPHP `php-cli` equivalent), verifies every named migration is `Ran`, and
prints only candidate path, runtime path, migration names, hashes, and statuses.

Any nonzero result blocks the release. Do not run `migrate --force`, change the
migration files, reload FrankenPHP, or cut over traffic until the discrepancy is
investigated and a separately approved recovery path exists.

## Active live reconciliation

The currently reconciled shared data state is:

- Draft orders: `0`.
- Used-book listing: exactly one listing, `id=1`, `book_id=93`, `sold_out`,
  quantity `0`.
- Used-book warehouse stock: exactly one stock record, quantity `0`,
  `warehouse_id=6`, `vendor_id=4`.
- No duplicate stock record and no cross-vendor mismatch were found.

Local and production use this shared data set. No seed, import, restore, or
other data-writing operation is permitted as part of this selective release.
