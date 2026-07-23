# KomiBook Windows Production Deployment Plan (FrankenPHP Native Architecture)

Káº¿ hoáº¡ch chi tiáº¿t chuyá»ƒn Ä‘á»•i mÃ´i trÆ°á»ng Production cá»§a KomiBook trÃªn mÃ¡y chá»§ Windows 11 sang Standalone FrankenPHP (Caddy Engine) cháº¡y dÆ°á»›i dáº¡ng Windows Service (qua WinSW), phá»¥c vá»¥ Ä‘á»“ng thá»i Frontend SPA tÄ©nh vÃ  Backend Laravel API trÃªn cá»•ng loopback `127.0.0.1:8080`.

- **Dá»± Ã¡n:** KomiBook - Ná»n táº£ng ThÆ°Æ¡ng máº¡i Äiá»‡n tá»­ SÃ¡ch Äa nhÃ  bÃ¡n
- **Há»‡ Ä‘iá»u hÃ nh target:** Microsoft Windows 11 Home Single Language 64-bit
- **Tráº¡ng thÃ¡i tÃ i liá»‡u:** `READY_FOR_CODEX_REVIEW` â€” Sáº´N SÃ€NG CHO CODEX THáº¨M Äá»ŠNH Tá»”NG THá»‚

> [!IMPORTANT]
> **Báº¢O TOÃ€N TUYá»†T Äá»I Vá»šI BATCH 0.6.2:**
> ÄÃ¢y lÃ  tÃ i liá»‡u láº­p káº¿ hoáº¡ch kiáº¿n trÃºc. **KHÃ”NG** táº£i runtime, khÃ´ng táº¡o thÆ° má»¥c ngoÃ i dá»± Ã¡n, khÃ´ng cÃ i service, khÃ´ng build, khÃ´ng gá»¡ pháº§n má»m, khÃ´ng sá»­a mÃ£ nguá»“n/config, khÃ´ng dá»«ng dev stack, khÃ´ng thay Ä‘á»•i Cloudflare ingress vÃ  khÃ´ng commit/push.

---

## 1. NGUYÃŠN Táº®C Káº¾T Cáº¤U & KIáº¾N TRÃšC MÃY CHá»¦ (ARCHITECTURAL INVARIANTS)

### 1.1. Kiáº¿n TrÃºc FrankenPHP Native Windows
1. **Unified Application Server:**
   - Sá»­ dá»¥ng **FrankenPHP Native for Windows** (tÃ­ch há»£p Caddy Web Server v2 vÃ  PHP 8.2+ engine) cháº¡y dÆ°á»›i dáº¡ng **Windows Service** Ä‘Æ°á»£c giÃ¡m sÃ¡t tá»± Ä‘á»™ng qua **WinSW v2.12.0** (`C:\runtimes\winsw\FrankenPHPService.exe`).
   - Láº¯ng nghe duy nháº¥t táº¡i loopback **`127.0.0.1:8080`**.
   - Cloudflare Tunnel trá» duy nháº¥t tá»›i `http://127.0.0.1:8080`.
   - Phá»¥c vá»¥ Ä‘á»“ng thá»i Static Frontend SPA (`frontend/dist`) vÃ  Backend Laravel API (`backend/public/index.php`) trong cÃ¹ng má»™t unified service.
   - Khoáº£n khá»Ÿi cháº¡y ban Ä‘áº§u: Khá»Ÿi cháº¡y á»Ÿ **Classic Mode** (cháº¿ Ä‘á»™ chuáº©n tÆ°Æ¡ng thÃ­ch CGI/FPM cá»§a FrankenPHP). **KHÃ”NG** báº­t Laravel Worker Mode khi chÆ°a hoÃ n thÃ nh kiá»ƒm thá»­ tÆ°Æ¡ng thÃ­ch bá»™ nhá»›.
2. **PhÆ°Æ¡ng Ãn Dá»± PhÃ²ng (Fallback Architecture):**
   - **Caddy for Windows (Windows Service) + Standalone PHP NTS FastCGI (`127.0.0.1:9000`)**.
   - Chá»‰ kÃ­ch hoáº¡t fallback náº¿u FrankenPHP tháº¥t báº¡i táº¡i cá»­a sá»• kiá»ƒm tra tÆ°Æ¡ng thÃ­ch (Compatibility Gate) do thiáº¿u extension, lá»—i PHP version hoáº·c Composer dependency.
   - Tuyá»‡t Ä‘á»‘i **KHÃ”NG** quay láº¡i Nginx Windows vÃ  **KHÃ”NG** sá»­ dá»¥ng `php artisan serve` trong mÃ´i trÆ°á»ng sáº£n xuáº¥t.
3. **Quáº£n LÃ½ Tiáº¿n TrÃ¬nh Node.js LTS Portable Äá»™c Láº­p:**
   - QuÃ¡ trÃ¬nh build Frontend Ä‘Æ°á»£c ghim phiÃªn báº£n chÃ­nh xÃ¡c: **Node.js v22.13.0 LTS Portable** (thá»a mÃ£n yÃªu cáº§u `^20.19.0 || >=22.12.0`), lÆ°u táº¡i `C:\runtimes\node-v22.13.0-win-x64\node.exe`.
   - Má»i cÃ¢u lá»‡nh build pháº£i gá»i qua **Ä‘Æ°á»ng dáº«n tuyá»‡t Ä‘á»‘i (Absolute Path)** cá»§a `npm.cmd`: `C:\runtimes\node-v22.13.0-win-x64\npm.cmd` Ä‘á»ƒ trÃ¡nh xung Ä‘á»™t vá»›i PATH hay NVM cá»§a Herd.
4. **Quy TrÃ¬nh Gá»¡ Bá» Herd An ToÃ n (Batch 4):**
   - Laravel Herd vÃ  dev stack (`5173`/`8000`) Ä‘Æ°á»£c giá»¯ nguyÃªn váº¹n vÃ  tiáº¿p tá»¥c cháº¡y trong suá»‘t quÃ¡ trÃ¬nh chuáº©n bá»‹, cutover vÃ  cá»­a sá»• rollback.
   - Viá»‡c gá»¡ bá» Herd Ä‘Æ°á»£c dá»i sang **Batch 4** (sau khi há»‡ thá»‘ng production má»›i vÆ°á»£t qua toÃ n bá»™ cá»­a sá»• theo dÃµi á»•n Ä‘á»‹nh 24h vÃ  kiá»ƒm tra sau reboot).
   - Giá»¯ nguyÃªn Ä‘Æ°á»ng dáº«n lÃ m viá»‡c `C:\Users\thean\Herd\DoAnTotNGhiep_komibook` Ä‘á»ƒ báº£o toÃ n workspace IDE vÃ  task links.

### 1.2. Pre-Release Source Gate â€” Giá»›i Háº¡n Trusted Proxies (Batch 1P)
- **Tráº¡ng thÃ¡i hiá»‡n táº¡i:** MÃ£ nguá»“n hiá»‡n táº¡i táº¡i `backend/bootstrap/app.php` Ä‘ang sá»­ dá»¥ng `$middleware->trustProxies(at: '*')`. Tuyá»‡t Ä‘á»‘i **khÃ´ng tuyÃªn bá»‘ `trustProxies` Ä‘Ã£ an toÃ n**.
- **Pre-Release Source Gate (Batch 1P):** Thá»±c hiá»‡n trÆ°á»›c khi Ä‘Ã³ng gÃ³i release SHA:
  1. Cáº­p nháº­t `backend/bootstrap/app.php` giá»›i háº¡n trusted proxies vá» Ä‘Ãºng loopback Ä‘Ã£ xÃ¡c minh: `$middleware->trustProxies(at: ['127.0.0.1'])`.
  2. Bá»• sung cÃ¡c bÃ i kiá»ƒm thá»­ targeted tests kiá»ƒm tra header proxy tá»« IP loopback vs IP khÃ´ng tin cáº­y.
  3. Cháº¡y láº¡i toÃ n bá»™ 9 Phase 1 Quality Gates.
  4. **Dá»«ng bÃ¡o cÃ¡o cho Codex duyá»‡t.** Sau khi Codex thÃ´ng qua, viá»‡c commit vÃ  táº¡o release artifact chá»‰ Ä‘Æ°á»£c thá»±c hiá»‡n khi NgÆ°á»i dÃ¹ng phÃª duyá»‡t rÃµ rÃ ng. Tuyá»‡t Ä‘á»‘i khÃ´ng tá»± Ã½ cháº¡y `git add .`.

### 1.3. Invariant P0 â€” KhÃ¡ch VÃ£ng Lai (Unauthenticated Guest Browsing)
Báº£o toÃ n kháº£ nÄƒng truy cáº­p tá»± do cho khÃ¡ch chÆ°a Ä‘Äƒng nháº­p trÃªn 8 trang cÃ´ng khai vÃ  4 public API:
- **Frontend Pages:** `/`, `/catalog`, `/blog`, `/book/{known-slug}`, `/cart`, `/help-center`, `/login`, `/register`.
- **Public API Endpoints:** `/api/books`, `/api/books/top-selling`, `/api/categories`, `/api/flash-sales` (tráº£ vá» `200 OK` JSON vá»›i `Content-Type: application/json`).
- **Quy táº¯c:** Tuyá»‡t Ä‘á»‘i khÃ´ng Ä‘áº·t Basic Auth hay Login Wall trÃªn cÃ¡c tuyáº¿n Ä‘Æ°á»ng cÃ´ng khai nÃ y.

---

## 2. PRODUCTION ENVIRONMENT PREFLIGHT FAIL-CLOSED GATE

TrÆ°á»›c khi khá»Ÿi cháº¡y Backend trong mÃ´i trÆ°á»ng Production, script kiá»ƒm tra cáº¥u hÃ¬nh mÃ´i trÆ°á»ng `.env` theo nguyÃªn táº¯c Fail-Closed (tháº¥t báº¡i lÃ  dá»«ng ngay) vÃ  báº£o máº­t tuyá»‡t Ä‘á»‘i (chá»‰ bÃ¡o tÃªn khÃ³a lá»—i, tuyá»‡t Ä‘á»‘i **khÃ´ng in giÃ¡ trá»‹ hay secret** ra console/log):

```powershell
$ENV_FILE = "C:\komibook_shared\.env"
if (-not (Test-Path $ENV_FILE)) { Write-Error "PREFLIGHT ERROR: Shared .env file missing!"; exit 1 }

$lines = Get-Content $ENV_FILE | Where-Object { $_ -notmatch '^\s*#' -and $_ -match '=' }
$CHECK_FAILED = $false

function Check-EnvKey($keyName, $expectedValue) {
    $matches = $script:lines | Where-Object { $_ -match "^\s*${keyName}\s*=" }
    if ($matches.Count -eq 0) {
        Write-Error "PREFLIGHT ERROR: Missing environment key [$keyName]!"
        $script:CHECK_FAILED = $true
        return
    }
    if ($matches.Count -gt 1) {
        Write-Error "PREFLIGHT ERROR: Duplicate environment key [$keyName] found ($($matches.Count) occurrences)!"
        $script:CHECK_FAILED = $true
        return
    }
    $val = ($matches[0] -split '=', 2)[1].Trim().Trim('"').Trim("'")
    if ($val -ne $expectedValue) {
        Write-Error "PREFLIGHT ERROR: Environment key [$keyName] validation failed!"
        $script:CHECK_FAILED = $true
    }
}

# Kiá»ƒm tra cÃ¡c khÃ³a báº¯t buá»™c (Fail-closed & khÃ´ng in secret value)
Check-EnvKey "APP_ENV" "production"
Check-EnvKey "APP_DEBUG" "false"
Check-EnvKey "APP_URL" "https://komibook.id.vn"
Check-EnvKey "FRONTEND_URL" "https://komibook.id.vn"
Check-EnvKey "SANCTUM_STATEFUL_DOMAINS" "komibook.id.vn"
Check-EnvKey "SESSION_SECURE_COOKIE" "true"
Check-EnvKey "SESSION_SAME_SITE" "lax"
Check-EnvKey "SESSION_DOMAIN" "komibook.id.vn"

if ($CHECK_FAILED) {
    Write-Error "PRODUCTION PREFLIGHT FAILED: Environment validation checks failed!"
    exit 1
} else {
    Write-Host "PASS: Production environment preflight verified successfully." -ForegroundColor Green
}
```

---

## 3. FRANKENPHP COMPATIBILITY GATE (BATCH 1A.2)

Táº¡i **Batch 1A.2**, bá»™ kiá»ƒm tra tÆ°Æ¡ng thÃ­ch Ä‘Æ°á»£c thá»±c hiá»‡n trá»±c tiáº¿p trÃªn cÃ¢y mÃ£ nguá»“n lÃ m viá»‡c hiá»‡n cÃ³ (`C:\Users\thean\Herd\DoAnTotNGhiep_komibook\backend`), vÃ¬ thÆ° má»¥c `C:\komibook_releases\<SHA>` chÆ°a Ä‘Æ°á»£c táº¡o trÆ°á»›c Batch 1B.1:

1. **XÃ¡c Minh Binary & Checksum:**
   - Táº£i artifact chÃ­nh thá»©c Ä‘Ã£ ghim phiÃªn báº£n (Pinned Version) vÃ  kiá»ƒm tra mÃ£ bÄƒm SHA-256 checksum so vá»›i cÃ´ng bá»‘ chÃ­nh thá»©c. Tuyá»‡t Ä‘á»‘i **KHÃ”NG** dÃ¹ng lá»‡nh tá»± Ä‘á»™ng khÃ´ng kiá»ƒm soÃ¡t nhÆ° `irm ... | iex`.
2. **Lá»‡nh Kiá»ƒm Tra TÆ°Æ¡ng ThÃ­ch Chuáº©n HÃ³a:**
   - **Working Directory:** Thá»±c thi táº¡i `C:\Users\thean\Herd\DoAnTotNGhiep_komibook\backend`.
   - **Absolute Path FrankenPHP & Composer:**

   ```powershell
   Set-Location "C:\Users\thean\Herd\DoAnTotNGhiep_komibook\backend"

   # 1. Kiá»ƒm tra 8 PHP Extensions báº¯t buá»™c
   & "C:\runtimes\frankenphp\frankenphp.exe" php-cli -m

   # 2. Kiá»ƒm tra platform requirements cá»§a Composer báº±ng composer.phar trá»±c tiáº¿p
   & "C:\runtimes\frankenphp\frankenphp.exe" php-cli "C:\ProgramData\ComposerSetup\bin\composer.phar" check-platform-reqs --no-dev

   # 3. Thá»±c thi bá»™ kiá»ƒm thá»­ Laravel Test Suite
   & "C:\runtimes\frankenphp\frankenphp.exe" php-cli artisan test
   ```
3. **Kiá»ƒm Tra Káº¿t Ná»‘i Dá»¯ Liá»‡u & Storage:**
   - Kiá»ƒm tra káº¿t ná»‘i MySQL database vÃ  session database table.
   - Kiá»ƒm tra quyá»n truy cáº­p Ä‘á»c/ghi trÃªn Public Storage (`storage/app/public`) vÃ  Private Storage (`storage/app/private`).
4. **Phase 1 Quality Gates:** VÆ°á»£t qua toÃ n bá»™ 9 cá»•ng kiá»ƒm tra cháº¥t lÆ°á»£ng mÃ£ nguá»“n Giai Ä‘oáº¡n 1.

---

## 4. QUY TRÃŒNH QUáº¢N LÃ CONFIG CADDYFILE TÃCH Báº¬C (INITIAL VS SUBSEQUENT)

Äá»ƒ Ä‘áº£m báº£o hoÃ¡n Ä‘á»•i nguyÃªn tá»­ vÃ  khÃ´ng lÃ m giÃ¡n Ä‘oáº¡n dá»‹ch vá»¥ trÃªn Windows:

1. **Giá»¯ NguyÃªn Template `Caddyfile.<COMMIT_SHA>`:**
   - File template tÄ©nh `C:\komibook_shared\config\Caddyfile.<COMMIT_SHA>` chá»©a Ä‘Æ°á»ng dáº«n SHA tuyá»‡t Ä‘á»‘i dáº¡ng literal (xÃ³a bá» hoÃ n toÃ n `{env.ACTIVE_SHA}`).
2. **Quy TrÃ¬nh Khá»Ÿi Táº¡o Láº§n Äáº§u (Initial Activation Workflow - Batch 1B.2):**
   - **BÆ°á»›c 1:** Copy `Caddyfile.<COMMIT_SHA>` thÃ nh `C:\komibook_shared\config\Caddyfile.candidate`.
   - **BÆ°á»›c 2:** Validate cÃº phÃ¡p: `& "C:\runtimes\frankenphp\frankenphp.exe" validate --config "C:\komibook_shared\config\Caddyfile.candidate"`.
   - **BÆ°á»›c 3:** Copy candidate thÃ nh active config: `Copy-Item "C:\komibook_shared\config\Caddyfile.candidate" "C:\komibook_shared\config\Caddyfile" -Force`.
   - **BÆ°á»›c 4:** CÃ i Ä‘áº·t & Khá»Ÿi cháº¡y Service WinSW láº§n Ä‘áº§u:
     ```powershell
     & "C:\runtimes\winsw\FrankenPHPService.exe" install
     & "C:\runtimes\winsw\FrankenPHPService.exe" start
     ```
   - **BÆ°á»›c 5:** Kiá»ƒm tra tráº¡ng thÃ¡i service Ä‘áº¡t `Started` / `Running`. **TUYá»†T Äá»I KHÃ”NG** gá»i `frankenphp reload` trÆ°á»›c khi service Ä‘Æ°á»£c khá»Ÿi cháº¡y thÃ nh cÃ´ng.
3. **Quy TrÃ¬nh HoÃ¡n Äá»•i Cáº­p Nháº­t (Subsequent Deployment Workflow):**
   - **BÆ°á»›c 1:** Copy `Caddyfile.<COMMIT_SHA>` má»›i thÃ nh `C:\komibook_shared\config\Caddyfile.candidate`.
   - **BÆ°á»›c 2:** Validate cÃº phÃ¡p candidate.
   - **BÆ°á»›c 3:** Thá»±c hiá»‡n hoÃ¡n Ä‘á»•i Replace nguyÃªn tá»­ trÃªn cÃ¹ng NTFS volume:
     ```powershell
     [System.IO.File]::Replace("C:\komibook_shared\config\Caddyfile.candidate", "C:\komibook_shared\config\Caddyfile", "C:\komibook_shared\config\Caddyfile.bak")
     ```
   - **BÆ°á»›c 4:** Graceful Reload service Ä‘ang cháº¡y:
     ```powershell
     & "C:\runtimes\frankenphp\frankenphp.exe" reload --config "C:\komibook_shared\config\Caddyfile"
     ```
   - **BÆ°á»›c 5 (Atomic Rollback khi Reload Tháº¥t Báº¡i):** Kiá»ƒm tra `$LASTEXITCODE`. Náº¿u khÃ¡c 0, thá»±c hiá»‡n hoÃ n nguyÃªn vÃ  kiá»ƒm tra láº¡i exit code rollback:
     ```powershell
     if ($LASTEXITCODE -ne 0) {
         Write-Warning "Reload failed! Initiating atomic rollback to Caddyfile.bak..."
         [System.IO.File]::Replace("C:\komibook_shared\config\Caddyfile.bak", "C:\komibook_shared\config\Caddyfile", $null)
         & "C:\runtimes\frankenphp\frankenphp.exe" reload --config "C:\komibook_shared\config\Caddyfile"
         if ($LASTEXITCODE -ne 0) { Write-Error "CRITICAL: Config rollback reload failed!"; exit 1 }
     }
     ```

---

## 5. Cáº¤U HÃŒNH CADDYFILE CHUáº¨N HÃ“A (`C:\komibook_shared\config\Caddyfile.<COMMIT_SHA>`)

```caddyfile
{
    admin 127.0.0.1:2019
    auto_https off
    frankenphp
}

http://127.0.0.1:8080 {
    # 1. Document Root cho Static SPA Frontend (ÄÆ°á»ng dáº«n SHA tuyá»‡t Ä‘á»‘i Literal)
    root * C:/komibook_releases/a1b2c3d4e5f6/frontend/dist

    # 2. Security Headers cho Táº¤T Cáº¢ tuyáº¿n Ä‘Æ°á»ng Frontend (Trá»« /api/* vÃ  /sanctum/*)
    @frontendRoutes {
        not path /api/* /sanctum/*
    }
    header @frontendRoutes {
        X-Frame-Options "SAMEORIGIN"
        X-Content-Type-Options "nosniff"
        Referrer-Policy "strict-origin-when-cross-origin"
        X-XSS-Protection "1; mode=block"
        Content-Security-Policy-Report-Only "default-src 'self'; frame-ancestors 'self'; img-src 'self' data: https:; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline';"
    }

    # 3. Táº¥t cáº£ SPA Route Fallback & Index.html Báº®T BUá»˜C No-Cache
    @spaRoutes {
        not path /assets/* /storage/* /api/* /sanctum/*
    }
    header @spaRoutes Cache-Control "no-store, no-cache, must-revalidate, proxy-revalidate, max-age=0"

    # 4. Shared Immutable Static Assets (Phá»¥c vá»¥ cáº£ assets cÅ© cho client Ä‘ang má»Ÿ phiÃªn)
    handle_path /assets/* {
        root * C:/komibook_shared/assets
        header Cache-Control "public, max-age=31536000, immutable"
        file_server
    }

    # 5. Public Storage (BÃ¬a sÃ¡ch, Banner)
    handle_path /storage/* {
        root * C:/komibook_shared/storage/app/public
        header Cache-Control "public, max-age=604800"
        file_server
    }

    # 6. Proxy API & Sanctum sang Backend Laravel (KHÃ”NG láº·p láº¡i Caddy Security Headers)
    handle /api/* {
        root * C:/komibook_releases/a1b2c3d4e5f6/backend/public
        php_server
    }

    handle /sanctum/* {
        root * C:/komibook_releases/a1b2c3d4e5f6/backend/public
        php_server
    }

    # 7. SPA Fallback cho cÃ¡c tuyáº¿n Ä‘Æ°á»ng Frontend
    handle {
        try_files {path} {path}/ /index.html
        file_server
    }
}
```

---

## 6. Cáº¤U HÃŒNH WINSW SERVICE MONITORING & WINDOWS ACL PERMISSIONS

### 6.1. XML Schema Chuáº©n HÃ³a WinSW 2.12 (`C:\runtimes\winsw\FrankenPHPService.xml`)
> **Quy Táº¯c Äáº·t TÃªn WinSW:** File thá»±c thi `FrankenPHPService.exe` vÃ  file XML `FrankenPHPService.xml` Báº®T BUá»˜C pháº£i Ä‘áº·t cÃ¹ng thÆ° má»¥c `C:\runtimes\winsw\` vÃ  Ä‘áº·t tÃªn trÃ¹ng khá»›p theo Ä‘Ãºng chuáº©n WinSW 2.12.

```xml
<service>
  <id>FrankenPHPService</id>
  <name>KomiBook FrankenPHP Service</name>
  <description>Standalone FrankenPHP Web &amp; Application Server for KomiBook</description>
  <executable>C:\runtimes\frankenphp\frankenphp.exe</executable>
  <arguments>run --config C:\komibook_shared\config\Caddyfile</arguments>
  <workingdirectory>C:\komibook_shared</workingdirectory>

  <!-- Service Account Chuáº©n Schema WinSW 2.12 (KhÃ´ng chá»©a tháº» password vá»›i LocalService) -->
  <serviceaccount>
    <username>NT AUTHORITY\LocalService</username>
  </serviceaccount>

  <startmode>Automatic</startmode>

  <!-- Auto-Restart & Failure Policies -->
  <onfailure action="restart" delay="5 sec"/>
  <onfailure action="restart" delay="10 sec"/>
  <onfailure action="restart" delay="30 sec"/>
  <resetfailure>60 sec</resetfailure>
  <stoptimeout>30sec</stoptimeout>

  <!-- Valid Logpath & Roll-by-Size Mode -->
  <logpath>C:\komibook_shared\service-logs</logpath>
  <log mode="roll-by-size">
    <sizeThreshold>10240</sizeThreshold>
    <keepFiles>10</keepFiles>
  </log>
</service>
```

### 6.2. PhÃ¢n Quyá»n Windows File ACLs Theo Quyá»n Tá»‘i Thiá»ƒu (Least-Privilege ACLs)
Quyá»n cá»§a `NT AUTHORITY\LocalService` Ä‘Æ°á»£c phÃ¢n tÃ¡ch chÃ­nh xÃ¡c:
- **Read & Execute (RX):**
  - `C:\komibook_shared\.env`
  - `C:\komibook_shared\assets\`
  - `C:\komibook_shared\config\` (Service account **KHÃ”NG** cÃ³ quyá»n ghi active `Caddyfile`).
  - `C:\runtimes\`
  - `C:\komibook_releases\`
- **Modify / Write (M) CHá»ˆ táº¡i cÃ¡c vá»‹ trÃ­ cáº§n ghi dá»¯ liá»‡u:**
  - `C:\komibook_shared\service-logs`
  - `C:\komibook_shared\storage\app\public`
  - `C:\komibook_shared\storage\app\private`
  - `C:\komibook_shared\storage\framework\cache`
  - `C:\komibook_shared\storage\framework\sessions`
  - `C:\komibook_shared\storage\framework\views`
  - `C:\komibook_shared\storage\logs`
  - `C:\komibook_releases\<SHA>\backend\bootstrap\cache` cá»§a release active.

---

## 7. CHIA Sáºº TRáº NG THÃI LARAVEL (SHARED LARAVEL STATE & JUNCTIONS)

Má»—i báº£n phÃ¡t hÃ nh táº¡i `C:\komibook_releases\<SHA>\backend` Ä‘Æ°á»£c liÃªn káº¿t dá»¯ liá»‡u dÃ¹ng chung thÃ´ng qua NTFS Junctions & Hardlinks:

1. **`.env` File:** Copy/Hardlink tá»« `C:\komibook_shared\.env` sang `C:\komibook_releases\<SHA>\backend\.env`.
2. **Public Storage (BÃ¬a sÃ¡ch, Banner):** Junction `backend\storage\app\public` -> `C:\komibook_shared\storage\app\public`.
3. **Private Storage (CCCD tÃ¡c giáº£, File ticket):** Junction `backend\storage\app\private` -> `C:\komibook_shared\storage\app\private`.
4. **Framework Cache/Sessions/Views:** Junction `backend\storage\framework\{cache,sessions,views}` -> `C:\komibook_shared\storage\framework\{cache,sessions,views}`.
5. **Logs:** Junction `backend\storage\logs` -> `C:\komibook_shared\storage\logs`.

---

## 8. Bá»˜ SCRIPT KIá»‚M TRA BUNDLE Sáº CH Äá»† QUY (RECURSIVE SCAN)

```powershell
$BUNDLE_PATH = "C:\komibook_releases\$COMMIT_SHA\frontend\dist"
$DEV_MATCHES = Get-ChildItem -Path $BUNDLE_PATH -Recurse -File | Select-String -Pattern "vite/client|/src/main\.js|vue-devtools"

if ($DEV_MATCHES.Count -gt 0) {
    Write-Error "CRITICAL BUILD ERROR: Found $($DEV_MATCHES.Count) development assets/HMR references in production bundle!"
    $DEV_MATCHES | Select-Object Path, LineNumber, Line | Format-Table -AutoSize
    exit 1
} else {
    Write-Host "PASS: Production bundle clean. No dev assets found." -ForegroundColor Green
}
```

---

## 9. QUY TRÃŒNH PHÃ‚N ÄOáº N RUNBOOK CHI TIáº¾T (STRICT SUB-BATCHES)

```
[Batch 0: Inventory Read-Only (HoÃ n táº¥t)]
   â””â”€â”€> [Batch 0.6.2: Refinement & Document Completion (Hiá»‡n táº¡i)]
         â””â”€â”€> (Dá»ªNG & CODEX PHÃŠ DUYá»†T)
               â”œâ”€â”€> [Batch 1A.1: Manifest Backup & Version/Hash Pinning] â”€â”€â–º (Dá»ªNG & BÃO CÃO)
               â”œâ”€â”€> [Batch 1A.2: Isolated Runtime Download & Compatibility Gate] â”€â”€â–º (Dá»ªNG & BÃO CÃO)
               â”œâ”€â”€> [Batch 1P: Pre-Release Source Gate (trustProxies & Quality Gates)] â”€â”€â–º (Dá»ªNG & BÃO CÃO)
               â”œâ”€â”€> [Batch 1B.1: Clean Release Assembly & Frontend Build] â”€â”€â–º (Dá»ªNG & BÃO CÃO)
               â”œâ”€â”€> [Batch 1B.2: WinSW Service Setup & Local Cookie Smoke Verification] â”€â”€â–º (Dá»ªNG & BÃO CÃO)
               â”œâ”€â”€> [Batch 2: Cloudflare Ingress Switch & Immediate External Health Check] â”€â”€â–º (Dá»ªNG & BÃO CÃO)
               â”œâ”€â”€> [Batch 3: Extended Browser Smoke Test & 24h Stability Gate] â”€â”€â–º (Dá»ªNG & BÃO CÃO)
               â””â”€â”€> [Batch 4: Decommission Dev Stack, Herd Uninstall & Post-Cleanup Verification]
```

### BATCH 1A.1 â€” MANIFEST BACKUP & VERSION/HASH PINNING
1. Táº¡o backup manifest mÃ£ nguá»“n vÃ  cáº¥u hÃ¬nh hiá»‡n táº¡i.
2. Khai bÃ¡o báº£ng Pinned Version, URL chÃ­nh thá»©c vÃ  mÃ£ bÄƒm SHA-256 cá»§a cÃ¡c runtime artifact:
   - `Node.js v22.13.0 LTS Portable`
   - `FrankenPHP Windows Executable`
   - `WinSW v2.12.0`
3. **TUYá»†T Äá»I KHÃ”NG** táº£i xuá»‘ng hay cÃ i Ä‘áº·t á»Ÿ sub-batch nÃ y.
4. **Dá»ªNG Láº I & BÃO CÃO Káº¾T QUáº¢ CHO CODEX.**

### BATCH 1A.2 â€” ISOLATED RUNTIME DOWNLOAD & COMPATIBILITY GATE
1. Táº£i vÃ  giáº£i nÃ©n cÃ¡c runtime artifacts vÃ o thÆ° má»¥c cÃ´ láº­p `C:\runtimes\`.
2. Kiá»ƒm tra mÃ£ bÄƒm SHA-256 checksum cá»§a tá»«ng file Ä‘Ã£ táº£i.
3. Thá»±c thi **FrankenPHP Compatibility Gate** táº¡i `C:\Users\thean\Herd\DoAnTotNGhiep_komibook\backend`.
4. Tuyá»‡t Ä‘á»‘i **KHÃ”NG** cÃ i Ä‘áº·t Windows Service, **KHÃ”NG** build release, **KHÃ”NG** chuyá»ƒn traffic.
5. **Dá»ªNG Láº I & BÃO CÃO Káº¾T QUáº¢ CHO CODEX.**

### BATCH 1P â€” PRE-RELEASE SOURCE GATE (TRUSTED PROXIES & QUALITY GATES)
1. Cáº­p nháº­t `backend/bootstrap/app.php` giá»›i háº¡n trusted proxies vá» `127.0.0.1`.
2. Bá»• sung targeted tests kiá»ƒm tra header proxy.
3. Cháº¡y láº¡i toÃ n bá»™ 9 Phase 1 Quality Gates.
4. Tuyá»‡t Ä‘á»‘i **KHÃ”NG commit** hay Ä‘Ã³ng gÃ³i release artifact á»Ÿ bÆ°á»›c nÃ y.
5. **Dá»ªNG Láº I & BÃO CÃO Káº¾T QUáº¢ CHO CODEX.** Sau khi Codex duyá»‡t, viá»‡c commit vÃ  Ä‘Ã³ng gÃ³i release artifact á»Ÿ Batch 1B.1 chá»‰ thá»±c hiá»‡n khi cÃ³ phÃª duyá»‡t rÃµ rÃ ng tá»« NgÆ°á»i dÃ¹ng.

### BATCH 1B.1 â€” CLEAN RELEASE ASSEMBLY & FRONTEND BUILD
1. Dá»±ng release tá»« Clean Commit SHA Ä‘Ã£ phÃª duyá»‡t táº¡i `C:\komibook_releases\<SHA>\`.
2. DÃ¹ng `C:\runtimes\node-v22.13.0-win-x64\npm.cmd` tuyá»‡t Ä‘á»‘i cháº¡y `npm ci` vÃ  `npm run build` trong `frontend/`.
3. Cháº¡y script kiá»ƒm tra Ä‘á»‡ quy bundle sáº¡ch (exit 0).
4. **Dá»ªNG Láº I & BÃO CÃO Káº¾T QUáº¢ CHO CODEX.**

### BATCH 1B.2 â€” WINSW SERVICE SETUP & LOCAL COOKIE SMOKE VERIFICATION
1. Thá»±c hiá»‡n quy trÃ¬nh Caddyfile Config phÃ¹ há»£p (Initial cho láº§n Ä‘áº§u, Subsequent cho nÃ¢ng cáº¥p).
2. Kiá»ƒm tra tráº¡ng thÃ¡i service WinSW Ä‘áº¡t `Started` / `Running`.
3. **Thá»±c Hiá»‡n Local Smoke Test Thá»±c Táº¿ Kiá»ƒm Tra Session Cookie & XSRF-TOKEN:**
   ```powershell
   $sess = New-Object Microsoft.PowerShell.Commands.WebRequestSession
   $Headers = @{
       "Host" = "komibook.id.vn"
       "X-Forwarded-Proto" = "https"
       "X-Forwarded-Host" = "komibook.id.vn"
   }

   # 1. Gá»i Endpoint CSRF Cookie cá»§a Sanctum
   $csrfResp = Invoke-WebRequest -Uri "http://127.0.0.1:8080/sanctum/csrf-cookie" -Headers $Headers -WebSession $sess -UseBasicParsing
   if ($csrfResp.StatusCode -ne 204 -and $csrfResp.StatusCode -ne 200) { Write-Error "Sanctum CSRF cookie check failed!"; exit 1 }

   # 2. Kiá»ƒm tra Cookie Session & XSRF-TOKEN thá»±c táº¿ (KhÃ´ng dÃ¹ng vÃ²ng láº·p rá»—ng)
   $cookies = $sess.Cookies.GetCookies("https://komibook.id.vn")
   if ($cookies.Count -eq 0) { Write-Error "COOKIE SMOKE FAILED: Cookie collection is empty!"; exit 1 }

   $sessionCookie = $cookies | Where-Object { $_.Name -match "session" }
   $xsrfCookie = $cookies | Where-Object { $_.Name -eq "XSRF-TOKEN" }

   if (-not $sessionCookie) { Write-Error "COOKIE SMOKE FAILED: Session cookie missing!"; exit 1 }
   if (-not $xsrfCookie) { Write-Error "COOKIE SMOKE FAILED: XSRF-TOKEN cookie missing!"; exit 1 }

   # Session cookie: Secure, HttpOnly, SameSite=Lax
   if (-not $sessionCookie.HttpOnly) { Write-Error "Session cookie missing HttpOnly!"; exit 1 }
   if (-not $sessionCookie.Secure) { Write-Error "Session cookie missing Secure!"; exit 1 }

   # XSRF-TOKEN cookie: Secure, SameSite=Lax (KhÃ´ng báº¯t buá»™c HttpOnly Ä‘á»ƒ JS Ä‘á»c)
   if (-not $xsrfCookie.Secure) { Write-Error "XSRF-TOKEN missing Secure!"; exit 1 }

   # 3. Test Public API
   $apiResp = Invoke-WebRequest -Uri "http://127.0.0.1:8080/api/books" -Headers $Headers -WebSession $sess -UseBasicParsing
   if ($apiResp.StatusCode -ne 200 -or $apiResp.Headers["Content-Type"] -notmatch "application/json") { Write-Error "Public API check failed!"; exit 1 }
   ```
4. **Dá»ªNG Láº I & BÃO CÃO Káº¾T QUáº¢ CHO CODEX.**

### BATCH 2 â€” CLOUDFLARE INGRESS SWITCH & IMMEDIATE EXTERNAL HEALTH CHECK
1. **LÆ°u Ingress Target CÅ©:** Ghi láº¡i cáº¥u hÃ¬nh ingress target hiá»‡n táº¡i (`http://localhost:5173`).
2. **Cáº­p Nháº­t Ingress Rule:** TrÃªn Cloudflare Zero Trust Dashboard, chuyá»ƒn target hostname `komibook.id.vn` tá»« `5173` sang `http://127.0.0.1:8080` (ÄÃ¢y lÃ  bÆ°á»›c thao tÃ¡c cuá»‘i cÃ¹ng cá»§a Batch 2).
3. **Immediate External Health Check:**
   - Kiá»ƒm tra HTTP status 200 táº¡i `https://komibook.id.vn/`.
   - TrÃ­ch xuáº¥t toÃ n bá»™ asset scripts/css tá»« `index.html` vÃ  thá»±c hiá»‡n GET verify HTTP 200 OK.
   - Kiá»ƒm tra Public API `/api/books` tráº£ vá» `application/json`.
4. **Quy TrÃ¬nh Rollback Thá»§ CÃ´ng CÃ³ XÃ¡c Nháº­n (Manual User-Confirmed Rollback Protocol):**
   - Náº¿u báº¥t ká»³ kiá»ƒm tra ngoÃ i nÃ o tháº¥t báº¡i, ngÆ°á»i thá»±c thi tiáº¿n hÃ nh rollback thá»§ cÃ´ng theo 2 bÆ°á»›c:
     1. Thá»±c hiá»‡n hoÃ¡n Ä‘á»•i atomic Caddyfile khÃ´i phá»¥c vá» release stable trÆ°á»›c Ä‘Ã³ vÃ  graceful reload.
     2. Äá»•i láº¡i Ingress Rule trÃªn Cloudflare Zero Trust Dashboard vá» `http://localhost:5173`.
   - **Giá»¯ NguyÃªn Dev Stack:** Tuyá»‡t Ä‘á»‘i **KHÃ”NG** dá»«ng dev stack (`5173`/`8000`) trÆ°á»›c khi cá»­a sá»• rollback káº¿t thÃºc.
5. **Dá»ªNG Láº I & BÃO CÃO Káº¾T QUáº¢ CHO CODEX.**

### BATCH 3 â€” EXTENDED BROWSER SMOKE TEST & STABILITY GATE
1. Duy trÃ¬ dev stack (`5173`/`8000`) cháº¡y song song trong suá»‘t cá»­a sá»• theo dÃµi.
2. Kiá»ƒm thá»­ toÃ n bá»™ 8 trang cÃ´ng khai trÃªn trÃ¬nh duyá»‡t thá»±c táº¿.
3. Kiá»ƒm thá»­ phÃ¢n quyá»n Ä‘Äƒng nháº­p vÃ  chuyá»ƒn hÆ°á»›ng post-login theo Role:
   - **Customer:** Trang chá»§ `/`
   - **Admin:** Admin Dashboard `/admin/dashboard`
   - **Vendor:** Vendor Dashboard `/vendor/dashboard`
4. **Stability Gate (Tá»‘i thiá»ƒu 24 giá» + 1 láº§n OS Reboot):**
   - Giá»¯ há»‡ thá»‘ng váº­n hÃ nh á»•n Ä‘á»‹nh liÃªn tá»¥c Ã­t nháº¥t **24 giá»**.
   - Thá»±c hiá»‡n **1 láº§n khá»Ÿi Ä‘á»™ng láº¡i mÃ¡y chá»§ (OS Reboot)** Ä‘Æ°á»£c NgÆ°á»i dÃ¹ng phÃª duyá»‡t.
   - XÃ¡c minh cÃ¡c Windows Services (`FrankenPHPService`, `Cloudflared`) tá»± Ä‘á»™ng khá»Ÿi cháº¡y sau khi reboot, vÃ  kiá»ƒm tra `https://komibook.id.vn/` Ä‘áº¡t status `200 OK` trÆ°á»›c khi chuyá»ƒn sang Batch 4.
5. **Dá»ªNG Láº I & BÃO CÃO Káº¾T QUáº¢ CHO CODEX.**

### BATCH 4 â€” DECOMMISSION DEV STACK, HERD UNINSTALL & POST-CLEANUP VERIFICATION
Batch 4 chá»‰ Ä‘Æ°á»£c thá»±c thi sau khi Batch 3 káº¿t thÃºc vÃ  há»‡ thá»‘ng váº­n hÃ nh hoÃ n toÃ n á»•n Ä‘á»‹nh trÃªn Production stack má»›i:
1. Sao lÆ°u dá»± phÃ²ng cÃ¡c thÆ° má»¥c cáº¥u hÃ¬nh vÃ  dá»¯ liá»‡u:
   - `C:\Users\thean\.codex`
   - `C:\Users\thean\AppData\Roaming\Antigravity IDE`
   - ToÃ n bá»™ Git Repository & chat data.
2. Dá»«ng tiáº¿n trÃ¬nh dev stack (`npm run dev` port 5173 vÃ  `php artisan serve` port 8000).
3. Thá»±c hiá»‡n gá»¡ bá» **Laravel Herd** báº±ng **Windows Uninstaller chÃ­nh thá»©c** (`Control Panel` -> `Programs and Features`). Tuyá»‡t Ä‘á»‘i khÃ´ng dÃ¹ng thao tÃ¡c xÃ³a rÃ¡c cá»§a Herd Site Manager.
4. Kiá»ƒm tra vÃ  cáº­p nháº­t System Environment variables, xÃ³a bá» cÃ¡c Ä‘Æ°á»ng dáº«n liÃªn quan Ä‘áº¿n Herd.
5. Thá»±c hiá»‡n verification toÃ n bá»™ cÃ´ng cá»¥ Ä‘á»™c láº­p:
   ```powershell
   node -v
   php -v
   composer --version
   git status --short --branch
   ```
6. BÃ¡o cÃ¡o hoÃ n táº¥t bÃ n giao dá»± Ã¡n.

---

## 10. TRáº NG THÃI TÃ€I LIá»†U

**Tráº¡ng thÃ¡i:** `READY_FOR_CODEX_REVIEW` (Sáº´N SÃ€NG CHO CODEX THáº¨M Äá»ŠNH Tá»”NG THá»‚ BATCH 0.6.2)
> *Ghi chÃº:* Tuyá»‡t Ä‘á»‘i KHÃ”NG tá»± Ã½ ghi `APPROVED` hay triá»ƒn khai cÃ i Ä‘áº·t khi chÆ°a cÃ³ sá»± phÃª duyá»‡t chÃ­nh thá»©c tá»« Codex vÃ  NgÆ°á»i dÃ¹ng.
