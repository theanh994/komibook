# Giai đoạn 6E.1 — Nghiệm thu cục bộ

Ngày nghiệm thu: 2026-07-29

## Kết quả

- Login, Register, Forgot Password và Reset Password đã kiểm tra 375/768/1024/1440; không overflow.
- Label email Login được nối đúng với input; show/hide password và các link chính có vùng chạm 44px.
- Forgot Password dùng `autocomplete="email"`; Reset Password giữ cảnh báo token và password strength.
- `/author/verify` đã chuyển hoàn toàn từ phone/SMS sang OTP email Tác giả 8 chữ số.
- Browser với tài khoản seed cục bộ xác nhận trang hiển thị email che `ve***@gmail.com`, CTA `Gửi mã OTP qua email`; không gửi OTP thật.
- Google bypass chỉ hiển thị khi response backend có `data.bypassed=true`; test frontend và backend đều bao phủ.
- Author Register không còn CTA xác minh số điện thoại và không chuyển sang bước phone sau khi gửi hồ sơ.
- Vendor Register có label thật cho trường pháp lý/ngân hàng, loading state semantic và checkbox/submit target phù hợp.
- Browser đã quan sát Author Register, Author Verify và Vendor Register với tài khoản seed cục bộ ở 375/768/1024/1440; không gửi hồ sơ, file hoặc dữ liệu ngân hàng.

## Gate

- Frontend: 11 file test, 68 test đạt.
- Frontend build: đạt.
- `Phase5AuthorEmailOtpTest`: 3 test, 14 assertion đạt.
- `git diff --check`: đạt.

Đây là nghiệm thu local. Không có email/SMS production, hồ sơ thật, commit, push hoặc deploy.
