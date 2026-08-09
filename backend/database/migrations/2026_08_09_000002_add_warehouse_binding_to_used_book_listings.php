<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('used_book_listings', function (Blueprint $table): void {
            $table->foreignId('warehouse_id')->nullable()->after('book_id')->constrained('warehouses')->restrictOnDelete();
            $table->index('warehouse_id', 'used_book_listings_warehouse_id_index');
        });
    }

    public function down(): void
    {
        Schema::table('used_book_listings', function (Blueprint $table): void {
            $table->dropForeign(['warehouse_id']);
            $table->dropIndex('used_book_listings_warehouse_id_index');
            $table->dropColumn('warehouse_id');
        });
    }
};
