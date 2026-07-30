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
}
