<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('payment_provider_settings')) {
            return;
        }

        DB::table('payment_provider_settings')
            ->whereIn('provider', ['momo', 'payos'])
            ->delete();

        DB::table('payment_provider_settings')
            ->where('provider', 'vnpay')
            ->update([
                'mode' => 'sandbox',
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        if (! Schema::hasTable('payment_provider_settings')) {
            return;
        }

        DB::table('payment_provider_settings')
            ->where('provider', 'vnpay')
            ->where('mode', 'sandbox')
            ->update([
                'mode' => 'existing',
                'updated_at' => now(),
            ]);
    }
};
