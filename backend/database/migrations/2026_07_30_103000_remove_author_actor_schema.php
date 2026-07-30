<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const ACTOR_TABLES = [
        'authors',
        'author_onboarding_events',
        'author_fulfillment_addresses',
        'author_commerce_profiles',
        'book_authors',
        'author_delegations',
        'copyright_claims',
        'copyright_claim_authors',
        'copyright_claim_events',
        'rights_relation_events',
        'royalty_agreements',
        'royalty_agreement_acceptances',
        'royalty_ledger_entries',
    ];

    public function up(): void
    {
        if (! Schema::hasTable('retired_actor_archives')) {
            Schema::create('retired_actor_archives', function (Blueprint $table) {
                $table->id();
                $table->string('source_table', 80);
                $table->unsignedBigInteger('source_id');
                $table->json('payload');
                $table->timestamps();
                $table->unique(['source_table', 'source_id']);
            });
        }

        foreach (self::ACTOR_TABLES as $sourceTable) {
            if (! Schema::hasTable($sourceTable)) {
                continue;
            }
            DB::table($sourceTable)->orderBy('id')->each(function (object $record) use ($sourceTable): void {
                DB::table('retired_actor_archives')->updateOrInsert(
                    ['source_table' => $sourceTable, 'source_id' => $record->id],
                    [
                        'payload' => json_encode((array) $record, JSON_THROW_ON_ERROR),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ],
                );
            });
        }

        DB::table('users')->where('role', 'author')->orderBy('id')->each(function (object $user): void {
            DB::table('retired_actor_archives')->updateOrInsert(
                ['source_table' => 'user_roles', 'source_id' => $user->id],
                [
                    'payload' => json_encode(['role' => 'author'], JSON_THROW_ON_ERROR),
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
            );
        });
        DB::table('users')->where('role', 'author')->update(['role' => 'customer']);

        if (Schema::hasColumn('articles', 'author_id')) {
            Schema::table('articles', fn (Blueprint $table) => $table->dropConstrainedForeignId('author_id'));
        }
        if (Schema::hasColumn('coupons', 'author_id')) {
            Schema::table('coupons', function (Blueprint $table) {
                $table->dropForeign(['author_id']);
                $table->dropIndex(['author_id', 'status']);
                $table->dropColumn('author_id');
            });
        }
        if (Schema::hasColumn('warehouses', 'author_fulfillment_address_id')) {
            Schema::table('warehouses', function (Blueprint $table) {
                $table->dropForeign(['author_fulfillment_address_id']);
                $table->dropUnique(['author_fulfillment_address_id']);
                $table->dropColumn('author_fulfillment_address_id');
            });
        }

        foreach ([
            'royalty_ledger_entries',
            'royalty_agreement_acceptances',
            'royalty_agreements',
            'copyright_claim_events',
            'copyright_claim_authors',
            'copyright_claims',
            'rights_relation_events',
            'author_delegations',
            'book_authors',
            'author_onboarding_events',
            'author_commerce_profiles',
            'author_fulfillment_addresses',
            'authors',
        ] as $table) {
            Schema::dropIfExists($table);
        }
    }

    public function down(): void
    {
        $this->recreateActorSchema();

        Schema::disableForeignKeyConstraints();
        foreach (self::ACTOR_TABLES as $table) {
            if (! Schema::hasTable($table)) {
                continue;
            }
            $columns = Schema::getColumnListing($table);
            DB::table('retired_actor_archives')->where('source_table', $table)->orderBy('source_id')
                ->each(function (object $archive) use ($table, $columns): void {
                    $payload = array_intersect_key(
                        json_decode($archive->payload, true, 512, JSON_THROW_ON_ERROR),
                        array_flip($columns),
                    );
                    DB::table($table)->insert($payload);
                });
        }
        DB::table('retired_actor_archives')->where('source_table', 'user_roles')->orderBy('source_id')
            ->each(fn (object $archive) => DB::table('users')->where('id', $archive->source_id)->update(['role' => 'author']));
        Schema::enableForeignKeyConstraints();

        Schema::dropIfExists('retired_actor_archives');
    }

    private function recreateActorSchema(): void
    {
        Schema::create('authors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('pen_name')->nullable();
            $table->text('bio')->nullable();
            $table->string('identity_document')->nullable();
            $table->timestamp('phone_verified_at')->nullable();
            $table->string('bank_account_number')->nullable();
            $table->string('bank_name')->nullable();
            $table->string('bank_holder_name')->nullable();
            $table->string('status', 24)->default('pending');
            $table->string('rejection_reason')->nullable();
            $table->string('onboarding_status', 32)->default('draft')->index();
            $table->unsignedInteger('application_version')->default(1);
            $table->timestamp('terms_accepted_at')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('review_started_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('changes_requested_at')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->timestamp('suspended_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->text('last_review_reason')->nullable();
            $table->timestamps();
            $table->unique('user_id', 'authors_user_id_unique');
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

        Schema::create('author_fulfillment_addresses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('author_id')->constrained()->cascadeOnDelete();
            $table->string('recipient_name');
            $table->text('phone');
            $table->text('address_line');
            $table->string('ward')->nullable();
            $table->string('district')->nullable();
            $table->string('province');
            $table->string('postal_code', 20)->nullable();
            $table->string('status', 20)->default('draft');
            $table->timestamp('verified_at')->nullable();
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('retired_at')->nullable();
            $table->timestamps();
            $table->index(['author_id', 'status']);
        });

        Schema::create('author_commerce_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('author_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('vendor_id')->unique()->constrained()->restrictOnDelete();
            $table->string('status', 20)->default('active');
            $table->json('capabilities');
            $table->timestamp('activated_at');
            $table->timestamp('suspended_at')->nullable();
            $table->timestamps();
        });

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
            $table->decimal('share_percent', 5, 2)->nullable();
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
            $table->decimal('share_percent', 5, 2);
            $table->unsignedBigInteger('royalty_amount');
            $table->string('operation_key', 128)->unique();
            $table->timestamp('earned_at');
            $table->timestamps();
        });

        Schema::table('articles', fn (Blueprint $table) => $table->foreignId('author_id')->nullable()->constrained('users')->restrictOnDelete());
        Schema::table('coupons', function (Blueprint $table) {
            $table->foreignId('author_id')->nullable()->constrained()->cascadeOnDelete();
            $table->index(['author_id', 'status']);
        });
        Schema::table('warehouses', function (Blueprint $table) {
            $table->foreignId('author_fulfillment_address_id')->nullable()->unique()
                ->constrained('author_fulfillment_addresses')->nullOnDelete();
        });
    }
};
