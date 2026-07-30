<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('coupons', function (Blueprint $table) {
            $table->foreignId('author_id')->nullable()->constrained()->cascadeOnDelete();
            $table->json('scope_book_ids')->nullable();
            $table->string('stacking_policy', 20)->default('deny');
            $table->string('status', 20)->default('active');
            $table->index(['author_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::table('coupons', function (Blueprint $table) {
            $table->dropIndex(['author_id', 'status']);
            $table->dropConstrainedForeignId('author_id');
            $table->dropColumn(['scope_book_ids', 'stacking_policy', 'status']);
        });
    }
};
