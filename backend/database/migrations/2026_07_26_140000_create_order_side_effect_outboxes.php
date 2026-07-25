<?php

declare(strict_types=1);

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
        Schema::create('order_side_effect_outboxes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->onDelete('restrict');
            $table->string('operation_key')->unique();
            $table->string('effect_type');
            $table->json('payload');
            $table->string('status')->default('pending');
            $table->unsignedInteger('attempt_count')->default(0);
            $table->timestamp('available_at')->nullable();
            $table->timestamp('locked_at')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamps();

            $table->unique(['order_id', 'effect_type']);
            $table->index(['status', 'available_at']);
        });

        Schema::table('user_notifications', function (Blueprint $table) {
            $table->string('operation_key')->nullable()->unique()->after('id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('user_notifications', function (Blueprint $table) {
            $table->dropUnique('user_notifications_operation_key_unique');
            $table->dropColumn('operation_key');
        });

        Schema::dropIfExists('order_side_effect_outboxes');
    }
};
