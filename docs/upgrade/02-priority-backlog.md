# Danh SÃ¡ch Backlog Æ¯u TiÃªn NÃ¢ng Cáº¥p KomiBook (P0 / P1 / P2)

- **NgÃ y cáº­p nháº­t:** 22/07/2026
- **Giai Ä‘oáº¡n:** GIAI ÄOáº N 1 â€” SECURITY HARDENING (HOÃ€N THÃ€NH BIÃŠN Táº¬P VÃ€ DUYá»†T Báº°NG CHá»¨NG CORRECTIVE REVIEW)
- **BÃ¡o cÃ¡o káº¿t quáº£ Giai Ä‘oáº¡n 1:** [03-phase-1-security-report.md](file:///c:/Users/thean/Herd/DoAnTotNGhiep_komibook/docs/upgrade/03-phase-1-security-report.md)
- **Quy táº¯c:** Má»i issue (P0/P1/P2) Ä‘á»u cÃ³ Ä‘áº§y Ä‘á»§ 11 trÆ°á»ng thÃ´ng tin chuáº©n xÃ¡c. PhÃ¢n biá»‡t rÃµ phÆ°Æ¡ng phÃ¡p xÃ¡c minh: "ÄÃ£ xÃ¡c nháº­n báº±ng static analysis", "ÄÃ£ xÃ¡c nháº­n báº±ng test", "Cáº§n kiá»ƒm tra thá»§ cÃ´ng".

---

## 1. Danh SÃ¡ch Issue Æ¯u TiÃªn Cao Nháº¥t (P0 â€” Critical / Blocker)

---

### Issue `SEC-01`: MÃ£ OTP Cá»‘ Äá»‹nh `'123456'` VÃ  Tráº£ MÃ£ OTP Trong Response Debug [Tráº¡ng thÃ¡i: VERIFIED]

- **Priority:** P0
- **Module:** Security / Auth
- **MÃ´ táº£:** `PhoneAuthController.php` cho phÃ©p vÆ°á»£t qua xÃ¡c thá»±c sá»‘ Ä‘iá»‡n thoáº¡i báº±ng mÃ£ OTP cá»‘ Ä‘á»‹nh `'123456'` báº¥t ká»ƒ sá»‘ Ä‘iá»‡n thoáº¡i nÃ o. NgoÃ i ra, trong mÃ´i trÆ°á»ng local/debug, mÃ£ OTP thá»±c táº¿ Ä‘Æ°á»£c tráº£ vá» trá»±c tiáº¿p trong JSON response.
- **Má»©c Ä‘á»™ áº£nh hÆ°á»Ÿng:** Nguy cÆ¡ chiáº¿m Ä‘oáº¡t tÃ i khoáº£n (Account Takeover) cá»§a báº¥t ká»³ ngÆ°á»i dÃ¹ng nÃ o chá»‰ báº±ng cÃ¡ch biáº¿t sá»‘ Ä‘iá»‡n thoáº¡i.
- **Báº±ng chá»©ng file & dÃ²ng:**
  - `backend/app/Http/Controllers/Api/PhoneAuthController.php` (DÃ²ng 36â€“38 & DÃ²ng 67)
  ```php
  // DÃ²ng 36-38: Tráº£ OTP trong response
  if (config('app.env') === 'local' || config('app.debug')) {
      $data['otp'] = $otp;
  }
  // DÃ²ng 67: Cho phÃ©p OTP '123456' máº·c Ä‘á»‹nh
  if ($otp !== $cachedOtp && $otp !== '123456') { ... }
  ```
- **PhÆ°Æ¡ng phÃ¡p xÃ¡c minh:** ÄÃ£ xÃ¡c nháº­n báº±ng static analysis & test backend (`AuthorDrmInventoryTest.php:L218`).
- **CÃ¡ch tÃ¡i hiá»‡n / Kiá»ƒm chá»©ng:** Gá»­i request `POST /api/auth/phone/verify-otp` vá»›i `phone = 0901234567` vÃ  `otp = 123456`. Há»‡ thá»‘ng tráº£ vá» Bearer Token thÃ nh cÃ´ng.
- **HÆ°á»›ng kháº¯c phá»¥c:** Loáº¡i bá» Ä‘iá»u kiá»‡n `$otp !== '123456'` vÃ  khÃ´ng tráº£ OTP trong response. Sá»­ dá»¥ng SMS Gateway / Log driver an toÃ n.
- **Test cáº§n bá»• sung:** Feature test verify OTP vá»›i mÃ£ '123456' pháº£i bá»‹ tá»« chá»‘i 422 Unprocessable Entity.
- **Phá»¥ thuá»™c:** KhÃ´ng.
- **Æ¯á»›c lÆ°á»£ng:** S

---

### Issue `SEC-02`: Lá»— Há»•ng Stored XSS Táº¡i MÃ´ Táº£ SÃ¡ch Do Render `v-html` KhÃ´ng Sanitize [Tráº¡ng thÃ¡i: VERIFIED]

- **Priority:** P0
- **Module:** Security / Frontend & Backend
- **MÃ´ táº£:** MÃ n hÃ¬nh chi tiáº¿t sÃ¡ch `BookDetailView.vue` render ná»™i dung mÃ´ táº£ sÃ¡ch (`book.description`) thÃ´ng qua directive `v-html` mÃ  khÃ´ng qua báº¥t ká»³ lá»›p lÃ m sáº¡ch HTML nÃ o.
- **Má»©c Ä‘á»™ áº£nh hÆ°á»Ÿng:** Vendor cÃ³ thá»ƒ chÃ¨n script Ä‘á»™c háº¡i vÃ o mÃ´ táº£ sÃ¡ch Ä‘á»ƒ thá»±c thi JavaScript trÃªn trÃ¬nh duyá»‡t ngÆ°á»i mua.
- **Báº±ng chá»©ng file & dÃ²ng:**
  - `frontend/src/views/BookDetailView.vue` (DÃ²ng 278)
  ```html
  <div v-html="formatDescription(book.description)"></div>
  ```
- **PhÆ°Æ¡ng phÃ¡p xÃ¡c minh:** ÄÃ£ xÃ¡c nháº­n báº±ng static analysis.
- **CÃ¡ch tÃ¡i hiá»‡n / Kiá»ƒm chá»©ng:** ÄÄƒng sáº£n pháº©m vá»›i description chá»©a `<img src=x onerror=alert(document.cookie)>` vÃ  xem trang chi tiáº¿t.
- **HÆ°á»›ng kháº¯c phá»¥c:**
  1. PhÃ­a Backend: Sanitize HTML báº±ng HTMLPurifier / allowlist trÆ°á»›c khi lÆ°u DB hoáº·c tráº£ vá» response.
  2. PhÃ­a Frontend: TÃ­ch há»£p `DOMPurify` lÃ m lá»›p phÃ²ng vá»‡ bá»• sung trÆ°á»›c khi truyá»n vÃ o `v-html`.
- **Test cáº§n bá»• sung:** Backend Feature test Ä‘áº©y payload `<script>` vÃ  Frontend Component test kiá»ƒm tra output DOM Ä‘Ã£ loáº¡i bá» script.
- **Phá»¥ thuá»™c:** Package HTMLPurifier (Backend) & DOMPurify (Frontend).
- **Æ¯á»›c lÆ°á»£ng:** M

---

### Issue `SEC-03`: Giáº¥y Tá» TÃ¹y ThÃ¢n CCCD Cá»§a TÃ¡c Giáº£ LÆ°u Trá»±c Tiáº¿p TrÃªn ÄÄ©a Public [Tráº¡ng thÃ¡i: VERIFIED]

- **Priority:** P0
- **Module:** Security / Author
- **MÃ´ táº£:** File áº£nh CCCD/Há»™ chiáº¿u khi Ä‘Äƒng kÃ½ tÃ¡c giáº£ Ä‘Æ°á»£c lÆ°u trá»±c tiáº¿p vÃ o á»• Ä‘Ä©a public `storage/app/public/authors/cccd`.
- **Má»©c Ä‘á»™ áº£nh hÆ°á»Ÿng:** Vi pháº¡m nghiÃªm trá»ng an toÃ n dá»¯ liá»‡u cÃ¡ nhÃ¢n. Báº¥t ká»³ ai cÅ©ng cÃ³ thá»ƒ truy cáº­p URL public `http://domain/storage/authors/cccd/...` Ä‘á»ƒ táº£i áº£nh CCCD cá»§a tÃ¡c giáº£ mÃ  khÃ´ng cáº§n Ä‘Äƒng nháº­p.
- **Báº±ng chá»©ng file & dÃ²ng:**
  - `backend/app/Http/Controllers/Api/AuthorController.php` (DÃ²ng 40)
  ```php
  $filePath = $request->file('identity_document')->store('authors/cccd', 'public');
  ```
- **PhÆ°Æ¡ng phÃ¡p xÃ¡c minh:** ÄÃ£ xÃ¡c nháº­n báº±ng static analysis.
- **CÃ¡ch tÃ¡i hiá»‡n / Kiá»ƒm chá»©ng:** ÄÄƒng kÃ½ tÃ¡c giáº£, má»Ÿ URL file tráº£ vá» trong trÃ¬nh duyá»‡t áº©n danh (khÃ´ng cáº§n token), file váº«n xem Ä‘Æ°á»£c.
- **HÆ°á»›ng kháº¯c phá»¥c:** Chuyá»ƒn lÆ°u trá»¯ sang Private Storage (`local` disk khÃ´ng link public). Táº¡o API Controller Stream File kÃ¨m Policy authorization kiá»ƒm tra quyá»n Admin/TÃ¡c giáº£ sá»Ÿ há»¯u vÃ  ghi Audit Log.
- **Test cáº§n bá»• sung:** Feature test kiá»ƒm tra truy cáº­p URL public file CCCD tráº£ vá» 404/403.
- **Phá»¥ thuá»™c:** Laravel Authorization Policy.
- **Æ¯á»›c lÆ°á»£ng:** M

---

### Issue `AUTH-01`: ÄÄƒng Nháº­p Google ChÆ°a XÃ¡c Thá»±c Audience (`aud`) VÃ  Cho PhÃ©p Bá» Qua Token á»ž Dev Mode [Tráº¡ng thÃ¡i: VERIFIED]

- **Priority:** P0
- **Module:** Auth / Security
- **MÃ´ táº£:** HÃ m `googleLogin` gá»i Google endpoint `tokeninfo` nhÆ°ng khÃ´ng kiá»ƒm tra Client ID (`aud`), Issuer (`iss`), hay thá»i háº¡n token (`exp`). NgoÃ i ra khi `APP_DEBUG=true`, cÃ³ thá»ƒ bá» qua `id_token` Ä‘á»ƒ Ä‘Äƒng nháº­p báº±ng thÃ´ng tin mock.
- **Má»©c Ä‘á»™ áº£nh hÆ°á»Ÿng:** Káº» táº¥n cÃ´ng cÃ³ thá»ƒ dÃ¹ng Google ID Token táº¡o tá»« á»©ng dá»¥ng khÃ¡c hoáº·c gá»­i request mock Ä‘á»ƒ Ä‘Äƒng nháº­p dÆ°á»›i danh nghÄ©a báº¥t ká»³ tÃ i khoáº£n nÃ o.
- **Báº±ng chá»©ng file & dÃ²ng:**
  - `backend/app/Http/Controllers/Api/AuthController.php` (DÃ²ng 230â€“260)
- **PhÆ°Æ¡ng phÃ¡p xÃ¡c minh:** ÄÃ£ xÃ¡c nháº­n báº±ng static analysis.
- **CÃ¡ch tÃ¡i hiá»‡n / Kiá»ƒm chá»©ng:** Gá»­i request `POST /api/auth/google-login` kÃ¨m email Admin vÃ  `google_id` khi báº­t debug mode.
- **HÆ°á»›ng kháº¯c phá»¥c:** XÃ¢y dá»±ng cÆ¡ cháº¿ xÃ¡c minh Google Token chuáº©n kiá»ƒm tra báº¯t buá»™c cÃ¡c trÆ°á»ng `aud`, `iss`, `exp`, `sub`, `email_verified`. Loáº¡i bá» hoÃ n toÃ n luá»“ng mock á»Ÿ Backend.
- **Test cáº§n bá»• sung:** Unit test xÃ¡c thá»±c Google Token sai Audience hoáº·c sai Issuer bá»‹ tá»« chá»‘i 422.
- **Phá»¥ thuá»™c:** Google Client ID configuration.
- **Æ¯á»›c lÆ°á»£ng:** M

---

### Issue `PAY-01`: Checkout Äa Vendor Qua VNPAY Chá»‰ Gá»­i Thanh ToÃ¡n Cho ÄÆ¡n HÃ ng Äáº§u TiÃªn

- **Priority:** P0
- **Module:** Payment / Checkout
- **MÃ´ táº£:** Sau khi checkout giá» hÃ ng Ä‘a vendor, Backend táº¡o nhiá»u Ä‘Æ¡n hÃ ng (`res.data.data`). Tuy nhiÃªn `CartView.vue` chá»‰ láº¥y Ä‘Æ¡n hÃ ng Ä‘áº§u tiÃªn `res.data.data[0].id` Ä‘á»ƒ gá»­i thanh toÃ¡n VNPAY.
- **Má»©c Ä‘á»™ áº£nh hÆ°á»Ÿng:** KhÃ¡ch hÃ ng chá»‰ thanh toÃ¡n tiá»n cho Vendor A, cÃ¡c Ä‘Æ¡n hÃ ng cÃ²n láº¡i cá»§a Vendor B, C váº«n chÆ°a thanh toÃ¡n nhÆ°ng Ä‘Ã£ táº¡o Ä‘Æ¡n.
- **Báº±ng chá»©ng file & dÃ²ng:**
  - `frontend/src/views/CartView.vue` (DÃ²ng 495â€“497)
  ```javascript
  const firstOrder = res.data.data[0]
  const vnpayRes = await apiClient.post('/api/vnpay/create', { order_id: firstOrder.id })
  ```
- **PhÆ°Æ¡ng phÃ¡p xÃ¡c minh:** ÄÃ£ xÃ¡c nháº­n báº±ng static analysis.
- **CÃ¡ch tÃ¡i hiá»‡n / Kiá»ƒm chá»©ng:** Chá»n mua 1 sÃ¡ch Vendor A + 1 sÃ¡ch Vendor B. Tiáº¿n hÃ nh thanh toÃ¡n VNPAY, quan sÃ¡t sá»‘ tiá»n thanh toÃ¡n VNPAY chá»‰ báº±ng giÃ¡ trá»‹ sáº£n pháº©m Vendor A.
- **HÆ°á»›ng kháº¯c phá»¥c:** Há»— trá»£ thanh toÃ¡n gá»™p cho mÃ£ giao dá»‹ch tá»•ng (Master Transaction / Parent Order) hoáº·c xá»­ lÃ½ chuá»—i thanh toÃ¡n Ä‘a vendor.
- **Test cáº§n bá»• sung:** Integration test checkout Ä‘a vendor Ä‘áº£m báº£o VNPAY amount khá»›p 100% tá»•ng tiá»n giá» hÃ ng.
- **Phá»¥ thuá»™c:** Master Order Grouping.
- **Æ¯á»›c lÆ°á»£ng:** L

---

### Issue `PAY-02` & `INV-01`: Job `ProcessOrder` KhÃ´ng Idempotent Dáº«n Äáº¿n Trá»« Tá»“n Kho 2 Láº§n Khi Nháº­n VNPAY IPN

- **Priority:** P0
- **Module:** Payment / Inventory
- **MÃ´ táº£:** `CheckoutService` phÃ¡t `ProcessOrder::dispatch($order->id)` ngay khi táº¡o Ä‘Æ¡n `pending`. Sau Ä‘Ã³ VNPAY IPN gá»i láº¡i (`VnpayController.php`) tiáº¿p tá»¥c phÃ¡t `ProcessOrder::dispatch($order->id)` láº§n ná»¯a. Do `ProcessOrder.php` khÃ´ng cÃ³ Idempotency check, tá»“n kho MySQL bá»‹ trá»« 2 láº§n.
- **Má»©c Ä‘á»™ áº£nh hÆ°á»Ÿng:** Má»i Ä‘Æ¡n hÃ ng VNPAY bá»‹ trá»« tá»“n kho gáº¥p Ä‘Ã´i. Náº¿u IPN retry nhiá»u láº§n, tá»“n kho bá»‹ trá»« Ã¢m.
- **Báº±ng chá»©ng file & dÃ²ng:**
  - `backend/app/Services/CheckoutService.php` (DÃ²ng 283)
  - `backend/app/Http/Controllers/Api/VnpayController.php` (DÃ²ng 180)
  - `backend/app/Jobs/ProcessOrder.php` (DÃ²ng 48)
- **PhÆ°Æ¡ng phÃ¡p xÃ¡c minh:** ÄÃ£ xÃ¡c nháº­n báº±ng static analysis.
- **CÃ¡ch tÃ¡i hiá»‡n / Kiá»ƒm chá»©ng:** Giáº£ láº­p cháº¡y IPN VNPAY cho Ä‘Æ¡n hÃ ng vá»«a táº¡o, kiá»ƒm tra sá»‘ lÆ°á»£ng tá»“n kho trong DB bá»‹ giáº£m 2 láº§n.
- **HÆ°á»›ng kháº¯c phá»¥c:** ThÃªm cá» `inventory_deducted` hoáº·c State transition check trÆ°á»›c khi trá»« tá»“n kho.
- **Test cáº§n bá»• sung:** Unit test thá»±c thi `ProcessOrder` 3 láº§n liÃªn tiáº¿p cho 1 Ä‘Æ¡n hÃ ng, tá»“n kho chá»‰ bá»‹ trá»« Ä‘Ãºng 1 láº§n.
- **Phá»¥ thuá»™c:** DB Migration cá» cáº£n trá»« láº·p.
- **Æ¯á»›c lÆ°á»£ng:** M

---

### Issue `ORD-01`: Cáº­p Nháº­t Tráº¡ng ThÃ¡i Giao HÃ ng `delivered` Cho PhÃ©p TÃ­ch Äiá»ƒm ThÃ nh ViÃªn VÃ´ Háº¡n

- **Priority:** P0
- **Module:** Order / Loyalty CRM
- **MÃ´ táº£:** Trong `OrderController.php`, khi `shipping_status` chuyá»ƒn thÃ nh `delivered`, há»‡ thá»‘ng tá»± Ä‘á»™ng cá»™ng Ä‘iá»ƒm cho user mÃ  khÃ´ng kiá»ƒm tra Ä‘Æ¡n hÃ ng Ä‘Ã£ hoÃ n táº¥t trÆ°á»›c Ä‘Ã³ hay chÆ°a.
- **Má»©c Ä‘á»™ áº£nh hÆ°á»Ÿng:** Vendor cÃ³ thá»ƒ báº¯n request update `delivered` nhiá»u láº§n Ä‘á»ƒ gian láº­n tÃ­ch lÅ©y Ä‘iá»ƒm vÃ  thÄƒng háº¡ng VIP vÃ´ háº¡n.
- **Báº±ng chá»©ng file & dÃ²ng:**
  - `backend/app/Http/Controllers/Api/OrderController.php` (DÃ²ng 156â€“164)
- **PhÆ°Æ¡ng phÃ¡p xÃ¡c minh:** ÄÃ£ xÃ¡c nháº­n báº±ng static analysis.
- **CÃ¡ch tÃ¡i hiá»‡n / Kiá»ƒm chá»©ng:** Gá»­i PATCH update status `delivered` 5 láº§n liÃªn tiáº¿p cho 1 Ä‘Æ¡n hÃ ng, sá»‘ Ä‘iá»ƒm cá»§a user tÄƒng gáº¥p 5 láº§n.
- **HÆ°á»›ng kháº¯c phá»¥c:** Bá»• sung Ä‘iá»u kiá»‡n `if ($order->status !== 'completed')` trÆ°á»›c khi tÃ­nh Ä‘iá»ƒm vÃ  ghi váº¿t báº£ng `point_transactions`.
- **Test cáº§n bá»• sung:** Feature test update `delivered` láº§n 2 khÃ´ng tÄƒng Ä‘iá»ƒm user.
- **Phá»¥ thuá»™c:** Point Transaction History Table.
- **Æ¯á»›c lÆ°á»£ng:** S

---

### Issue `FE-01`: Sai TÃªn Endpoint Kiá»ƒm Tra Quyá»n Sá»Ÿ Há»¯u Ebook Trong `BookDetailView.vue`

- **Priority:** P0
- **Module:** Frontend / Ebook
- **MÃ´ táº£:** Frontend gá»i `/api/books/${bookId}/ownership`, nhÆ°ng Backend Ä‘á»‹nh nghÄ©a `/books/{id}/check-ownership`.
- **Má»©c Ä‘á»™ áº£nh hÆ°á»Ÿng:** Request bá»‹ 404, nÃºt "Äá»c SÃ¡ch Ngay" khÃ´ng bao giá» xuáº¥t hiá»‡n ká»ƒ cáº£ khi Ä‘Ã£ mua sÃ¡ch.
- **Báº±ng chá»©ng file & dÃ²ng:**
  - `frontend/src/views/BookDetailView.vue` (DÃ²ng 900)
  - `backend/routes/api.php` (DÃ²ng 60)
- **PhÆ°Æ¡ng phÃ¡p xÃ¡c minh:** ÄÃ£ xÃ¡c nháº­n báº±ng static analysis.
- **CÃ¡ch tÃ¡i hiá»‡n / Kiá»ƒm chá»©ng:** ÄÄƒng nháº­p tÃ i khoáº£n Ä‘Ã£ mua ebook, xem trang chi tiáº¿t sÃ¡ch, quan sÃ¡t console bÃ¡o lá»—i 404.
- **HÆ°á»›ng kháº¯c phá»¥c:** Sá»­a `BookDetailView.vue` gá»i Ä‘Ãºng `/api/books/${bookId}/check-ownership`.
- **Test cáº§n bá»• sung:** Component test kiá»ƒm tra gá»i Ä‘Ãºng API check ownership.
- **Phá»¥ thuá»™c:** KhÃ´ng.
- **Æ¯á»›c lÆ°á»£ng:** S

---

### Issue `FE-02`: Lá»—i Route VÃ  Params Chuyá»ƒn MÃ n HÃ¬nh Äá»c Ebook (`EbookReader`)

- **Priority:** P0
- **Module:** Frontend / Routing
- **MÃ´ táº£:** Router khai bÃ¡o `/reader/:orderId/:bookId`. `BookDetailView.vue` gá»i `params: { id }` (thiáº¿u orderId), `MyLibraryView.vue` gá»i sai Ä‘Æ°á»ng dáº«n `/read/${orderId}/${bookId}`.
- **Má»©c Ä‘á»™ áº£nh hÆ°á»Ÿng:** KhÃ¡ch hÃ ng khÃ´ng thá»ƒ má»Ÿ mÃ n hÃ¬nh Ä‘á»c ebook tá»« cáº£ Tá»§ sÃ¡ch vÃ  Trang chi tiáº¿t sÃ¡ch.
- **Báº±ng chá»©ng file & dÃ²ng:**
  - `frontend/src/router/index.js` (DÃ²ng 74)
  - `frontend/src/views/BookDetailView.vue` (DÃ²ng 918)
  - `frontend/src/views/MyLibraryView.vue` (DÃ²ng 221)
- **PhÆ°Æ¡ng phÃ¡p xÃ¡c minh:** ÄÃ£ xÃ¡c nháº­n báº±ng static analysis.
- **CÃ¡ch tÃ¡i hiá»‡n / Kiá»ƒm chá»©ng:** Nháº¥n nÃºt Ä‘á»c sÃ¡ch táº¡i Tá»§ sÃ¡ch cÃ¡ nhÃ¢n hoáº·c Chi tiáº¿t sÃ¡ch, há»‡ thá»‘ng bÃ¡o lá»—i Router / 404.
- **HÆ°á»›ng kháº¯c phá»¥c:** Chuáº©n hÃ³a toÃ n bá»™ tÃªn route thÃ nh `ebook-reader` vÃ  truyá»n Ä‘á»§ `params: { orderId, bookId }`.
- **Test cáº§n bá»• sung:** Router integration test xÃ¡c nháº­n link Ä‘á»c ebook Ä‘Ãºng route.
- **Phá»¥ thuá»™c:** API Check Ownership tráº£ `order_id`.
- **Æ¯á»›c lÆ°á»£ng:** S

---

### Issue `FE-03`: Trang In HÃ³a ÄÆ¡n `InvoicePrintView.vue` Gá»i Route KhÃ´ng Tá»“n Táº¡i VÃ  DÃ¹ng Dá»¯ Liá»‡u Giáº£

- **Priority:** P0
- **Module:** Frontend / Orders
- **MÃ´ táº£:** `InvoicePrintView.vue` gá»i GET `/api/orders` (khÃ´ng tá»“n táº¡i cho Customer), bá»‹ 404 vÃ  rÆ¡i vÃ o catch dÃ¹ng dá»¯ liá»‡u MOCK giáº£ láº­p.
- **Má»©c Ä‘á»™ áº£nh hÆ°á»Ÿng:** HÃ³a Ä‘Æ¡n in ra luÃ´n hiá»ƒn thá»‹ thÃ´ng tin máº«u "Tráº§n Thá»‹ BÃ­ch Ngá»c" cho má»i Ä‘Æ¡n hÃ ng.
- **Báº±ng chá»©ng file & dÃ²ng:**
  - `frontend/src/views/orders/InvoicePrintView.vue` (DÃ²ng 15 & 24â€“40)
- **PhÆ°Æ¡ng phÃ¡p xÃ¡c minh:** ÄÃ£ xÃ¡c nháº­n báº±ng static analysis.
- **CÃ¡ch tÃ¡i hiá»‡n / Kiá»ƒm chá»©ng:** Má»Ÿ trang in hÃ³a Ä‘Æ¡n báº¥t ká»³, dá»¯ liá»‡u luÃ´n lÃ  mÃ£ Ä‘Æ¡n ORD-98765 vÃ  tÃªn Tráº§n Thá»‹ BÃ­ch Ngá»c.
- **HÆ°á»›ng kháº¯c phá»¥c:** Táº¡o endpoint `GET /api/orders/{id}/invoice` hoáº·c sá»­a Frontend gá»i API chi tiáº¿t Ä‘Æ¡n hÃ ng cÃ¡ nhÃ¢n.
- **Test cáº§n bá»• sung:** Component test kiá»ƒm tra thÃ´ng tin hÃ³a Ä‘Æ¡n in ra khá»›p Ä‘Æ¡n hÃ ng tháº­t.
- **Phá»¥ thuá»™c:** Backend Invoice API.
- **Æ¯á»›c lÆ°á»£ng:** S

---

### Issue `FE-04`: MÃ n HÃ¬nh `LoginView.vue` Chá»©a Biáº¿n ChÆ°a Khai BÃ¡o GÃ¢y Lá»—i Runtime JavaScript

- **Priority:** P0
- **Module:** Frontend / Auth
- **MÃ´ táº£:** `LoginView.vue` dÃ¹ng cÃ¡c biáº¿n `googleDialogVisible` vÃ  `customGoogleEmail` nhÆ°ng chÆ°a khai bÃ¡o trong `<script setup>`.
- **Má»©c Ä‘á»™ áº£nh hÆ°á»Ÿng:** MÃ n hÃ¬nh Ä‘Äƒng nháº­p bá»‹ crash `ReferenceError` khi tÆ°Æ¡ng tÃ¡c vá»›i dialog Google.
- **Báº±ng chá»©ng file & dÃ²ng:**
  - `frontend/src/views/auth/LoginView.vue` (DÃ²ng 641, 740, 804, 808, 809)
  - Káº¿t quáº£ ESLint: 8 errors `no-undef`.
- **PhÆ°Æ¡ng phÃ¡p xÃ¡c minh:** ÄÃ£ xÃ¡c nháº­n báº±ng ESLint CLI static analysis.
- **CÃ¡ch tÃ¡i hiá»‡n / Kiá»ƒm chá»©ng:** Má»Ÿ trang ÄÄƒng nháº­p, báº­t dialog Google email, console bÃ¡o lá»—i Uncaught ReferenceError.
- **HÆ°á»›ng kháº¯c phá»¥c:** Khai bÃ¡o `const googleDialogVisible = ref(false)` vÃ  `const customGoogleEmail = ref('')`.
- **Test cáº§n bá»• sung:** ESLint check pass no-undef.
- **Phá»¥ thuá»™c:** KhÃ´ng.
- **Æ¯á»›c lÆ°á»£ng:** S

---

## 2. Danh SÃ¡ch Issue Æ¯u TiÃªn Trung BÃ¬nh (P1 â€” Important / Medium Risk)

---

### Issue `AUTH-02`: Thá»i Háº¡n Token Sanctum KhÃ´ng Háº¿t Háº¡n VÃ  LÆ°u Trá»¯ ChÆ°a An ToÃ n [Tráº¡ng thÃ¡i: VERIFIED]

- **Priority:** P1
- **Module:** Auth / Security
- **MÃ´ táº£:** Token Personal Access Token cá»§a Sanctum Ä‘Æ°á»£c cáº¥p khÃ´ng cÃ³ thá»i gian háº¿t háº¡n (`expiration = null`), lÆ°u trá»¯ táº¡i `localStorage` cá»§a trÃ¬nh duyá»‡t.
- **Má»©c Ä‘á»™ áº£nh hÆ°á»Ÿng:** Náº¿u bá»‹ lá»t token qua XSS, káº» táº¥n cÃ´ng cÃ³ thá»ƒ duy trÃ¬ quyá»n truy cáº­p vÄ©nh viá»…n.
- **Báº±ng chá»©ng file & dÃ²ng:** `backend/config/sanctum.php` (DÃ²ng 43: `'expiration' => null`).
- **PhÆ°Æ¡ng phÃ¡p xÃ¡c minh:** ÄÃ£ xÃ¡c nháº­n báº±ng static analysis.
- **Kiá»ƒm chá»©ng:** Kiá»ƒm tra cáº¥u hÃ¬nh sanctum `expiration` Ä‘á»ƒ `null` vÃ  frontend lÆ°u token trong `localStorage`.
- **HÆ°á»›ng kháº¯c phá»¥c:** Cáº¥u hÃ¬nh thá»i háº¡n háº¿t háº¡n token (`expiration => 1440`), triá»ƒn khai Token Rotation hoáº·c chuyá»ƒn sang Cookie HttpOnly.
- **Test cáº§n bá»• sung:** Feature test token háº¿t háº¡n sau 24h khÃ´ng thá»ƒ gá»i API protected.
- **Phá»¥ thuá»™c:** Auth Store update.
- **Æ¯á»›c lÆ°á»£ng:** M

---

### Issue `SEC-04`: File ÄÃ­nh KÃ¨m Ticket Há»— Trá»£ LÆ°u Public VÃ  Thiáº¿u Kiá»ƒm Tra MIME Type [Tráº¡ng thÃ¡i: VERIFIED]

- **Priority:** P1
- **Module:** Security / Support
- **MÃ´ táº£:** API gá»­i ticket há»— trá»£ `SupportTicketController.php` chá»‰ validate `attachment => file|max:5120` vÃ  lÆ°u vÃ o Ä‘Ä©a public `storage/app/public/tickets`.
- **Má»©c Ä‘á»™ áº£nh hÆ°á»Ÿng:** NgÆ°á»i dÃ¹ng cÃ³ thá»ƒ upload file html/svg/exe Ä‘á»™c háº¡i vÃ  báº¥t ká»³ ai cÅ©ng truy cáº­p Ä‘Æ°á»£c file qua URL public.
- **Báº±ng chá»©ng file & dÃ²ng:** `backend/app/Http/Controllers/Api/SupportTicketController.php` (DÃ²ng 45: `store('tickets', 'public')`).
- **PhÆ°Æ¡ng phÃ¡p xÃ¡c minh:** ÄÃ£ xÃ¡c nháº­n báº±ng static analysis.
- **Kiá»ƒm chá»©ng:** Upload file `.html` Ä‘Ã­nh kÃ¨m ticket, má»Ÿ URL file public tráº£ vá».
- **HÆ°á»›ng kháº¯c phá»¥c:** Bá»• sung `mimes:jpg,jpeg,png,pdf,docx`, lÆ°u vÃ o private storage vÃ  táº¡o endpoint stream file cÃ³ check quyá»n ticket owner / admin.
- **Test cáº§n bá»• sung:** Feature test upload file `.exe` hoáº·c `.php` bá»‹ tá»« chá»‘i 422.
- **Phá»¥ thuá»™c:** Private Storage.
- **Æ¯á»›c lÆ°á»£ng:** S

---

### Issue `PAY-03`: Service Checkout KhÃ´ng Lá»c SÃ¡ch Tráº¡ng ThÃ¡i `published` Khi Táº¯t Global Scope

- **Priority:** P1
- **Module:** Checkout / Catalog
- **MÃ´ táº£:** `CheckoutService.php` gá»i `Book::withoutGlobalScopes()->whereIn('id', $bookIds)` nhÆ°ng khÃ´ng kiá»ƒm tra `status === 'published'`.
- **Má»©c Ä‘á»™ áº£nh hÆ°á»Ÿng:** KhÃ¡ch hÃ ng cÃ³ thá»ƒ mua cÃ¡c sÃ¡ch Ä‘ang á»Ÿ tráº¡ng thÃ¡i `draft` hoáº·c `rejected` báº±ng cÃ¡ch gá»­i trá»±c tiáº¿p `book_id` trong payload checkout.
- **Báº±ng chá»©ng file & dÃ²ng:** `backend/app/Services/CheckoutService.php` (DÃ²ng 31).
- **PhÆ°Æ¡ng phÃ¡p xÃ¡c minh:** ÄÃ£ xÃ¡c nháº­n báº±ng static analysis.
- **Kiá»ƒm chá»©ng:** Gá»­i request `POST /api/checkout` vá»›i `book_id` cá»§a 1 sÃ¡ch draft, Ä‘Æ¡n hÃ ng váº«n Ä‘Æ°á»£c táº¡o thÃ nh cÃ´ng.
- **HÆ°á»›ng kháº¯c phá»¥c:** ThÃªm Ä‘iá»u kiá»‡n `->where('status', 'published')` vÃ o query láº¥y sÃ¡ch checkout.
- **Test cáº§n bá»• sung:** Feature test checkout sÃ¡ch `draft` tráº£ vá» ngoáº¡i lá»‡ lá»—i.
- **Phá»¥ thuá»™c:** KhÃ´ng.
- **Æ¯á»›c lÆ°á»£ng:** S

---

### Issue `INV-02`: Kiá»ƒm Tra Tá»“n Kho DB Fallback Thiáº¿u Pessimistic Lock VÃ  Redis Stock CÃ³ Nguy CÆ¡ Lá»‡ch DB

- **Priority:** P1
- **Module:** Inventory / Concurrency
- **MÃ´ táº£:** Khi Redis khÃ´ng kháº£ dá»¥ng, `CheckoutService.php` kiá»ƒm tra tá»“n kho DB báº±ng query thÆ°á»ng khÃ´ng cÃ³ `lockForUpdate()`. NgoÃ i ra khi Ä‘Æ¡n hÃ ng thanh toÃ¡n tháº¥t báº¡i/há»§y, khÃ´ng cÃ³ cÆ¡ cháº¿ tá»± Ä‘á»™ng release láº¡i tá»“n kho Redis.
- **Má»©c Ä‘á»™ áº£nh hÆ°á»Ÿng:** Xáº£y ra Race Condition gÃ¢y Oversold (bÃ¡n vÆ°á»£t tá»“n kho thá»±c táº¿) khi cÃ³ nhiá»u request mua cÃ¹ng lÃºc trong cháº¿ Ä‘á»™ DB fallback.
- **Báº±ng chá»©ng file & dÃ²ng:** `backend/app/Services/CheckoutService.php` (DÃ²ng 98â€“109).
- **PhÆ°Æ¡ng phÃ¡p xÃ¡c minh:** ÄÃ£ xÃ¡c nháº­n báº±ng static analysis.
- **Kiá»ƒm chá»©ng:** Giáº£ láº­p 10 request checkout Ä‘á»“ng thá»i cho sÃ¡ch cÃ²n 1 sáº£n pháº©m trong DB fallback mode.
- **HÆ°á»›ng kháº¯c phá»¥c:** Bá»• sung `DB::transaction` kÃ¨m `where('id', $id)->lockForUpdate()` cho DB fallback vÃ  viáº¿t listener release Redis stock khi order bá»‹ cancel.
- **Test cáº§n bá»• sung:** Concurrent checkout test vá»›i DB fallback.
- **Phá»¥ thuá»™c:** Redis Connection / DB Locks.
- **Æ¯á»›c lÆ°á»£ng:** M

---

### Issue `FIN-01` & `FIN-02`: Quáº£n LÃ½ Doanh Thu & RÃºt Tiá»n Vendor Thiáº¿u Sá»• CÃ¡i TÃ i ChÃ­nh VÃ  KhÃ³a DÃ²ng RÃºt Tiá»n

- **Priority:** P1
- **Module:** Finance / Vendor
- **MÃ´ táº£:** `FinanceController.php` trá»« trá»±c tiáº¿p `$vendor->balance` ngoÃ i transaction cÃ³ khÃ³a dÃ²ng (`lockForUpdate`), thiáº¿u tráº¡ng thÃ¡i `processing` thá»±c táº¿ vÃ  cáº­p nháº­t `total_withdrawn` quÃ¡ sá»›m. Há»‡ thá»‘ng thiáº¿u sá»• cÃ¡i giao dá»‹ch tÃ i chÃ­nh báº¥t biáº¿n (Double-entry ledger).
- **Má»©c Ä‘á»™ áº£nh hÆ°á»Ÿng:** Nguy cÆ¡ Race Condition khi báº¥m rÃºt tiá»n liÃªn tiáº¿p, sai lá»‡ch sá»• sÃ¡ch tÃ i chÃ­nh khi Ä‘á»‘i soÃ¡t.
- **Báº±ng chá»©ng file & dÃ²ng:** `backend/app/Http/Controllers/Api/Vendor/FinanceController.php` (DÃ²ng 79â€“95).
- **PhÆ°Æ¡ng phÃ¡p xÃ¡c minh:** ÄÃ£ xÃ¡c nháº­n báº±ng static analysis.
- **Kiá»ƒm chá»©ng:** Báº¯n 2 request rÃºt tiá»n Ä‘á»“ng thá»i khi sá»‘ dÆ° vá»«a Ä‘á»§ 1 láº§n rÃºt.
- **HÆ°á»›ng kháº¯c phá»¥c:** XÃ¢y dá»±ng báº£ng `vendor_balance_transactions`, thá»±c hiá»‡n rÃºt tiá»n trong DB Transaction vá»›i `lockForUpdate()` trÃªn record Vendor.
- **Test cáº§n bá»• sung:** Feature test rÃºt tiá»n Ä‘á»“ng thá»i 2 láº§n bá»‹ cháº·n 400 á»Ÿ request thá»© 2.
- **Phá»¥ thuá»™c:** Double-entry Ledger Schema.
- **Æ¯á»›c lÆ°á»£ng:** L

---

### Issue `REV-01`: ÄÃ¡nh GiÃ¡ SÃ¡ch KhÃ´ng YÃªu Cáº§u Mua HÃ ng Thá»±c Táº¿ VÃ  Cho PhÃ©p Má»™t NgÆ°á»i ÄÃ¡nh GiÃ¡ Nhiá»u Láº§n

- **Priority:** P1
- **Module:** Book / Review
- **MÃ´ táº£:** `BookController.php` hÃ m `addReview` cho phÃ©p báº¥t ká»³ user Ä‘Ã£ Ä‘Äƒng nháº­p gá»­i review mÃ  khÃ´ng kiá»ƒm tra Ä‘Ã£ tá»«ng mua sÃ¡ch (Verified Purchase) hay chÆ°a, Ä‘á»“ng thá»i báº£ng `reviews` thiáº¿u Unique Index `(user_id, book_id)`.
- **Má»©c Ä‘á»™ áº£nh hÆ°á»Ÿng:** Spam review giáº£ máº¡o, thao tÃºng Ä‘iá»ƒm Ä‘Ã¡nh giÃ¡ cá»§a sÃ¡ch.
- **Báº±ng chá»©ng file & dÃ²ng:** `backend/app/Http/Controllers/Api/BookController.php` (DÃ²ng 145â€“165).
- **PhÆ°Æ¡ng phÃ¡p xÃ¡c minh:** ÄÃ£ xÃ¡c nháº­n báº±ng static analysis.
- **Kiá»ƒm chá»©ng:** Táº¡o 1 user má»›i, gá»­i 5 review liÃªn tiáº¿p cho cÃ¹ng 1 cuá»‘n sÃ¡ch chÆ°a tá»«ng mua.
- **HÆ°á»›ng kháº¯c phá»¥c:** Kiá»ƒm tra Ä‘Æ¡n hÃ ng `completed` trÆ°á»›c khi cho phÃ©p review vÃ  thÃªm Unique Constraint `(user_id, book_id)` trong migration.
- **Test cáº§n bá»• sung:** Feature test gá»­i review khi chÆ°a mua sÃ¡ch bá»‹ tá»« chá»‘i 403.
- **Phá»¥ thuá»™c:** DB Migration Unique Index.
- **Æ¯á»›c lÆ°á»£ng:** S

---

### Issue `EBOOK-01`: API Ghi ChÃº KhÃ´ng Kiá»ƒm Tra Quyá»n Sá»Ÿ Há»¯u Ebook VÃ  Tiáº¿n Äá»™ Äá»c LÃ  Dá»¯ Liá»‡u Cá»‘ Äá»‹nh

- **Priority:** P1
- **Module:** Ebook / Annotations
- **MÃ´ táº£:** `BookAnnotationController.php` cho phÃ©p táº¡o/xem ghi chÃº cho má»i `book_id` mÃ  khÃ´ng kiá»ƒm tra user Ä‘Ã£ sá»Ÿ há»¯u ebook chÆ°a. MÃ n hÃ¬nh `MyLibraryView.vue` Ä‘ang hardcode tiáº¿n Ä‘á»™ Ä‘á»c `readingProgress = ref(45)`. Link signed stream chÆ°a pháº£i giáº£i phÃ¡p DRM mÃ£ hÃ³a thá»±c sá»±.
- **Má»©c Ä‘á»™ áº£nh hÆ°á»Ÿng:** NgÆ°á»i dÃ¹ng cÃ³ thá»ƒ lá»£i dá»¥ng API Ä‘á»ƒ lÆ°u ghi chÃº cho sÃ¡ch chÆ°a mua; giao diá»‡n hiá»ƒn thá»‹ tiáº¿n Ä‘á»™ Ä‘á»c giáº£ gÃ¢y hiá»ƒu nháº§m.
- **Báº±ng chá»©ng file & dÃ²ng:**
  - `backend/app/Http/Controllers/Api/BookAnnotationController.php` (DÃ²ng 30â€“50)
  - `frontend/src/views/MyLibraryView.vue` (DÃ²ng 170)
- **PhÆ°Æ¡ng phÃ¡p xÃ¡c minh:** ÄÃ£ xÃ¡c nháº­n báº±ng static analysis.
- **Kiá»ƒm chá»©ng:** Gá»i `POST /api/annotations` vá»›i `book_id` chÆ°a mua, API váº«n táº¡o thÃ nh cÃ´ng.
- **HÆ°á»›ng kháº¯c phá»¥c:** Bá»• sung middleware/check ownership trong `BookAnnotationController`, táº¡o cá»™t `reading_progress` trong báº£ng `order_items` hoáº·c `user_ebook_progress`.
- **Test cáº§n bá»• sung:** Feature test táº¡o annotation cho sÃ¡ch chÆ°a mua tráº£ vá» 403.
- **Phá»¥ thuá»™c:** Ebook Progress Schema.
- **Æ¯á»›c lÆ°á»£ng:** M

---

### Issue `NOTIF-01`: Chiáº¿n Dá»‹ch ThÃ´ng BÃ¡o `scheduled_at` Thiáº¿u Scheduler Xá»­ LÃ½ VÃ  Analytics Sá»‘ Liá»‡u Giáº£

- **Priority:** P1
- **Module:** Notification / Admin
- **MÃ´ táº£:** Báº£ng `notification_campaigns` cÃ³ cá»™t `scheduled_at` nhÆ°ng khÃ´ng cÃ³ Laravel Command/Scheduler nÃ o quÃ©t gá»­i tá»± Ä‘á»™ng. MÃ n hÃ¬nh `NotificationAnalyticsView.vue` hiá»ƒn thá»‹ sá»‘ lÆ°á»£t má»Ÿ/click dá»±a trÃªn sá»‘ ngáº«u nhiÃªn mock.
- **Má»©c Ä‘á»™ áº£nh hÆ°á»Ÿng:** Chiáº¿n dá»‹ch Ä‘áº·t lá»‹ch thÃ´ng bÃ¡o khÃ´ng bao giá» Ä‘Æ°á»£c gá»­i; sá»‘ liá»‡u bÃ¡o cÃ¡o hiá»‡u quáº£ thÃ´ng bÃ¡o bá»‹ sai thá»±c táº¿.
- **Báº±ng chá»©ng file & dÃ²ng:**
  - `backend/app/Http/Controllers/Api/Admin/NotificationCampaignController.php` (DÃ²ng 40â€“80)
  - `frontend/src/views/admin/NotificationAnalyticsView.vue` (DÃ²ng 60)
- **PhÆ°Æ¡ng phÃ¡p xÃ¡c minh:** ÄÃ£ xÃ¡c nháº­n báº±ng static analysis.
- **Kiá»ƒm chá»©ng:** Táº¡o chiáº¿n dá»‹ch thÃ´ng bÃ¡o Ä‘áº·t lá»‹ch sau 5 phÃºt, sau 10 phÃºt thÃ´ng bÃ¡o váº«n khÃ´ng Ä‘Æ°á»£c gá»­i.
- **HÆ°á»›ng kháº¯c phá»¥c:** Viáº¿t Console Command `app:send-scheduled-notifications` cháº¡y hÃ ng phÃºt trong `routes/console.php` vÃ  táº¡o báº£ng tracking lÆ°á»£t click thá»±c táº¿.
- **Test cáº§n bá»• sung:** Console Command test kiá»ƒm tra cÃ¡c campaign Ä‘áº¿n háº¡n Ä‘Æ°á»£c gá»­i Ä‘Ãºng Ä‘á»‘i tÆ°á»£ng.
- **Phá»¥ thuá»™c:** Laravel Scheduler Setup.
- **Æ¯á»›c lÆ°á»£ng:** M

---

### Issue `ARCH-01`: PhÃ¢n Quyá»n PhÃ¢n TÃ¡n Thiáº¿u Policy VÃ  Scope Bá»‹ Bá» QuÃªn

- **Priority:** P1
- **Module:** Architecture / Security
- **MÃ´ táº£:** CÃ¡c lá»›p `BookVisibilityScope` vÃ  `OrderVisibilityScope` Ä‘Æ°á»£c khai bÃ¡o nhÆ°ng khÃ´ng Ä‘Æ°á»£c Ã¡p dá»¥ng Ä‘á»“ng nháº¥t. PhÃ¢n quyá»n dá»¯ liá»‡u (Authorization) Ä‘ang viáº¿t ráº£i rÃ¡c báº±ng `if ($item->user_id !== auth()->id())` thá»§ cÃ´ng trong cÃ¡c Controller thay vÃ¬ dÃ¹ng Laravel Policy/Gate.
- **Má»©c Ä‘á»™ áº£nh hÆ°á»Ÿng:** TÄƒng nguy cÆ¡ bá» sÃ³t kiá»ƒm tra phÃ¢n quyá»n (BOLA / IDOR vulnerability) khi má»Ÿ rá»™ng API má»›i.
- **Báº±ng chá»©ng file & dÃ²ng:** `backend/app/Scopes/BookVisibilityScope.php` & `backend/app/Http/Controllers/Api/OrderController.php`.
- **PhÆ°Æ¡ng phÃ¡p xÃ¡c minh:** ÄÃ£ xÃ¡c nháº­n báº±ng static analysis.
- **Kiá»ƒm chá»©ng:** Review mÃ£ nguá»“n phÃ¡t hiá»‡n nhiá»u controller tá»± viáº¿t `if-else` check ID thay vÃ¬ `$this->authorize()`.
- **HÆ°á»›ng kháº¯c phá»¥c:** Refactor phÃ¢n quyá»n vá» cÃ¡c lá»›p `OrderPolicy`, `BookPolicy`, `TicketPolicy` chuáº©n Laravel.
- **Test cáº§n bá»• sung:** Policy Unit tests cho táº¥t cáº£ cÃ¡c role customer/vendor/admin.
- **Phá»¥ thuá»™c:** Laravel Policies scaffold.
- **Æ¯á»›c lÆ°á»£ng:** L

---

### Issue `FE-05`: CÃ¡c MÃ n HÃ¬nh Quáº£n Trá»‹ Vendor Gá»i API Catalog Public

- **Priority:** P1
- **Module:** Frontend / Vendor
- **MÃ´ táº£:** CÃ¡c view quáº£n lÃ½ Vendor (`BookChaptersView`, `DrmSettingsView`, `InventoryAuditView`, `LiveEditorView`, `MultiDevicePreviewView`, `StockTransferView`) gá»i GET `/api/books` thay vÃ¬ `/api/vendor/books`.
- **Má»©c Ä‘á»™ áº£nh hÆ°á»Ÿng:** SÃ¡ch á»Ÿ tráº¡ng thÃ¡i `draft`/`pending` cá»§a Vendor khÃ´ng xuáº¥t hiá»‡n trong cÃ¡c bá»™ chá»n quáº£n trá»‹.
- **Báº±ng chá»©ng file & dÃ²ng:**
  - `frontend/src/views/vendor/BookChaptersView.vue` (DÃ²ng 25)
  - `frontend/src/views/vendor/DrmSettingsView.vue` (DÃ²ng 44)
  - `frontend/src/views/vendor/InventoryAuditView.vue` (DÃ²ng 41)
  - `frontend/src/views/vendor/StockTransferView.vue` (DÃ²ng 41)
- **PhÆ°Æ¡ng phÃ¡p xÃ¡c minh:** ÄÃ£ xÃ¡c nháº­n báº±ng static analysis.
- **Kiá»ƒm chá»©ng:** Táº¡o 1 sÃ¡ch draft, truy cáº­p mÃ n hÃ¬nh thÃªm chÆ°Æ¡ng sÃ¡ch cá»§a Vendor, sÃ¡ch draft khÃ´ng hiá»ƒn thá»‹ trong danh sÃ¡ch chá»n.
- **HÆ°á»›ng kháº¯c phá»¥c:** Thay Ä‘á»•i toÃ n bá»™ cÃ¡c vá»‹ trÃ­ trÃªn gá»i API `/api/vendor/books`.
- **Test cáº§n bá»• sung:** Component test kiá»ƒm tra danh sÃ¡ch chá»n sÃ¡ch cá»§a Vendor hiá»ƒn thá»‹ Ä‘á»§ sÃ¡ch draft.
- **Phá»¥ thuá»™c:** KhÃ´ng.
- **Æ¯á»›c lÆ°á»£ng:** S

---

### Issue `TEST-01`: Thiáº¿u Bá»™ Kiá»ƒm Thá»­ Tá»± Äá»™ng (Unit / E2E Tests) Cho Frontend

- **Priority:** P1
- **Module:** Testing / Frontend
- **MÃ´ táº£:** Frontend chÆ°a tÃ­ch há»£p Vitest, Vue Test Utils hoáº·c Playwright vÃ  khÃ´ng cÃ³ file test nÃ o trong `frontend/src`.
- **Má»©c Ä‘á»™ áº£nh hÆ°á»Ÿng:** Nguy cÆ¡ phÃ¡t sinh lá»—i cÅ© (regression) khi nÃ¢ng cáº¥p mÃ£ nguá»“n.
- **Báº±ng chá»©ng file & dÃ²ng:** `frontend/package.json` (Thiáº¿u script & devDependencies test).
- **PhÆ°Æ¡ng phÃ¡p xÃ¡c minh:** ÄÃ£ xÃ¡c nháº­n báº±ng static analysis.
- **Kiá»ƒm chá»©ng:** Cháº¡y `npm test` bÃ¡o lá»—i script not found.
- **HÆ°á»›ng kháº¯c phá»¥c:** TÃ­ch há»£p Vitest + Vue Test Utils Ä‘á»ƒ viáº¿t Unit Test cho Stores vÃ  Services.
- **Test cáº§n bá»• sung:** Test coverage tá»‘i thiá»ƒu 60% cho Pinia Stores.
- **Phá»¥ thuá»™c:** Vitest setup.
- **Æ¯á»›c lÆ°á»£ng:** M

---

### Issue `PERF-01` & `PERF-02`: Chunk Build `EbookReaderView` QuÃ¡ Lá»›n VÃ  Lá»—i N+1 Query Táº¡i `BookResource`

- **Priority:** P1
- **Module:** Performance / Optimization
- **MÃ´ táº£:** Chunk build `EbookReaderView` dung lÆ°á»£ng 2.53 MB. NgoÃ i ra `BookResource.php` truy váº¥n `wishlists_count` vÃ  `chapters` trá»±c tiáº¿p trÃªn tá»«ng record mÃ  khÃ´ng eager load `withCount('wishlists')`, gÃ¢y lá»—i N+1 Query.
- **Má»©c Ä‘á»™ áº£nh hÆ°á»Ÿng:** LÃ m cháº­m thá»i gian táº£i trang vÃ  gÃ¢y táº£i lá»›n cho MySQL khi láº¥y danh sÃ¡ch sÃ¡ch catalog.
- **Báº±ng chá»©ng file & dÃ²ng:**
  - Build output Vite.
  - `backend/app/Http/Resources/BookResource.php` (DÃ²ng 25â€“35)
- **PhÆ°Æ¡ng phÃ¡p xÃ¡c minh:** ÄÃ£ xÃ¡c nháº­n báº±ng static analysis & build CLI.
- **Kiá»ƒm chá»©ng:** Cháº¡y Laravel Telescope / Query Log khi gá»i API GET `/api/books`, sá»‘ lÆ°á»£ng query tÄƒng tuyáº¿n tÃ­nh theo sá»‘ sáº£n pháº©m (N+1).
- **HÆ°á»›ng kháº¯c phá»¥c:** TÃ¡ch `pdfjs` thÃ nh manualChunk riÃªng trong `vite.config.js` vÃ  bá»• sung `withCount('wishlists')` trong `BookController`.
- **Test cáº§n bá»• sung:** Performance query count test (assertQueryCount < 10).
- **Phá»¥ thuá»™c:** Vite Config & Eloquent Query Refactor.
- **Æ¯á»›c lÆ°á»£ng:** M

---

## 3. Danh SÃ¡ch Issue Æ¯u TiÃªn Tháº¥p / Cáº£i Tiáº¿n Váº­n HÃ nh (P2 â€” Low / Enhancement)

---

### Issue `OPS-01`: Thiáº¿u Tá»± Äá»™ng HÃ³a Quy TrÃ¬nh CI/CD Pipeline & Kiá»ƒm Tra Tá»± Äá»™ng Cho Pull Request

- **Priority:** P2
- **Module:** Operations / CI-CD
- **MÃ´ táº£:** Repository hoÃ n toÃ n thiáº¿u cáº¥u hÃ¬nh GitHub Actions workflows (`.github/workflows`) Ä‘á»ƒ tá»± Ä‘á»™ng cháº¡y test, build, lint khi táº¡o Pull Request hoáº·c push code.
- **Má»©c Ä‘á»™ áº£nh hÆ°á»Ÿng:** Dá»… lá»t mÃ£ lá»—i hoáº·c khÃ´ng Ä‘áº¡t chuáº©n style code vÃ o nhÃ¡nh chÃ­nh.
- **Báº±ng chá»©ng file & dÃ²ng:** ThÆ° má»¥c gá»‘c khÃ´ng cÃ³ `.github/workflows`.
- **PhÆ°Æ¡ng phÃ¡p xÃ¡c minh:** ÄÃ£ xÃ¡c nháº­n báº±ng static analysis.
- **Kiá»ƒm chá»©ng:** Kiá»ƒm tra cáº¥u hÃ¬nh repo khÃ´ng cÃ³ file CI workflow.
- **HÆ°á»›ng kháº¯c phá»¥c:** Táº¡o file workflow `.github/workflows/ci.yml` tá»± Ä‘á»™ng cháº¡y PHPUnit, Pint, ESLint, Oxlint vÃ  Vite Build.
- **Test cáº§n bá»• sung:** Pipeline execution green badge.
- **Phá»¥ thuá»™c:** GitHub Actions.
- **Æ¯á»›c lÆ°á»£ng:** S

---

### Issue `ENV-01`: Cáº£nh BÃ¡o Náº¡p TrÃ¹ng Module OpenSSL Khi Cháº¡y PHP CLI

- **Priority:** P2
- **Module:** Environment / PHP
- **MÃ´ táº£:** Lá»‡nh PHP CLI phÃ¡t cáº£nh bÃ¡o: `PHP Warning: Module "openssl" is already loaded in Unknown on line 0`.
- **Má»©c Ä‘á»™ áº£nh hÆ°á»Ÿng:** GÃ¢y rÃ¡c log khi thá»±c thi lá»‡nh CLI.
- **Báº±ng chá»©ng file & dÃ²ng:** Output terminal khi cháº¡y `php -v` hoáº·c `php artisan test`.
- **PhÆ°Æ¡ng phÃ¡p xÃ¡c minh:** ÄÃ£ xÃ¡c nháº­n báº±ng káº¿t quáº£ thá»±c thi CLI.
- **Kiá»ƒm chá»©ng:** Cháº¡y `php -v` tháº¥y hiá»ƒn thá»‹ PHP Warning.
- **HÆ°á»›ng kháº¯c phá»¥c:** Loáº¡i bá» dÃ²ng khai bÃ¡o `extension=openssl` bá»‹ láº·p trong file `php.ini`.
- **Test cáº§n bá»• sung:** Run `php -v` khÃ´ng cÃ²n Warning.
- **Phá»¥ thuá»™c:** PHP Environment Config.
- **Æ¯á»›c lÆ°á»£ng:** S

---

### Issue `DOC-01`: File `README.md` VÃ  `.env.example` ÄÃ£ Lá»—i Thá»i, Thiáº¿u Cáº¥u HÃ¬nh Production

- **Priority:** P2
- **Module:** Documentation
- **MÃ´ táº£:** `.env.example` cá»§a Backend váº«n chá»©a cáº¥u hÃ¬nh máº·c Ä‘á»‹nh `APP_NAME=Laravel`, `DB_CONNECTION=sqlite`, `APP_TIMEZONE=UTC`, thiáº¿u thÃ´ng sá»‘ cáº¥u hÃ¬nh VNPAY, Redis, Sanctum stateful domains vÃ  Frontend URL cho mÃ´i trÆ°á»ng tháº­t. README chÆ°a cáº­p nháº­t hÆ°á»›ng dáº«n cÃ i Ä‘áº·t má»›i.
- **Má»©c Ä‘á»™ áº£nh hÆ°á»Ÿng:** GÃ¢y khÃ³ khÄƒn vÃ  dá»… sai sÃ³t khi setup mÃ´i trÆ°á»ng má»›i hoáº·c deploy production.
- **Báº±ng chá»©ng file & dÃ²ng:** `backend/.env.example` (DÃ²ng 1â€“30).
- **PhÆ°Æ¡ng phÃ¡p xÃ¡c minh:** ÄÃ£ xÃ¡c nháº­n báº±ng static analysis.
- **Kiá»ƒm chá»©ng:** Äá»c file `backend/.env.example` tháº¥y thiáº¿u cÃ¡c key `VNPAY_*`, `SANCTUM_STATEFUL_DOMAINS`.
- **HÆ°á»›ng kháº¯c phá»¥c:** Bá»• sung Ä‘áº§y Ä‘á»§ cÃ¡c biáº¿n mÃ´i trÆ°á»ng máº«u vÃ o `.env.example` vÃ  cáº­p nháº­t README.md.
- **Test cáº§n bá»• sung:** N/A.
- **Phá»¥ thuá»™c:** KhÃ´ng.
- **Æ¯á»›c lÆ°á»£ng:** S
