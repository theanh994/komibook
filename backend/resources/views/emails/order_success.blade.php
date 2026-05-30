<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Xác nhận đơn hàng thành công - KomiBook</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f1f5f9;
            margin: 0;
            padding: 0;
            color: #334155;
        }
        .container {
            max-width: 600px;
            margin: 40px auto;
            background-color: #ffffff;
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -2px rgba(0, 0, 0, 0.1);
        }
        .header {
            background: linear-gradient(135deg, #6366f1 0%, #4f46e5 100%);
            padding: 40px 20px;
            text-align: center;
            color: #ffffff;
        }
        .header h1 {
            margin: 0;
            font-size: 24px;
            font-weight: 800;
            letter-spacing: -0.025em;
        }
        .header p {
            margin: 8px 0 0 0;
            opacity: 0.9;
            font-size: 16px;
        }
        .content {
            padding: 32px 24px;
        }
        .order-summary {
            background-color: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 24px;
        }
        .order-summary table {
            width: 100%;
            border-collapse: collapse;
        }
        .order-summary td {
            padding: 6px 0;
        }
        .order-summary td.label {
            color: #64748b;
            font-weight: 500;
        }
        .order-summary td.value {
            text-align: right;
            font-weight: 600;
            color: #0f172a;
        }
        .items-title {
            font-size: 16px;
            font-weight: 700;
            color: #0f172a;
            margin: 0 0 12px 0;
            border-bottom: 1px solid #e2e8f0;
            padding-bottom: 8px;
        }
        .item-row {
            border-bottom: 1px dotted #e2e8f0;
        }
        .item-row td {
            padding: 12px 0;
        }
        .item-name {
            font-weight: 600;
            color: #334155;
        }
        .item-qty {
            color: #64748b;
            font-size: 14px;
        }
        .item-price {
            text-align: right;
            font-weight: 600;
            color: #0f172a;
        }
        .total-section {
            margin-top: 16px;
            border-top: 2px solid #e2e8f0;
            padding-top: 16px;
        }
        .total-price {
            font-size: 18px;
            font-weight: 800;
            color: #6366f1;
            text-align: right;
        }
        .btn {
            display: inline-block;
            background-color: #6366f1;
            color: #ffffff !important;
            text-decoration: none;
            padding: 12px 32px;
            border-radius: 10px;
            font-weight: 600;
            text-align: center;
            margin: 24px auto 0 auto;
            display: block;
            width: fit-content;
            box-shadow: 0 4px 12px rgba(99, 102, 241, 0.2);
        }
        .footer {
            background-color: #f8fafc;
            padding: 24px;
            text-align: center;
            font-size: 12px;
            color: #94a3b8;
            border-top: 1px solid #e2e8f0;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Đặt Hàng Thành Công!</h1>
            <p>Cảm ơn bạn đã mua sách tại KomiBook</p>
        </div>
        <div class="content">
            <p>Chào <strong>{{ $order->user->name }}</strong>,</p>
            @if ($order->payment_method === 'cod')
                <p>Đơn hàng của bạn đã được đặt thành công. Chúng tôi sẽ chuẩn bị và giao hàng cho bạn, thanh toán sẽ được thực hiện khi nhận hàng (COD).</p>
            @else
                <p>Chúng tôi đã nhận được thanh toán cho đơn hàng của bạn. Đơn hàng hiện đang được chuẩn bị để đóng gói và giao hàng trong thời gian sớm nhất.</p>
            @endif
            
            <div class="order-summary">
                <table>
                    <tr>
                        <td class="label">Mã đơn hàng:</td>
                        <td class="value">{{ $order->order_code }}</td>
                    </tr>
                    <tr>
                        <td class="label">Trạng thái thanh toán:</td>
                        <td class="value" style="color: {{ $order->payment_status === 'paid' ? '#10b981' : '#f59e0b' }};">
                            @if ($order->payment_status === 'paid')
                                Đã thanh toán ({{ strtoupper($order->payment_method) }})
                            @else
                                Chưa thanh toán ({{ strtoupper($order->payment_method) }})
                            @endif
                        </td>
                    </tr>
                    <tr>
                        <td class="label">Ngày đặt:</td>
                        <td class="value">{{ $order->created_at->format('d/m/Y H:i') }}</td>
                    </tr>
                    <tr>
                        <td class="label">Người nhận:</td>
                        <td class="value">{{ $order->user->name }}</td>
                    </tr>
                    <tr>
                        <td class="label">Số điện thoại:</td>
                        <td class="value">{{ $order->phone }}</td>
                    </tr>
                    <tr>
                        <td class="label">Địa chỉ giao hàng:</td>
                        <td class="value">{{ $order->shipping_address }}</td>
                    </tr>
                </table>
            </div>

            <h3 class="items-title">Chi tiết sản phẩm</h3>
            <table style="width: 100%; border-collapse: collapse;">
                @foreach ($order->orderItems as $item)
                    <tr class="item-row">
                        <td style="width: 70%;">
                            <div class="item-name">{{ $item->book->title }}</div>
                            <div class="item-qty">Số lượng: {{ $item->quantity }}</div>
                        </td>
                        <td class="item-price">
                            {{ number_format($item->price * $item->quantity, 0, ',', '.') }} đ
                        </td>
                    </tr>
                @endforeach
                <tr class="total-section">
                    <td style="font-weight: 700; color: #0f172a; padding-top: 16px;">TỔNG CỘNG:</td>
                    <td class="total-price" style="padding-top: 16px;">
                        {{ number_format($order->total_amount, 0, ',', '.') }} đ
                    </td>
                </tr>
            </table>

            <a href="{{ config('app.frontend_url') }}/orders" class="btn">Theo dõi đơn hàng</a>
        </div>
        <div class="footer">
            Đây là email tự động từ hệ thống KomiBook. Vui lòng không phản hồi email này.<br>
            © {{ date('Y') }} KomiBook. All rights reserved.
        </div>
    </div>
</body>
</html>
