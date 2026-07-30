<?php

namespace App\Console\Commands;

use App\Services\CatalogPreservationManifestService;
use Illuminate\Console\Command;

class BuildCatalogPreservationManifest extends Command
{
    protected $signature = 'catalog:preservation-manifest {--pretty : Định dạng JSON dễ đọc}';

    protected $description = 'Xuất manifest chỉ đọc để bảo toàn catalog trước khi nhập dữ liệu bổ sung';

    public function handle(CatalogPreservationManifestService $service): int
    {
        $flags = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;
        if ($this->option('pretty')) {
            $flags |= JSON_PRETTY_PRINT;
        }

        $json = json_encode($service->build(), $flags);
        if ($json === false) {
            $this->error('Không thể tạo manifest bảo toàn catalog.');

            return self::FAILURE;
        }

        $this->line($json);

        return self::SUCCESS;
    }
}
