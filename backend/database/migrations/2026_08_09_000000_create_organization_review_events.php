<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('organization_review_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('actor_id')->constrained('users')->restrictOnDelete();
            $table->string('from_status', 32);
            $table->string('to_status', 32);
            $table->text('reason');
            $table->string('reviewed_fingerprint', 64)->nullable();
            $table->string('operation_key', 128)->unique();
            $table->timestamps();
            $table->index(['organization_id', 'to_status'], 'organization_review_event_current');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('organization_review_events');
    }
};
