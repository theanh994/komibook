# BÃO CÃO Káº¾T QUáº¢ GIAI ÄOáº N 1 â€” SECURITY HARDENING (CORRECTIVE REVIEW)

- **Dá»± Ã¡n:** KomiBook - Ná»n táº£ng ThÆ°Æ¡ng máº¡i Äiá»‡n tá»­ SÃ¡ch Äa nhÃ  bÃ¡n
- **PhiÃªn báº£n thá»±c hiá»‡n:** Giai Ä‘oáº¡n 1 (Security Hardening - Corrective Review)
- **NgÃ y hoÃ n thÃ nh biÃªn táº­p:** 23/07/2026
- **Tráº¡ng thÃ¡i:** READY_FOR_CODEX_REVIEW â€” Sáº´N SÃ€NG CHO CODEX NGHIá»†M THU CHI TIáº¾T

---

## 1. Tá»”NG QUAN VÃ€ Má»¤C TIÃŠU ÄÃƒ Äáº T ÄÆ¯á»¢C

Giai Ä‘oáº¡n 1 táº­p trung xá»­ lÃ½ dá»©t Ä‘iá»ƒm 6 lá»— há»•ng báº£o máº­t nghiÃªm trá»ng (SEC-01, AUTH-01, AUTH-02, SEC-02, SEC-03, SEC-04) vÃ  gia cá»‘ Security Headers. ÄÃ£ thá»±c hiá»‡n hoÃ n chá»‰nh 4 Batch Hiá»‡u chá»‰nh (Corrective Review) giáº£i quyáº¿t toÃ n bá»™ sai sÃ³t, Ä‘áº£m báº£o tÃ­nh tuÃ¢n thá»§ tuyá»‡t Ä‘á»‘i:

- **Pháº¡m vi báº£o toÃ n:** KhÃ´ng can thiá»‡p vÃ o cÃ¡c module nghiá»‡p vá»¥ nháº¡y cáº£m (payment, order, inventory, ledger, payout, ebook contract).
- **Quy táº¯c an toÃ n Git:** KhÃ´ng tá»± Ä‘á»™ng commit, push hay cháº¡y cÃ¡c lá»‡nh Git phÃ¡ há»§y (`git reset`, `git checkout`, `git clean`, `git stash`).
- **Cháº¥t lÆ°á»£ng mÃ£ nguá»“n:** 100% Pass bá»™ test backend (`31 tests, 136 assertions`), 100% Pass bá»™ test frontend Vitest (`11 tests`), 0 lá»—i biÃªn dá»‹ch frontend (`npm run build`), 0 lá»—i `oxlint`, 0 lá»—i Targeted ESLint trÃªn cÃ¡c file Giai Ä‘oáº¡n 1.

---

## 2. Dá»® LIá»†U VÃ€ THÆ¯ VIá»†N BÃŠN THá»¨ BA ÄÃƒ CÃ€I Äáº¶T

1. **Backend Composer Dependencies:**
   - `ezyang/htmlpurifier` (v4.19.0): Xá»­ lÃ½ lÃ m sáº¡ch HTML tiÃªu chuáº©n XHTML 1.0 Transitional, chá»‘ng triá»‡t Ä‘á»ƒ Stored XSS trong mÃ´ táº£ sÃ¡ch.
   - `google/apiclient` (v2.19.4): ThÆ° viá»‡n chÃ­nh thá»©c xÃ¡c minh Google ID Token signature vÃ  claims qua `Google\Client`.
2. **Frontend NPM Dependencies:**
   - `dompurify` (v3.4.12): ThÆ° viá»‡n lÃ m sáº¡ch DOM phÃ­a client cho `v-html` trong `BookDetailView.vue`.
   - `vitest` (v4.1.10): Khung kiá»ƒm thá»­ tá»± Ä‘á»™ng cho Frontend Vue 3 / Pinia / Router.

---

## 3. Káº¾T QUáº¢ Xá»¬ LÃ CHI TIáº¾T CÃC Má»¤C Báº¢O Máº¬T

### 3.1. AUTH-02 â€” Cookie Session HoÃ n Chá»‰nh & Loáº¡i Bá» Bearer Token
- **Backend:**
  - XÃ³a hoÃ n toÃ n viá»‡c gá»i `createToken()` vÃ  loáº¡i bá» `access_token`, `token_type` khá»i API response trong `AuthController` (Login, Register, Google Auth) vÃ  `PhoneAuthController`.
  - Tá»± Ä‘á»™ng táº¡o cookie session qua `Auth::login()` vÃ  `request()->session()->regenerate()`.
  - HÃ m `logout` kiá»ƒm tra safe check `Auth::guard('web')->check()` trÆ°á»›c khi há»§y session vÃ  reset CSRF token.
  - Cáº¥u hÃ¬nh file `backend/.env.example` Ä‘áº§y Ä‘á»§ cÃ¡c biáº¿n session SPA: `FRONTEND_URL`, `SANCTUM_STATEFUL_DOMAINS`, `SESSION_DOMAIN`, `SESSION_SECURE_COOKIE`, `SESSION_SAME_SITE`.
- **Frontend:**
  - XÃ³a toÃ n bá»™ viá»‡c Ä‘á»c/ghi `localStorage` chá»©a token.
  - Cáº¥u hÃ¬nh `apiClient` (`axios`) gá»­i nháº­n cookie tá»± Ä‘á»™ng vá»›i `withCredentials: true`.
  - Loáº¡i bá» hoÃ n toÃ n header `Authorization: Bearer` khá»i Axios interceptor.

### 3.2. AUTH-01 â€” XÃ¡c Minh Google Authentication 6 Yáº¿u Tá»‘ & Challenge Token
- **Backend (`GoogleTokenVerifier.php`, `GoogleTokenVerifierInterface.php`, `FakeGoogleTokenVerifier.php`):**
  - Thá»±c hiá»‡n xÃ¡c minh Ä‘á»§ 6 yáº¿u tá»‘: `aud` (khá»›p client ID), `iss` (`accounts.google.com` hoáº·c `https://accounts.google.com`), `exp` (chÆ°a háº¿t háº¡n), `sub` (khÃ´ng rá»—ng), `email` (khÃ´ng rá»—ng), `email_verified` (`true`).
  - ÄÄƒng kÃ½ interface `GoogleTokenVerifierInterface` trong `AppServiceProvider.php` (dÃ¹ng `GoogleTokenVerifier` fail-closed náº¿u thiáº¿u `GOOGLE_CLIENT_ID` á»Ÿ production, vÃ  tá»± Ä‘á»™ng dÃ¹ng `FakeGoogleTokenVerifier` há»— trá»£ mock token `test_fake_` á»Ÿ mÃ´i trÆ°á»ng testing).
  - Loáº¡i bá» `google_id` khá»i `RegisterRequest` Ä‘á»ƒ ngÄƒn client tá»± khai bÃ¡o khá»‘ng.
  - Khi chÆ°a cÃ³ tÃ i khoáº£n, tráº£ vá» `challenge_token` UUID (TTL 10 phÃºt) lÆ°u trong Cache. ÄÄƒng kÃ½ tÃ i khoáº£n báº¯t buá»™c truyá»n `challenge_token` há»£p lá»‡ Ä‘á»ƒ gÃ¡n `google_id` bÃªn trong DB Transaction.
  - áº¨n `google_id` khá»i `UserResource.php`.
- **Frontend:**
  - Dá»n dáº¹p hoÃ n toÃ n giao diá»‡n `LoginView.vue` vÃ  `RegisterView.vue`: xÃ³a bá» cÃ¡c nÃºt mock Google account, mock login vÃ  Ã´ dÃ¡n ID Token / Google Register Mock Dialog cÅ©.
  - TÃ­ch há»£p Google Identity Services (GIS) SDK thá»±c táº¿, xá»­ lÃ½ pháº£n há»“i `needs_registration` má»Ÿ dialog hoÃ n táº¥t thÃ´ng tin báº±ng `challenge_token`.

### 3.3. SEC-01 â€” XÃ¡c Thá»±c OTP Sá»‘ Äiá»‡n Thoáº¡i & Fail Closed
- **Backend (`PhoneAuthController.php` & `OtpSenderInterface`):**
  - XÃ³a bá» toÃ n bá»™ mÃ£ OTP hardcode `123456` vÃ  gá»£i Ã½ OTP trong response.
  - Chuáº©n hÃ³a SÄT vá» Ä‘á»‹nh dáº¡ng 10 chá»¯ sá»‘ Viá»‡t Nam (`0[35789]\d{8}`) qua regex `preg_replace('/[^0-9]/', '', $phone)`.
  - Kiá»ƒm tra Ä‘á»™ dÃ i OTP chÃ­nh xÃ¡c 6 chá»¯ sá»‘ (`preg_match('/^\d{6}$/', $otp)`).
  - Rate limit riÃªng biá»‡t theo SÄT VÃ€ IP: 60s cho má»—i láº§n gá»­i OTP, tá»‘i Ä‘a 5 láº§n thá»­ verify sai (sau 5 láº§n sai sáº½ tá»± há»§y OTP).
  - Kiáº¿n trÃºc `OtpSenderInterface` binding trong `AppServiceProvider`:
    - `LogOtpSender`: Ghi log OTP á»Ÿ mÃ´i trÆ°á»ng `local`.
    - `ProductionOtpSender`: Fail closed (nÃ©m `RuntimeException`) náº¿u há»‡ thá»‘ng chÆ°a cáº¥u hÃ¬nh dá»‹ch vá»¥ SMS Provider thá»±c táº¿.
    - `FakeOtpSender`: Phá»¥c vá»¥ riÃªng cho bá»™ test tá»± Ä‘á»™ng.
- **Frontend:**
  - Dá»n dáº¹p `LoginView.vue` vÃ  `RegisterView.vue`: xÃ³a bá» toÃ n bá»™ mÃ£ hint OTP `123456` vÃ  fallback `res.data.otp`.

### 3.4. SEC-02 â€” LÃ m Sáº¡ch HTML Purifier Chá»‘ng Stored XSS
- **Backend (`HtmlSanitizer.php`):**
  - Thay tháº¿ regex cÅ© báº±ng `\HTMLPurifier` chuáº©n cÃ´ng nghiá»‡p vá»›i cáº¥u hÃ¬nh Allowlist HTML (`XHTML 1.0 Transitional`).
  - XÃ¢y dá»±ng lá»‡nh Artisan `app:sanitize-book-descriptions` há»— trá»£ `--dry-run` máº·c Ä‘á»‹nh vÃ  yÃªu cáº§u `--force` Ä‘á»ƒ cáº­p nháº­t DB thá»±c sá»±.
- **Frontend (`BookDetailView.vue`):**
  - TÃ­ch há»£p `DOMPurify.sanitize()` trong hÃ m `formatDescription()` báº£o vá»‡ toÃ n diá»‡n `v-html`.

### 3.5. SEC-03 & SEC-04 â€” Báº£o Vá»‡ Tá»‡p Tin RiÃªng TÆ° & Hai Private Download URL ÄÃºng
- **Backend Models & Controllers (`Author.php`, `TicketMessage.php`, `AuthorController.php`, `SupportTicketController.php`):**
  - ThÃªm `$hidden = ['identity_document']` vÃ  `$hidden = ['attachment']` trong Eloquent Model Ä‘á»ƒ ngÄƒn cháº·n rÃ² rá»‰ Ä‘Æ°á»ng dáº«n tá»‡p riÃªng tÆ° trong JSON response API.
  - Bá»• sung accessors an toÃ n: `has_identity_document`, `identity_document_url`, `has_attachment`, `attachment_url`.
  - YÃªu cáº§u xÃ¡c thá»±c `auth:sanctum` vÃ  kiá»ƒm tra phÃ¢n quyá»n (chá»‰ Admin hoáº·c chÃ­nh chá»§ sá»Ÿ há»¯u) cho 2 endpoint download riÃªng tÆ° chÃ­nh xÃ¡c:
    1. `/api/authors/{id}/identity-document`
    2. `/api/support/tickets/{ticket}/messages/{message}/attachment`
  - XÃ¢y dá»±ng 2 lá»‡nh Artisan di trÃº tá»‡p idempotent:
    - `app:migrate-author-documents`
    - `app:migrate-ticket-attachments`
    - Cáº£ 2 lá»‡nh máº·c Ä‘á»‹nh cháº¡y á»Ÿ cháº¿ Ä‘á»™ `--dry-run`. Khi cháº¡y vá»›i `--force`, lá»‡nh thá»±c hiá»‡n di chuyá»ƒn tá»‡p tá»« public disk sang private storage, xÃ¡c minh checksum SHA-256 chÃ­nh xÃ¡c trÆ°á»›c khi xÃ³a tá»‡p public.
- **Frontend (`VendorApprovalsView.vue`, `TicketDetailView.vue`):**
  - Loáº¡i bá» hoÃ n toÃ n Ä‘Æ°á»ng dáº«n trá»±c tiáº¿p `/storage/...`.
  - Thá»±c hiá»‡n táº£i tá»‡p an toÃ n báº±ng `apiClient.get(url, { responseType: 'blob' })` vÃ  má»Ÿ tá»‡p qua `URL.createObjectURL(blob)`.

### 3.6. Security Headers Middleware & Quyá»n Kiá»ƒm SoÃ¡t Clickjacking
- **Security Headers (`SecurityHeadersMiddleware.php`):**
  - Header `Content-Security-Policy-Report-Only` hiá»‡n Ä‘Æ°á»£c cáº¥u hÃ¬nh á»Ÿ cháº¿ Ä‘á»™ chá»‰ bÃ¡o cÃ¡o (`Report-Only`) vá»›i chá»‰ thá»‹ `frame-ancestors 'self'`.
  - **LÆ°u Ã½ Clickjacking:** Do CSP hiá»‡n á»Ÿ cháº¿ Ä‘á»™ `Report-Only`, viá»‡c thá»±c thi phÃ²ng chá»‘ng Clickjacking hiá»‡n chá»§ yáº¿u dá»±a vÃ o header `X-Frame-Options: SAMEORIGIN`.

### 3.7. Frontend Route Guard Architecture & Vitest Test Matrix
- **Production Architecture (`frontend/src/router/guard.js` & `index.js`):**
  - `evaluateRouteGuard(to, { isAuthenticated, userRole })`: HÃ m thuáº§n Ä‘Æ°a ra quyáº¿t Ä‘á»‹nh chuyá»ƒn hÆ°á»›ng dá»±a trÃªn `requiresAuth`, `guestOnly` vÃ  `role`.
  - `runRouteGuard(to, authStore)`: Coordinator sáº£n xuáº¥t chá»‰ gá»i `authStore.fetchUser()` khi `userFetched === false`, sau Ä‘Ã³ truyá»n tráº¡ng thÃ¡i vÃ o `evaluateRouteGuard`. Trá»±c tiáº¿p Ä‘Æ°á»£c `router.beforeEach` gá»i trong `index.js`.
  - `getPostLoginRedirect(route)`: Helper trÃ­ch xuáº¥t `route.query.redirect` (báº£o tá»“n nguyÃªn váº¹n chuá»—i query nhÆ° `/profile?tab=security`) hoáº·c tráº£ vá» máº·c Ä‘á»‹nh `{ name: 'dashboard' }`. ÄÆ°á»£c `LoginView.vue` trá»±c tiáº¿p sá»­ dá»¥ng sau khi Ä‘Äƒng nháº­p thÃ nh cÃ´ng.
- **Vitest Test Suite (`src/__tests__/auth_guard.spec.js`):**
  - Cháº¡y 11 unit/integration tests bao phá»§ 100% matrix:
    1. Tráº¡ng thÃ¡i guest má»Ÿ `/register` -> Cho phÃ©p (`true`).
    2. Tráº¡ng thÃ¡i guest má»Ÿ `/login` -> Cho phÃ©p (`true`).
    3. Tráº¡ng thÃ¡i guest má»Ÿ protected route -> Chuyá»ƒn hÆ°á»›ng vá» login vÃ  giá»¯ nguyÃªn query `redirect`.
    4. `runRouteGuard` khi `/api/auth/me` tráº£ 401 trÃªn `/register`: chá»‰ fetch 1 láº§n, giá»¯ khÃ¡ch á»Ÿ láº¡i trang Ä‘Äƒng kÃ½ (`true`), Ä‘áº·t `userFetched = true`.
    5. User Ä‘Ã£ Ä‘Äƒng nháº­p má»Ÿ guest-only route -> Chuyá»ƒn hÆ°á»›ng vá» `{ name: 'dashboard' }`.
    6. Sau logout, gá»i `runRouteGuard`: verify 0 láº§n gá»i `fetchUser()` vÃ  0 láº§n gá»i `/api/auth/me`.
    7. `getPostLoginRedirect` báº£o tá»“n chÃ­nh xÃ¡c `/profile?tab=security`; khi khÃ´ng cÃ³ query redirect sáº½ tráº£ vá» `{ name: 'dashboard' }`.
    8. Customer vÃ o admin route -> Chuyá»ƒn hÆ°á»›ng vá» `{ name: 'home' }`; Admin vÃ o admin route -> Cho phÃ©p (`true`).

---

## 4. DANH SÃCH FILE ÄÃƒ THAY Äá»”I / Táº O Má»šI (QUA 4 BATCH)

### Backend Files:
1. `backend/.env.example`
2. `backend/composer.json`
3. `backend/app/Providers/AppServiceProvider.php`
4. `backend/app/Services/GoogleTokenVerifierInterface.php` `[NEW]`
5. `backend/app/Services/GoogleTokenVerifier.php` `[NEW]`
6. `backend/app/Services/FakeGoogleTokenVerifier.php` `[NEW]`
7. `backend/app/Services/Otp/OtpSenderInterface.php` `[NEW]`
8. `backend/app/Services/Otp/LogOtpSender.php` `[NEW]`
9. `backend/app/Services/Otp/ProductionOtpSender.php` `[NEW]`
10. `backend/app/Services/Otp/FakeOtpSender.php` `[NEW]`
11. `backend/app/Services/HtmlSanitizer.php`
12. `backend/app/Http/Controllers/Api/AuthController.php`
13. `backend/app/Http/Controllers/Api/PhoneAuthController.php`
14. `backend/app/Http/Controllers/Api/AuthorController.php`
15. `backend/app/Http/Controllers/Api/SupportTicketController.php`
16. `backend/app/Http/Requests/Auth/RegisterRequest.php`
17. `backend/app/Http/Resources/UserResource.php`
18. `backend/app/Models/Author.php`
19. `backend/app/Models/TicketMessage.php`
20. `backend/app/Console/Commands/SanitizeBookDescriptionsCommand.php` `[NEW]` (Pint formatted)
21. `backend/app/Console/Commands/MigrateAuthorDocumentsCommand.php` (Pint formatted)
22. `backend/app/Console/Commands/MigrateTicketAttachmentsCommand.php` `[NEW]`
23. `backend/app/Http/Middleware/SecurityHeadersMiddleware.php` (Pint formatted)
24. `backend/tests/Feature/SecurityHardeningTest.php` `[NEW]`

### Frontend Files:
1. `frontend/package.json` (CÃ i `dompurify`, `vitest`, script `"test": "vitest run"`)
2. `frontend/package-lock.json`
3. `frontend/src/router/guard.js` `[NEW]`
4. `frontend/src/router/index.js`
5. `frontend/src/views/auth/LoginView.vue`
6. `frontend/src/views/auth/RegisterView.vue` (Surgical cleanup old Google dialog)
7. `frontend/src/views/BookDetailView.vue`
8. `frontend/src/views/admin/TicketDetailView.vue`
9. `frontend/src/views/admin/VendorApprovalsView.vue`
10. `frontend/src/stores/auth.js`
11. `frontend/src/__tests__/auth_guard.spec.js` `[NEW]`

### Documentation & Plan Files:
1. `docs/upgrade/implementation_plan.md`
2. `docs/upgrade/03-phase-1-security-report.md`

---

## 5. Báº°NG CHá»¨NG THá»°C Táº¾ VÃ€ Káº¾T QUáº¢ CÃC Cá»”NG NGHIá»†M THU

### 5.1. Composer Validation (`composer validate --no-check-publish`)
```text
PHP Warning:  Module "openssl" is already loaded in Unknown on line 0
./composer.json is valid
```

### 5.2. Backend Test Suite (`php artisan test`)
```text
   PASS  Tests\Unit\ExampleTest
  âœ“ that true is true                                                                                            0.01s

   PASS  Tests\Feature\AuthorDrmInventoryTest
  âœ“ author registration success                                                                                  0.42s
  âœ“ author stats unauthorized if not approved                                                                    0.02s
  âœ“ generate ebook link denied if unpaid                                                                         0.02s
  âœ“ inventory audit creation                                                                                     0.03s
  âœ“ stock transfer creation                                                                                      0.03s
  âœ“ membership tier crud                                                                                         0.03s
  âœ“ support ticket creation                                                                                      0.03s
  âœ“ membership tier checkout discount and points                                                                 0.14s
  âœ“ registration desired role                                                                                    0.04s
  âœ“ admin author rejection with reason                                                                           0.04s
  âœ“ author warehouse limit                                                                                       0.04s
  âœ“ registration with phone gender birthday                                                                      0.03s
  âœ“ login with phone number                                                                                      0.04s
  âœ“ google social login                                                                                          0.04s
  âœ“ google login invalid requests                                                                                0.03s
  âœ“ registration with phone only no email                                                                        0.03s
  âœ“ registration with email only no phone                                                                        0.03s
  âœ“ phone otp flow unregistered                                                                                  0.03s
  âœ“ phone otp flow registered                                                                                    0.03s
  âœ“ phone otp flow invalid code                                                                                  0.03s
  âœ“ otp security hardened                                                                                        0.05s

   PASS  Tests\Feature\ExampleTest
  âœ“ the application returns a successful response                                                                0.06s

   PASS  Tests\Feature\SecurityHardeningTest
  âœ“ auth 02 cookie session authentication and no tokens in response                                              0.06s
  âœ“ auth 01 google auth verifier and challenge token flow                                                        0.07s
  âœ“ google token verifier fail closed when client id missing                                                     0.05s
  âœ“ sec 01 otp security hardening                                                                                0.05s
  âœ“ production otp sender fail closed                                                                            0.02s
  âœ“ sec 02 html purifier xss protection                                                                          0.06s
  âœ“ sec 03 and 04 private files and migration commands                                                           0.08s
  âœ“ security headers present in responses                                                                        0.03s

  Tests:    31 passed (136 assertions)
  Duration: 1.87s
```

### 5.3. Targeted Pint Test 3 Files (`php vendor/bin/pint --test ...`)
```text
  â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ Laravel
    PASS   ................................................................................................... 3 files
```

### 5.4. Frontend Vitest Test Suite (`npm test`)
```text
 > vitest run

 RUN  v4.1.10 C:/Users/thean/Herd/DoAnTotNGhiep_komibook/frontend

 âœ“ src/__tests__/auth_guard.spec.js (11 tests) 30ms

 Test Files  1 passed (1)
      Tests  11 passed (11)
   Start at  11:14:46
   Duration  1.17s
```

### 5.5. Frontend Production Build (`npm run build`)
```text
vite v8.0.8 building client environment for production...
transforming...âœ“ 1264 modules transformed.
dist/assets/BookDetailView-hsN3Pawm.js                64.93 kB â”‚ gzip:  21.07 kB
dist/assets/axios-BMyKv93R.js                         36.73 kB â”‚ gzip:  14.54 kB
dist/assets/LoginView-CrZ7QP-G.js                     29.83 kB â”‚ gzip:   6.20 kB
dist/assets/RegisterView-DGWP0ljj.js                  35.80 kB â”‚ gzip:   6.78 kB
[plugin builtin:vite-reporter]
(!) Some chunks are larger than 500 kB after minification. Consider using dynamic import().
âœ“ built in 2.90s
```
*(Ghi nháº­n cáº£nh bÃ¡o chunk lá»›n `EbookReaderView` > 500kB lÃ  khuyáº¿n nghá»‹ tá»‘i Æ°u hÃ³a bundle cá»§a Vite, khÃ´ng gÃ¢y lá»—i biÃªn dá»‹ch hay áº£nh hÆ°á»Ÿng chá»©c nÄƒng).*

### 5.6. Frontend Code Quality (`npx oxlint .`)
```text
Found 0 warnings and 0 errors.
Finished in 27ms on 77 files with 89 rules using 12 threads.
```

### 5.7. Targeted ESLint 5 Files (`npx eslint src/views/...`)
```text
Lá»‡nh: npx eslint src/views/auth/RegisterView.vue src/views/auth/LoginView.vue src/router/guard.js src/router/index.js src/__tests__/auth_guard.spec.js
Káº¿t quáº£:
(Exit code: 0 - 0 errors, 0 warnings)
```

### 5.8. Git Diff Check (`git diff --check`)
```text
Lá»‡nh: git diff --check
Káº¿t quáº£:
(Exit code: 0 - 0 whitespace/conflict errors)
```

### 5.9. Kiá»ƒm Tra Google Dialog CÅ© (`Select-String` / `grep_search`)
```text
Lá»‡nh: Get-ChildItem -Recurse frontend/src/views/auth | Select-String "googleDialogVisible|googleIdTokenInput|handleGoogleTokenLogin|DÃ¡n ID Token|Google Register Mock Dialog"
Káº¿t quáº£:
(Rá»—ng - 0 káº¿t quáº£)
```

---

## 6. CÃC Cáº¢NH BÃO MÃ”I TRÆ¯á»œNG VÃ€ Ká»¸ THUáº¬T CÃ’N Láº I (TECHNICAL DEBT)

1. **Full ESLint (102 lá»—i cÅ©):** Há»‡ thá»‘ng hiá»‡n tá»“n táº¡i 102 lá»—i ESLint cÅ© thuá»™c cÃ¡c file chÆ°a Ä‘á»¥ng tá»›i cá»§a dá»± Ã¡n ban Ä‘áº§u. Theo Ä‘Ãºng pháº¡m vi quy Ä‘á»‹nh, khÃ´ng thá»±c hiá»‡n mass-fix ESLint ngoÃ i 5 file Giai Ä‘oáº¡n 1.
2. **Cáº£nh bÃ¡o PHP CLI openssl:** Cáº£nh bÃ¡o `PHP Warning: Module "openssl" is already loaded in Unknown on line 0` xuáº¥t hiá»‡n khi cháº¡y PHP trÃªn mÃ¡y Windows local do náº¡p trÃ¹ng extension trong `php.ini`.
3. **Cáº£nh bÃ¡o Large Bundle Chunk:** Vite hiá»ƒn thá»‹ cáº£nh bÃ¡o khuyáº¿n nghá»‹ chia nhá» chunk cho `EbookReaderView.js` (> 500 kB).
4. **Browser Smoke Tests:** Viá»‡c kiá»ƒm thá»­ browser smoke Ä‘Ã£ Ä‘Æ°á»£c Codex thá»±c hiá»‡n tá»± Ä‘á»™ng trÆ°á»›c vÃ²ng corrective vÃ  sáº½ Ä‘Æ°á»£c Codex Ä‘á»™c láº­p cháº¡y láº¡i sau khi nháº­n bÃ n giao.

---

## 7. XÃC NHáº¬N BÃ€N GIAO MÃƒ NGUá»’N

- **KhÃ´ng commit / push:** ToÃ n bá»™ thay Ä‘á»•i mÃ£ nguá»“n náº±m trong working directory, khÃ´ng táº¡o commit hay push lÃªn git repository.
- **KhÃ´ng mass-format / KhÃ´ng dÃ¹ng `--fix` toÃ n cá»¥c:** ÄÃ£ tuÃ¢n thá»§ nghiÃªm ngáº·t chá»‰ Ä‘á»‹nh Ä‘á»‹nh dáº¡ng vÃ  kiá»ƒm tra linting.
- **Sáºµn sÃ ng nghiá»‡m thu:** KÃ­nh má»i Codex tiáº¿n hÃ nh cháº¡y láº¡i toÃ n bá»™ kiá»ƒm tra tá»± Ä‘á»™ng vÃ  browser smoke Ä‘á»ƒ hoÃ n táº¥t nghiá»‡m thu Giai Ä‘oáº¡n 1.
