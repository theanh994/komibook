<!doctype html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <title>{{ $typeLabel }} {{ $document->document_code }}</title>
    <style>
        @page { margin: 18mm 14mm; }
        body { color: #172033; font-family: DejaVu Sans, sans-serif; font-size: 11px; line-height: 1.5; }
        h1 { margin: 0 0 4px; font-size: 20px; text-align: center; }
        .muted { color: #5f6b7a; }
        .meta { margin: 18px 0; width: 100%; }
        .meta td { padding: 4px 8px 4px 0; vertical-align: top; }
        table.lines { border-collapse: collapse; width: 100%; }
        .lines th, .lines td { border: 1px solid #c8d0dc; padding: 7px; text-align: left; }
        .lines th { background: #edf2f7; }
        .number { text-align: right !important; }
        .footer { margin-top: 22px; }
        @media print { .print-action { display: none; } }
    </style>
</head>
<body>
    <button class="print-action" type="button" onclick="window.print()">In phiếu</button>
    <h1>{{ $typeLabel }}</h1>
    <p style="text-align:center" class="muted">{{ $document->document_code }} · {{ $statusLabel }}</p>
    <table class="meta">
        <tr><td><strong>Nhà bán:</strong> {{ $document->vendor?->shop_name }}</td><td><strong>Ngày lập:</strong> {{ $document->created_at?->timezone(config('app.timezone'))->format('d/m/Y H:i') }}</td></tr>
        <tr><td><strong>Kho nguồn:</strong> {{ $document->sourceWarehouse?->name ?? '—' }}</td><td><strong>Kho đích:</strong> {{ $document->destinationWarehouse?->name ?? '—' }}</td></tr>
        <tr><td><strong>Đơn hàng:</strong> {{ $document->order?->order_code ?? '—' }}</td><td><strong>Đơn vị ngoài hệ thống:</strong> {{ $document->external_counterparty_name ?? '—' }}</td></tr>
        <tr><td colspan="2"><strong>Lý do:</strong> {{ $document->reason ?? '—' }}</td></tr>
    </table>
    <table class="lines">
        <thead><tr><th>STT</th><th>Sách</th><th>ISBN/SKU</th><th>Bản in</th><th class="number">Số lượng</th></tr></thead>
        <tbody>
        @foreach ($document->lines as $line)
            <tr>
                <td>{{ $loop->iteration }}</td>
                <td>{{ $line->book?->display_title }}</td>
                <td>{{ $line->book?->isbn ?? '—' }}</td>
                <td>{{ $line->book?->print_edition ?? 1 }}</td>
                <td class="number">{{ $document->type === 'count' ? ($line->actual_quantity ?? 0) : $line->quantity }}</td>
            </tr>
        @endforeach
        </tbody>
        <tfoot><tr><th colspan="4" class="number">Tổng</th><th class="number">{{ $totalQuantity }}</th></tr></tfoot>
    </table>
    <p class="footer muted">Phiếu được kết xuất từ snapshot chứng từ KomiBook. Các bút toán đã ghi sổ không thể chỉnh sửa.</p>
</body>
</html>
