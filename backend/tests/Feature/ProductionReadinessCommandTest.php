<?php

namespace Tests\Feature;

use App\Console\Commands\CheckProductionReadiness;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use ReflectionMethod;
use Tests\TestCase;

class ProductionReadinessCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_readiness_passes_when_every_runtime_contract_is_satisfied(): void
    {
        $originalEnvironment = app()->environment();
        app()->detectEnvironment(fn () => 'production');
        $this->configureHealthyRuntime();

        try {
            $this->artisan('production:readiness', ['--json' => true])
                ->expectsOutputToContain('"status": "ready"')
                ->assertSuccessful();
        } finally {
            app()->detectEnvironment(fn () => $originalEnvironment);
        }
    }

    public function test_readiness_blocks_cutover_when_expected_data_is_missing(): void
    {
        $originalEnvironment = app()->environment();
        app()->detectEnvironment(fn () => 'production');
        $this->configureHealthyRuntime();
        config(['production_safety.minimum_counts.users' => 1]);

        try {
            $this->artisan('production:readiness', ['--json' => true])
                ->expectsOutputToContain('"status": "blocked"')
                ->assertFailed();
        } finally {
            app()->detectEnvironment(fn () => $originalEnvironment);
        }
    }

    public function test_readiness_blocks_cutover_when_a_canonical_schema_column_is_missing(): void
    {
        $originalEnvironment = app()->environment();
        app()->detectEnvironment(fn () => 'production');
        $this->configureHealthyRuntime();
        config(['production_safety.required_columns.vendors' => ['missing_vendor_contract_column']]);

        try {
            $this->artisan('production:readiness', ['--json' => true])
                ->expectsOutputToContain('"status": "blocked"')
                ->assertFailed();
        } finally {
            app()->detectEnvironment(fn () => $originalEnvironment);
        }
    }

    public function test_unrelated_database_all_privileges_do_not_block_production_database(): void
    {
        $method = new ReflectionMethod(CheckProductionReadiness::class, 'grantsContainDestructivePrivilege');
        $command = app(CheckProductionReadiness::class);
        $grants = [
            'GRANT USAGE ON *.* TO `komibook_app`@`127.0.0.1`',
            'GRANT SELECT, INSERT, UPDATE, DELETE, CREATE, REFERENCES, INDEX, ALTER ON `komibook`.* TO `komibook_app`@`127.0.0.1`',
            'GRANT ALL PRIVILEGES ON `komibook_restore_check_d284cd4`.* TO `komibook_app`@`127.0.0.1`',
        ];

        $this->assertFalse($method->invoke($command, $grants, 'komibook'));
        $this->assertTrue($method->invoke($command, [
            'GRANT SELECT, DROP ON `komibook`.* TO `komibook_app`@`127.0.0.1`',
        ], 'komibook'));
        $this->assertTrue($method->invoke($command, [
            'GRANT ALL PRIVILEGES ON *.* TO `komibook_app`@`127.0.0.1`',
        ], 'komibook'));
    }

    private function configureHealthyRuntime(): void
    {
        config([
            'app.url' => 'https://komibook.id.vn',
            'production_safety.expected_database' => DB::connection()->getDatabaseName(),
            'production_safety.expected_host' => 'komibook.id.vn',
            'production_safety.shared_root' => 'C:/komibook_shared',
            'production_safety.minimum_counts' => [
                'users' => 0,
                'books' => 0,
                'vendors' => 0,
                'organizations' => 0,
            ],
            'production_safety.required_columns' => [
                'vendors' => [
                    'onboarding_status',
                    'business_model',
                    'is_demo',
                    'submitted_at',
                    'last_review_reason',
                ],
            ],
            'session.domain' => 'komibook.id.vn',
            'session.secure' => true,
            'sanctum.stateful' => ['komibook.id.vn'],
            'filesystems.disks.local.root' => 'C:/komibook_shared/storage/app',
            'filesystems.disks.public.root' => 'C:/komibook_shared/storage/app/public',
            'filesystems.disks.private.root' => 'C:/komibook_shared/storage/app/private',
        ]);
    }
}
