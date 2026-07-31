<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vendors', function (Blueprint $table) {
            $table->unsignedBigInteger('views_count')->default(0)->after('description')->index();
        });

        Schema::create('vendor_follows', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('vendor_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['user_id', 'vendor_id']);
            $table->index(['vendor_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vendor_follows');
        Schema::table('vendors', function (Blueprint $table) {
            $table->dropIndex(['views_count']);
            $table->dropColumn('views_count');
        });
    }
};
