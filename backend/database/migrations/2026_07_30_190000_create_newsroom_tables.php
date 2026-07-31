<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('articles', function (Blueprint $table) {
            $table->foreignId('vendor_id')->nullable()->after('created_by')->constrained()->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->after('vendor_id')->constrained('users')->nullOnDelete();
            $table->string('article_type', 32)->default('news')->after('article_category_id')->index();
            $table->string('social_image')->nullable()->after('cover_image');
            $table->boolean('allow_comments')->default(true)->after('home_featured');
            $table->unsignedSmallInteger('reading_minutes')->default(1)->after('allow_comments');
            $table->index(['vendor_id', 'status', 'updated_at'], 'articles_vendor_status_updated_index');
        });

        Schema::create('article_media', function (Blueprint $table) {
            $table->id();
            $table->foreignId('article_id')->constrained()->cascadeOnDelete();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('disk', 32)->default('public');
            $table->string('path');
            $table->string('mime_type', 100);
            $table->string('alt_text', 500);
            $table->unsignedBigInteger('size_bytes');
            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();
            $table->timestamps();
        });

        Schema::create('article_comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('article_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('article_comments')->cascadeOnDelete();
            $table->string('guest_name')->nullable();
            $table->string('guest_email_hash', 64)->nullable();
            $table->text('body');
            $table->string('status', 24)->default('pending')->index();
            $table->foreignId('moderated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('moderation_reason')->nullable();
            $table->timestamp('moderated_at')->nullable();
            $table->timestamps();
            $table->index(['article_id', 'status', 'created_at'], 'article_comments_public_index');
        });

        Schema::create('article_comment_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('article_comment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('from_status', 24);
            $table->string('to_status', 24);
            $table->text('reason')->nullable();
            $table->string('operation_key', 128)->unique();
            $table->timestamps();
        });

        Schema::create('article_submissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('book_id')->constrained()->cascadeOnDelete();
            $table->foreignId('converted_article_id')->nullable()->constrained('articles')->nullOnDelete();
            $table->string('title');
            $table->longText('body');
            $table->string('status', 24)->default('pending')->index();
            $table->boolean('verified_purchase')->default(false);
            $table->unsignedInteger('word_count')->default(0);
            $table->foreignId('moderated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('moderation_reason')->nullable();
            $table->timestamp('moderated_at')->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'book_id', 'title'], 'article_submission_user_book_title_unique');
        });

        Schema::create('article_metrics_daily', function (Blueprint $table) {
            $table->id();
            $table->foreignId('article_id')->constrained()->cascadeOnDelete();
            $table->date('metric_date');
            $table->unsignedBigInteger('views')->default(0);
            $table->unsignedBigInteger('book_clicks')->default(0);
            $table->unsignedBigInteger('shop_clicks')->default(0);
            $table->unsignedBigInteger('comments')->default(0);
            $table->timestamps();
            $table->unique(['article_id', 'metric_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('article_metrics_daily');
        Schema::dropIfExists('article_submissions');
        Schema::dropIfExists('article_comment_events');
        Schema::dropIfExists('article_comments');
        Schema::dropIfExists('article_media');

        Schema::table('articles', function (Blueprint $table) {
            $table->dropIndex('articles_vendor_status_updated_index');
            $table->dropIndex(['article_type']);
            $table->dropConstrainedForeignId('approved_by');
            $table->dropConstrainedForeignId('vendor_id');
            $table->dropColumn(['article_type', 'social_image', 'allow_comments', 'reading_minutes']);
        });
    }
};
