<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('commerce_fee_schedules', function (Blueprint $table) {
            $table->id();
            $table->decimal('commission_rate', 5, 2);
            $table->decimal('service_fee_rate', 5, 2);
            $table->timestamp('effective_at')->unique();
            $table->foreignId('actor_id')->constrained('users')->restrictOnDelete();
            $table->text('reason');
            $table->timestamps();
        });

        Schema::create('commerce_fee_schedule_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('commerce_fee_schedule_id')->constrained()->restrictOnDelete();
            $table->foreignId('actor_id')->constrained('users')->restrictOnDelete();
            $table->string('action', 32);
            $table->json('snapshot');
            $table->string('operation_key', 128)->unique();
            $table->timestamps();
        });

        Schema::table('checkout_session_orders', function (Blueprint $table) {
            $table->foreignId('commerce_fee_schedule_id')->nullable()->after('vendor_id')->constrained()->restrictOnDelete();
            $table->decimal('service_fee_rate', 5, 2)->default(0)->after('fee_amount');
        });
    }

    public function down(): void
    {
        Schema::table('checkout_session_orders', function (Blueprint $table) {
            $table->dropForeign(['commerce_fee_schedule_id']);
            $table->dropColumn(['commerce_fee_schedule_id', 'service_fee_rate']);
        });
        Schema::dropIfExists('commerce_fee_schedule_events');
        Schema::dropIfExists('commerce_fee_schedules');
    }
};
