# KomiBook Phase 5B — Author email OTP và Google verified bypass

Ngày: 2026-07-29

## Mục tiêu

- Xác minh đăng ký tác giả bằng OTP email 8 chữ số, không còn lấy phone OTP làm gate.
- Tài khoản Google chỉ bypass khi backend đã lưu `google_id` và `email_verified_at`.
- OTP có TTL, rate limit, attempt limit và không ghi OTP plaintext.
- Ghi rõ phương thức xác minh vào hồ sơ tác giả.

## Prompt giao Antigravity

> Thực hiện 5B theo ADR 62. Tạo flow OTP email riêng cho user đang đăng nhập muốn trở thành tác giả; không dùng email do client tùy ý chỉ định. Google bypass chỉ dựa trên identity đã được backend xác minh. Bỏ phone verification khỏi author approval gate, cập nhật UI và tests. Không gửi email thật khi test, không đổi credential/provider, không commit/push/deploy.

Antigravity hết quota; Codex trực tiếp thực hiện.

## Gate

- Tests OTP success/failure/expiry/attempt limit và Google bypass.
- Regression author onboarding.
- Frontend unit/build.
- Không gọi email provider thật.
