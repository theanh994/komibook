# Batch 3C.4 — Eight-Digit OTP Contract and Input

## Mục tiêu

Nâng luồng xác thực số điện thoại từ OTP 6 chữ số lên 8 chữ số end-to-end và dùng một giao diện tám ô nhập dùng chung cho Login/Register.

## Contract

- Backend sinh OTP ngẫu nhiên mật mã trong khoảng `10000000..99999999`.
- Verify chỉ nhận chuỗi đúng tám chữ số.
- Rate limit, TTL, hash-at-rest, attempt lockout, one-time use và production fail-closed giữ nguyên.
- Frontend không xác thực khi chưa đủ tám chữ số.
- Component tám ô hỗ trợ nhập từng số, paste toàn bộ mã, Backspace, phím mũi tên, numeric keyboard và `one-time-code`.

## Files

- `backend/app/Http/Controllers/Api/PhoneAuthController.php`
- `backend/app/Http/Controllers/Api/Admin/VendorApprovalController.php`
- `backend/tests/Feature/SecurityHardeningTest.php`
- `backend/tests/Feature/AuthorDrmInventoryTest.php`
- `frontend/src/components/auth/OtpCodeInput.vue` — new
- `frontend/src/views/auth/LoginView.vue`
- `frontend/src/views/auth/RegisterView.vue`
- `frontend/src/views/auth/AccountVerificationView.vue`
- `frontend/src/__tests__/phase3_otp_eight_digit.spec.js` — new

## Gates

Backend:

- `php vendor/bin/phpunit tests/Feature/SecurityHardeningTest.php tests/Feature/AuthorDrmInventoryTest.php --filter=otp`
- `php vendor/bin/pint --test app/Http/Controllers/Api/PhoneAuthController.php tests/Feature/SecurityHardeningTest.php tests/Feature/AuthorDrmInventoryTest.php`

Frontend:

- `npm.cmd test -- --run src/__tests__/phase3_otp_eight_digit.spec.js src/__tests__/phase3_public_journey_truth.spec.js`
- `npm.cmd run build`
- Oxlint và ESLint trên component, Login/Register và test mới.

Repository:

- `git diff --check`

Không commit/push và không tác động production/database ngoài test environment.
