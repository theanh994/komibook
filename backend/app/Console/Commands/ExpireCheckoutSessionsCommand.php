<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\CheckoutSessionLifecycleService;
use Illuminate\Console\Command;

class ExpireCheckoutSessionsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'checkout-sessions:expire';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Expire unpaid online checkout sessions past their expiration time';

    /**
     * Execute the console command.
     */
    public function handle(CheckoutSessionLifecycleService $lifecycleService): int
    {
        $count = $lifecycleService->expireAllExpiredSessions();
        $this->info("Expired {$count} checkout session(s).");

        return Command::SUCCESS;
    }
}
