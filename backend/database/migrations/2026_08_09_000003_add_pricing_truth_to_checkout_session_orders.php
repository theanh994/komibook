<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('checkout_session_orders', function (Blueprint $table) {
            // Nullable preserves the explicit unknown state of every legacy row.
            $table->unsignedBigInteger('coupon_id')->nullable()->after('vendor_id');
            $table->string('coupon_type', 20)->nullable()->after('coupon_id');
            $table->string('coupon_code', 100)->nullable()->after('coupon_type');
            $table->unsignedBigInteger('coupon_discount_amount')->nullable()->after('discount_amount');
            $table->unsignedBigInteger('membership_discount_amount')->nullable()->after('coupon_discount_amount');
            $table->unsignedBigInteger('shipping_fee_amount')->nullable()->after('membership_discount_amount');
            $table->json('pricing_policy_snapshot')->nullable()->after('shipping_fee_amount');
            $table->index('coupon_id');
        });
    }

    public function down(): void
    {
        Schema::table('checkout_session_orders', function (Blueprint $table) {
            $table->dropIndex(['coupon_id']);
            $table->dropColumn([
                'coupon_id',
                'coupon_type',
                'coupon_code',
                'coupon_discount_amount',
                'membership_discount_amount',
                'shipping_fee_amount',
                'pricing_policy_snapshot',
            ]);
        });
    }
};
