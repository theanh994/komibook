<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class Phase8MigrationReversibilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_phase_8_migrations_can_roll_back_and_reapply_on_sqlite(): void
    {
        $identityMigration = require database_path('migrations/2026_07_30_100000_create_phase8_identity_and_commercial_parties.php');
        $warehouseMigration = require database_path('migrations/2026_07_30_101000_create_phase8_warehouse_documents_and_ledger.php');

        $warehouseMigration->down();
        $identityMigration->down();

        $this->assertFalse(Schema::hasTable('organizations'));
        $this->assertFalse(Schema::hasTable('warehouse_documents'));
        $this->assertFalse(Schema::hasColumn('order_items', 'commercial_parties_snapshot'));
        $this->assertFalse(Schema::hasColumn('vendors', 'business_model'));

        $identityMigration->up();
        $warehouseMigration->up();

        $this->assertTrue(Schema::hasTable('organizations'));
        $this->assertTrue(Schema::hasTable('book_commercial_parties'));
        $this->assertTrue(Schema::hasTable('warehouse_manager_assignments'));
        $this->assertTrue(Schema::hasTable('warehouse_documents'));
        $this->assertTrue(Schema::hasTable('warehouse_stock_ledgers'));
        $this->assertTrue(Schema::hasColumn('order_items', 'commercial_parties_snapshot'));
        $this->assertTrue(Schema::hasColumn('vendors', 'business_model'));
    }

    public function test_partner_commerce_migration_can_roll_back_and_reapply_on_sqlite(): void
    {
        $migration = require database_path('migrations/2026_07_31_230000_add_partner_commerce_and_payout_verification.php');

        $migration->down();

        $this->assertFalse(Schema::hasTable('organization_memberships'));
        $this->assertFalse(Schema::hasTable('organization_distribution_agreements'));
        $this->assertFalse(Schema::hasTable('organization_distribution_agreement_events'));
        $this->assertFalse(Schema::hasColumn('vendors', 'payout_bank_status'));

        $migration->up();

        $this->assertTrue(Schema::hasTable('organization_memberships'));
        $this->assertTrue(Schema::hasTable('organization_distribution_agreements'));
        $this->assertTrue(Schema::hasTable('organization_distribution_agreement_events'));
        $this->assertTrue(Schema::hasColumn('vendors', 'payout_bank_status'));
        $this->assertTrue(Schema::hasColumn('vendors', 'payout_bank_verified_at'));
        $this->assertTrue(Schema::hasColumn('vendors', 'payout_bank_verified_by'));
    }
}
