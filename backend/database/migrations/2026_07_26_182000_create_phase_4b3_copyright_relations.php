<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('book_authors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('book_id')->constrained()->cascadeOnDelete();
            $table->foreignId('author_id')->constrained()->cascadeOnDelete();
            $table->foreignId('invited_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('role', 32)->default('coauthor');
            $table->string('status', 24)->default('pending');
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->text('reason')->nullable();
            $table->string('operation_key', 128)->unique();
            $table->timestamps();
            $table->unique(['book_id', 'author_id']);
        });

        Schema::create('author_delegations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('grantor_author_id')->constrained('authors')->cascadeOnDelete();
            $table->foreignId('delegate_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('book_id')->nullable()->constrained()->cascadeOnDelete();
            $table->json('permissions');
            $table->string('status', 24)->default('pending');
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->text('reason')->nullable();
            $table->string('operation_key', 128)->unique();
            $table->timestamps();
            $table->index(['grantor_author_id', 'book_id', 'status']);
        });

        Schema::create('copyright_claims', function (Blueprint $table) {
            $table->id();
            $table->foreignId('book_id')->constrained()->cascadeOnDelete();
            $table->foreignId('owner_author_id')->constrained('authors')->cascadeOnDelete();
            $table->string('registration_type', 32);
            $table->string('registration_number')->nullable();
            $table->json('rights_scope');
            $table->json('territory_scope');
            $table->date('valid_from')->nullable();
            $table->date('valid_until')->nullable();
            $table->string('evidence_document');
            $table->string('status', 32)->default('draft')->index();
            $table->unsignedInteger('application_version')->default(1);
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('review_started_at')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->timestamp('changes_requested_at')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->timestamp('disputed_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->text('last_review_reason')->nullable();
            $table->timestamps();
            $table->index(['book_id', 'owner_author_id']);
        });

        Schema::create('copyright_claim_authors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('copyright_claim_id')->constrained()->cascadeOnDelete();
            $table->foreignId('author_id')->constrained()->cascadeOnDelete();
            $table->string('role', 32)->default('coauthor');
            $table->decimal('share_percent', 5, 2)->unsigned()->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamps();
            $table->unique(['copyright_claim_id', 'author_id']);
        });

        Schema::create('copyright_claim_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('copyright_claim_id')->constrained()->cascadeOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('from_status', 32);
            $table->string('to_status', 32);
            $table->text('reason')->nullable();
            $table->string('operation_key', 128)->unique();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->index(['copyright_claim_id', 'created_at']);
        });

        Schema::create('rights_relation_events', function (Blueprint $table) {
            $table->id();
            $table->string('subject_type', 32);
            $table->unsignedBigInteger('subject_id');
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action', 32);
            $table->text('reason')->nullable();
            $table->string('operation_key', 128)->unique();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->index(['subject_type', 'subject_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rights_relation_events');
        Schema::dropIfExists('copyright_claim_events');
        Schema::dropIfExists('copyright_claim_authors');
        Schema::dropIfExists('copyright_claims');
        Schema::dropIfExists('author_delegations');
        Schema::dropIfExists('book_authors');
    }
};
