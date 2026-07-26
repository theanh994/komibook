<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $duplicates = DB::table('vendors')->select('user_id', DB::raw('COUNT(*) aggregate'))
            ->groupBy('user_id')->havingRaw('COUNT(*) > 1')->pluck('user_id');
        if ($duplicates->isNotEmpty()) {
            throw new RuntimeException('Cannot add unique vendor ownership: duplicate vendors.user_id values: '.$duplicates->implode(', '));
        }

        Schema::table('vendors', function (Blueprint $table) {
            $table->string('shop_name')->nullable()->change();
            $table->string('slug')->nullable()->change();
            $table->string('onboarding_status', 32)->nullable()->after('status')->index();
            $table->unsignedInteger('application_version')->default(1)->after('onboarding_status');
            $table->string('legal_name')->nullable()->after('description');
            $table->string('tax_code', 64)->nullable()->after('legal_name');
            $table->string('business_registration_document')->nullable()->after('tax_code');
            $table->string('representative_identity_document')->nullable()->after('business_registration_document');
            $table->string('payout_bank_account', 64)->nullable()->after('representative_identity_document');
            $table->string('payout_bank_name')->nullable()->after('payout_bank_account');
            $table->string('payout_bank_holder')->nullable()->after('payout_bank_name');
            $table->timestamp('terms_accepted_at')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('review_started_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('changes_requested_at')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->timestamp('suspended_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->text('last_review_reason')->nullable();
            $table->unique('user_id', 'vendors_user_id_unique');
        });

        DB::table('vendors')->orderBy('id')->each(function (object $vendor): void {
            $canonical = match ($vendor->status) {
                'active' => 'approved',
                'rejected' => 'rejected',
                default => 'draft',
            };
            DB::table('vendors')->where('id', $vendor->id)->update([
                'onboarding_status' => $canonical,
                'approved_at' => $canonical === 'approved' ? $vendor->updated_at : null,
                'rejected_at' => $canonical === 'rejected' ? $vendor->updated_at : null,
                'last_review_reason' => property_exists($vendor, 'rejection_reason')
                    ? $vendor->rejection_reason
                    : null,
            ]);
        });

        Schema::create('vendor_onboarding_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vendor_id')->constrained()->cascadeOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('from_status', 32);
            $table->string('to_status', 32);
            $table->text('reason')->nullable();
            $table->string('operation_key', 128)->unique();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->index(['vendor_id', 'created_at']);
        });

        DB::table('vendors')->orderBy('id')->each(function (object $vendor): void {
            DB::table('vendor_onboarding_events')->insert([
                'vendor_id' => $vendor->id,
                'actor_id' => null,
                'from_status' => 'legacy_'.$vendor->status,
                'to_status' => $vendor->onboarding_status,
                'reason' => 'Phase 4B.2 deterministic legacy status import.',
                'operation_key' => "vendor:{$vendor->id}:legacy-import",
                'metadata' => json_encode(['source' => 'vendors.status'], JSON_THROW_ON_ERROR),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vendor_onboarding_events');
        DB::table('vendors')->whereNull('shop_name')->update(['shop_name' => 'Pending']);
        DB::table('vendors')->whereNull('slug')->orderBy('id')->each(function (object $vendor): void {
            DB::table('vendors')->where('id', $vendor->id)->update(['slug' => 'pending-'.$vendor->id]);
        });

        if (Schema::getConnection()->getDriverName() === 'mysql'
            && ! Schema::hasIndex('vendors', 'vendors_user_id_foreign')) {
            Schema::table('vendors', function (Blueprint $table) {
                $table->index('user_id', 'vendors_user_id_foreign');
            });
        }

        Schema::table('vendors', function (Blueprint $table) {
            $table->dropUnique('vendors_user_id_unique');
            $table->dropIndex(['onboarding_status']);
            $table->dropColumn([
                'onboarding_status', 'application_version', 'legal_name', 'tax_code',
                'business_registration_document', 'representative_identity_document',
                'payout_bank_account', 'payout_bank_name', 'payout_bank_holder',
                'terms_accepted_at', 'submitted_at', 'review_started_at', 'approved_at',
                'changes_requested_at', 'rejected_at', 'suspended_at', 'revoked_at', 'last_review_reason',
            ]);
            $table->string('shop_name')->nullable(false)->change();
            $table->string('slug')->nullable(false)->change();
        });
    }
};
