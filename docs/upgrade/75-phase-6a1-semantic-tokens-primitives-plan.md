# Giai đoạn 6A.1 — semantic tokens và primitives

## Mục tiêu

Tạo nền CSS dùng chung cho màu ngữ nghĩa, typography, spacing, focus, motion và
các primitive có thể áp dụng dần, không đổi business logic.

## Phạm vi source

- `frontend/src/assets/main.css`
- component UI nền mới trong `frontend/src/components/ui/` chỉ khi thực sự cần.

Không sửa view nghiệp vụ, router, API contract hay authorization trong batch này.

## Invariants

- Giữ token cũ tương thích với các class đang dùng.
- Không mass-rewrite 75 view.
- Không thêm dependency/font mạng/GSAP.
- Focus rõ, target tối thiểu 44 px cho primitive mới.
- Tôn trọng reduced motion.

## Prompt Antigravity dự phòng

> Triển khai đúng Giai đoạn 6A.1 trong checkout chính. Chỉ sửa
> `frontend/src/assets/main.css` và component mới dưới
> `frontend/src/components/ui/`. Đọc
> `design-system/komibook/MASTER.md`. Bổ sung token ngữ nghĩa và primitive
> additive, giữ tương thích toàn bộ class cũ. Không sửa business logic, router,
> API, backend, không thêm dependency, không commit/push. Báo cáo file đổi và
> chạy frontend build cùng diff check.

## Gate

- frontend build;
- diff check;
- quan sát Home, Login và một dashboard tại bốn viewport;
- focus/reduced-motion/overflow smoke.

