<?php

namespace Tests\Feature;

use App\Models\PayoutRequest;
use App\Models\User;
use App\Models\Vendor;
use App\Services\PayoutService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Phase7ReconciliationTruthTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_reconciliation_reports_only_real_payout_integrity_mismatches(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        [$vendorUser, $vendor] = $this->vendorWithBalance(500000);

        app(PayoutService::class)->reserve($vendor, [
            'amount' => 100000,
            'bank_name' => 'Komi Bank',
            'account_number' => '123456',
            'account_name' => 'Healthy Vendor',
        ], $vendorUser, 'phase7-healthy-payout');

        $broken = PayoutRequest::withoutGlobalScopes()->create([
            'vendor_id' => $vendor->id,
            'operation_key' => 'phase7-broken-payout',
            'amount' => 50000,
            'bank_name' => 'Komi Bank',
            'account_number' => '999999',
            'account_name' => 'Broken Vendor',
            'status' => 'pending',
        ]);

        $response = $this->actingAs($admin)->getJson('/api/admin/reconciliation?per_page=1');

        $response->assertOk()
            ->assertJsonPath('data.kpi.unreconciled', 1)
            ->assertJsonPath('data.meta.current_page', 1)
            ->assertJsonPath('data.meta.last_page', 2)
            ->assertJsonPath('data.meta.total', 2)
            ->assertJsonPath('data.payout_requests.0.id', $broken->id)
            ->assertJsonPath('data.payout_requests.0.reconciliation.is_consistent', false)
            ->assertJsonPath('data.payout_requests.0.reconciliation.ledger_entry_count', 0)
            ->assertJsonPath('data.payout_requests.0.reconciliation.issues', [
                'missing_source_ledger',
                'missing_transition',
            ]);
    }

    public function test_completed_payout_with_ledger_transition_and_evidence_is_consistent(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        [$vendorUser, $vendor] = $this->vendorWithBalance(300000);
        $service = app(PayoutService::class);
        $payout = $service->reserve($vendor, [
            'amount' => 100000,
            'bank_name' => 'Komi Bank',
            'account_number' => '123456',
            'account_name' => 'Completed Vendor',
        ], $vendorUser, 'phase7-completed-payout');

        $service->transition($payout, 'approved', $admin, 'phase7-approved', ['reason' => 'Đã kiểm tra']);
        $service->transition($payout, 'processing', $admin, 'phase7-processing', ['transfer_reference' => 'BANK-P7']);
        $service->transition($payout, 'completed', $admin, 'phase7-completed', [
            'transfer_reference' => 'BANK-P7',
            'transfer_evidence' => 'evidence/BANK-P7.pdf',
        ]);

        $this->actingAs($admin)->getJson('/api/admin/reconciliation?status=completed')
            ->assertOk()
            ->assertJsonPath('data.kpi.unreconciled', 0)
            ->assertJsonCount(1, 'data.payout_requests')
            ->assertJsonPath('data.payout_requests.0.reconciliation.is_consistent', true)
            ->assertJsonPath('data.payout_requests.0.reconciliation.ledger_entry_count', 2)
            ->assertJsonPath('data.payout_requests.0.reconciliation.latest_transition.to_status', 'completed');
    }

    private function vendorWithBalance(int $balance): array
    {
        $user = User::factory()->create(['role' => 'vendor']);
        $vendor = Vendor::withoutGlobalScopes()->create([
            'user_id' => $user->id,
            'shop_name' => 'Phase 7 Payout Shop',
            'slug' => 'phase-7-payout-shop-'.uniqid(),
            'status' => 'active',
            'payout_bank_name' => 'Komi Bank',
            'payout_bank_account' => '123456',
            'payout_bank_holder' => 'Healthy Vendor',
            'payout_bank_status' => 'verified',
            'payout_bank_verified_at' => now(),
        ]);
        $vendor->forceFill(['balance' => $balance, 'total_withdrawn' => 0])->save();

        return [$user, $vendor];
    }
}
