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
        Schema::create('checkout_sessions', function (Blueprint $table) {
            $table->id();
            $table->uuid('checkout_code')->unique();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->string('currency', 3)->default('VND');
            $table->unsignedBigInteger('subtotal_amount');
            $table->unsignedBigInteger('discount_amount')->default(0);
            $table->unsignedBigInteger('fee_amount')->default(0);
            $table->unsignedBigInteger('total_amount');
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
            $table->index('expires_at');
        });

        Schema::create('checkout_session_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('checkout_session_id')->constrained('checkout_sessions')->restrictOnDelete();
            $table->foreignId('order_id')->unique()->constrained('orders')->restrictOnDelete();
            $table->foreignId('vendor_id')->constrained('vendors')->restrictOnDelete();
            $table->unsignedBigInteger('subtotal_amount');
            $table->unsignedBigInteger('discount_amount')->default(0);
            $table->unsignedBigInteger('fee_amount')->default(0);
            $table->decimal('commission_rate', 5, 2)->default(0);
            $table->unsignedBigInteger('commission_amount')->default(0);
            $table->unsignedBigInteger('total_amount');
            $table->timestamps();
        });

        Schema::create('payment_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('checkout_session_id')->constrained('checkout_sessions')->restrictOnDelete();
            $table->string('provider', 32);
            $table->string('provider_reference', 128);
            $table->string('provider_transaction_id', 128)->nullable();
            $table->string('idempotency_key', 128);
            $table->unsignedBigInteger('amount');
            $table->string('currency', 3)->default('VND');
            $table->string('status', 32)->default('pending');
            $table->json('request_payload')->nullable();
            $table->json('response_payload')->nullable();
            $table->timestamp('provider_occurred_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('refund_started_at')->nullable();
            $table->timestamp('refunded_at')->nullable();
            $table->timestamps();

            $table->unique(['provider', 'provider_reference']);
            $table->unique(['provider', 'idempotency_key']);
            $table->unique(['provider', 'provider_transaction_id']);

            $table->index(['checkout_session_id', 'status']);
            $table->index('status');
            $table->index('expires_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payment_transactions');
        Schema::dropIfExists('checkout_session_orders');
        Schema::dropIfExists('checkout_sessions');
    }
};
