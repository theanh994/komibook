<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $duplicates = DB::table('authors')
            ->select('user_id', DB::raw('COUNT(*) as aggregate'))
            ->groupBy('user_id')
            ->havingRaw('COUNT(*) > 1')
            ->pluck('user_id');

        if ($duplicates->isNotEmpty()) {
            throw new RuntimeException('Cannot add unique author ownership: duplicate authors.user_id values: '.$duplicates->implode(', '));
        }

        Schema::table('authors', function (Blueprint $table) {
            $table->string('pen_name')->nullable()->change();
            $table->string('identity_document')->nullable()->change();
            $table->string('bank_account_number')->nullable()->change();
            $table->string('bank_name')->nullable()->change();
            $table->string('bank_holder_name')->nullable()->change();
            $table->string('onboarding_status', 32)->default('draft')->after('status')->index();
            $table->unsignedInteger('application_version')->default(1)->after('onboarding_status');
            $table->timestamp('terms_accepted_at')->nullable()->after('application_version');
            $table->timestamp('submitted_at')->nullable()->after('terms_accepted_at');
            $table->timestamp('review_started_at')->nullable()->after('submitted_at');
            $table->timestamp('approved_at')->nullable()->after('review_started_at');
            $table->timestamp('changes_requested_at')->nullable()->after('approved_at');
            $table->timestamp('rejected_at')->nullable()->after('changes_requested_at');
            $table->timestamp('suspended_at')->nullable()->after('rejected_at');
            $table->timestamp('revoked_at')->nullable()->after('suspended_at');
            $table->text('last_review_reason')->nullable()->after('revoked_at');
            $table->unique('user_id', 'authors_user_id_unique');
        });

        foreach (['identity_document', 'bank_account_number', 'bank_name'] as $field) {
            DB::table('authors')->where($field, 'Pending')->update([$field => null]);
        }

        DB::table('authors')->orderBy('id')->each(function (object $author): void {
            $complete = filled($author->identity_document)
                && $author->identity_document !== 'Pending'
                && filled($author->bank_account_number)
                && $author->bank_account_number !== 'Pending'
                && filled($author->bank_name)
                && $author->bank_name !== 'Pending';

            $canonical = match ($author->status) {
                'active' => 'approved',
                'rejected' => 'rejected',
                default => $complete ? 'submitted' : 'draft',
            };

            $timestamps = [];
            if ($canonical === 'approved') {
                $timestamps['approved_at'] = $author->updated_at;
                $timestamps['submitted_at'] = $author->created_at;
            } elseif ($canonical === 'rejected') {
                $timestamps['rejected_at'] = $author->updated_at;
                $timestamps['submitted_at'] = $author->created_at;
            } elseif ($canonical === 'submitted') {
                $timestamps['submitted_at'] = $author->updated_at;
            }

            DB::table('authors')->where('id', $author->id)->update([
                'onboarding_status' => $canonical,
                'last_review_reason' => $author->rejection_reason,
                ...$timestamps,
            ]);
        });

        Schema::create('author_onboarding_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('author_id')->constrained()->cascadeOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('from_status', 32);
            $table->string('to_status', 32);
            $table->text('reason')->nullable();
            $table->string('operation_key', 128)->unique();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->index(['author_id', 'created_at']);
        });

        DB::table('authors')->orderBy('id')->each(function (object $author): void {
            DB::table('author_onboarding_events')->insert([
                'author_id' => $author->id,
                'actor_id' => null,
                'from_status' => 'legacy_'.$author->status,
                'to_status' => $author->onboarding_status,
                'reason' => 'Phase 4B.1 deterministic legacy status import.',
                'operation_key' => "author:{$author->id}:legacy-import",
                'metadata' => json_encode(['source' => 'authors.status'], JSON_THROW_ON_ERROR),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('author_onboarding_events');

        foreach (['pen_name', 'identity_document', 'bank_account_number', 'bank_name', 'bank_holder_name'] as $field) {
            DB::table('authors')->whereNull($field)->update([$field => 'Pending']);
        }

        Schema::table('authors', function (Blueprint $table) {
            $table->dropUnique('authors_user_id_unique');
            $table->dropIndex(['onboarding_status']);
            $table->dropColumn([
                'onboarding_status', 'application_version', 'terms_accepted_at', 'submitted_at',
                'review_started_at', 'approved_at', 'changes_requested_at', 'rejected_at',
                'suspended_at', 'revoked_at', 'last_review_reason',
            ]);
            $table->string('pen_name')->nullable(false)->change();
            $table->string('identity_document')->nullable(false)->change();
            $table->string('bank_account_number')->nullable(false)->change();
            $table->string('bank_name')->nullable(false)->change();
            $table->string('bank_holder_name')->nullable(false)->change();
        });
    }
};
