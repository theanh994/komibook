# Biên bản triển khai cục bộ luồng Bản in và chứng từ kho

Ngày: 2026-08-01
Phạm vi nghiệm thu: source và dữ liệu kiểm thử cục bộ tại checkout chính
Không thuộc phạm vi: commit, push, deploy, database production, credential và dịch vụ bên ngoài

## 1. Kết quả triển khai

### Sách và Bản in

- Thêm `print_edition`, mặc định là 1; tên gốc không bị sửa.
- API cung cấp `display_title`: bản in đầu giữ nguyên tên, bản in từ lần 2 dùng hậu tố `— Tái bản lần N`.
- Sách mới được công khai với tồn 0, truy cập được bằng URL trực tiếp và tìm kiếm, nhưng không xuất hiện ở dải duyệt/danh mục và chưa thể mua.
- Thẻ sách, trang chi tiết, trang chủ và giỏ hàng ưu tiên tên hiển thị và trạng thái mua do API trả về.

### Kho tổng và chuỗi cung ứng

- Nhà bán có một `primary_warehouse_id`; API và giao diện cho phép chọn kho tổng hợp lệ.
- Có lệnh `warehouse:backfill-primary`, mặc định chỉ báo cáo và chỉ ghi dữ liệu khi chủ động dùng `--apply`.
- Form thêm sách lấy create-scope dùng chung với backend, cố định kho tổng và chỉ hiện các vai trò cung ứng thực sự cần theo mô hình Nhà bán.
- Mô hình tự cung tự cấp suy ra tổ chức của chính Nhà bán; dữ liệu `demo_accepted` giữ nguyên ý nghĩa mô phỏng, không được biến thành quan hệ đã xác minh.

### Thêm sách và phiếu nhập

- Một transaction tạo sách tồn 0, gắn chuỗi cung ứng và tạo phiếu nhập nháp vào kho tổng.
- Số lượng ban đầu chỉ được điền vào phiếu; tồn tăng duy nhất khi phiếu được ghi sổ.
- Retry bằng operation key không sinh sách hoặc phiếu trùng.
- Trang phiếu hỗ trợ nhập bổ sung sách/bản in đã có mà không tạo Book mới; phiếu nháp số lượng 0 được phép lưu nhưng không được ghi sổ.

### Phiếu xuất từ đơn hàng

- Khi commit giữ chỗ, hệ thống tạo và ghi sổ phiếu xuất theo từng `order + vendor + source warehouse` từ allocation thực tế.
- Ghi sổ phiếu là điểm giảm tồn duy nhất; kiểm thử xác nhận không giảm tồn hai lần.
- Phiếu liên kết đơn hàng và lưu lý do gợi ý kho bằng dữ liệu tỉnh/huyện hoặc phương án hạn chế tách kiện; không dùng dịch vụ bản đồ trả phí.

### Trang phiếu và điều chuyển

- Bổ sung bộ lọc, nhãn loại/trạng thái tiếng Việt, thông tin đơn hàng, Bản in, nguồn phát sinh và thao tác sửa phiếu nháp.
- Khi Nhà bán chỉ có một kho hoạt động, UI ẩn thao tác điều chuyển và API cũng chặn truy cập trực tiếp bằng lỗi nghiệp vụ rõ ràng.
- Có trang in A4 và endpoint PDF/XLSX dùng cùng dữ liệu chứng từ; Excel có bảo vệ formula injection.

## 2. Cổng nghiệm thu đã đạt

| Cổng | Kết quả |
|---|---|
| Migration fresh, down/up trên SQLite tạm | Đạt |
| Backend mục tiêu: luồng sách/kho/phiếu | Đạt |
| Backend full regression | 366 passed, 2068 assertions |
| Frontend full regression | 28 files, 126 passed |
| ESLint | Đạt, không lỗi |
| Oxlint | 0 warnings, 0 errors trên 136 files |
| Laravel Pint trên phạm vi thay đổi | Đạt |
| Composer validate | `composer.json` hợp lệ |
| Composer audit | Không có cảnh báo bảo mật trong lock file |
| Frontend production build | Đạt; còn cảnh báo chunk lớn đã có tính chất tối ưu hiệu năng |
| HTML in phiếu có tiếng Việt | Đạt qua feature test |
| PDF/XLSX runtime | Đạt; Dompdf và PhpSpreadsheet nạp được, feature test 3 passed/60 assertions |

## 3. Bổ sung nghiệm thu runtime ngày 2026-08-01

- Database MySQL cục bộ tại `127.0.0.1` đã chạy migration `2026_08_01_190000...` ở batch 19; lỗi thiếu `primary_warehouse_id` và `print_edition` không còn.
- Vendor Kim Đồng cục bộ được gán Kho Yên Nghĩa (warehouse 4) làm kho tổng sau khi xác minh đây là kho hoạt động duy nhất của vendor.
- 19/19 sách vật lý của vendor Kim Đồng đã có bản ghi tồn tại Kho Yên Nghĩa; không cần di chuyển hoặc tạo lại tồn kho.
- `composer dump-autoload --optimize` hoàn tất sau khoảng 291 giây, sinh 44.968 lớp. Dompdf và PhpSpreadsheet đều vượt qua kiểm tra `class_exists`.
- Endpoint HTML/PDF/XLSX được kiểm tra bằng feature test: 3 bài kiểm thử, 60 assertions, đều đạt.
- Backend development cục bộ được khởi động lại tại `127.0.0.1:8000`; `/api/books` trả HTTP 200 sau khi xóa cache.

### Tinh chỉnh sau kiểm tra giao diện

- Trường Bản in dùng danh sách có tên rõ ràng từ `Bản in đầu` đến `Bản in lần thứ mười`; lựa chọn cuối cho phép nhập số từ 11 trở lên.
- Nút lưu khi sửa sách chỉ cập nhật sách và quay về danh sách, không còn tự chuyển sang luồng “Thông tin xuất bản và cung ứng” cũ. API đồng bộ đúng `status`, `publishing_status` và `published_at`.
- Tập 19 trong dữ liệu MySQL cục bộ được đưa khỏi trạng thái nháp mắc kẹt: sách đã xuất bản, giữ tồn 0, có đủ ba vai trò chuỗi cung ứng và có phiếu nhập nháp tại Kho Yên Nghĩa.
- Cấu hình media của Vite cục bộ đã chuyển từ cổng release cũ `127.0.0.1:8080` sang backend phát triển `127.0.0.1:8000`. Sáu ảnh bìa tập 16–21 đều trả HTTP 200 qua `localhost:5173` sau khi khởi động lại Vite.
- Danh sách sách Nhà bán ưu tiên `display_title`; trang chủ có trạng thái dự phòng nếu ảnh không tải được.
- Màn phiếu có bước xem xét trong hộp thoại, tóm tắt kho/số dòng/tổng số lượng, danh sách sách và tiến trình bốn trạng thái trước khi gửi duyệt, duyệt hoặc ghi sổ. Lý do hủy là bắt buộc.
- Nhãn dữ liệu mô phỏng trên trang chi tiết sách và các mô tả phụ nhỏ ở màn quản lý liên quan được chuyển vào info icon/tooltip có nhãn hỗ trợ đọc màn hình. Trang NXB & Nhà cung cấp được giữ nguyên phần giải thích theo yêu cầu.

Lưu ý vận hành: package discovery trên máy này chậm bất thường nhưng có hoàn tất; khi rehearsal release cần đặt timeout tối thiểu 5 phút và không kết luận deadlock quá sớm. Việc nghiệm thu local không thay thế cài dependency và smoke trong release directory trước cutover.

## 4. Bằng chứng an toàn và ranh giới bàn giao

- Không truy cập hoặc sửa database production.
- Không dùng tài khoản/credential demo hay thật.
- Không thay đổi Cloudflare Tunnel, Caddy/FrankenPHP hoặc release đang chạy.
- Không commit, push hoặc deploy.
- `git diff --check` sạch tại thời điểm nghiệm thu.

Source và cổng export hiện sẵn sàng để review cục bộ. Rehearsal release vẫn phải hoàn tất trước khi xin phê duyệt commit/deploy.
