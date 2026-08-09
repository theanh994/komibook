<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('organizations', fn (Blueprint $table) => $table->string('authority_fingerprint', 64)->nullable());
        Schema::table('vendor_organization_relationships', fn (Blueprint $table) => $table->string('authority_fingerprint', 64)->nullable());
        Schema::table('organization_distribution_agreements', fn (Blueprint $table) => $table->string('authority_fingerprint', 64)->nullable());
        Schema::table('organization_relationship_events', fn (Blueprint $table) => $table->string('reviewed_fingerprint', 64)->nullable());
        Schema::table('organization_distribution_agreement_events', fn (Blueprint $table) => $table->string('reviewed_fingerprint', 64)->nullable());
    }

    public function down(): void
    {
        Schema::table('organization_distribution_agreement_events', fn (Blueprint $table) => $table->dropColumn('reviewed_fingerprint'));
        Schema::table('organization_relationship_events', fn (Blueprint $table) => $table->dropColumn('reviewed_fingerprint'));
        Schema::table('organization_distribution_agreements', fn (Blueprint $table) => $table->dropColumn('authority_fingerprint'));
        Schema::table('vendor_organization_relationships', fn (Blueprint $table) => $table->dropColumn('authority_fingerprint'));
        Schema::table('organizations', fn (Blueprint $table) => $table->dropColumn('authority_fingerprint'));
    }
};
