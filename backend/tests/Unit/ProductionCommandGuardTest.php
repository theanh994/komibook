<?php

namespace Tests\Unit;

use App\Support\ProductionCommandGuard;
use Illuminate\Console\Events\CommandStarting;
use RuntimeException;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Tests\TestCase;

class ProductionCommandGuardTest extends TestCase
{
    public function test_it_blocks_destructive_artisan_commands_in_production(): void
    {
        $originalEnvironment = app()->environment();
        app()->detectEnvironment(fn () => 'production');

        try {
            $this->expectException(RuntimeException::class);
            app(ProductionCommandGuard::class)->handle($this->event('migrate:fresh'));
        } finally {
            app()->detectEnvironment(fn () => $originalEnvironment);
        }
    }

    public function test_it_allows_forward_migrations_in_production(): void
    {
        $originalEnvironment = app()->environment();
        app()->detectEnvironment(fn () => 'production');

        try {
            app(ProductionCommandGuard::class)->handle($this->event('migrate'));
            $this->addToAssertionCount(1);
        } finally {
            app()->detectEnvironment(fn () => $originalEnvironment);
        }
    }

    public function test_it_does_not_change_local_or_testing_commands(): void
    {
        app(ProductionCommandGuard::class)->handle($this->event('migrate:fresh'));
        $this->addToAssertionCount(1);
    }

    private function event(string $command): CommandStarting
    {
        return new CommandStarting($command, new ArrayInput([]), new BufferedOutput);
    }
}
