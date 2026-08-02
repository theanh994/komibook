<?php

namespace Tests\Feature;

use App\Models\DemoWalletAccount;
use App\Models\DemoWalletLedgerEntry;
use App\Models\PayoutRequest;
use App\Models\User;
use App\Models\WalletPayoutAccount;
use App\Services\PayoutService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class WalletWithdrawalTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_can_only_withdraw_existing_wallet_funds_after_bank_verification(): void
    {
        $customer = User::factory()->create(['email_verified_at' => now(), 'role' => 'customer']);
        $admin = User::factory()->create(['email_verified_at' => now(), 'role' => 'admin']);
        $wallet = DemoWalletAccount::create([
            'user_id' => $customer->id,
            'balance' => 200000,
            'reserved_balance' => 0,
            'currency' => 'VND',
            'status' => 'active',
        ]);
        DemoWalletLedgerEntry::create([
            'demo_wallet_account_id' => $wallet->id,
            'entry_type' => 'refund_credit',
            'amount' => 200000,
            'balance_before' => 0,
            'balance_after' => 200000,
            'operation_key' => 'test-wallet-refund-credit',
            'metadata' => ['original_payment_method' => 'cod'],
        ]);

        $trustedCustomer = $this->actingAs($customer)
            ->withHeader('Origin', 'https://komibook.id.vn')
            ->withSession(['auth.password_confirmed_at' => time()]);
        $trustedCustomer->getJson('/api/wallet')
            ->assertOk()
            ->assertJsonPath('data.wallet.balance', 200000)
            ->assertJsonPath('data.wallet.can_top_up', false)
            ->assertJsonPath('data.policy.external_top_up_enabled', false);
        $trustedCustomer->putJson('/api/wallet/payout-account', [
            'bank_name' => 'Ngân hàng kiểm thử',
            'account_number' => '1234567890',
            'account_name' => 'NGUYEN VAN A',
        ])->assertOk()->assertJsonPath('data.status', 'unverified');
        $trustedCustomer->postJson('/api/wallet/withdrawals', [
            'amount' => 100000,
            'idempotency_key' => 'customer-withdrawal-before-verification',
        ])->assertStatus(422);

        $account = WalletPayoutAccount::where('user_id', $customer->id)->firstOrFail();
        $this->actingAs($admin)
            ->withHeader('Origin', 'https://komibook.id.vn')
            ->withSession(['auth.password_confirmed_at' => time()])
            ->patchJson("/api/admin/reconciliation/payout-accounts/{$account->id}/review", [
                'target' => 'verified',
                'reason' => 'Đối chiếu tài khoản thành công.',
            ])->assertOk()->assertJsonPath('data.status', 'verified');

        $response = $this->actingAs($customer)
            ->withHeader('Origin', 'https://komibook.id.vn')
            ->withSession(['auth.password_confirmed_at' => time()])
            ->postJson('/api/wallet/withdrawals', [
                'amount' => 100000,
                'idempotency_key' => 'customer-withdrawal-verified',
            ])->assertCreated()->assertJsonPath('data.user_id', $customer->id);

        $payoutId = $response->json('data.id');
        $this->assertNull(PayoutRequest::findOrFail($payoutId)->vendor_id);
        $this->assertDatabaseHas('demo_wallet_accounts', [
            'user_id' => $customer->id,
            'balance' => 100000,
            'reserved_balance' => 100000,
        ]);
    }

    public function test_admin_rejection_releases_customer_wallet_reservation_idempotently(): void
    {
        $customer = User::factory()->create(['email_verified_at' => now(), 'role' => 'customer']);
        $admin = User::factory()->create(['email_verified_at' => now(), 'role' => 'admin']);
        $wallet = DemoWalletAccount::create(['user_id' => $customer->id, 'balance' => 150000, 'reserved_balance' => 0, 'currency' => 'VND', 'status' => 'active']);
        $account = WalletPayoutAccount::create([
            'user_id' => $customer->id, 'bank_name' => 'Ngân hàng kiểm thử', 'account_number' => '1234567890',
            'account_name' => 'NGUYEN VAN A', 'status' => 'verified', 'verified_by' => $admin->id, 'verified_at' => now(),
        ]);
        $payout = app(PayoutService::class)->reserveWallet($customer, $account, ['amount' => 100000], 'customer-release-test');

        $payload = ['target' => 'rejected', 'reason' => 'Thông tin nhận tiền cần cập nhật.', 'idempotency_key' => 'customer-release-transition'];
        $trustedAdmin = $this->actingAs($admin)->withHeader('Origin', 'https://komibook.id.vn')->withSession(['auth.password_confirmed_at' => time()]);
        $trustedAdmin->patchJson("/api/admin/reconciliation/payouts/{$payout->id}/transition", $payload)->assertOk();
        $trustedAdmin->patchJson("/api/admin/reconciliation/payouts/{$payout->id}/transition", $payload)->assertOk();

        $this->assertDatabaseHas('demo_wallet_accounts', ['id' => $wallet->id, 'balance' => 150000, 'reserved_balance' => 0]);
        $this->assertDatabaseCount('payout_ledger_entries', 2);
    }

    public function test_admin_can_complete_customer_withdrawal_with_transfer_evidence(): void
    {
        $customer = User::factory()->create(['email_verified_at' => now(), 'role' => 'customer']);
        $admin = User::factory()->create(['email_verified_at' => now(), 'role' => 'admin']);
        DemoWalletAccount::create(['user_id' => $customer->id, 'balance' => 150000, 'reserved_balance' => 0, 'currency' => 'VND', 'status' => 'active']);
        $account = WalletPayoutAccount::create([
            'user_id' => $customer->id, 'bank_name' => 'Ngân hàng kiểm thử', 'account_number' => '1234567890',
            'account_name' => 'NGUYEN VAN A', 'status' => 'verified', 'verified_by' => $admin->id, 'verified_at' => now(),
        ]);
        $payout = app(PayoutService::class)->reserveWallet($customer, $account, ['amount' => 100000], 'customer-complete-test');
        $trustedAdmin = $this->actingAs($admin)->withHeader('Origin', 'https://komibook.id.vn')->withSession(['auth.password_confirmed_at' => time()]);
        $trustedAdmin->patchJson("/api/admin/reconciliation/payouts/{$payout->id}/transition", [
            'target' => 'approved', 'reason' => 'Yêu cầu hợp lệ.', 'idempotency_key' => 'customer-complete-approved',
        ])->assertOk();
        $trustedAdmin->patchJson("/api/admin/reconciliation/payouts/{$payout->id}/transition", [
            'target' => 'processing', 'transfer_reference' => 'BANK-TEST-001', 'idempotency_key' => 'customer-complete-processing',
        ])->assertOk();
        $trustedAdmin->patchJson("/api/admin/reconciliation/payouts/{$payout->id}/transition", [
            'target' => 'completed', 'transfer_reference' => 'BANK-TEST-001',
            'transfer_evidence' => 'internal://evidence/test-001', 'idempotency_key' => 'customer-complete-completed',
        ])->assertOk();

        $this->assertDatabaseHas('demo_wallet_accounts', ['user_id' => $customer->id, 'balance' => 50000, 'reserved_balance' => 0]);
        $this->assertDatabaseHas('payout_requests', ['id' => $payout->id, 'status' => 'completed', 'transfer_reference' => 'BANK-TEST-001']);
        $this->assertDatabaseHas('payout_ledger_entries', ['payout_request_id' => $payout->id, 'entry_type' => 'completed']);
    }

    public function test_wallet_withdrawal_migration_can_roll_back_and_reapply_on_sqlite(): void
    {
        $migration = require database_path('migrations/2026_08_03_020000_unify_wallet_withdrawals.php');
        $migration->down();
        $this->assertFalse(Schema::hasTable('wallet_payout_accounts'));
        $migration->up();
        $this->assertTrue(Schema::hasTable('wallet_payout_accounts'));
        $this->assertTrue(Schema::hasColumns('payout_requests', ['user_id', 'wallet_payout_account_id']));
    }
}
