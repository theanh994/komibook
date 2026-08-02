<?php

namespace App\Support;

use Illuminate\Console\Events\CommandStarting;
use RuntimeException;

final class ProductionCommandGuard
{
    public function handle(CommandStarting $event): void
    {
        if (! app()->environment('production')) {
            return;
        }

        $forbiddenCommands = config('production_safety.forbidden_commands', []);

        if (! in_array($event->command, $forbiddenCommands, true)) {
            return;
        }

        throw new RuntimeException(sprintf(
            'Lệnh "%s" bị chặn tuyệt đối trên production. Hãy dùng migration tiến tới và quy trình phục hồi đã phê duyệt.',
            $event->command,
        ));
    }
}
