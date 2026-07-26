<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('flash_sales', function (Blueprint $table) {
            $table->string('status', 32)->default('draft')->index();
            $table->string('timezone', 64)->default('Asia/Ho_Chi_Minh');
            $table->string('coupon_stacking_policy', 16)->default('deny');
            $table->unsignedInteger('priority')->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
        });
        Schema::table('flash_sale_books', function (Blueprint $table) {
            $table->foreignId('vendor_id')->nullable()->after('book_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('sale_price')->nullable()->after('discount_percent');
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('decision_reason')->nullable();
            $table->unique(['flash_sale_id', 'book_id']);
        });
        Schema::create('flash_sale_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('flash_sale_id')->constrained()->restrictOnDelete();
            $table->foreignId('flash_sale_book_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action', 64);
            $table->string('from_status', 32)->nullable();
            $table->string('to_status', 32)->nullable();
            $table->text('reason')->nullable();
            $table->json('snapshot')->nullable();
            $table->string('operation_key', 128)->unique();
            $table->timestamps();
        });
        Schema::table('order_items', function (Blueprint $table) {
            $table->unsignedBigInteger('list_unit_price')->nullable()->after('price');
            $table->unsignedBigInteger('promotion_discount_amount')->default(0);
            $table->foreignId('flash_sale_book_id')->nullable()->constrained()->restrictOnDelete();
            $table->json('promotion_snapshot')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropForeign(['flash_sale_book_id']);
            $table->dropColumn(['list_unit_price', 'promotion_discount_amount', 'flash_sale_book_id', 'promotion_snapshot']);
        });
        Schema::dropIfExists('flash_sale_events');
        Schema::table('flash_sale_books', function (Blueprint $table) {
            $table->dropUnique(['flash_sale_id', 'book_id']);
            $table->dropForeign(['vendor_id']);
            $table->dropForeign(['decided_by']);
            $table->dropColumn(['vendor_id', 'sale_price', 'decided_by', 'decision_reason']);
        });
        Schema::table('flash_sales', function (Blueprint $table) {
            $table->dropIndex(['status']);
            $table->dropForeign(['created_by']);
            $table->dropColumn(['status', 'timezone', 'coupon_stacking_policy', 'priority', 'created_by']);
        });
    }
};
