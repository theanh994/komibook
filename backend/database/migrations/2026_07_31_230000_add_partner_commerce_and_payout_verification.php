<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('organization_memberships', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('role', 32)->default('viewer');
            $table->string('status', 32)->default('active')->index();
            $table->foreignId('invited_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['user_id', 'organization_id']);
            $table->index(['organization_id', 'status', 'role'], 'organization_membership_lookup');
        });

        Schema::create('organization_distribution_agreements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('publisher_organization_id');
            $table->foreignId('distributor_organization_id');
            $table->string('status', 32)->default('draft')->index();
            $table->json('scope')->nullable();
            $table->string('evidence_document')->nullable();
            $table->date('effective_from')->nullable();
            $table->date('effective_until')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->text('last_review_reason')->nullable();
            $table->string('operation_key', 128)->unique();
            $table->timestamps();
            $table->foreign('publisher_organization_id', 'distribution_agreement_publisher_fk')
                ->references('id')->on('organizations')->restrictOnDelete();
            $table->foreign('distributor_organization_id', 'distribution_agreement_distributor_fk')
                ->references('id')->on('organizations')->restrictOnDelete();
            $table->index(
                ['publisher_organization_id', 'distributor_organization_id', 'status'],
                'distribution_agreement_lookup'
            );
        });

        Schema::create('organization_distribution_agreement_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_distribution_agreement_id')
                ->constrained('organization_distribution_agreements', 'id', 'distribution_agreement_event_fk')
                ->cascadeOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('from_status', 32);
            $table->string('to_status', 32);
            $table->text('reason')->nullable();
            $table->string('operation_key', 128)->unique();
            $table->timestamps();
        });

        Schema::table('vendors', function (Blueprint $table) {
            $table->string('payout_bank_status', 32)->default('unverified')->after('payout_bank_holder')->index();
            $table->timestamp('payout_bank_verified_at')->nullable()->after('payout_bank_status');
            $table->foreignId('payout_bank_verified_by')->nullable()->after('payout_bank_verified_at')
                ->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('vendors', function (Blueprint $table) {
            $table->dropForeign(['payout_bank_verified_by']);
            $table->dropIndex(['payout_bank_status']);
            $table->dropColumn(['payout_bank_status', 'payout_bank_verified_at', 'payout_bank_verified_by']);
        });

        Schema::dropIfExists('organization_distribution_agreement_events');
        Schema::dropIfExists('organization_distribution_agreements');
        Schema::dropIfExists('organization_memberships');
    }
};
