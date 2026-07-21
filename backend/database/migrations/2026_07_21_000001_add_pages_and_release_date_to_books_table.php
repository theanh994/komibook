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
        Schema::table('books', function (Blueprint $table) {
            if (!Schema::hasColumn('books', 'pages')) {
                $table->unsignedInteger('pages')->nullable()->after('target_age');
            }
            if (!Schema::hasColumn('books', 'release_date')) {
                $table->string('release_date')->nullable()->after('pages');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('books', function (Blueprint $table) {
            $table->dropColumn(['pages', 'release_date']);
        });
    }
};
