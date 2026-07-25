<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Jobs\DeliverOrderSideEffect;
use App\Services\OrderSideEffectOutboxService;
use Illuminate\Console\Command;

class DispatchOrderSideEffectsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'order-side-effects:dispatch {--limit=100 : Maximum number of outbox records to process}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Recover and dispatch pending, retryable, and stale order side-effect outbox records';

    /**
     * Execute the console command.
     */
    public function handle(OrderSideEffectOutboxService $outboxService): int
    {
        $limitOption = $this->option('limit');

        if (! is_string($limitOption) || ! preg_match('/^[1-9][0-9]*$/', $limitOption)) {
            $this->error('Limit must be an integer between 1 and 1000.');

            return 1;
        }

        $limit = (int) $limitOption;
        if ($limit > 1000) {
            $this->error('Limit must be an integer between 1 and 1000.');

            return 1;
        }

        $outboxes = $outboxService->getEligibleQuery()
            ->orderBy('id', 'asc')
            ->limit($limit)
            ->get();

        $dispatchedCount = 0;
        foreach ($outboxes as $outbox) {
            DeliverOrderSideEffect::dispatch($outbox->id);
            $dispatchedCount++;
        }

        $this->info("Dispatched {$dispatchedCount} order side-effect outbox records.");

        return 0;
    }
}
