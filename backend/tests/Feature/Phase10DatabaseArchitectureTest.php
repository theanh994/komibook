<?php

namespace Tests\Feature;

use Tests\TestCase;

class Phase10DatabaseArchitectureTest extends TestCase
{
    public function test_runtime_only_defines_mysql_and_explicit_test_sqlite_connections(): void
    {
        $this->assertSame(['sqlite', 'mysql'], array_keys(config('database.connections')));
        $this->assertSame('sqlite', config('database.default'));
        $this->assertSame(':memory:', config('database.connections.sqlite.database'));
    }

    public function test_project_bootstrap_no_longer_creates_a_persistent_sqlite_database(): void
    {
        $composer = json_decode(
            file_get_contents(base_path('composer.json')),
            true,
            512,
            JSON_THROW_ON_ERROR,
        );
        $scripts = json_encode($composer['scripts'], JSON_THROW_ON_ERROR);
        $example = file_get_contents(base_path('.env.example'));

        $this->assertStringNotContainsString('database.sqlite', $scripts);
        $this->assertStringContainsString('DB_CONNECTION=mysql', $example);
        $this->assertStringContainsString('DB_DATABASE=komibook', $example);
    }
}
