# Giai đoạn 6E.1 — Xác thực và onboarding entry

## Mục tiêu

- Nghiệm thu Login, Register, Forgot/Reset Password ở bốn viewport.
- Đăng ký Tác giả dùng OTP email 8 chữ số; tài khoản Google đã xác minh được backend cho phép bỏ qua.
- Loại bỏ UI xác minh số điện thoại khỏi luồng đăng ký Tác giả.
- Chuẩn hóa trạng thái, label, focus, vùng chạm và phản hồi form của entry Tác giả/Nhà bán.

## Phạm vi

- `frontend/src/views/auth/LoginView.vue`
- `frontend/src/views/auth/RegisterView.vue`
- `frontend/src/views/auth/ForgotPasswordView.vue`
- `frontend/src/views/auth/ResetPasswordView.vue`
- `frontend/src/views/auth/AccountVerificationView.vue`
- `frontend/src/views/auth/AuthorRegisterView.vue`
- `frontend/src/views/auth/VendorRegisterView.vue`
- `frontend/src/__tests__/phase3_otp_eight_digit.spec.js` (cập nhật regression test cũ từ phone OTP sang email OTP)

## Invariants

- Không đổi endpoint, token contract, route guard hoặc cơ chế Google GIS.
- Không gọi SMS, dịch vụ trả phí hoặc email production trong browser gate.
- OTP tác giả dùng endpoint `/api/author/email-verification/*`, đúng 8 chữ số.
- Google bypass chỉ dựa trên kết quả backend, không tự suy đoán ở frontend.
- Không gửi hồ sơ thật, tài liệu thật hoặc thông tin ngân hàng thật khi nghiệm thu.
- Không commit, push hoặc deploy.

## Prompt Antigravity dự phòng

> Thực hiện 6E.1 trong checkout chính. Chỉ sửa bảy auth views đã liệt kê. Giữ
> endpoint/guard/Google GIS. Chuyển author verify khỏi phone/SMS sang author
> email OTP 8 chữ số, Google bypass chỉ theo backend. Chuẩn hóa responsive,
> labels, focus, 44px targets, loading/error/success; không gửi dữ liệu thật.
> Kiểm tra 375/768/1024/1440, frontend tests/build/diff và backend
> Phase5AuthorEmailOtpTest. Không commit/push.

## Gate

- Login/Register/Forgot/Reset không overflow, keyboard/form semantics đạt.
- Author Register/Verify chỉ nói và gọi email OTP; không còn SMS/phone flow.
- Google bypass UI phản ánh đúng status backend.
- Vendor entry giữ hồ sơ pháp lý độc lập và không lộ dữ liệu.
- Frontend tests/build, `Phase5AuthorEmailOtpTest` và diff check đạt.
