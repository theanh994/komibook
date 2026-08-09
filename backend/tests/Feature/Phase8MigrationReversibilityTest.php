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

    public function test_batch_3b_review_event_and_fingerprint_migrations_can_roll_back_and_reapply_on_sqlite(): void
    {
        $reviewEventsMigration = require database_path('migrations/2026_08_09_000000_create_organization_review_events.php');
        $fingerprintsMigration = require database_path('migrations/2026_08_09_000001_add_authority_review_fingerprints.php');
        $fingerprintColumns = [
            ['organizations', 'authority_fingerprint'],
            ['vendor_organization_relationships', 'authority_fingerprint'],
            ['organization_distribution_agreements', 'authority_fingerprint'],
            ['organization_relationship_events', 'reviewed_fingerprint'],
            ['organization_distribution_agreement_events', 'reviewed_fingerprint'],
        ];

        $this->assertTrue(Schema::hasTable('organization_review_events'));
        foreach ($fingerprintColumns as [$table, $column]) {
            $this->assertTrue(Schema::hasColumn($table, $column));
        }

        $fingerprintsMigration->down();
        $reviewEventsMigration->down();

        $this->assertFalse(Schema::hasTable('organization_review_events'));
        foreach ($fingerprintColumns as [$table, $column]) {
            $this->assertFalse(Schema::hasColumn($table, $column));
        }

        $reviewEventsMigration->up();
        $fingerprintsMigration->up();

        $this->assertTrue(Schema::hasTable('organization_review_events'));
        foreach ($fingerprintColumns as [$table, $column]) {
            $this->assertTrue(Schema::hasColumn($table, $column));
        }
    }
}
