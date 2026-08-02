<?php

namespace App\Console\Commands;

use App\Services\ProductionMediaIntegrityService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

class CheckProductionReadiness extends Command
{
    protected $signature = 'production:readiness {--json : Xuất kết quả JSON an toàn, không chứa credential}';

    protected $description = 'Kiểm tra database, session và shared storage trước khi chuyển production sang release mới';

    public function handle(ProductionMediaIntegrityService $mediaIntegrity): int
    {
        $checks = [];

        $this->check($checks, 'environment', app()->environment('production'), app()->environment(), 'production');

        try {
            $databaseName = DB::connection()->getDatabaseName();
            $this->check(
                $checks,
                'database_name',
                $databaseName === config('production_safety.expected_database'),
                $databaseName,
                config('production_safety.expected_database'),
            );

            foreach (config('production_safety.minimum_counts', []) as $table => $minimum) {
                $count = Schema::hasTable($table) ? DB::table($table)->count() : 0;
                $this->check($checks, "count_{$table}", $count >= $minimum, $count, ">={$minimum}");
            }

            foreach (config('production_safety.required_columns', []) as $table => $requiredColumns) {
                $actualColumns = Schema::hasTable($table) ? Schema::getColumnListing($table) : [];
                $missingColumns = array_values(array_diff($requiredColumns, $actualColumns));
                $this->check(
                    $checks,
                    "schema_{$table}",
                    $missingColumns === [],
                    $missingColumns === [] ? 'complete' : $missingColumns,
                    'complete',
                );
            }

            if (DB::connection()->getDriverName() === 'mysql') {
                $grants = collect(DB::select('SHOW GRANTS FOR CURRENT_USER'))
                    ->flatMap(fn (object $row) => array_values((array) $row))
                    ->all();
                $hasDestructivePrivilege = $this->grantsContainDestructivePrivilege($grants, $databaseName);
                $this->check($checks, 'runtime_database_drop_denied', ! $hasDestructivePrivilege, ! $hasDestructivePrivilege, true);
            }
        } catch (Throwable $exception) {
            $this->check($checks, 'database_probe', false, $exception::class, 'successful read-only query');
        }

        $expectedHost = (string) config('production_safety.expected_host');
        $sessionDomain = ltrim((string) config('session.domain'), '.');
        $statefulDomains = array_map(
            fn (string $domain) => explode(':', trim($domain))[0],
            config('sanctum.stateful', []),
        );

        $this->check($checks, 'app_url_https', str_starts_with((string) config('app.url'), 'https://'), config('app.url'), 'https://...');
        $this->check($checks, 'session_domain', $sessionDomain === $expectedHost, $sessionDomain, $expectedHost);
        $this->check($checks, 'session_secure', config('session.secure') === true, config('session.secure'), true);
        $this->check($checks, 'sanctum_stateful', in_array($expectedHost, $statefulDomains, true), $statefulDomains, $expectedHost);

        $sharedRoot = $this->normalizePath((string) config('production_safety.shared_root'));
        foreach (['local', 'public', 'private'] as $disk) {
            $root = $this->normalizePath((string) config("filesystems.disks.{$disk}.root"));
            $this->check($checks, "shared_storage_{$disk}", str_starts_with($root, $sharedRoot.'/'), $root, $sharedRoot.'/...');
        }

        try {
            $media = $mediaIntegrity->inspect();
            $this->check(
                $checks,
                'public_media_integrity',
                $media['missing_count'] === 0,
                $media,
                ['missing_count' => 0],
            );
        } catch (Throwable $exception) {
            $this->check($checks, 'public_media_integrity', false, $exception::class, ['missing_count' => 0]);
        }

        $passed = collect($checks)->every(fn (array $check) => $check['passed']);
        $payload = ['status' => $passed ? 'ready' : 'blocked', 'checks' => $checks];

        if ($this->option('json')) {
            $this->line((string) json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));
        } else {
            $this->table(
                ['Kiểm tra', 'Kết quả', 'Thực tế', 'Yêu cầu'],
                collect($checks)->map(fn (array $check) => [
                    $check['name'],
                    $check['passed'] ? 'PASS' : 'FAIL',
                    $this->displayValue($check['actual']),
                    $this->displayValue($check['expected']),
                ])->all(),
            );
        }

        return $passed ? self::SUCCESS : self::FAILURE;
    }

    /** @param array<int, string> $grants */
    private function grantsContainDestructivePrivilege(array $grants, string $databaseName): bool
    {
        $expectedDatabase = strtoupper($databaseName);

        foreach ($grants as $grant) {
            if (preg_match('/^GRANT (.+) ON (.+) TO /i', $grant, $matches) !== 1) {
                continue;
            }

            $scope = strtoupper(str_replace('`', '', trim($matches[2])));
            $scopeDatabase = explode('.', $scope, 2)[0];
            if ($scopeDatabase !== '*' && $scopeDatabase !== $expectedDatabase) {
                continue;
            }

            $privileges = array_map('trim', explode(',', strtoupper($matches[1])));
            if (in_array('ALL PRIVILEGES', $privileges, true) || in_array('DROP', $privileges, true)) {
                return true;
            }
        }

        return false;
    }

    private function check(array &$checks, string $name, bool $passed, mixed $actual, mixed $expected): void
    {
        $checks[] = compact('name', 'passed', 'actual', 'expected');
    }

    private function normalizePath(string $path): string
    {
        return rtrim(strtolower(str_replace('\\', '/', $path)), '/');
    }

    private function displayValue(mixed $value): string
    {
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if (is_array($value)) {
            return implode(',', $value);
        }

        return (string) $value;
    }
}
