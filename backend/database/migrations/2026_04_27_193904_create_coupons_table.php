<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('coupons', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->decimal('discount_percent', 5, 2)->unsigned(); // e.g. 10.00
            $table->integer('min_order_value')->unsigned()->default(0);
            $table->integer('max_discount_amount')->unsigned()->nullable();
            $table->timestamp('valid_until')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('coupons');
    }
};
