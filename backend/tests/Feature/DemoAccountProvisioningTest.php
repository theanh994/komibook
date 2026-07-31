<?php

namespace Tests\Feature;

use App\Console\Commands\ProvisionDemoAccounts;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DemoAccountProvisioningTest extends TestCase
{
    use RefreshDatabase;

    public function test_dry_run_performs_no_writes(): void
    {
        Storage::fake('private');

        $this->artisan('demo:provision-accounts --dry-run')
            ->expectsOutputToContain('Dry-run hoàn tất')
            ->assertSuccessful();

        $this->assertDatabaseCount('users', 0);
        Storage::disk('private')->assertMissing(ProvisionDemoAccounts::CREDENTIALS_PATH);
    }

    public function test_command_creates_verified_demo_accounts_and_private_credentials_once(): void
    {
        Storage::fake('private');

        $this->artisan('demo:provision-accounts')->assertSuccessful();

        $this->assertSame(0, User::query()->where('role', 'vendor')->count());
        $this->assertSame(14, User::query()->where('role', 'customer')->count());
        $this->assertSame(14, User::query()->whereNotNull('email_verified_at')->count());
        $this->assertSame(14, User::query()->whereNotNull('marketing_opt_out_at')->count());
        $this->assertSame(0, User::query()->whereHas('vendor')->count());
        Storage::disk('private')->assertExists(ProvisionDemoAccounts::CREDENTIALS_PATH);

        $rows = array_map('str_getcsv', preg_split('/\r\n|\r|\n/', trim(Storage::disk('private')->get(
            ProvisionDemoAccounts::CREDENTIALS_PATH
        ))));
        $this->assertCount(15, $rows);
        $this->assertSame('nxblaodong.demo@komibook.id.vn', $rows[1][1]);
        $this->assertTrue(Hash::check(
            $rows[1][2],
            User::query()->where('email', $rows[1][1])->firstOrFail()->password,
        ));

        $this->artisan('demo:provision-accounts')
            ->expectsOutputToContain('đã được tạo')
            ->assertSuccessful();
        $this->assertDatabaseCount('users', 14);
    }

    public function test_partial_collision_fails_without_creating_more_accounts_or_credentials(): void
    {
        Storage::fake('private');
        User::factory()->create(['email' => 'nxbtre.demo@komibook.id.vn']);

        $this->artisan('demo:provision-accounts')->assertFailed();

        $this->assertDatabaseCount('users', 1);
        Storage::disk('private')->assertMissing(ProvisionDemoAccounts::CREDENTIALS_PATH);
    }
}
