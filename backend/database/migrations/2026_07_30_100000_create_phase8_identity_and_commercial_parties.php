<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('organizations', function (Blueprint $table) {
            $table->id();
            $table->string('legal_name');
            $table->string('display_name');
            $table->string('slug')->unique();
            $table->json('organization_types');
            $table->string('tax_code', 64)->nullable();
            $table->string('license_number', 128)->nullable();
            $table->string('verification_document')->nullable();
            $table->text('description')->nullable();
            $table->string('logo')->nullable();
            $table->string('website')->nullable();
            $table->string('status', 32)->default('draft')->index();
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->timestamp('suspended_at')->nullable();
            $table->timestamp('archived_at')->nullable();
            $table->text('last_review_reason')->nullable();
            $table->timestamps();
        });

        Schema::table('vendors', function (Blueprint $table) {
            $table->string('business_model', 32)->default('bookstore')->after('onboarding_status')->index();
            $table->foreignId('primary_organization_id')->nullable()->after('business_model')
                ->constrained('organizations')->nullOnDelete();
        });

        Schema::create('vendor_organization_relationships', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vendor_id')->constrained()->cascadeOnDelete();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('role', 40);
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
            $table->index(['vendor_id', 'status', 'role'], 'vendor_org_relation_lookup');
        });

        Schema::create('organization_relationship_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vendor_organization_relationship_id')
                ->constrained('vendor_organization_relationships', 'id', 'phase8_org_relation_event_fk')
                ->cascadeOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('from_status', 32);
            $table->string('to_status', 32);
            $table->text('reason')->nullable();
            $table->string('operation_key', 128)->unique();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('book_commercial_parties', function (Blueprint $table) {
            $table->id();
            $table->foreignId('book_id')->constrained()->cascadeOnDelete();
            $table->foreignId('organization_id')->constrained()->restrictOnDelete();
            $table->foreignId('vendor_organization_relationship_id')->nullable()
                ->constrained('vendor_organization_relationships', 'id', 'phase8_book_party_relation_fk')
                ->nullOnDelete();
            $table->string('role', 40);
            $table->string('status', 32)->default('pending')->index();
            $table->unsignedInteger('version')->default(1);
            $table->string('active_slot', 16)->nullable();
            $table->timestamp('effective_at')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('review_note')->nullable();
            $table->timestamps();
            $table->unique(['book_id', 'role', 'active_slot'], 'book_party_one_active_role');
            $table->index(['book_id', 'status', 'role'], 'book_party_lookup');
        });

        Schema::table('order_items', function (Blueprint $table) {
            $table->json('commercial_parties_snapshot')->nullable()->after('product_taxonomy_snapshot');
        });

        Schema::create('warehouse_manager_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('vendor_id')->constrained()->cascadeOnDelete();
            $table->foreignId('warehouse_id')->constrained()->cascadeOnDelete();
            $table->foreignId('invited_by')->constrained('users')->restrictOnDelete();
            $table->json('capabilities');
            $table->string('status', 32)->default('invited')->index();
            $table->string('invitation_token_hash', 64)->nullable()->unique();
            $table->timestamp('invited_at')->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('suspended_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->text('last_reason')->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'warehouse_id']);
            $table->index(['vendor_id', 'warehouse_id', 'status'], 'warehouse_assignment_lookup');
        });

        Schema::create('warehouse_assignment_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('warehouse_manager_assignment_id')
                ->constrained('warehouse_manager_assignments', 'id', 'phase8_assignment_event_fk')
                ->cascadeOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('from_status', 32);
            $table->string('to_status', 32);
            $table->text('reason')->nullable();
            $table->string('operation_key', 128)->unique();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('warehouse_assignment_events');
        Schema::dropIfExists('warehouse_manager_assignments');

        Schema::table('order_items', function (Blueprint $table) {
            $table->dropColumn('commercial_parties_snapshot');
        });

        Schema::dropIfExists('book_commercial_parties');
        Schema::dropIfExists('organization_relationship_events');
        Schema::dropIfExists('vendor_organization_relationships');

        Schema::table('vendors', function (Blueprint $table) {
            $table->dropForeign(['primary_organization_id']);
            $table->dropIndex(['business_model']);
            $table->dropColumn(['business_model', 'primary_organization_id']);
        });

        Schema::dropIfExists('organizations');
    }
};
