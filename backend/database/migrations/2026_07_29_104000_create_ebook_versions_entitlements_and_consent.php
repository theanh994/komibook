<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ebook_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('book_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('version');
            $table->string('file_path')->nullable();
            $table->json('chapter_snapshot')->nullable();
            $table->text('release_notes')->nullable();
            $table->foreignId('published_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('published_at');
            $table->timestamps();
            $table->unique(['book_id', 'version']);
        });

        Schema::create('ebook_entitlements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->foreignId('book_id')->constrained()->restrictOnDelete();
            $table->foreignId('order_item_id')->constrained()->restrictOnDelete();
            $table->foreignId('purchase_version_id')->constrained('ebook_versions')->restrictOnDelete();
            $table->timestamp('activated_at');
            $table->timestamp('revoked_at')->nullable();
            $table->string('revocation_reason')->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'book_id']);
        });

        Schema::table('order_items', function (Blueprint $table) {
            $table->foreignId('ebook_version_id')->nullable()->constrained('ebook_versions')->restrictOnDelete();
            $table->json('product_taxonomy_snapshot')->nullable();
            $table->json('return_policy_snapshot')->nullable();
            $table->json('ebook_consent_snapshot')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('ebook_version_id');
            $table->dropColumn(['product_taxonomy_snapshot', 'return_policy_snapshot', 'ebook_consent_snapshot']);
        });
        Schema::dropIfExists('ebook_entitlements');
        Schema::dropIfExists('ebook_versions');
    }
};
