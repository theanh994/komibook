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
        Schema::create('order_transition_operations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->onDelete('restrict');
            $table->string('operation_key')->unique();
            $table->string('actor_type')->nullable();
            $table->unsignedBigInteger('actor_id')->nullable();
            $table->string('transition_kind'); // 'order' or 'shipping'
            $table->string('from_state');
            $table->string('to_state');
            $table->json('metadata')->nullable();
            $table->timestamp('occurred_at');
            $table->timestamps();
        });

        Schema::create('loyalty_point_ledgers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('restrict');
            $table->foreignId('order_id')->constrained('orders')->onDelete('restrict');
            $table->string('operation_key')->unique();
            $table->string('type'); // 'order_completed'
            $table->integer('points');
            $table->timestamps();

            $table->unique('order_id');
        });

        Schema::create('vendor_earning_ledgers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vendor_id')->constrained('vendors')->onDelete('restrict');
            $table->foreignId('order_id')->constrained('orders')->onDelete('restrict');
            $table->string('operation_key')->unique();
            $table->unsignedBigInteger('gross_amount');
            $table->unsignedBigInteger('commission_amount');
            $table->unsignedBigInteger('net_amount');
            $table->string('currency')->default('VND');
            $table->timestamps();

            $table->unique('order_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vendor_earning_ledgers');
        Schema::dropIfExists('loyalty_point_ledgers');
        Schema::dropIfExists('order_transition_operations');
    }
};
