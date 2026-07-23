# Phase 1 Corrective Review Implementation Plan

Implement the final corrective actions for KomiBook Phase 1 Security Hardening according to strict constraints and specifications.

## User Review Required

> [!IMPORTANT]
> - Status in security report will be set to `READY_FOR_CODEX_REVIEW` (never `VERIFIED`).
> - Scope is strictly restricted to the specified files and tasks. No mass formatting or fixing pre-existing ESLint errors (102 errors logged in report).
> - No git commits or pushes will be performed.
> - Private download URLs strictly set to:
>   - `/api/authors/{id}/identity-document`
>   - `/api/support/tickets/{ticket}/messages/{message}/attachment`

## Proposed Changes

### 1. Frontend: Remove Old Google Dialog/Token Input (Surgical Edit)
#### [MODIFY] [RegisterView.vue](file:///c:/Users/thean/Herd/DoAnTotNGhiep_komibook/frontend/src/views/auth/RegisterView.vue)
- Perform **surgical edit only** without reformatting or rewriting the rest of the file.
- Remove the `<!-- Google Register Mock Dialog -->` template block containing `googleDialogVisible`, `googleIdTokenInput`, `handleGoogleTokenLogin`, "Dán ID Token", and "Google Register Mock Dialog".
- Remove `const googleDialogVisible = ref(false)` and `const googleIdTokenInput = ref('')` from `<script setup>`.
- Verify `rg -n "googleDialogVisible|googleIdTokenInput|handleGoogleTokenLogin|Dán ID Token|Google Register Mock Dialog" frontend/src/views/auth` returns empty output.

### 2. Frontend: Login Redirect Production Helper Integration
#### [MODIFY] [LoginView.vue](file:///c:/Users/thean/Herd/DoAnTotNGhiep_komibook/frontend/src/views/auth/LoginView.vue)
- Import and use `getPostLoginRedirect` from `@/router/guard.js`.
- Remove local `getRedirectTarget` function implementation.
- Default target strictly returns `{ name: 'dashboard' }` when no valid `redirect` query parameter exists.

### 3. Frontend: Minimal Vitest Setup
#### [MODIFY] [package.json](file:///c:/Users/thean/Herd/DoAnTotNGhiep_komibook/frontend/package.json)
- Install Vitest in `frontend/` using targeted command: `npm install --save-dev vitest` without upgrading unrelated dependencies.
- Add `"test": "vitest run"` script to `package.json`.
- Update `package-lock.json`.

### 4. Frontend: Production Route Guard Coordinator & Test Matrix
#### [NEW] [guard.js](file:///c:/Users/thean/Herd/DoAnTotNGhiep_komibook/frontend/src/router/guard.js)
- Define and export pure production guard functions and coordinator:
  - `evaluateRouteGuard(to, { isAuthenticated, userRole })`: pure decision logic for route access permissions, returning `true` or redirect targets (`{ name: 'login', query: ... }`, `{ name: 'dashboard' }`, `{ name: 'home' }`).
  - `getPostLoginRedirect(route)`: extracts `route.query.redirect` (preserving full query strings like `/profile?tab=security`) or defaults strictly to `{ name: 'dashboard' }`.
  - `runRouteGuard(to, authStore)`: production coordinator that calls `authStore.fetchUser()` ONLY when `authStore.userFetched === false`, then invokes `evaluateRouteGuard`.
#### [MODIFY] [index.js](file:///c:/Users/thean/Herd/DoAnTotNGhiep_komibook/frontend/src/router/index.js)
- Import `runRouteGuard` from `@/router/guard.js`.
- Call `await runRouteGuard(to, authStore)` directly inside `router.beforeEach`.
#### [MODIFY] [auth_guard.spec.js](file:///c:/Users/thean/Herd/DoAnTotNGhiep_komibook/frontend/src/__tests__/auth_guard.spec.js)
- Preserve existing auth store tests and test production guard coordinator `runRouteGuard` and helpers directly:
  1. **Guest opens `/register`**: returns `true`.
  2. **Guest opens `/login`**: returns `true`.
  3. **Guest opens protected route**: returns redirect to `{ name: 'login', query: { redirect: to.fullPath } }`.
  4. **401 handling on `/register`**: when `fetchUser()` receives 401, `authStore.user` is `null` and `authStore.userFetched` is `true`; calling `runRouteGuard` for `/register` returns `true` (guest is allowed to stay, not pushed to `/login`).
  5. **Authenticated user opens guest-only route**: returns redirect to `{ name: 'dashboard' }`.
  6. **Logout loop-free check**: after `logout()`, `user` is `null` and `userFetched` is `true`; calling `runRouteGuard` verifies no calls to `fetchUser()` or `/api/auth/me` are made.
  7. **Preserved Query Redirect**: verify `getPostLoginRedirect({ query: { redirect: '/profile?tab=security' } })` returns exact string `'/profile?tab=security'`.
  8. **Role-based guard regression test**: customer accessing admin route redirects to `{ name: 'home' }`; admin accessing admin route returns `true`.

### 5. Backend: Targeted Pint Fixes
- Fix Pint formatting issues on strictly these 3 files:
  #### [MODIFY] [SanitizeBookDescriptionsCommand.php](file:///c:/Users/thean/Herd/DoAnTotNGhiep_komibook/backend/app/Console/Commands/SanitizeBookDescriptionsCommand.php)
  #### [MODIFY] [MigrateAuthorDocumentsCommand.php](file:///c:/Users/thean/Herd/DoAnTotNGhiep_komibook/backend/app/Console/Commands/MigrateAuthorDocumentsCommand.php)
  #### [MODIFY] [SecurityHeadersMiddleware.php](file:///c:/Users/thean/Herd/DoAnTotNGhiep_komibook/backend/app/Http/Middleware/SecurityHeadersMiddleware.php)

### 6. Documentation Update
#### [MODIFY] [03-phase-1-security-report.md](file:///c:/Users/thean/Herd/DoAnTotNGhiep_komibook/docs/upgrade/03-phase-1-security-report.md)
- Set status to `READY_FOR_CODEX_REVIEW` (never `VERIFIED`).
- Update test statistics with exact numbers from `php artisan test` and `npm test`.
- Describe Google GIS/verifier flow and `FakeGoogleTokenVerifier` accurately.
- Describe frontend Vitest unit test suite matrix.
- Specify the two correct private download URLs:
  - `/api/authors/{id}/identity-document`
  - `/api/support/tickets/{ticket}/messages/{message}/attachment`
- Log the 102 pre-existing ESLint errors without fixing unrelated files.
- State clearly that CSP header is `Content-Security-Policy-Report-Only` and Clickjacking protection relies on `X-Frame-Options: SAMEORIGIN`.

---

## Verification Plan

### Automated Tests
Execute and report raw outputs for all required commands:
1. `composer validate --no-check-publish` (in `backend/`)
2. `php artisan test` (in `backend/`)
3. `./vendor/bin/pint --test app/Console/Commands/SanitizeBookDescriptionsCommand.php app/Console/Commands/MigrateAuthorDocumentsCommand.php app/Http/Middleware/SecurityHeadersMiddleware.php` (in `backend/`)
4. `npm test` (in `frontend/`)
5. `npm run build` (in `frontend/`)
6. `npx oxlint .` (in `frontend/`)
7. Targeted ESLint WITHOUT `--fix` run from `frontend/`: `npx eslint src/views/auth/RegisterView.vue src/views/auth/LoginView.vue src/router/guard.js src/router/index.js src/__tests__/auth_guard.spec.js`
8. `git diff --check`
9. `rg -n "googleDialogVisible|googleIdTokenInput|handleGoogleTokenLogin|Dán ID Token|Google Register Mock Dialog" frontend/src/views/auth`
