# KomiBook Runtime Artifact & Backup Manifest (Batch 1P)

TÃ i liá»‡u quáº£n lÃ½ danh má»¥c Metadata cÃ¡c Runtime Artifacts chÃ­nh thá»©c vÃ  Káº¿ hoáº¡ch Backup cho quÃ¡ trÃ¬nh chuyá»ƒn Ä‘á»•i mÃ´i trÆ°á»ng Production dá»± Ã¡n KomiBook.

- **Dá»± Ã¡n:** KomiBook - Ná»n táº£ng ThÆ°Æ¡ng máº¡i Äiá»‡n tá»­ SÃ¡ch Äa nhÃ  bÃ¡n
- **Há»‡ Ä‘iá»u hÃ nh target:** Microsoft Windows 11 Home Single Language 64-bit
- **Thá»i Ä‘iá»ƒm cáº­p nháº­t Batch 1P.2:** 2026-07-23 18:05:05 +07:00
- **Tráº¡ng thÃ¡i tÃ i liá»‡u:** `BATCH_1P_READY_FOR_CODEX_REVIEW`

> [!IMPORTANT]
> **Káº¾T QUáº¢ THá»°C THI TOÃ€N Bá»˜ 9 QUALITY GATES (BATCH 1P.2):**
> - **Táº¥t Cáº£ 9 Gates Báº¯t Buá»™c Äá»u Äáº¡t Standard PASS 100%:**
>   - **Gate 1 (Composer Validate):** Exit `0` (`composer.json is valid`).
>   - **Gate 2 (Backend PHPUnit):** Exit `0` (`33 tests, 140 assertions, 0 errors, 4 PHP 8.5 deprecations` â€” PASS 100%).
>   - **Gate 3 (Targeted Pint):** Exit `0` (4 files target backend Ä‘áº¡t chuáº©n code style Pint).
>   - **Gate 4 (Frontend Unit Tests):** Exit `0` (`11/11 tests passed`).
>   - **Gate 5 (Frontend Build):** Exit `0` (`Production bundle built successfully in dist/`). Ghi nháº­n cáº£nh bÃ¡o chunk lá»›n non-blocking.
>   - **Gate 6 (Oxlint):** Exit `0` (`0 errors, 0 warnings`).
>   - **Gate 7 (Targeted ESLint):** Exit `0` (0 lá»—i lint trÃªn 5 file auth/router Ä‘Æ°á»£c chá»‰ Ä‘á»‹nh). *(Full ESLint inventory kiá»ƒm kÃª ná»£ ká»¹ thuáº­t cÅ©: 102 errors, 0 warnings â€” non-mandatory).*
>   - **Gate 8 (Git Whitespace Check):** Exit `0` (`git diff --check` sáº¡ch 100%).
>   - **Gate 9 (Dead Code & Source Inspection):** Raw `rg` exit `1` = semantic `PASS` (0 dead Google auth code references). ÄÃ£ xÃ¡c minh `bootstrap/app.php` chá»‰ cÃ³ `trustProxies(at: ['127.0.0.1'])`, khÃ´ng cÃ²n `*`, vÃ  cÃ³ `SecurityHeadersMiddleware`.
> - **Há»‡ Thá»‘ng Invariants:**
>   - `test-config/php.ini` SHA-256: `F72140EB0ECCC6926ADE7AE3A9A8A82FD5FF5275B505A477A9EFF73C273E5E1C` (**UNTOUCHED**).
>   - Benchmark `database.sqlite` SHA-256: `FFFDAB73ED40BBC368890786E6B4EBF62EE2F0ACCDA66DF143B14310BF7C39A6` (**UNTOUCHED**).
>   - `.env.testing` khÃ´ng bá»‹ táº¡o ra (**PASS**).
>   - `0` tiáº¿n trÃ¬nh tá»« `C:\runtimes\`, `FrankenPHPService` khÃ´ng tá»“n táº¡i, no listener on 8080 (**PASS**).
> - **Tuyá»‡t Äá»‘i KhÃ´ng Tá»± Ghi `VERIFIED`:** Dá»«ng láº¡i táº¡i tráº¡ng thÃ¡i `BATCH_1P_READY_FOR_CODEX_REVIEW` Ä‘á»ƒ Codex nghiá»‡m thu láº¡i vÃ  xin Ã½ kiáº¿n NgÆ°á»i dÃ¹ng quyáº¿t Ä‘á»‹nh commit.

---

## 1. Báº¢NG METADATA RUNTIME ARTIFACTS

| Thuá»™c tÃ­nh | Artifact 1: Node.js Portable | Artifact 2: FrankenPHP Windows | Artifact 3: WinSW |
|---|---|---|---|
| **Exact Version** | `v22.13.0` | `v1.12.6` | `v2.12.0` |
| **Architecture** | `x64` (64-bit) | `x64` (x86_64) | `x64` |
| **Exact Filename** | `node-v22.13.0-win-x64.zip` | `frankenphp-windows-x86_64.zip` | `WinSW-x64.exe` |
| **Official Release Page** | `https://nodejs.org/en/blog/release/v22.13.0` | `https://github.com/php/frankenphp/releases/tag/v1.12.6` | `https://github.com/winsw/winsw/releases/tag/v2.12.0` |
| **Direct Official HTTPS URL** | `https://nodejs.org/dist/v22.13.0/node-v22.13.0-win-x64.zip` | `https://github.com/php/frankenphp/releases/download/v1.12.6/frankenphp-windows-x86_64.zip` | `https://github.com/winsw/winsw/releases/download/v2.12.0/WinSW-x64.exe` |
| **Original File SHA-256** | `b0feb09ebf41328628e7383f7a092fb7342ce1e05c867a90cf8f1379205a8429` | `a02a4a54ba0afa60e6284c90bcd9e0d46268b403cd90a5f3f36491001bc3648d` | `05b82d46ad331cc16bdc00de5c6332c1ef818df8ceefcd49c726553209b3a0da` |
| **Actual Byte Size** | `34,866,524 bytes` (34.87 MB) | `59,182,849 bytes` (59.18 MB) | `18,243,033 bytes` (18.24 MB) |
| **ThÆ° Má»¥c LÆ°u / Giáº£i NÃ©n** | `C:\runtimes\node-v22.13.0-win-x64` | `C:\runtimes\frankenphp-v1.12.6` | `C:\runtimes\winsw-v2.12.0` |
| **Introspection Version Result** | **Node:** `v22.13.0` (Exit 0)<br>**NPM:** `10.9.2` (Exit 0) | **FrankenPHP:** `1.12.6` (Exit 0)<br>**PHP Engine:** `8.5.8`<br>**Caddy Engine:** `v2.11.4` | **WinSW:** Required XML config missing (Exit `-1` / `FileNotFoundException`) |
| **PHP Configuration Path** | N/A | Production: `C:\runtimes\frankenphp-v1.12.6\php.ini`<br>Test: `C:\runtimes\frankenphp-v1.12.6\test-config\php.ini` | N/A |
| **Full Quality Gates Result** | N/A | **33 Tests, 140 Assertions (Exit 0), All 9 Quality Gates 100% PASS** | N/A |
| **Tráº¡ng ThÃ¡i Triá»ƒn Khai** | `BATCH_1P_READY_FOR_CODEX_REVIEW` | `BATCH_1P_READY_FOR_CODEX_REVIEW` | `BATCH_1P_READY_FOR_CODEX_REVIEW` |

---

## 2. Báº¢NG Tá»”NG Há»¢P Káº¾T QUáº¢ 9 PHASE 1 QUALITY GATES (POST-TRUSTED PROXY CHANGE)

| Gate ID | TÃªn Kiá»ƒm Thá»­ | Lá»‡nh Thá»±c Thi Thá»±c Táº¿ | Raw Exit | Norm Exit | Tráº¡ng ThÃ¡i & Káº¿t Luáº­n |
|---|---|---|---|---|---|
| **Gate 1** | Composer Validate | `frankenphp.exe php-cli composer.phar validate --no-check-publish` | `0` | `0` | **PASS** â€” `composer.json` há»£p lá»‡. |
| **Gate 2** | Backend PHPUnit | `frankenphp.exe php-cli vendor\phpunit\phpunit\phpunit` | `0` | `0` | **PASS** â€” `33 tests, 140 assertions, 0 errors, 4 PHP 8.5 deprecations`. |
| **Gate 3** | Targeted Pint Check | `frankenphp.exe php-cli vendor/laravel/pint/builds/pint --test <4 files>` | `0` | `0` | **PASS** â€” 4 files target backend Ä‘áº¡t chuáº©n code style Pint. |
| **Gate 4** | Frontend Unit Tests | `npm test` (sá»­ dá»¥ng Node portable v22.13.0) | `0` | `0` | **PASS** â€” `11/11 tests passed`. |
| **Gate 5** | Frontend Build | `npm run build` | `0` | `0` | **PASS** â€” Static bundle Ä‘Æ°á»£c Ä‘Ã³ng gÃ³i thÃ nh cÃ´ng vÃ o `dist/`. *(Ná»£ ká»¹ thuáº­t chunk lá»›n non-blocking).* |
| **Gate 6** | Oxlint Check | `npx --no-install oxlint .` | `0` | `0` | **PASS** â€” `0 warnings, 0 errors` trÃªn 77 files. |
| **Gate 7** | Targeted ESLint | `npx --no-install eslint <5 auth/router files>` | `0` | `0` | **PASS** â€” `0 errors` trÃªn 5 files auth/router Ä‘Æ°á»£c chá»‰ Ä‘á»‹nh. |
| *(Info)* | *(Full ESLint Inventory)* | `npx --no-install eslint .` | `1` | `1` | *(Non-mandatory)* â€” Kiá»ƒm kÃª 102 errors ná»£ ká»¹ thuáº­t cÅ©. |
| **Gate 8** | Git Whitespace Check | `git diff --check` | `0` | `0` | **PASS** â€” Sáº¡ch 100%, khÃ´ng phÃ¡t sinh lá»—i khoáº£ng tráº¯ng hay conflict markers. |
| **Gate 9** | Dead Code Check & Source Inspection | `rg -n <dead_symbols> frontend/src/views/auth` | `1` | `0` | **PASS** â€” 0 dead code references. Source `bootstrap/app.php` verified: chá»‰ chá»©a `trustProxies(at: ['127.0.0.1'])` vÃ  `SecurityHeadersMiddleware`. |

---

## 3. Káº¾T QUáº¢ KIá»‚M TRA FILE INVARIANTS & Há»† THá»NG

1. **File `test-config/php.ini`:** **`PASS`** (Giá»¯ nguyÃªn mÃ£ bÄƒm `F72140EB0ECCC6926ADE7AE3A9A8A82FD5FF5275B505A477A9EFF73C273E5E1C`).
2. **File `database.sqlite` trong `backend`:** **`PASS`** (Giá»¯ nguyÃªn mÃ£ bÄƒm `FFFDAB73ED40BBC368890786E6B4EBF62EE2F0ACCDA66DF143B14310BF7C39A6`).
3. **File `.env.testing` trong `backend`:** **`PASS`** (KhÃ´ng bá»‹ táº¡o ra).
4. **Tiáº¿n TrÃ¬nh Äang Cháº¡y (Processes):** **`0`** tiáº¿n trÃ¬nh tá»« `C:\runtimes\` cÃ²n tá»“n táº¡i (`PASS`).
5. **Windows Service:** `FrankenPHPService` **khÃ´ng tá»“n táº¡i** (`PASS`).
6. **Network Listener (Port 8080):** **KhÃ´ng cÃ³ tiáº¿n trÃ¬nh nÃ o láº¯ng nghe trÃªn port 8080** (`PASS`).

---

## 4. DANH Má»¤C BACKUP MANIFEST CHUáº¨N HÃ“A (BACKUP SCOPE)

Vá»‹ trÃ­ lÆ°u trá»¯ backup dá»± kiáº¿n: `TBD â€” REQUIRES USER APPROVAL` (ThÆ° má»¥c sao lÆ°u riÃªng biá»‡t ngoÃ i cÃ¢y mÃ£ nguá»“n, chÆ°a táº¡o Ä‘Æ°á»ng dáº«n).

| STT | Danh Má»¥c Dá»¯ Liá»‡u | Loáº¡i Dá»¯ Liá»‡u & ÄÆ°á»ng Dáº«n Target | Quy TrÃ¬nh & PhÆ°Æ¡ng Ãn Sao LÆ°u |
|---|---|---|---|
| 1 | **Repository Source** | `C:\Users\thean\Herd\DoAnTotNGhiep_komibook` | ÄÃ³ng gÃ³i nÃ©n archive (.zip) toÃ n bá»™ lÃ m viá»‡c + tÃ­nh toÃ¡n mÃ£ bÄƒm SHA-256. KhÃ´ng dÃ¹ng "local branch backup". |
| 2 | **Production .env & Private Files** | `C:\komibook_shared\.env`<br>`storage/app/private/*` | ÄÃ³ng gÃ³i nÃ©n archive cÃ³ mÃ£ hÃ³a máº­t kháº©u (Password-Encrypted Archive). **Tuyá»‡t Ä‘á»‘i khÃ´ng lÆ°u file `.env.bak` cáº¡nh mÃ£ nguá»“n.** |
| 3 | **Database Dump** | MySQL Database Instance (`komibook`) | Cháº¡y `mysqldump` / `mariadb-dump` nháº¥t quÃ¡n + tÃ­nh SHA-256 + thá»±c hiá»‡n bÆ°á»›c kiá»ƒm tra file `.sql` Ä‘á»c Ä‘Æ°á»£c ná»™i dung trÆ°á»›c khi lÆ°u. |
| 4 | **Public Storage** | `C:\komibook_shared\storage\app\public` | ÄÃ³ng gÃ³i nÃ©n archive (.zip) + tÃ­nh toÃ¡n mÃ£ bÄƒm SHA-256. |
| 5 | **Cloudflare Ingress Config** | Quy táº¯c Ingress Tunnel | Export/Screenshot cáº¥u hÃ¬nh ingress target hiá»‡n táº¡i (`http://localhost:5173`) vÃ  ghi nháº­n target rollback rÃµ rÃ ng. |
| 6 | **Codex & IDE Data** | `C:\Users\thean\.codex`<br>`C:\Users\thean\AppData\Roaming\Antigravity IDE` | ÄÃ³ng gÃ³i nÃ©n archive (.zip) dá»¯ liá»‡u á»©ng dá»¥ng + tÃ­nh toÃ¡n mÃ£ bÄƒm SHA-256. |

---

## 5. GHI NHáº¬N TRáº NG THÃI GIT & RELEASE SHA POLICY

- **Branch hiá»‡n táº¡i:** `master`
- **Commit `HEAD` hiá»‡n táº¡i:** `053c684b0bdd518450f8eb8330c5ff07f4e10b52`
- **Quy táº¯c Release SHA nghiÃªm ngáº·t:**
  Worktree hiá»‡n táº¡i Ä‘ang á»Ÿ tráº¡ng thÃ¡i **chÆ°a sáº¡ch** (`git status --short` cÃ²n cÃ¡c file modified vÃ  untracked). Do Ä‘Ã³, Commit `HEAD` hiá»‡n táº¡i **TUYá»†T Äá»I KHÃ”NG ÄÆ¯á»¢C COI LÃ€ RELEASE SHA**.
  Báº£n Ä‘Ã³ng gÃ³i Clean Release Artifact (`C:\komibook_releases\<SHA>\`) **chá»‰ Ä‘Æ°á»£c phÃ©p táº¡o ra** sau khi hoÃ n thÃ nh Batch 1P, Ä‘Æ°á»£c Codex tháº©m Ä‘á»‹nh thÃ´ng qua vÃ  NgÆ°á»i dÃ¹ng ra chá»‰ thá»‹ commit chÃ­nh thá»©c.

---

## 6. DEFERRED CODEX GATES (CÃC ÄIá»€U KIá»†N CÃ’N TREO)

1. **HoÃ n thiá»‡n release assembly trong Batch 1B.1:** Äáº£m báº£o toÃ n bá»™ quÃ¡ trÃ¬nh Ä‘Ã³ng gÃ³i release SHA, link shared `.env`/storage, install backend/frontend dependencies vÃ  build static bundle Ä‘áº¡t chuáº©n fail-closed.
2. **Rollback initial service failure:** Quy trÃ¬nh hoÃ¡n Ä‘á»•i Caddyfile vÃ  quáº£n lÃ½ WinSW Service pháº£i cÃ³ cÆ¡ cháº¿ tá»± Ä‘á»™ng khÃ´i phá»¥c báº£n cÅ© náº¿u lá»‡nh khá»Ÿi cháº¡y hoáº·c reload tháº¥t báº¡i.
3. **Cookie smoke theo raw `Set-Cookie` vÃ  assert `SameSite`:** BÃ i kiá»ƒm thá»­ cookie smoke test pháº£i Ä‘á»c trá»±c tiáº¿p response headers/cookies (`Secure`, `HttpOnly`, `SameSite=Lax` cho session cookie; `Secure`, `SameSite=Lax` cho `XSRF-TOKEN`).
4. **Post-Herd external verification:** XÃ¡c minh Ä‘á»™c láº­p kháº£ nÄƒng váº­n hÃ nh cá»§a há»‡ thá»‘ng sau khi dá»«ng dev stack vÃ  gá»¡ bá» Laravel Herd táº¡i Batch 4.

---

## 7. TRáº NG THÃI TÃ€I LIá»†U

**Tráº¡ng thÃ¡i:** `BATCH_1P_READY_FOR_CODEX_REVIEW`
> *Ghi chÃº:* Sáºµn sÃ ng cho Codex kiá»ƒm tra láº¡i toÃ n bá»™ 9 quality gates vÃ  xin Ã½ kiáº¿n NgÆ°á»i dÃ¹ng quyáº¿t Ä‘á»‹nh commit. Tuyá»‡t Ä‘á»‘i KHÃ”NG tá»± Ã½ chuyá»ƒn sang `VERIFIED`.
