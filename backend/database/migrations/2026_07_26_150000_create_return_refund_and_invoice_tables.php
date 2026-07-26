<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('refund_status', 32)->default('none')->after('payment_status');
            $table->index(['vendor_id', 'refund_status']);
        });

        Schema::create('invoice_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->unique()->constrained('orders')->restrictOnDelete();
            $table->string('invoice_number', 64)->unique();
            $table->string('currency', 3)->default('VND');
            $table->timestamp('issued_at');
            $table->json('buyer_snapshot');
            $table->json('seller_snapshot');
            $table->json('line_items');
            $table->unsignedBigInteger('subtotal_amount');
            $table->unsignedBigInteger('coupon_discount_amount')->default(0);
            $table->unsignedBigInteger('membership_discount_amount')->default(0);
            $table->unsignedBigInteger('shipping_fee_amount')->default(0);
            $table->unsignedBigInteger('service_fee_amount')->default(0);
            $table->decimal('tax_rate', 5, 2)->default(0);
            $table->unsignedBigInteger('tax_amount')->default(0);
            $table->unsignedBigInteger('total_amount');
            $table->timestamps();
        });

        Schema::create('return_requests', function (Blueprint $table) {
            $table->id();
            $table->uuid('code')->unique();
            $table->foreignId('order_id')->constrained('orders')->restrictOnDelete();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('vendor_id')->constrained('vendors')->restrictOnDelete();
            $table->string('status', 32)->default('requested');
            $table->string('currency', 3)->default('VND');
            $table->unsignedBigInteger('refund_amount');
            $table->text('reason');
            $table->text('resolution_reason')->nullable();
            $table->timestamp('requested_at');
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('item_received_at')->nullable();
            $table->timestamp('refund_started_at')->nullable();
            $table->timestamp('refunded_at')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->timestamps();

            $table->index(['order_id', 'status']);
            $table->index(['vendor_id', 'status']);
            $table->index(['user_id', 'created_at']);
        });

        Schema::create('return_request_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('return_request_id')->constrained('return_requests')->cascadeOnDelete();
            $table->foreignId('order_item_id')->constrained('order_items')->restrictOnDelete();
            $table->unsignedInteger('quantity');
            $table->unsignedBigInteger('unit_amount');
            $table->unsignedBigInteger('refund_amount');
            $table->timestamp('inventory_restored_at')->nullable();
            $table->timestamps();

            $table->unique(['return_request_id', 'order_item_id'], 'return_item_request_order_unique');
        });

        Schema::create('return_request_transitions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('return_request_id')->constrained('return_requests')->cascadeOnDelete();
            $table->string('operation_key', 128)->unique();
            $table->string('from_state', 32)->nullable();
            $table->string('to_state', 32);
            $table->string('actor_type', 32);
            $table->unsignedBigInteger('actor_id');
            $table->text('reason')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('occurred_at');
            $table->timestamps();

            $table->index(['return_request_id', 'occurred_at'], 'return_transition_request_time_idx');
        });

        Schema::create('refund_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('return_request_id')->unique()->constrained('return_requests')->restrictOnDelete();
            $table->foreignId('payment_transaction_id')->nullable()->constrained('payment_transactions')->restrictOnDelete();
            $table->string('provider', 32);
            $table->string('idempotency_key', 128)->unique();
            $table->string('provider_reference', 128)->nullable();
            $table->unsignedBigInteger('amount');
            $table->string('currency', 3)->default('VND');
            $table->string('status', 32)->default('pending');
            $table->text('evidence')->nullable();
            $table->text('failure_reason')->nullable();
            $table->timestamp('processing_at')->nullable();
            $table->timestamp('refunded_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->timestamps();

            $table->index(['payment_transaction_id', 'status'], 'refund_payment_status_idx');
        });

        Schema::create('refund_transaction_attempts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('refund_transaction_id')->constrained('refund_transactions')->cascadeOnDelete();
            $table->string('operation_key', 128)->unique();
            $table->unsignedInteger('attempt_number');
            $table->string('status', 32);
            $table->json('request_payload')->nullable();
            $table->json('response_payload')->nullable();
            $table->text('failure_reason')->nullable();
            $table->timestamp('attempted_at');
            $table->timestamps();

            $table->unique(['refund_transaction_id', 'attempt_number'], 'refund_attempt_number_unique');
        });

        Schema::create('vendor_financial_holds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vendor_id')->constrained('vendors')->restrictOnDelete();
            $table->foreignId('return_request_id')->unique()->constrained('return_requests')->restrictOnDelete();
            $table->string('operation_key', 128)->unique();
            $table->unsignedBigInteger('amount');
            $table->string('currency', 3)->default('VND');
            $table->string('status', 32)->default('active');
            $table->timestamp('released_at')->nullable();
            $table->timestamp('consumed_at')->nullable();
            $table->timestamps();

            $table->index(['vendor_id', 'status']);
        });

        Schema::create('inventory_return_restorations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('return_request_item_id')->constrained('return_request_items')->restrictOnDelete();
            $table->foreignId('inventory_reservation_allocation_id')->constrained('inventory_reservation_allocations')->restrictOnDelete();
            $table->foreignId('warehouse_stock_id')->constrained('warehouse_stocks')->restrictOnDelete();
            $table->string('operation_key', 128)->unique();
            $table->unsignedInteger('quantity');
            $table->timestamp('restored_at');
            $table->timestamps();

            $table->unique(
                ['return_request_item_id', 'inventory_reservation_allocation_id'],
                'return_item_allocation_restore_unique'
            );
        });

        Schema::create('inventory_cancellation_restorations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_item_id')->constrained('order_items')->restrictOnDelete();
            $table->foreignId('inventory_reservation_allocation_id')->constrained('inventory_reservation_allocations')->restrictOnDelete();
            $table->foreignId('warehouse_stock_id')->constrained('warehouse_stocks')->restrictOnDelete();
            $table->string('operation_key', 128)->unique();
            $table->unsignedInteger('quantity');
            $table->timestamp('restored_at');
            $table->timestamps();

            $table->unique(
                ['order_item_id', 'inventory_reservation_allocation_id'],
                'cancel_item_allocation_restore_unique'
            );
        });

        Schema::create('loyalty_point_reversals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('order_id')->constrained('orders')->restrictOnDelete();
            $table->foreignId('return_request_id')->unique()->constrained('return_requests')->restrictOnDelete();
            $table->string('operation_key', 128)->unique();
            $table->unsignedInteger('points');
            $table->timestamps();
        });

        Schema::create('vendor_earning_reversals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vendor_id')->constrained('vendors')->restrictOnDelete();
            $table->foreignId('order_id')->constrained('orders')->restrictOnDelete();
            $table->foreignId('return_request_id')->unique()->constrained('return_requests')->restrictOnDelete();
            $table->string('operation_key', 128)->unique();
            $table->unsignedBigInteger('gross_amount');
            $table->unsignedBigInteger('commission_amount');
            $table->unsignedBigInteger('net_amount');
            $table->string('currency', 3)->default('VND');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vendor_earning_reversals');
        Schema::dropIfExists('loyalty_point_reversals');
        Schema::dropIfExists('inventory_cancellation_restorations');
        Schema::dropIfExists('inventory_return_restorations');
        Schema::dropIfExists('vendor_financial_holds');
        Schema::dropIfExists('refund_transaction_attempts');
        Schema::dropIfExists('refund_transactions');
        Schema::dropIfExists('return_request_transitions');
        Schema::dropIfExists('return_request_items');
        Schema::dropIfExists('return_requests');
        Schema::dropIfExists('invoice_snapshots');

        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex(['vendor_id', 'refund_status']);
            $table->dropColumn('refund_status');
        });
    }
};
