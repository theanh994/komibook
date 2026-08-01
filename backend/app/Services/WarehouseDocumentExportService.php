<?php

namespace App\Services;

use App\Models\WarehouseDocument;
use Barryvdh\DomPDF\Facade\Pdf;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class WarehouseDocumentExportService
{
    public function data(WarehouseDocument $document): array
    {
        $document->loadMissing([
            'vendor:id,shop_name,legal_name',
            'sourceWarehouse:id,name,address',
            'destinationWarehouse:id,name,address',
            'order:id,order_code,shipping_address,status,shipping_status',
            'lines.book:id,title,isbn,print_edition',
            'events.actor:id,name',
        ]);

        return [
            'document' => $document,
            'typeLabel' => $this->typeLabel($document->type),
            'statusLabel' => $this->statusLabel($document->status),
            'totalQuantity' => (int) $document->lines->sum(fn ($line) => $document->type === 'count'
                ? ($line->actual_quantity ?? 0)
                : $line->quantity),
        ];
    }

    public function pdf(WarehouseDocument $document)
    {
        $data = $this->data($document);

        return Pdf::loadView('warehouse-documents.print', $data)
            ->setPaper('a4')
            ->download($this->filename($document, 'pdf'));
    }

    public function excel(WarehouseDocument $document): BinaryFileResponse
    {
        $data = $this->data($document);
        $spreadsheet = new Spreadsheet;
        $info = $spreadsheet->getActiveSheet();
        $info->setTitle('Thông tin phiếu');
        $infoRows = [
            ['Mã phiếu', $document->document_code],
            ['Loại phiếu', $data['typeLabel']],
            ['Trạng thái', $data['statusLabel']],
            ['Nhà bán', $document->vendor?->shop_name],
            ['Kho nguồn', $document->sourceWarehouse?->name],
            ['Kho đích', $document->destinationWarehouse?->name],
            ['Đơn hàng', $document->order?->order_code],
            ['Đơn vị ngoài hệ thống', $document->external_counterparty_name],
            ['Tổng số lượng', $data['totalQuantity']],
            ['Ngày tạo', $document->created_at?->timezone(config('app.timezone'))->format('d/m/Y H:i')],
        ];
        foreach ($infoRows as $index => $row) {
            $info->setCellValue('A'.($index + 1), $row[0]);
            $info->setCellValue('B'.($index + 1), $this->safeSpreadsheetValue($row[1]));
        }
        $info->getColumnDimension('A')->setWidth(28);
        $info->getColumnDimension('B')->setWidth(55);

        $lines = $spreadsheet->createSheet();
        $lines->setTitle('Dòng hàng');
        $lines->fromArray(['STT', 'Sách', 'ISBN/SKU', 'Bản in', 'Số lượng', 'Thực tế', 'Vị trí kệ'], null, 'A1');
        foreach ($document->lines as $index => $line) {
            $lines->fromArray([
                $index + 1,
                $this->safeSpreadsheetValue($line->book?->display_title),
                $this->safeSpreadsheetValue($line->book?->isbn),
                $line->book?->print_edition ?? 1,
                $line->quantity,
                $line->actual_quantity,
                $this->safeSpreadsheetValue($line->shelf_location),
            ], null, 'A'.($index + 2));
        }
        foreach (range('A', 'G') as $column) {
            $lines->getColumnDimension($column)->setAutoSize(true);
        }

        $events = $spreadsheet->createSheet();
        $events->setTitle('Lịch sử trạng thái');
        $events->fromArray(['Thời gian', 'Từ trạng thái', 'Đến trạng thái', 'Người thao tác', 'Lý do'], null, 'A1');
        foreach ($document->events as $index => $event) {
            $events->fromArray([
                $event->created_at?->timezone(config('app.timezone'))->format('d/m/Y H:i'),
                $this->statusLabel($event->from_status),
                $this->statusLabel($event->to_status),
                $this->safeSpreadsheetValue($event->actor?->name),
                $this->safeSpreadsheetValue($event->reason),
            ], null, 'A'.($index + 2));
        }

        $temporaryPath = storage_path('app/'.uniqid('warehouse-document-', true).'.xlsx');
        (new Xlsx($spreadsheet))->save($temporaryPath);
        $spreadsheet->disconnectWorksheets();

        return response()->download($temporaryPath, $this->filename($document, 'xlsx'))->deleteFileAfterSend(true);
    }

    private function filename(WarehouseDocument $document, string $extension): string
    {
        return "{$document->document_code}-".now()->format('Ymd').".{$extension}";
    }

    private function safeSpreadsheetValue(mixed $value): mixed
    {
        if (is_string($value) && preg_match('/^[=+\-@]/', ltrim($value))) {
            return "'{$value}";
        }

        return $value;
    }

    private function typeLabel(string $type): string
    {
        return match ($type) {
            'receipt' => 'Phiếu nhập kho',
            'dispatch' => 'Phiếu xuất kho',
            'transfer' => 'Phiếu điều chuyển',
            'count' => 'Phiếu kiểm kê',
            default => $type,
        };
    }

    private function statusLabel(string $status): string
    {
        return match ($status) {
            'draft' => 'Bản nháp',
            'submitted' => 'Chờ duyệt',
            'approved' => 'Đã duyệt',
            'posted' => 'Đã ghi sổ',
            'cancelled' => 'Đã hủy',
            default => $status,
        };
    }
}
