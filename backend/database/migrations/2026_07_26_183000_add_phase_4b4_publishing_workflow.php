<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('books', function (Blueprint $table) {
            $table->string('publishing_status', 32)->nullable()->after('status')->index();
            $table->unsignedInteger('publication_version')->default(1);
            $table->timestamp('submitted_for_review_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('scheduled_for')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->text('publication_feedback')->nullable();
        });

        Schema::table('book_chapters', function (Blueprint $table) {
            $table->unsignedInteger('current_revision')->default(0);
            $table->timestamp('autosaved_at')->nullable();
        });

        Schema::create('book_chapter_revisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('book_chapter_id')->constrained()->cascadeOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedInteger('revision');
            $table->string('title');
            $table->longText('content')->nullable();
            $table->boolean('is_free')->default(false);
            $table->string('source', 24)->default('manual');
            $table->timestamps();
            $table->unique(['book_chapter_id', 'revision']);
        });

        Schema::create('book_publishing_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('book_id')->constrained()->cascadeOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('from_status', 32);
            $table->string('to_status', 32);
            $table->text('reason')->nullable();
            $table->string('operation_key', 128)->unique();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->index(['book_id', 'created_at']);
        });

        Schema::create('book_published_revisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('book_id')->constrained()->cascadeOnDelete();
            $table->foreignId('published_by')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedInteger('version');
            $table->json('book_snapshot');
            $table->json('chapter_snapshot');
            $table->timestamp('published_at');
            $table->timestamps();
            $table->unique(['book_id', 'version']);
        });

        Schema::create('royalty_agreements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('book_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('version');
            $table->json('shares');
            $table->foreignId('proposed_by')->constrained('users')->cascadeOnDelete();
            $table->timestamp('proposed_at');
            $table->string('operation_key', 128)->unique();
            $table->timestamps();
            $table->unique(['book_id', 'version']);
        });

        Schema::create('royalty_agreement_acceptances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('royalty_agreement_id')->constrained()->cascadeOnDelete();
            $table->foreignId('author_id')->constrained()->restrictOnDelete();
            $table->foreignId('accepted_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('accepted_at');
            $table->string('operation_key', 128)->unique();
            $table->timestamps();
            $table->unique(['royalty_agreement_id', 'author_id'], 'royalty_acceptance_author_unique');
        });

        Schema::create('royalty_ledger_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('book_id')->constrained()->cascadeOnDelete();
            $table->foreignId('royalty_agreement_id')->constrained()->restrictOnDelete();
            $table->foreignId('author_id')->constrained()->restrictOnDelete();
            $table->foreignId('order_item_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedBigInteger('gross_amount');
            $table->decimal('share_percent', 5, 2)->unsigned();
            $table->unsignedBigInteger('royalty_amount');
            $table->string('operation_key', 128)->unique();
            $table->timestamp('earned_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('royalty_ledger_entries');
        Schema::dropIfExists('royalty_agreement_acceptances');
        Schema::dropIfExists('royalty_agreements');
        Schema::dropIfExists('book_published_revisions');
        Schema::dropIfExists('book_publishing_events');
        Schema::dropIfExists('book_chapter_revisions');
        Schema::table('book_chapters', function (Blueprint $table) {
            $table->dropColumn(['current_revision', 'autosaved_at']);
        });
        Schema::table('books', function (Blueprint $table) {
            $table->dropIndex(['publishing_status']);
            $table->dropColumn(['publishing_status', 'publication_version', 'submitted_for_review_at', 'approved_at', 'scheduled_for', 'published_at', 'publication_feedback']);
        });
    }
};
