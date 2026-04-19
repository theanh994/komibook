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
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('order_code')->unique();

            // Foreign keys
            $table->foreignId('user_id')->constrained('users')->onDelete('restrict');
            $table->foreignId('vendor_id')->constrained('vendors')->onDelete('restrict');

            // Pricing
            $table->decimal('total_amount', 14, 0);

            // Statuses
            $table->enum('status', ['pending', 'processing', 'shipped', 'completed', 'cancelled'])
                  ->default('pending');
            $table->enum('payment_status', ['unpaid', 'paid'])
                  ->default('unpaid');
            $table->enum('payment_method', ['cod', 'online'])
                  ->default('cod');

            // Shipping info
            $table->string('shipping_address');
            $table->string('phone', 20);

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
