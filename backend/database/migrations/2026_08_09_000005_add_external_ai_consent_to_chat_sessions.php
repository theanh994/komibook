<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('chat_sessions')) {
            return;
        }

        Schema::table('chat_sessions', function (Blueprint $table) {
            if (! Schema::hasColumn('chat_sessions', 'external_ai_consent_version')) {
                $table->string('external_ai_consent_version', 32)->nullable()->after('lock_version');
            }
            if (! Schema::hasColumn('chat_sessions', 'external_ai_consent_scope')) {
                $table->json('external_ai_consent_scope')->nullable()->after('external_ai_consent_version');
            }
            if (! Schema::hasColumn('chat_sessions', 'external_ai_consented_at')) {
                $table->timestamp('external_ai_consented_at')->nullable()->after('external_ai_consent_scope');
            }
            if (! Schema::hasColumn('chat_sessions', 'external_ai_consent_revoked_at')) {
                $table->timestamp('external_ai_consent_revoked_at')->nullable()->after('external_ai_consented_at');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('chat_sessions')) {
            return;
        }

        $columns = collect([
            'external_ai_consent_version',
            'external_ai_consent_scope',
            'external_ai_consented_at',
            'external_ai_consent_revoked_at',
        ])->filter(fn (string $column): bool => Schema::hasColumn('chat_sessions', $column))->all();

        if ($columns !== []) {
            Schema::table('chat_sessions', fn (Blueprint $table) => $table->dropColumn($columns));
        }
    }
};
