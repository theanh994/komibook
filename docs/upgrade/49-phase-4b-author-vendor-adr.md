# KomiBook Phase 4B — ADR Author–Vendor

Date: 2026-07-26  
Status: **Approved by the user**

## Decision

1. A user may own both an Author profile and a Vendor profile. The profiles have independent onboarding and lifecycle states.
2. An Author owns or is delegated creative rights. A Vendor operates commerce: storefront, price, inventory, orders and settlement. `role:vendor` must not stand in for authorship.
3. `users.role` remains temporarily for backward-compatible customer/vendor/admin navigation. Author authorization is capability-based and does not add an `author` enum value.
4. `books.vendor_id` remains the seller/operator. The legacy `books.author` string is display-only. Phase 4B.3 introduces real book-author relations.
5. Co-authorship and delegated actions require explicit persisted grants; neither names nor existing vendor links imply ownership.
6. An author may self-sell after independently becoming an approved vendor, or publish through a platform/partner vendor under a publishing agreement.
7. Publishing requires an approved Author, an accepted ownership/delegation relation, verified copyright, and an approved book revision. Selling additionally requires an active Vendor/agreement plus valid price and physical inventory or ebook file.
8. Royalty allocations originate from a versioned publishing agreement. The royalty ledger is append-only; refunds create reversals instead of rewriting history.

## Permission matrix

| Capability | Customer | Approved Author | Active Vendor | Admin |
|---|---:|---:|---:|---:|
| Maintain own author application | No | Own profile | If also author | Review only |
| Create/manage authored content | No | Own/delegated | Only if separately authorized | Review/override with audit |
| Operate storefront, price, stock, orders | No | Only if also active vendor | Own vendor | Review/override with audit |
| Review onboarding/copyright/publishing | No | No | No | Yes |

## Migration and backfill policy

- Audit duplicate `authors.user_id` and `vendors.user_id` before adding uniqueness; fail with a diagnostic instead of choosing a record.
- Preserve legacy `active` as approved and `rejected` as rejected. A complete legacy pending Author application becomes submitted; placeholder/incomplete records remain draft.
- Preserve `books.vendor_id` and the `books.author` display string. Do not invent `book_authors`, ownership, delegation, copyright or royalty data.
- Existing published books are later marked `legacy_rights_unverified`; they may remain available, but no new revision or republish is permitted before verification.
- Do not create retroactive royalties. All migrations must have safe `down()` behavior and be rehearsed only on temporary databases.

## Consequences for Phase 4B

- 4B.1 Author approval never changes `users.role` and never creates or activates a Vendor.
- 4B.2 implements independent Vendor onboarding and guards commerce capabilities.
- 4B.3 implements copyright, co-author and delegation records.
- 4B.4 implements publishing lifecycle, agreements and royalty ledgers.

