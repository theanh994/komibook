<!doctype html>
<html lang="vi">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>{{ $typeLabel }} {{ $document->document_code }}</title>
    <style>
        @page {
            margin: 15mm 12mm;
            size: A4;
        }
        * {
            font-family: "DejaVu Sans", sans-serif !important;
        }
        body {
            color: #1e293b;
            font-family: "DejaVu Sans", sans-serif !important;
            font-size: 11px;
            line-height: 1.5;
            margin: 0;
            padding: 0;
            background: #ffffff;
        }

        .header-table {
            width: 100%;
            border-bottom: 2px solid #e2e8f0;
            padding-bottom: 12px;
            margin-bottom: 16px;
            border-collapse: collapse;
        }
        .header-left {
            text-align: left;
            vertical-align: top;
        }
        .header-right {
            text-align: right;
            vertical-align: top;
        }
        .brand-title {
            font-size: 20px;
            font-weight: bold;
            color: #be123c;
            text-transform: uppercase;
            letter-spacing: -0.5px;
            margin: 0 0 2px 0;
        }
        .brand-sub {
            font-size: 10px;
            color: #64748b;
            margin: 0 0 8px 0;
        }
        .shop-name {
            font-size: 11px;
            font-weight: bold;
            color: #0f172a;
        }
        .doc-title {
            font-size: 16px;
            font-weight: bold;
            color: #0f172a;
            text-transform: uppercase;
            margin: 0 0 4px 0;
        }
        .doc-code {
            font-size: 11px;
            font-weight: bold;
            color: #be123c;
        }
        .doc-date {
            font-size: 10px;
            color: #64748b;
            margin-top: 2px;
        }

        /* 2 Column Info Card */
        .info-card {
            width: 100%;
            background-color: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 10px 12px;
            margin-bottom: 16px;
        }
        .info-table {
            width: 100%;
            border-collapse: collapse;
        }
        .info-table td {
            vertical-align: top;
            padding: 3px 0;
        }
        .info-title {
            font-size: 10px;
            font-weight: bold;
            color: #0f172a;
            text-transform: uppercase;
            border-bottom: 1px solid #cbd5e1;
            padding-bottom: 4px;
            margin-bottom: 6px;
        }
        .label {
            font-weight: bold;
            color: #334155;
        }

        /* Products Table */
        .lines-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 16px;
            border: 1px solid #e2e8f0;
        }
        .lines-table th {
            background-color: #f1f5f9;
            color: #334155;
            font-weight: bold;
            font-size: 10px;
            text-transform: uppercase;
            padding: 8px 10px;
            border-bottom: 1px solid #cbd5e1;
            border-right: 1px solid #e2e8f0;
        }
        .lines-table td {
            padding: 8px 10px;
            border-bottom: 1px solid #f1f5f9;
            border-right: 1px solid #f1f5f9;
            vertical-align: middle;
        }
        .lines-table th:last-child, .lines-table td:last-child {
            border-right: none;
        }
        .text-center { text-align: center !important; }
        .text-right { text-align: right !important; }
        .font-bold { font-weight: bold !important; }

        .footer-note {
            clear: both;
            padding-top: 16px;
            text-align: center;
            font-size: 10px;
            color: #64748b;
            font-style: italic;
            border-top: 1px dashed #e2e8f0;
            margin-top: 16px;
        }

        .print-action {
            display: inline-block;
            margin-bottom: 16px;
            padding: 6px 14px;
            background: #be123c;
            color: #ffffff;
            border: none;
            border-radius: 6px;
            font-weight: bold;
            cursor: pointer;
        }
        @media print {
            .print-action { display: none !important; }
        }
    </style>
</head>
<body>
    @if(!request()->is('*/pdf'))
        <button class="print-action" type="button" onclick="window.print()">In {{ $typeLabel }}</button>
    @endif

    <!-- Header Table -->
    <table class="header-table">
        <tr>
            <td class="header-left">
                <div class="brand-title">KomiBook</div>
                <div class="brand-sub">Hệ thống Phát hành & Phân phối Sách Trực tuyến</div>
                <div class="shop-name">Gian hàng: {{ $document->vendor?->shop_name ?? 'Gian hàng KomiBook' }}</div>
            </td>
            <td class="header-right">
                <div class="doc-title">{{ strtoupper($typeLabel) }}</div>
                <div class="doc-code">Mã phiếu: {{ $document->document_code }}</div>
                <div class="doc-date">Ngày lập: {{ $document->created_at?->timezone(config('app.timezone'))->format('d/m/Y H:i') }}</div>
            </td>
        </tr>
    </table>

    <!-- 2 Column Info Card -->
    <div class="info-card">
        <table class="info-table">
            <tr>
                <td style="width: 50%; padding-right: 12px;">
                    <div class="info-title">Thông tin chứng từ</div>
                    <div><span class="label">Nhà bán:</span> {{ $document->vendor?->shop_name ?? '—' }}</div>
                    <div><span class="label">Kho nguồn:</span> {{ $document->sourceWarehouse?->name ?? '—' }}</div>
                    <div><span class="label">Kho đích:</span> {{ $document->destinationWarehouse?->name ?? '—' }}</div>
                    <div><span class="label">Lý do:</span> {{ $document->reason ?? '—' }}</div>
                </td>
                <td style="width: 50%; padding-left: 12px; border-left: 1px solid #e2e8f0;">
                    <div class="info-title">Thông tin bổ sung</div>
                    <div><span class="label">Trạng thái:</span> {{ $statusLabel }}</div>
                    <div><span class="label">Đơn hàng:</span> {{ $document->order?->order_code ?? '—' }}</div>
                    <div><span class="label">Đơn vị ngoài:</span> {{ $document->external_counterparty_name ?? '—' }}</div>
                </td>
            </tr>
        </table>
    </div>

    <!-- Products Lines Table -->
    <table class="lines-table">
        <thead>
            <tr>
                <th style="width: 40px;" class="text-center">STT</th>
                <th style="text-align: left;">Tên Sách</th>
                <th style="width: 120px;" class="text-center">ISBN / SKU</th>
                <th style="width: 60px;" class="text-center">Bản in</th>
                <th style="width: 80px;" class="text-right">Số lượng</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($document->lines as $line)
                <tr>
                    <td class="text-center" style="color: #64748b;">{{ $loop->iteration }}</td>
                    <td class="font-bold" style="color: #0f172a;">{{ $line->book?->display_title ?? $line->book?->title ?? 'Sách #'.$line->book_id }}</td>
                    <td class="text-center">{{ $line->book?->isbn ?? '—' }}</td>
                    <td class="text-center">{{ $line->book?->print_edition ?? 1 }}</td>
                    <td class="text-right font-bold" style="color: #0f172a;">
                        {{ $document->type === 'count' ? ($line->actual_quantity ?? 0) : $line->quantity }}
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <!-- Summary Box -->
    <table style="width: 100%; border-collapse: collapse;">
        <tr>
            <td style="width: 50%;"></td>
            <td style="width: 50%; text-align: right;">
                <table style="width: 250px; margin-left: auto; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 8px; border-collapse: collapse;">
                    <tr>
                        <td style="text-align: left; padding: 3px 0; color: #475569;">Tổng danh mục sách:</td>
                        <td style="text-align: right; padding: 3px 0; font-weight: bold; color: #0f172a;">{{ count($document->lines) }} mục</td>
                    </tr>
                    <tr style="border-top: 1px solid #e2e8f0;">
                        <td style="text-align: left; padding: 6px 0 0; font-weight: bold; color: #0f172a; font-size: 11px;">Tổng số lượng:</td>
                        <td style="text-align: right; padding: 6px 0 0; font-weight: bold; color: #be123c; font-size: 13px;">{{ $totalQuantity }} cuốn</td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>

    <!-- Footer Note -->
    <div class="footer-note">
        Phiếu được kết xuất từ snapshot chứng từ KomiBook. Các bút toán đã ghi sổ không thể chỉnh sửa.
    </div>
</body>
</html>
