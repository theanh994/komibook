<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('inventory_reservations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('checkout_session_id')->constrained('checkout_sessions')->onDelete('restrict');
            $table->foreignId('order_item_id')->unique()->constrained('order_items')->onDelete('restrict');
            $table->foreignId('book_id')->constrained('books')->onDelete('restrict');
            $table->unsignedInteger('quantity');
            $table->string('status');
            $table->string('operation_key')->unique();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('committed_at')->nullable();
            $table->timestamp('released_at')->nullable();
            $table->timestamp('expired_at')->nullable();
            $table->timestamps();

            $table->index(['book_id', 'status', 'expires_at']);
            $table->index(['checkout_session_id', 'status']);
        });

        Schema::create('inventory_reservation_allocations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inventory_reservation_id')->constrained('inventory_reservations')->onDelete('cascade');
            $table->foreignId('warehouse_stock_id')->constrained('warehouse_stocks')->onDelete('restrict');
            $table->unsignedInteger('quantity');
            $table->timestamps();

            $table->unique(['inventory_reservation_id', 'warehouse_stock_id'], 'inv_res_alloc_res_wh_unique');
            $table->index('warehouse_stock_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inventory_reservation_allocations');
        Schema::dropIfExists('inventory_reservations');
    }
};
