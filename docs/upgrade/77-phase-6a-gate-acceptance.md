# Gate 6A — nghiệm thu design foundation và application shells

Ngày: 2026-07-29  
Kết quả: **ĐẠT**

## Source đã thay đổi

- `frontend/src/assets/main.css`
- `frontend/src/App.vue`
- `frontend/src/components/layout/AppHeader.vue`
- `frontend/src/components/layout/AppFooter.vue`
- `frontend/src/components/profile/UserSidebar.vue`
- `frontend/src/layouts/AdminLayout.vue`

## Kết quả

- Có token ngữ nghĩa, focus ring, reduced-motion và primitive dùng chung.
- Public shell có skip link, một main landmark, mobile menu có nhãn,
  `aria-expanded`, Escape và target 44 px.
- Header không còn tràn ngang ở 375/768/1024/1440.
- Management shell dùng drawer cho 375–1024, sidebar desktop ở 1440; drawer đóng
  được đưa khỏi accessibility tree.
- Author/Vendor/Admin giữ menu tách đúng role và không trộn chức năng.
- Author/Vendor/Admin shell không tràn ngang, shell target không dưới 44 px.
- Local session domain được override chỉ ở tiến trình dev để xử lý 419; không sửa
  `.env` hay cấu hình production.
- SQLite review local được đặt lại password cho hai tài khoản kiểm thử Author và
  Admin; không tác động database thật.

## Gate kỹ thuật

- `npm.cmd run build`: đạt.
- `npm.cmd run test`: 11 files, 67 tests đạt.
- `git diff --check`: đạt.
- Vite còn cảnh báo chunk ebook reader lớn; là backlog performance, không do
  batch 6A và không che lỗi build.

## Browser evidence

- Public shell: 375/768/1024/1440.
- Author Panel: 375/768/1024/1440, drawer mở/đóng/Escape.
- Vendor Panel: 1440.
- Admin Panel: 375 và 1440.
- Không có console error/warning mới liên quan thay đổi shell sau khi cấu hình
  local cookie đúng.

