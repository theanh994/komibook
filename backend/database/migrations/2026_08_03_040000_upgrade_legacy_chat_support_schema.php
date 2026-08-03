<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('chat_sessions') || ! Schema::hasTable('chat_messages')) {
            return;
        }

        $isLegacySchema = Schema::hasColumn('chat_sessions', 'assigned_admin_id');

        if ($isLegacySchema && DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE chat_sessions MODIFY status VARCHAR(30) NOT NULL DEFAULT 'open'");
            DB::statement('ALTER TABLE chat_messages MODIFY sender_type VARCHAR(20) NOT NULL');
        }

        Schema::table('chat_sessions', function (Blueprint $table) {
            if (! Schema::hasColumn('chat_sessions', 'assigned_user_id')) {
                $table->foreignId('assigned_user_id')->nullable()->after('vendor_id')->constrained('users')->nullOnDelete();
            }
            if (! Schema::hasColumn('chat_sessions', 'support_ticket_id')) {
                $table->foreignId('support_ticket_id')->nullable()->after('assigned_user_id')->constrained('support_tickets')->nullOnDelete();
            }
            if (! Schema::hasColumn('chat_sessions', 'guest_token_hash')) {
                $table->char('guest_token_hash', 64)->nullable()->after('support_ticket_id')->index();
            }
            if (! Schema::hasColumn('chat_sessions', 'target_type')) {
                $table->string('target_type', 20)->default('platform')->after('guest_token_hash');
            }
            if (! Schema::hasColumn('chat_sessions', 'responder_mode')) {
                $table->string('responder_mode', 20)->default('ai')->after('target_type');
            }
            if (! Schema::hasColumn('chat_sessions', 'subject')) {
                $table->string('subject')->nullable()->after('status');
            }
            if (! Schema::hasColumn('chat_sessions', 'category')) {
                $table->string('category', 100)->nullable()->after('subject');
            }
            if (! Schema::hasColumn('chat_sessions', 'lock_version')) {
                $table->unsignedInteger('lock_version')->default(0)->after('category');
            }
            if (! Schema::hasColumn('chat_sessions', 'last_message_at')) {
                $table->timestamp('last_message_at')->nullable()->after('lock_version');
            }
            if (! Schema::hasColumn('chat_sessions', 'assigned_at')) {
                $table->timestamp('assigned_at')->nullable()->after('last_message_at');
            }
            if (! Schema::hasColumn('chat_sessions', 'resolved_at')) {
                $table->timestamp('resolved_at')->nullable()->after('assigned_at');
            }
        });

        Schema::table('chat_messages', function (Blueprint $table) {
            if (! Schema::hasColumn('chat_messages', 'feedback')) {
                $table->string('feedback', 20)->nullable()->after('metadata');
            }
            if (! Schema::hasColumn('chat_messages', 'feedback_comment')) {
                $table->string('feedback_comment', 500)->nullable()->after('feedback');
            }
        });

        if ($isLegacySchema) {
            DB::table('chat_sessions')->update([
                'assigned_user_id' => DB::raw('assigned_admin_id'),
                'target_type' => DB::raw("CASE WHEN vendor_id IS NULL THEN 'platform' ELSE 'vendor' END"),
                'responder_mode' => DB::raw("CASE WHEN status = 'bot_active' THEN 'ai' ELSE 'human' END"),
                'last_message_at' => DB::raw('COALESCE(last_activity_at, updated_at, created_at)'),
                'assigned_at' => DB::raw('CASE WHEN assigned_admin_id IS NULL THEN NULL ELSE COALESCE(updated_at, created_at) END'),
                'resolved_at' => DB::raw("CASE WHEN status = 'closed' THEN COALESCE(updated_at, created_at) ELSE NULL END"),
            ]);

            if (DB::getDriverName() === 'mysql') {
                DB::statement('UPDATE chat_sessions SET guest_token_hash = SHA2(session_token, 256) WHERE session_token IS NOT NULL');
            } else {
                DB::table('chat_sessions')->whereNotNull('session_token')->orderBy('id')->each(function (object $session) {
                    DB::table('chat_sessions')->where('id', $session->id)->update([
                        'guest_token_hash' => hash('sha256', $session->session_token),
                    ]);
                });
            }

            DB::table('chat_sessions')->update([
                'status' => DB::raw("CASE status WHEN 'bot_active' THEN 'open' WHEN 'waiting_human' THEN 'queued' WHEN 'human_active' THEN 'assigned' ELSE 'closed' END"),
            ]);
            DB::table('chat_messages')->update([
                'sender_type' => DB::raw("CASE sender_type WHEN 'user' THEN 'customer' WHEN 'bot' THEN 'ai' ELSE sender_type END"),
            ]);

            Schema::table('chat_sessions', function (Blueprint $table) {
                $table->index(['target_type', 'vendor_id', 'status']);
                $table->index(['user_id', 'status']);
            });
            Schema::table('chat_messages', function (Blueprint $table) {
                $table->index(['chat_session_id', 'id']);
                $table->index(['chat_session_id', 'is_read']);
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasColumn('chat_sessions', 'assigned_admin_id')) {
            return;
        }

        DB::table('chat_sessions')->update([
            'assigned_admin_id' => DB::raw('assigned_user_id'),
            'last_activity_at' => DB::raw('last_message_at'),
            'status' => DB::raw("CASE status WHEN 'open' THEN 'bot_active' WHEN 'queued' THEN 'waiting_human' WHEN 'assigned' THEN 'human_active' WHEN 'waiting_customer' THEN 'human_active' ELSE 'closed' END"),
        ]);
        DB::table('chat_messages')->update([
            'sender_type' => DB::raw("CASE sender_type WHEN 'customer' THEN 'user' WHEN 'ai' THEN 'bot' WHEN 'system' THEN 'bot' ELSE sender_type END"),
        ]);

        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE chat_sessions MODIFY status ENUM('bot_active','waiting_human','human_active','closed') NOT NULL DEFAULT 'bot_active'");
            DB::statement("ALTER TABLE chat_messages MODIFY sender_type ENUM('user','bot','vendor','admin') NOT NULL");
        }

        Schema::table('chat_messages', function (Blueprint $table) {
            $table->dropIndex(['chat_session_id', 'id']);
            $table->dropIndex(['chat_session_id', 'is_read']);
            $table->dropColumn(['feedback', 'feedback_comment']);
        });

        Schema::table('chat_sessions', function (Blueprint $table) {
            $table->dropIndex(['target_type', 'vendor_id', 'status']);
            $table->dropIndex(['user_id', 'status']);
            $table->dropForeign(['assigned_user_id']);
            $table->dropForeign(['support_ticket_id']);
            $table->dropIndex(['guest_token_hash']);
            $table->dropColumn([
                'assigned_user_id',
                'support_ticket_id',
                'guest_token_hash',
                'target_type',
                'responder_mode',
                'subject',
                'category',
                'lock_version',
                'last_message_at',
                'assigned_at',
                'resolved_at',
            ]);
        });
    }
};
