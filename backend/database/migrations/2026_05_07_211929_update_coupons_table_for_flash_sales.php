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
        Schema::table('coupons', function (Blueprint $table) {
            $table->dateTime('start_time')->nullable()->after('max_discount_amount');
            $table->dateTime('end_time')->nullable()->after('start_time');
            $table->foreignId('category_id')->nullable()->constrained()->onDelete('set null')->after('end_time');
            $table->integer('usage_limit')->default(0)->after('category_id');
            $table->integer('used_count')->default(0)->after('usage_limit');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('coupons', function (Blueprint $table) {
            $table->dropForeign(['category_id']);
            $table->dropColumn(['start_time', 'end_time', 'category_id', 'usage_limit', 'used_count']);
        });
    }
};
