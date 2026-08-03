<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('chat_sessions') || Schema::hasColumn('chat_sessions', 'conversation_key')) {
            return;
        }

        Schema::table('chat_sessions', function (Blueprint $table) {
            $table->string('conversation_key', 160)->nullable()->after('guest_token_hash');
        });

        $canonicalByKey = [];

        DB::table('chat_sessions')->orderBy('id')->get()->each(function (object $session) use (&$canonicalByKey): void {
            $scope = $session->target_type === 'vendor' && $session->vendor_id
                ? 'vendor:'.$session->vendor_id
                : 'platform';
            $identity = $session->user_id
                ? 'user:'.$session->user_id
                : ($session->guest_token_hash ? 'guest:'.$session->guest_token_hash : 'session:'.$session->id);
            $key = $identity.':'.$scope;

            if (! isset($canonicalByKey[$key])) {
                $canonicalByKey[$key] = $session->id;
                DB::table('chat_sessions')->where('id', $session->id)->update(['conversation_key' => $key]);

                return;
            }

            $canonicalId = $canonicalByKey[$key];
            DB::table('chat_messages')->where('chat_session_id', $session->id)->update(['chat_session_id' => $canonicalId]);

            $canonical = DB::table('chat_sessions')->where('id', $canonicalId)->first();
            if ($canonical && ($session->last_message_at ?? '') >= ($canonical->last_message_at ?? '')) {
                DB::table('chat_sessions')->where('id', $canonicalId)->update([
                    'assigned_user_id' => $session->assigned_user_id,
                    'support_ticket_id' => $session->support_ticket_id,
                    'responder_mode' => $session->responder_mode,
                    'status' => $session->status,
                    'subject' => $session->subject,
                    'category' => $session->category,
                    'lock_version' => max((int) $canonical->lock_version, (int) $session->lock_version),
                    'last_message_at' => $session->last_message_at,
                    'assigned_at' => $session->assigned_at,
                    'resolved_at' => $session->resolved_at,
                    'updated_at' => $session->updated_at,
                ]);
            }

            DB::table('chat_sessions')->where('id', $session->id)->delete();
        });

        Schema::table('chat_sessions', function (Blueprint $table) {
            $table->unique('conversation_key');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('chat_sessions', 'conversation_key')) {
            return;
        }

        Schema::table('chat_sessions', function (Blueprint $table) {
            $table->dropUnique(['conversation_key']);
            $table->dropColumn('conversation_key');
        });
    }
};
