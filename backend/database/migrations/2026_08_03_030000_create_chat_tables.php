<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chat_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('vendor_id')->nullable()->constrained('vendors')->nullOnDelete();
            $table->foreignId('assigned_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('support_ticket_id')->nullable()->constrained('support_tickets')->nullOnDelete();
            $table->char('guest_token_hash', 64)->nullable()->index();
            $table->string('target_type', 20)->default('platform');
            $table->string('responder_mode', 20)->default('ai');
            $table->string('status', 30)->default('open');
            $table->string('subject')->nullable();
            $table->string('category', 100)->nullable();
            $table->unsignedInteger('lock_version')->default(0);
            $table->timestamp('last_message_at')->nullable();
            $table->timestamp('assigned_at')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->index(['target_type', 'vendor_id', 'status']);
            $table->index(['user_id', 'status']);
        });

        Schema::create('chat_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('chat_session_id')->constrained('chat_sessions')->cascadeOnDelete();
            $table->string('sender_type', 20);
            $table->foreignId('sender_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('message');
            $table->json('metadata')->nullable();
            $table->string('feedback', 20)->nullable();
            $table->string('feedback_comment', 500)->nullable();
            $table->boolean('is_read')->default(false);
            $table->timestamps();

            $table->index(['chat_session_id', 'id']);
            $table->index(['chat_session_id', 'is_read']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chat_messages');
        Schema::dropIfExists('chat_sessions');
    }
};
