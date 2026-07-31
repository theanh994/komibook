<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vendor_flash_sale_requests', function (Blueprint $table) {
            $table->uuid('campaign_key')->nullable()->after('vendor_id')->index();
            $table->json('groups')->nullable()->after('book_id');
        });
    }

    public function down(): void
    {
        Schema::table('vendor_flash_sale_requests', function (Blueprint $table) {
            $table->dropIndex(['campaign_key']);
        });
        Schema::table('vendor_flash_sale_requests', function (Blueprint $table) {
            $table->dropColumn(['campaign_key', 'groups']);
        });
    }
};
