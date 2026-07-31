# Nghiệm thu batch 7F.1 — Bảo toàn catalog và dữ liệu chuyển tiếp

Ngày: 29/07/2026  
Kết quả: **accepted-local, import held for conflict review**

## Đã hoàn thành

- Bổ sung service và command `catalog:preservation-manifest` chỉ đọc.
- ISBN được chuẩn hóa bỏ dấu phân cách; slug được chuẩn hóa chữ thường trước khi dò trùng.
- Mỗi sách có stable key, fingerprint cho các trường người dùng nhập (title, mô tả, giá, tồn, bìa), tổng tồn kho và số liên kết legacy.
- Manifest phát hiện thiếu/trùng stable key, chênh `books.stock` với tổng `warehouse_stocks.quantity` và stock nối chéo tenant.
- Summary bảo toàn Author legacy, quan hệ sách, listing sách cũ, kho và stock mà không lộ địa chỉ/PII, không tự map Author thành Quản lý kho.
- Không triển khai upsert vì chưa có tập dữ liệu import cụ thể; mọi lần import sau phải đi qua manifest và so fingerprint trước.

## Kết quả dry-run database dev

- `mode`: `dry_run`; `writes_performed`: `false`.
- 55 sách: 12 `preserve_existing`, 43 `conflict_review`.
- 43 xung đột đều là `stock_total_mismatch`; quyết định tự động: `stop_for_conflict_review_before_import`.
- 3 Author legacy, 0 book-author relation, 0 used-book listing, 3 kho, 3 dòng warehouse stock.
- Không sửa tồn, không ghi đè sách/bìa/giá/mô tả và không thêm dữ liệu giả để che xung đột.

## Gate

- 11 backend test, 74 assertion đạt, gồm fingerprint trước/sau và command JSON.
- PHP lint và Pint đạt.
- Command chạy thành công trên database dev hiện tại; output không có địa chỉ, giấy tờ, ngân hàng hay credential.

Việc import được giữ lại đúng theo điểm dừng đã duyệt trong kế hoạch 7F.1. Cloudflare/origin 8080 không đổi; không commit, push hoặc deploy.

