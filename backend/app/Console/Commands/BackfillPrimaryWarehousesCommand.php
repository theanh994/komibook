<?php

namespace App\Console\Commands;

use App\Models\Vendor;
use App\Models\Warehouse;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class BackfillPrimaryWarehousesCommand extends Command
{
    protected $signature = 'warehouse:backfill-primary {--apply : Ghi lựa chọn kho tổng vào database}';

    protected $description = 'Đề xuất hoặc gán kho tổng cho Nhà bán theo dữ liệu tồn hiện hữu';

    public function handle(): int
    {
        $apply = (bool) $this->option('apply');
        $rows = [];

        Vendor::withoutGlobalScopes()->orderBy('id')->each(function (Vendor $vendor) use ($apply, &$rows): void {
            if ($vendor->primary_warehouse_id) {
                $rows[] = [$vendor->id, $vendor->shop_name, $vendor->primary_warehouse_id, 'Giữ nguyên'];

                return;
            }

            $warehouses = Warehouse::withoutGlobalScopes()
                ->where('vendor_id', $vendor->id)
                ->whereIn('status', ['active', 'Hoạt động'])
                ->get();
            if ($warehouses->isEmpty()) {
                $rows[] = [$vendor->id, $vendor->shop_name, '-', 'Không có kho hoạt động'];

                return;
            }

            $stockTotals = DB::table('warehouse_stocks')
                ->whereIn('warehouse_id', $warehouses->pluck('id'))
                ->selectRaw('warehouse_id, SUM(quantity) AS total_quantity')
                ->groupBy('warehouse_id')
                ->pluck('total_quantity', 'warehouse_id');
            $candidate = $warehouses->sortByDesc(fn (Warehouse $warehouse) => [
                (int) ($stockTotals[$warehouse->id] ?? 0),
                -$warehouse->id,
            ])->first();
            $reason = $warehouses->count() === 1 ? 'Kho hoạt động duy nhất' : 'Kho có tồn lớn nhất';
            if ($apply) {
                $vendor->update(['primary_warehouse_id' => $candidate->id]);
            }
            $rows[] = [$vendor->id, $vendor->shop_name, $candidate->id, $apply ? "Đã gán: {$reason}" : "Đề xuất: {$reason}"];
        });

        $this->table(['Vendor ID', 'Nhà bán', 'Kho tổng', 'Kết quả'], $rows);
        if (! $apply) {
            $this->warn('Đây là dry-run. Chạy lại với --apply sau khi đã rà soát báo cáo.');
        }

        return self::SUCCESS;
    }
}
