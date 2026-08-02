# Kế hoạch Ví KomiBook, doanh thu và hỗ trợ báo cáo thuế

## 1. Mục tiêu và phạm vi

- Ví KomiBook là phương thức thanh toán nội bộ dành cho khách hàng.
- Mọi khoản hoàn tiền được ghi có vào Ví KomiBook của người mua, không phụ thuộc phương thức thanh toán ban đầu.
- Khi đơn hàng hoàn tất, phần doanh thu ròng sau commission được ghi có vào Ví KomiBook của chủ gian hàng.
- Nhà bán gửi yêu cầu rút tiền về tài khoản ngân hàng pháp nhân đã xác minh; Admin tiếp tục duyệt theo quy trình hiện có.
- Hệ thống ngừng tính, giữ và khấu trừ thuế. KomiBook chỉ lưu báo cáo doanh thu để hỗ trợ đối soát, kê khai khi có yêu cầu hợp lệ.
- Admin quản lý báo cáo doanh thu theo 24 tháng gần nhất. Nhà bán xem và xuất doanh thu theo tháng, quý hoặc năm.
- VNPAY được giữ nguyên để xử lý ở đợt riêng; không mở rộng hoặc gọi dịch vụ thanh toán có phí trong đợt này.

## 2. Nguyên tắc dữ liệu

1. Sổ cái ví là bằng chứng bất biến cho mọi ghi nợ, ghi có, giữ tiền và giải phóng tiền.
2. Mỗi thao tác tài chính có khóa idempotency duy nhất; gửi lại yêu cầu không tạo bút toán trùng.
3. `vendors.balance` được giữ làm số dư tương thích cho mã cũ, nhưng luôn thay đổi cùng giao dịch với bút toán ví.
4. Tài khoản ví mới có số dư khởi tạo bằng 0. Dữ liệu ví demo đang có được giữ nguyên để không làm mất bằng chứng kiểm thử.
5. Dữ liệu thuế và khoản khấu trừ lịch sử được giữ chỉ đọc. Giao dịch mới luôn có thuế bằng 0 và không tạo bút toán thuế.
6. Báo cáo doanh thu lưu ảnh chụp từng tháng, làm mới được và chỉ giữ cửa sổ 24 tháng gần nhất.

## 3. Luồng nghiệp vụ đích

### 3.1 Thanh toán bằng Ví KomiBook

1. Khách chọn Ví KomiBook tại checkout.
2. Hệ thống khóa tài khoản ví, kiểm tra trạng thái, loại tiền và số dư.
3. Hệ thống ghi nợ đúng tổng tiền checkout bằng một bút toán duy nhất.
4. Giao dịch thanh toán và đơn hàng được xác nhận trong cùng luồng idempotent.

### 3.2 Hoàn tiền

1. Nhân sự duyệt yêu cầu trả hàng theo quy trình hiện có.
2. Hệ thống tạo giao dịch hoàn tiền nội bộ và ghi có Ví KomiBook của người mua.
3. Kho, doanh thu nhà bán, commission và trạng thái đơn được đảo theo tỷ lệ hàng hoàn.
4. Không gọi cổng thanh toán ngoài để hoàn tiền trong luồng mới.

### 3.3 Doanh thu và rút tiền nhà bán

1. Khi đơn hoàn tất, hệ thống tính `doanh thu gộp - commission`; thuế bằng 0.
2. Phần ròng được ghi có vào Ví KomiBook của chủ gian hàng và cập nhật số dư tương thích.
3. Khi tạo yêu cầu rút, số tiền chuyển từ khả dụng sang giữ chờ duyệt.
4. Admin có thể duyệt, từ chối, chuyển xử lý và hoàn tất. Từ chối trả tiền về số dư khả dụng; hoàn tất tiêu thụ số tiền đang giữ.
5. Gian hàng demo vẫn không được rút tiền thật.

### 3.4 Báo cáo doanh thu

- Admin: ảnh chụp 24 tháng gồm doanh thu khách thanh toán, số đơn hoàn tất, commission, doanh thu ròng nhà bán và hoàn tiền; có làm mới và xuất CSV.
- Nhà bán: bộ lọc tháng/quý/năm, tổng hợp và chi tiết bút toán; xuất CSV có BOM UTF-8 để mở đúng tiếng Việt trong Excel.
- Báo cáo là dữ liệu hỗ trợ đối soát/kê khai, không phải chứng từ xác nhận nghĩa vụ thuế.

## 4. Các lô triển khai

### Lô A — Nền tảng dữ liệu

- Mở rộng bút toán ví với liên kết đơn hàng, nhà bán, hoàn trả và yêu cầu rút.
- Bổ sung bảng ảnh chụp báo cáo doanh thu tháng.
- Chuyển cấu hình phương thức ví từ tên kỹ thuật demo sang Ví KomiBook, vẫn đọc được dữ liệu cũ.
- Backfill số dư nhà bán vào ví bằng bút toán import idempotent.

### Lô B — Nghiệp vụ tài chính

- Chuẩn hóa dịch vụ Ví KomiBook.
- Kết nối checkout, mọi hoàn tiền, ghi nhận doanh thu hoàn tất và vòng đời payout.
- Dừng toàn bộ tính toán/khấu trừ thuế cho giao dịch mới.

### Lô C — API và báo cáo

- API ví khách hàng và lịch sử giao dịch.
- API doanh thu nhà bán theo tháng/quý/năm và xuất CSV.
- API làm mới, quản lý và xuất báo cáo 24 tháng cho Admin.

### Lô D — Giao diện và nghiệm thu

- Nâng cấp trang Doanh thu & Rút tiền cho nhà bán.
- Nâng cấp trang Báo cáo doanh thu Admin.
- Loại bỏ cấu hình biểu thuế khỏi giao diện hệ thống.
- Chạy migration cục bộ, test backend/frontend, lint và build; kiểm tra trực tiếp các trang liên quan nếu máy chủ local sẵn sàng.

## 5. Tiêu chí nghiệm thu

- Không có bút toán trùng khi cùng yêu cầu được gửi lại.
- Khách không thể thanh toán nếu ví thiếu tiền; số dư không âm.
- Mỗi khoản hoàn tiền chỉ được ghi có một lần và luôn vào Ví KomiBook.
- Mỗi đơn hoàn tất chỉ ghi nhận doanh thu nhà bán một lần.
- Số tiền chờ rút không thể tiếp tục được sử dụng hoặc rút lần hai.
- Giao dịch mới không phát sinh thuế và không giảm payout vì thuế.
- Báo cáo Admin đủ liên tục 24 tháng, kể cả tháng không có doanh thu.
- File CSV mở đúng tiếng Việt và phản ánh đúng phạm vi tháng/quý/năm đã chọn.

## 6. Ngoài phạm vi đợt này

- Nạp tiền thật vào Ví KomiBook qua ngân hàng/cổng thanh toán.
- Hoàn thiện VNPAY production.
- Tự động nộp thuế hoặc gửi dữ liệu cho cơ quan thuế.
- Commit, push, migration production và deployment; các thao tác này cần phê duyệt riêng.
