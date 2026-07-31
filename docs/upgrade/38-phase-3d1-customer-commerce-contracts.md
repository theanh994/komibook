# Phase 3D.1 — Customer commerce API contracts

## Objective

Normalize the response contracts used by the customer commerce journey without
attempting a repository-wide API rewrite.

The batch covers:

- checkout creation;
- coupon validation and public flash-sale reads;
- wishlist list, toggle, and membership checks;
- customer order and library reads plus order-action errors;
- the frontend consumers for those endpoints.

## Direct evidence before implementation

- Checkout and coupon endpoints use `success: boolean`, while most current API
  endpoints use `status: "success" | "error"`.
- Wishlist list uses Laravel's resource envelope, wishlist toggle overloads
  `status` with `"added" | "removed"`, and wishlist membership returns a bare
  object.
- Several customer views use `payload.data || payload`, so malformed responses
  can be silently accepted and different endpoint shapes remain coupled to
  view code.
- Some `OrderController` error branches and the e-book link success response do
  not include the common status/data envelope.

## Contract

Successful JSON responses in this batch use:

```json
{
  "status": "success",
  "message": "optional human-readable message",
  "data": {}
}
```

Failed JSON responses use:

```json
{
  "status": "error",
  "message": "human-readable message"
}
```

Collection endpoints return arrays in `data`. Paginated endpoints, when in
scope, keep Laravel's `data`, `links`, and `meta` fields.

For checkout and coupon responses, the existing `success` boolean is retained
for one compatibility window while `status` becomes the canonical field.

Wishlist toggle moves the mutation result to
`data.state: "added" | "removed"` and exposes the resulting boolean as
`data.is_favorite`.

## Implementation

1. Normalize the scoped backend controller responses.
2. Add a small frontend contract reader that validates the canonical envelope
   and exposes data/list/pagination helpers.
3. Migrate the scoped customer consumers to the reader and remove local
   `data || payload` guessing.
4. Add backend feature contracts and frontend unit/consumer contracts.
5. Update the API contract matrix with current direct evidence for this batch.

## Non-goals

- No production, Cloudflare, service, or database operations.
- No global middleware rewrite.
- No admin/vendor contract migration outside dependencies of the customer
  journey.
- No API version removal or cleanup of the retained compatibility boolean.
- No commit or push.

## Acceptance gates

- Focused backend commerce contract tests pass.
- Existing Phase 3 critical backend tests pass.
- Focused frontend contract tests pass.
- Frontend lint and production build pass.
- PHP formatting and `git diff --check` pass.
