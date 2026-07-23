# KomiBook Inventory Decision Record (Batch 0 - Revised)

TÃ i liá»‡u ghi nháº­n káº¿t quáº£ kiá»ƒm kÃª thá»±c táº¿ háº¡ táº§ng (Read-Only Inventory) vÃ  Ä‘á» xuáº¥t phÆ°Æ¡ng Ã¡n kiáº¿n trÃºc chuyá»ƒn Ä‘á»•i mÃ´i trÆ°á»ng Production cho dá»± Ã¡n KomiBook.

- **NgÃ y kiá»ƒm kÃª:** 2026-07-23
- **MÃ´i trÆ°á»ng kiá»ƒm tra:** Local Windows Host
- **Tráº¡ng thÃ¡i tÃ i liá»‡u:** `READY_FOR_CODEX_REVIEW` â€” Sáº´N SÃ€NG CHO CODEX THáº¨M Äá»ŠNH

---

## 1. Báº°NG CHá»¨NG Lá»†NH THá»°C Táº¾ (EMPIRICAL COMMAND EVIDENCE)

### 1.1. Há»‡ Äiá»u HÃ nh & Kiáº¿n TrÃºc MÃ¡y Chá»§ (OS & Architecture)
Lá»‡nh thá»±c thi:
```powershell
Get-CimInstance -ClassName Win32_OperatingSystem | Select-Object Caption, Version, OSArchitecture, LastBootUpTime | Format-List
```
Káº¿t quáº£ thá»±c táº¿:
```
Caption        : Microsoft Windows 11 Home Single Language
Version        : 10.0.26200
OSArchitecture : 64-bit
LastBootUpTime : 7/22/2026 10:40:14 AM
```
> **XÃ¡c nháº­n:** Origin Host thá»±c táº¿ lÃ  **Windows 11 Home Single Language 64-bit**. KhÃ´ng cÃ³ mÃ¡y chá»§ Linux tá»« xa nÃ o tham gia trá»±c tiáº¿p vÃ o viá»‡c láº¯ng nghe domain.

### 1.2. Tráº¡ng ThÃ¡i CÃ¡c Cá»•ng Máº¡ng (Port Listener Inspection)
Lá»‡nh thá»±c thi:
```powershell
Get-NetTCPConnection -State Listen | Where-Object { $_.LocalPort -in @(80, 443, 5173, 8000, 8080, 9074, 9080..9085) } | Select-Object LocalAddress, LocalPort, OwningProcess, State | Format-Table -AutoSize
```
Káº¿t quáº£ thá»±c táº¿:
```
LocalAddress LocalPort OwningProcess  State
------------ --------- -------------  -----
::1               5173         22604 Listen
127.0.0.1         8000         18520 Listen
```
- **Port 5173 (IPv6 ::1):** Äang má»Ÿ (LISTEN), chiáº¿m bá»Ÿi **PID 22604** (`node.exe` - Vite Dev Server).
- **Port 8000 (IPv4 127.0.0.1):** Äang má»Ÿ (LISTEN), chiáº¿m bá»Ÿi **PID 18520** (`php.exe` - `artisan serve`).
- **Port 80, 443, 8080, 9074, 9080â€“9085:** **KHÃ”NG** cÃ³ tiáº¿n trÃ¬nh nÃ o Ä‘ang láº¯ng nghe cá»¥c bá»™ (khÃ´ng cÃ³ Nginx listener hay FastCGI pool listener cá»§a Herd/PHP-FPM).

### 1.3. Chi Tiáº¿t Tiáº¿n TrÃ¬nh & Nginx Herd Inventory
Lá»‡nh thá»±c thi:
```powershell
Get-CimInstance Win32_Process | Where-Object { $_.Name -match "cloudflared|node|nginx|httpd|caddy|php|w3wp|herd" } | Select-Object ProcessId, Name, ExecutablePath, CommandLine | Format-List
```
Káº¿t quáº£ thá»±c táº¿:
```
ProcessId      : 10308
Name           : cloudflared.exe
ExecutablePath : C:\Program Files (x86)\cloudflared\cloudflared.exe
CommandLine    :

ProcessId      : 22604
Name           : node.exe
ExecutablePath : C:\Program Files\nodejs\node.exe
CommandLine    : "node" "C:\Users\thean\Herd\DoAnTotNGhiep_komibook\frontend\node_modules\.bin\..\vite\bin\vite.js"

ProcessId      : 22556
Name           : node.exe
ExecutablePath : C:\Program Files\nodejs\node.exe
CommandLine    : "C:\Program Files\nodejs\node.exe" C:\Users\thean\AppData\Roaming\npm/node_modules/npm/bin/npm-cli.js run dev

ProcessId      : 18520
Name           : php.exe
ExecutablePath : C:\xampp\php\php.exe
CommandLine    : C:\xampp\php\php.exe -S 127.0.0.1:8000 C:\Users\thean\Herd\DoAnTotNGhiep_komibook\backend\vendor\laravel\framework\src\Illuminate\Foundation\Console/../resources/server.php

ProcessId      : 27248
Name           : php.exe
ExecutablePath : C:\xampp\php\php.exe
CommandLine    : "C:\xampp\php\php.exe" artisan serve

ProcessId      : 5016
Name           : HerdHelper.exe
ExecutablePath : C:\Program Files\Herd\HerdHelper.exe
CommandLine    :
```

- **PhÃ¡t hiá»‡n vá» Laravel Herd:**
  - File binary Nginx cá»§a Herd cÃ³ tá»“n táº¡i trÃªn Ä‘Ä©a táº¡i: `C:\Program Files\Herd\resources\app.asar.unpacked\resources\bin\nginx\nginx.exe`.
  - File cáº¥u hÃ¬nh máº·c Ä‘á»‹nh cá»§a Herd (`herd.conf`) láº¯ng nghe táº¡i `127.0.0.1:80`.
  - **Tráº¡ng thÃ¡i thá»±c táº¿:** Hiá»‡n táº¡i **KHÃ”NG CÃ“** Nginx listener (port 80/443 khÃ´ng má»Ÿ) vÃ  **KHÃ”NG CÃ“** PHP FastCGI listener (port `9074`, `9080â€“9085` khÃ´ng má»Ÿ).
  - **Káº¿t luáº­n:** Laravel Herd **KHÃ”NG PHáº¢I** lÃ  web server production Ä‘ang hoáº¡t Ä‘á»™ng. Quyáº¿t Ä‘á»‹nh chÃ­nh thá»©c cá»§a dá»± Ã¡n lÃ  **loáº¡i bá» Laravel Herd á»Ÿ Batch 4** sau khi runtime sáº£n xuáº¥t má»›i váº­n hÃ nh á»•n Ä‘á»‹nh qua cá»­a sá»• rollback.

### 1.4. Dá»‹ch Vá»¥ Duy TrÃ¬ (Services & Tunnel Startup)
Lá»‡nh thá»±c thi:
```powershell
Get-CimInstance Win32_Service | Where-Object { $_.Name -like "*cloudflared*" -or $_.Name -like "*herd*" } | Select-Object Name, State, StartMode, PathName | Format-List
```
Káº¿t quáº£ thá»±c táº¿:
```
Name      : Cloudflared
State     : Running
StartMode : Auto
PathName  : "C:\Program Files (x86)\cloudflared\cloudflared.exe" tunnel run --token [MASKED_TOKEN]

Name      : HerdHelper
State     : Running
StartMode : Auto
PathName  : "C:\Program Files\Herd\HerdHelper.exe"
```
> **ÄÃ¡nh giÃ¡ Cloudflare Tunnel:** Dá»‹ch vá»¥ `Cloudflared` Ä‘ang cháº¡y dÆ°á»›i dáº¡ng **Cloudflare-Managed Remote Tunnel** (thÃ´ng qua lá»‡nh `tunnel run --token ...`). KhÃ´ng sá»­ dá»¥ng file cáº¥u hÃ¬nh `config.yml` cá»¥c bá»™; toÃ n bá»™ quy táº¯c Ingress Ä‘Æ°á»£c cáº¥u hÃ¬nh Ä‘á»™ng trÃªn Cloudflare Zero Trust Dashboard.

### 1.5. Kiá»ƒm Tra Pháº£n Há»“i Domain CÃ´ng Khai (Public Domain Request Probe)
Lá»‡nh thá»±c thi:
```powershell
curl.exe -svo NUL https://komibook.id.vn/ 2>&1
```
Káº¿t quáº£ tÃ³m táº¯t headers:
```
* Established connection to komibook.id.vn (172.67.215.79 port 443)
> GET / HTTP/1.1
> Host: komibook.id.vn
< HTTP/1.1 200 OK
< Server: cloudflare
< CF-RAY: a1f9783e9a294b09-HKG
< Content-Type: text/html
< Cache-Control: no-cache
```

---

## 2. LÆ¯á»’NG KIáº¾N TRÃšC HIá»†N Táº I (ACTUAL TOPOLOGY)

```
[NgÆ°á»i dÃ¹ng Internet]
       â”‚ (HTTPS 443)
       â–¼
[Cloudflare Edge Network] (SSL Termination)
       â”‚ (Cloudflare Tunnel / QUIC-gRPC)
       â–¼
[cloudflared.exe (PID 10308)] (Windows Service)
       â”‚
       â–¼
[Vite Dev Server (::1:5173)] (node.exe PID 22604, npm run dev)
       â”‚
       â””â”€â–º Proxy ná»™i bá»™ Vite (/api, /sanctum, /storage) â”€â”€â–º [Laravel Backend (127.0.0.1:8000)] (php.exe PID 18520)
```

> **LÆ°u Ã½ vá» luá»“ng Cloudflared:** Cloudflared **KHÃ”NG** phÃ¢n nhÃ¡nh trá»±c tiáº¿p tá»›i cáº£ 2 port. Cloudflared chá»‰ chuyá»ƒn tiáº¿p duy nháº¥t traffic tá»›i Vite dev port `5173`. CÃ¡c request API (`/api`, `/sanctum`, `/storage`) do chÃ­nh Vite Dev Server proxy ngÆ°á»£c vá» Laravel port `8000`.

---

## 3. NGUYÃŠN NHÃ‚N Gá»C Rá»„ DOMAIN TRá»Ž VÃ€O VITE 5173

1. **Quy táº¯c Ingress trÃªn Cloudflare Zero Trust Dashboard:**
   - Dá»‹ch vá»¥ Cloudflare Tunnel khá»Ÿi cháº¡y dáº¡ng `--token` nháº­n cáº¥u hÃ¬nh Ä‘iá»u hÆ°á»›ng Ä‘á»™ng tá»« xa.
   - **XÃ¡c minh Ingress:** Viá»‡c Ingress rule trÃªn Dashboard Ä‘ang trá» vÃ o port `5173` lÃ  **suy luáº­n Ä‘Æ°á»£c xÃ¡c nháº­n bá»Ÿi pháº£n há»“i HTML/Vite client thá»±c táº¿ tá»« domain vÃ  listener 5173 hiá»‡n há»¯u**, chÆ°a pháº£i báº±ng chá»©ng Ä‘á»c trá»±c tiáº¿p tá»« mÃ n hÃ¬nh Dashboard UI.
2. **NguyÃªn nhÃ¢n crash Pinia store (`useWishlistStore()`):**
   - **Giáº£ thuyáº¿t chÆ°a chá»©ng minh:** Lá»—i crash xáº£y ra do Vite Dev Server thá»±c hiá»‡n HMR hoáº·c dependency re-optimization lÃ m re-evaluate cÃ¡c Vue/Pinia modules khÃ´ng theo thá»© tá»± tÄ©nh khi `HomeView.vue` khá»Ÿi cháº¡y.
   - Biá»‡n phÃ¡p Ä‘Ã³ng Ä‘Ã³ng gÃ³i bundle tÄ©nh (`npm run build`) loáº¡i bá» hoÃ n toÃ n HMR lÃ  yÃªu cáº§u báº¯t buá»™c, nhÆ°ng **váº«n cáº§n tráº£i qua kiá»ƒm thá»­ thá»±c nghiá»‡m (smoke test)** Ä‘á»ƒ kháº³ng Ä‘á»‹nh nguyÃªn nhÃ¢n gá»‘c rá»….

---

## 4. Lá»°A CHá»ŒN RUNBOOK Äá»€ XUáº¤T

- **Lá»±a chá»n Ä‘á» xuáº¥t:** **WINDOWS PRODUCTION RUNBOOK (FRANKENPHP NATIVE WINDOWS VIA WINSW)**.
- **PhÆ°Æ¡ng Ã¡n chÃ­nh:** FrankenPHP native Windows cháº¡y dÆ°á»›i dáº¡ng Windows Service (qua WinSW), láº¯ng nghe duy nháº¥t `127.0.0.1:8080`, vá»«a phá»¥c vá»¥ Frontend SPA tÄ©nh vá»«a phá»¥c vá»¥ Laravel API trong cÃ¹ng má»™t unified service (á»Ÿ Classic Mode).
- **PhÆ°Æ¡ng Ã¡n dá»± phÃ²ng (Fallback):** Caddy Windows Service + Standalone PHP NTS FastCGI Ä‘á»™c láº­p (chá»‰ sá»­ dá»¥ng náº¿u FrankenPHP tháº¥t báº¡i vá» tÆ°Æ¡ng thÃ­ch binary/extension/Composer). KhÃ´ng dÃ¹ng Nginx Windows vÃ  khÃ´ng dÃ¹ng `php artisan serve`.
- **Quyáº¿t Ä‘á»‹nh loáº¡i bá» Laravel Herd á»Ÿ Batch 4:**
  - Giá»¯ nguyÃªn Laravel Herd vÃ  dev stack (`5173`/`8000`) hoáº¡t Ä‘á»™ng song song trong suá»‘t quÃ¡ trÃ¬nh chuáº©n bá»‹, cutover vÃ  kiá»ƒm thá»­ cá»­a sá»• rollback.
  - Viá»‡c gá»¡ bá» Herd chá»‰ Ä‘Æ°á»£c thá»±c hiá»‡n á»Ÿ **Batch 4** sau khi production stack má»›i Ä‘áº¡t Ä‘á»™ á»•n Ä‘á»‹nh tuyá»‡t Ä‘á»‘i.
  - Giá»¯ nguyÃªn Ä‘Æ°á»ng dáº«n lÃ m viá»‡c `C:\Users\thean\Herd\DoAnTotNGhiep_komibook` Ä‘á»ƒ báº£o toÃ n workspace vÃ  link task.

---

## 5. Dá»® KIá»†N CÃ’N THIáº¾U & ÄIá»€U KIá»†N TIÃŠN QUYáº¾T CHO BATCH 1A

1. **FrankenPHP Compatibility Gate:** Kiá»ƒm tra binary FrankenPHP Windows pinned version & SHA-256 checksum, PHP extensions, `composer check-platform-reqs --no-dev` vÃ  `php artisan test`.
2. **Node Portable Runtime:** Chuáº©n bá»‹ Node.js LTS portable Ä‘Æ°á»£c Ä‘áº·t táº¡i thÆ° má»¥c runtime riÃªng biá»‡t vÃ  gá»i báº±ng absolute path Ä‘á»ƒ trÃ¡nh xung Ä‘á»™t NVM/Herd.
3. **Quyá»n truy cáº­p Cloudflare Zero Trust Dashboard:** Cáº§n quyá»n cáº­p nháº­t Ingress Rule tá»« port `5173` sang port `8080` á»Ÿ bÆ°á»›c cuá»‘i cá»§a Batch 2.

---

## 6. BÃO CÃO GIÃ‚ÌI Háº N GIT STATUS

### Git Status trÆ°á»›c kiá»ƒm kÃª:
```
## master...origin/master
 M backend/.env.example
 M backend/app/Http/Controllers/Api/AuthController.php
 M backend/app/Http/Controllers/Api/AuthorController.php
 M backend/app/Http/Controllers/Api/BookController.php
 M backend/app/Http/Controllers/Api/OrderController.php
 M backend/app/Http/Controllers/Api/PhoneAuthController.php
 M backend/app/Http/Controllers/Api/ProfileController.php
 M backend/app/Http/Controllers/Api/SupportTicketController.php
 M backend/app/Http/Requests/Auth/RegisterRequest.php
 M backend/app/Http/Requests/Auth/UpdateProfileRequest.php
 M backend/app/Http/Requests/Vendor/StoreBookRequest.php
 M backend/app/Http/Requests/Vendor/UpdateBookRequest.php
 M backend/app/Http/Resources/BookResource.php
 M backend/app/Http/Resources/UserResource.php
 M backend/app/Models/Author.php
 M backend/app/Models/TicketMessage.php
 M backend/app/Models/User.php
 M backend/app/Providers/AppServiceProvider.php
 M backend/bootstrap/app.php
 M backend/composer.json
 M backend/composer.lock
 M backend/config/filesystems.php
 M backend/config/services.php
 M backend/routes/api.php
 M backend/tests/Feature/AuthorDrmInventoryTest.php
 M frontend/package-lock.json
 M frontend/package.json
 M frontend/src/components/layout/AppHeader.vue
 M frontend/src/components/profile/UserSidebar.vue
 M frontend/src/router/index.js
 M frontend/src/services/axios.js
 M frontend/src/stores/auth.js
 M frontend/src/views/BookDetailView.vue
 M frontend/src/views/MyAnnotationsView.vue
 M frontend/src/views/MyLibraryView.vue
 M frontend/src/views/NotificationsView.vue
 M frontend/src/views/OrdersView.vue
 M frontend/src/views/ProfileView.vue
 M frontend/src/views/WishlistView.vue
 M frontend/src/views/admin/TicketDetailView.vue
 M frontend/src/views/admin/VendorApprovalsView.vue
 M frontend/src/views/auth/LoginView.vue
 M frontend/src/views/auth/RegisterView.vue
 M frontend/src/views/vendor/BookFormView.vue
 M frontend/src/views/vendor/BooksView.vue
 M frontend/vite.config.js
?? backend/app/Console/
?? backend/app/Http/Middleware/SecurityHeadersMiddleware.php
?? backend/app/Services/FakeGoogleTokenVerifier.php
?? backend/app/Services/GoogleTokenVerifier.php
?? backend/app/Services/GoogleTokenVerifierInterface.php
?? backend/app/Services/HtmlSanitizer.php
?? backend/app/Services/Otp/
?? backend/database/migrations/2026_07_22_120000_create_user_favorite_categories_table.php
?? backend/tests/Feature/SecurityHardeningTest.php
?? docs/
?? frontend/.env.example
?? frontend/src/__tests__/
?? frontend/src/router/guard.js
```

### Git Status sau kiá»ƒm kÃª:
`git status --short --branch` giá»¯ nguyÃªn 100% khÃ´ng cÃ³ sá»± thay Ä‘á»•i nÃ o Ä‘á»‘i vá»›i mÃ£ nguá»“n dá»± Ã¡n.

---

## 7. TRáº NG THÃI TÃ€I LIá»†U

**Tráº¡ng thÃ¡i:** `READY_FOR_CODEX_REVIEW` (Sáº´N SÃ€NG CHO CODEX THáº¨M Äá»ŠNH Tá»”NG THá»‚ BATCH 0)
> *Ghi chÃº:* Tuyá»‡t Ä‘á»‘i KHÃ”NG tá»± Ã½ ghi `APPROVED` hay chuyá»ƒn sang Batch 1 khi chÆ°a cÃ³ phÃª duyá»‡t chÃ­nh thá»©c tá»« Codex vÃ  NgÆ°á»i dÃ¹ng.
