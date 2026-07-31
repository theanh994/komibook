<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('coupons', function (Blueprint $table) {
            $table->foreignId('vendor_id')->nullable()->after('id')->constrained()->nullOnDelete();
            $table->index(['vendor_id', 'status']);
        });

        Schema::create('vendor_flash_sale_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vendor_id')->constrained()->cascadeOnDelete();
            $table->foreignId('book_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->dateTime('preferred_start_time');
            $table->dateTime('preferred_end_time');
            $table->decimal('discount_percent', 5, 2);
            $table->unsignedInteger('max_quantity')->nullable();
            $table->string('status', 24)->default('pending');
            $table->text('vendor_note')->nullable();
            $table->text('decision_reason')->nullable();
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->timestamps();
            $table->index(['vendor_id', 'status']);
            $table->index(['status', 'preferred_start_time']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vendor_flash_sale_requests');
        Schema::table('coupons', function (Blueprint $table) {
            $table->dropIndex(['vendor_id', 'status']);
            $table->dropConstrainedForeignId('vendor_id');
        });
    }
};
