<?php

namespace App\Console\Commands;

use App\Services\Phase8ReadinessManifestService;
use Illuminate\Console\Command;

class BuildPhase8ReadinessManifest extends Command
{
    protected $signature = 'phase8:readiness-manifest {--pretty : Định dạng JSON dễ đọc}';

    protected $description = 'Xuất manifest chỉ đọc cho organization, commercial parties và Warehouse Manager';

    public function handle(Phase8ReadinessManifestService $service): int
    {
        $flags = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;
        if ($this->option('pretty')) {
            $flags |= JSON_PRETTY_PRINT;
        }

        $json = json_encode($service->build(), $flags);
        if ($json === false) {
            $this->error('Không thể tạo manifest Giai đoạn 8.');

            return self::FAILURE;
        }

        $this->line($json);

        return self::SUCCESS;
    }
}
