<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('marketing_consent_at')->nullable()->after('email_verified_at');
            $table->timestamp('marketing_opt_out_at')->nullable()->after('marketing_consent_at');
        });

        Schema::table('payout_requests', function (Blueprint $table) {
            $table->string('operation_key', 100)->nullable()->unique()->after('vendor_id');
            $table->foreignId('reviewed_by')->nullable()->after('status')->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable()->after('reviewed_by');
            $table->text('review_reason')->nullable()->after('reviewed_at');
            $table->timestamp('processing_at')->nullable()->after('review_reason');
            $table->timestamp('completed_at')->nullable()->after('processing_at');
            $table->timestamp('rejected_at')->nullable()->after('completed_at');
            $table->string('transfer_reference', 120)->nullable()->after('rejected_at');
            $table->string('transfer_evidence', 500)->nullable()->after('transfer_reference');
        });

        Schema::create('payout_ledger_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payout_request_id')->constrained()->cascadeOnDelete();
            $table->foreignId('vendor_id')->constrained()->cascadeOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('entry_type', 32);
            $table->unsignedBigInteger('amount');
            $table->bigInteger('balance_before');
            $table->bigInteger('balance_after');
            $table->string('operation_key', 120)->unique();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->index(['vendor_id', 'created_at']);
        });

        Schema::create('payout_transitions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payout_request_id')->constrained()->cascadeOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('from_status', 32)->nullable();
            $table->string('to_status', 32);
            $table->text('reason')->nullable();
            $table->string('operation_key', 120)->unique();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        DB::table('payout_requests')->orderBy('id')->each(function ($payout) {
            $balance = (int) (DB::table('vendors')->where('id', $payout->vendor_id)->value('balance') ?? 0);
            DB::table('payout_ledger_entries')->insert([
                'payout_request_id' => $payout->id, 'vendor_id' => $payout->vendor_id, 'actor_id' => null,
                'entry_type' => 'legacy_import', 'amount' => $payout->amount,
                'balance_before' => $balance, 'balance_after' => $balance,
                'operation_key' => "payout:{$payout->id}:legacy-import",
                'metadata' => json_encode(['legacy_status' => $payout->status, 'balance_snapshot_available' => false]),
                'created_at' => now(), 'updated_at' => now(),
            ]);
            DB::table('payout_transitions')->insert([
                'payout_request_id' => $payout->id, 'actor_id' => null, 'from_status' => null,
                'to_status' => $payout->status, 'reason' => 'Legacy state imported during Phase 4A.3.',
                'operation_key' => "payout:{$payout->id}:legacy-state", 'metadata' => null,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        });

        Schema::table('notification_campaigns', function (Blueprint $table) {
            $table->string('dispatch_status', 32)->default('idle')->after('status');
            $table->string('dispatch_key', 100)->nullable()->unique()->after('dispatch_status');
            $table->unsignedInteger('audience_count')->default(0)->after('sent_count');
            $table->unsignedInteger('failed_count')->default(0)->after('audience_count');
            $table->unsignedInteger('chunk_count')->default(0)->after('failed_count');
            $table->unsignedInteger('completed_chunk_count')->default(0)->after('chunk_count');
            $table->unsignedInteger('failed_chunk_count')->default(0)->after('completed_chunk_count');
            $table->timestamp('dispatch_started_at')->nullable()->after('failed_chunk_count');
            $table->timestamp('dispatch_completed_at')->nullable()->after('dispatch_started_at');
            $table->text('last_error')->nullable()->after('dispatch_completed_at');
            $table->boolean('telemetry_available')->default(false)->after('last_error');
            $table->index(['status', 'scheduled_at', 'dispatch_status'], 'campaign_due_dispatch_index');
        });

        Schema::create('notification_campaign_chunks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('notification_campaign_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('chunk_number');
            $table->json('user_ids');
            $table->string('status', 32)->default('pending');
            $table->unsignedInteger('attempt_count')->default(0);
            $table->unsignedInteger('success_count')->default(0);
            $table->unsignedInteger('failure_count')->default(0);
            $table->text('last_error')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
            $table->unique(['notification_campaign_id', 'chunk_number'], 'campaign_chunk_unique');
            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_campaign_chunks');
        Schema::table('notification_campaigns', function (Blueprint $table) {
            $table->dropIndex('campaign_due_dispatch_index');
            $table->dropUnique(['dispatch_key']);
            $table->dropColumn(['dispatch_status', 'dispatch_key', 'audience_count', 'failed_count', 'chunk_count', 'completed_chunk_count', 'failed_chunk_count', 'dispatch_started_at', 'dispatch_completed_at', 'last_error', 'telemetry_available']);
        });
        Schema::dropIfExists('payout_transitions');
        Schema::dropIfExists('payout_ledger_entries');
        Schema::table('payout_requests', function (Blueprint $table) {
            $table->dropUnique(['operation_key']);
            $table->dropConstrainedForeignId('reviewed_by');
            $table->dropColumn(['operation_key', 'reviewed_at', 'review_reason', 'processing_at', 'completed_at', 'rejected_at', 'transfer_reference', 'transfer_evidence']);
        });
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['marketing_consent_at', 'marketing_opt_out_at']);
        });
    }
};
