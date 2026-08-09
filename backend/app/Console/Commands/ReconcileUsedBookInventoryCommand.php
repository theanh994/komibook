<?php

namespace App\Console\Commands;

use App\Services\UsedBookInventoryReconciliationService;
use Illuminate\Console\Command;

class ReconcileUsedBookInventoryCommand extends Command
{
    protected $signature = 'used-books:reconcile-inventory {--apply} {--json}';

    protected $description = 'Read-only used-book inventory reconciliation; --apply binds only provably canonical unbound listings.';

    public function handle(UsedBookInventoryReconciliationService $reconciliation): int
    {
        $result = $reconciliation->reconcile((bool) $this->option('apply'));
        if ($this->option('json')) {
            $this->line((string) json_encode($result, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        } else {
            $this->table(['listing_id', 'book_id', 'warehouse_id', 'warehouse_stock_id', 'reason_code', 'applied'], $result['rows']);
        }

        return self::SUCCESS;
    }
}
