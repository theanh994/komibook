<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ChatSchemaUpgradeTest extends TestCase
{
    use RefreshDatabase;

    public function test_legacy_chat_rows_are_upgraded_without_losing_the_conversation(): void
    {
        Schema::disableForeignKeyConstraints();
        Schema::dropIfExists('chat_messages');
        Schema::dropIfExists('chat_sessions');

        Schema::create('chat_sessions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->unsignedBigInteger('vendor_id')->nullable();
            $table->unsignedBigInteger('assigned_admin_id')->nullable();
            $table->string('session_token')->nullable()->unique();
            $table->string('status')->default('bot_active');
            $table->timestamp('last_activity_at')->nullable();
            $table->timestamps();
        });
        Schema::create('chat_messages', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('chat_session_id');
            $table->string('sender_type');
            $table->unsignedBigInteger('sender_id')->nullable();
            $table->text('message');
            $table->json('metadata')->nullable();
            $table->boolean('is_read')->default(false);
            $table->timestamps();
        });
        Schema::enableForeignKeyConstraints();

        $timestamp = now()->subMinute();
        $sessionId = DB::table('chat_sessions')->insertGetId([
            'session_token' => 'legacy-guest-token',
            'status' => 'waiting_human',
            'last_activity_at' => $timestamp,
            'created_at' => $timestamp,
            'updated_at' => $timestamp,
        ]);
        DB::table('chat_messages')->insert([
            'chat_session_id' => $sessionId,
            'sender_type' => 'user',
            'message' => 'Tôi cần hỗ trợ.',
            'created_at' => $timestamp,
            'updated_at' => $timestamp,
        ]);

        try {
            $migration = require database_path('migrations/2026_08_03_040000_upgrade_legacy_chat_support_schema.php');
            $migration->up();
            $persistentConversationMigration = require database_path('migrations/2026_08_03_050000_make_chat_conversations_persistent.php');
            $persistentConversationMigration->up();
            $externalAiConsentMigration = require database_path('migrations/2026_08_09_000005_add_external_ai_consent_to_chat_sessions.php');
            $externalAiConsentMigration->up();

            $this->assertTrue(Schema::hasColumns('chat_sessions', [
                'target_type',
                'responder_mode',
                'assigned_user_id',
                'support_ticket_id',
                'guest_token_hash',
                'conversation_key',
                'last_message_at',
                'external_ai_consent_version',
                'external_ai_consent_scope',
                'external_ai_consented_at',
                'external_ai_consent_revoked_at',
            ]));
            $this->assertTrue(Schema::hasColumns('chat_messages', ['feedback', 'feedback_comment']));
            $this->assertDatabaseHas('chat_sessions', [
                'id' => $sessionId,
                'target_type' => 'platform',
                'responder_mode' => 'human',
                'status' => 'queued',
                'guest_token_hash' => hash('sha256', 'legacy-guest-token'),
                'conversation_key' => 'guest:'.hash('sha256', 'legacy-guest-token').':platform',
                'external_ai_consent_version' => null,
                'external_ai_consent_scope' => null,
                'external_ai_consented_at' => null,
                'external_ai_consent_revoked_at' => null,
            ]);
            $this->assertDatabaseHas('chat_messages', [
                'chat_session_id' => $sessionId,
                'sender_type' => 'customer',
                'message' => 'Tôi cần hỗ trợ.',
            ]);
            $externalAiConsentMigration->down();
            $this->assertFalse(Schema::hasColumn('chat_sessions', 'external_ai_consent_version'));
            $externalAiConsentMigration->up();
            $this->assertTrue(Schema::hasColumns('chat_sessions', [
                'external_ai_consent_version',
                'external_ai_consent_scope',
                'external_ai_consented_at',
                'external_ai_consent_revoked_at',
            ]));
        } finally {
            Schema::disableForeignKeyConstraints();
            Schema::dropIfExists('chat_messages');
            Schema::dropIfExists('chat_sessions');
            Schema::enableForeignKeyConstraints();

            $canonicalMigration = require database_path('migrations/2026_08_03_030000_create_chat_tables.php');
            $canonicalMigration->up();
            $persistentConversationMigration = require database_path('migrations/2026_08_03_050000_make_chat_conversations_persistent.php');
            $persistentConversationMigration->up();
            $externalAiConsentMigration = require database_path('migrations/2026_08_09_000005_add_external_ai_consent_to_chat_sessions.php');
            $externalAiConsentMigration->up();
        }
    }
}
