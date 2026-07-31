# Gate 6G — nghiệm thu khu vực Vendor

Ngày nghiệm thu cục bộ: 2026-07-29

**Đạt Gate 6G cục bộ.**

- Toàn bộ 20 route Vendor trong page ledger đã được kiểm tra trực tiếp ở
  `375 / 768 / 1024 / 1440`.
- Management shell không còn tràn ngang khi xuất hiện thanh cuộn dọc; các màn
  Publishing, DRM, Order Detail, Transfer Print và Flash Sale có trạng thái,
  nhãn và vùng tương tác phù hợp.
- Luồng sách/xuất bản: `27` tests, `117` assertions đạt.
- Luồng kho/checkout/trả hàng/DRM: `68` tests, `306` assertions đạt.
- Luồng payout/promotion: `13` tests, `79` assertions đạt.
- Frontend `68/68`, production build và `git diff --check` đều đạt.
- Browser smoke chỉ đọc: không tạo/sửa/xóa sách, chương, kho, tồn kho, đơn hàng,
  hoàn trả, payout, coupon hay Flash Sale.
- Đây là nghiệm thu cục bộ; không commit, push hoặc deploy.

