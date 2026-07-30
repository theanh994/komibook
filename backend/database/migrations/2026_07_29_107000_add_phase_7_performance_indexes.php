<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('books', function (Blueprint $table): void {
            $table->index(['vendor_id', 'status'], 'books_vendor_status_idx');
            $table->index(['vendor_id', 'type', 'stock'], 'books_vendor_type_stock_idx');
        });

        Schema::table('warehouse_stocks', function (Blueprint $table): void {
            $table->index(['book_id', 'warehouse_id'], 'warehouse_stocks_book_warehouse_idx');
        });

        Schema::table('inventory_audits', function (Blueprint $table): void {
            $table->index(['vendor_id', 'created_at'], 'inventory_audits_vendor_created_idx');
        });

        Schema::table('stock_transfers', function (Blueprint $table): void {
            $table->index(['vendor_id', 'created_at'], 'stock_transfers_vendor_created_idx');
        });
    }

    public function down(): void
    {
        Schema::table('stock_transfers', function (Blueprint $table): void {
            $table->dropIndex('stock_transfers_vendor_created_idx');
        });

        Schema::table('inventory_audits', function (Blueprint $table): void {
            $table->dropIndex('inventory_audits_vendor_created_idx');
        });

        Schema::table('warehouse_stocks', function (Blueprint $table): void {
            $table->dropIndex('warehouse_stocks_book_warehouse_idx');
        });

        Schema::table('books', function (Blueprint $table): void {
            $table->dropIndex('books_vendor_type_stock_idx');
            $table->dropIndex('books_vendor_status_idx');
        });
    }
};
