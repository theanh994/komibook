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

        Schema::table('chat_sessions', function (Blueprint $table): void {
            if (! Schema::hasColumn('chat_sessions', 'auto_resume_at')) {
                $table->timestamp('auto_resume_at')->nullable()->after('resolved_at');
            }
            if (! Schema::hasColumn('chat_sessions', 'auto_resume_anchor_message_id')) {
                $table->unsignedBigInteger('auto_resume_anchor_message_id')->nullable()->after('auto_resume_at');
            }
        });

        if (! Schema::hasIndex('chat_sessions', 'chat_sessions_idle_auto_resume_index')) {
            Schema::table('chat_sessions', function (Blueprint $table): void {
                $table->index(
                    ['responder_mode', 'status', 'auto_resume_at'],
                    'chat_sessions_idle_auto_resume_index'
                );
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('chat_sessions')) {
            return;
        }

        if (Schema::hasIndex('chat_sessions', 'chat_sessions_idle_auto_resume_index')) {
            Schema::table('chat_sessions', fn (Blueprint $table) => $table->dropIndex('chat_sessions_idle_auto_resume_index'));
        }

        $columns = collect(['auto_resume_at', 'auto_resume_anchor_message_id'])
            ->filter(fn (string $column): bool => Schema::hasColumn('chat_sessions', $column))
            ->all();

        if ($columns !== []) {
            Schema::table('chat_sessions', fn (Blueprint $table) => $table->dropColumn($columns));
        }
    }
};
