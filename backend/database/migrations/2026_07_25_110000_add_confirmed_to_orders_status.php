<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->enum('status', [
                'pending',
                'confirmed',
                'processing',
                'shipped',
                'completed',
                'cancelled',
            ])->default('pending')->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('orders')->where('status', 'confirmed')->update(['status' => 'processing']);

        Schema::table('orders', function (Blueprint $table) {
            $table->enum('status', [
                'pending',
                'processing',
                'shipped',
                'completed',
                'cancelled',
            ])->default('pending')->change();
        });
    }
};
