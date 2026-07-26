<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reviews', function (Blueprint $table) {
            $table->foreignId('purchase_order_id')->nullable()->after('book_id')->constrained('orders')->nullOnDelete();
            $table->boolean('verified_purchase')->default(false)->after('purchase_order_id');
            $table->string('moderation_status', 32)->default('published')->after('comment');
            $table->timestamp('edited_at')->nullable()->after('moderation_status');
            $table->timestamp('moderated_at')->nullable()->after('edited_at');
            $table->foreignId('moderated_by')->nullable()->after('moderated_at')->constrained('users')->nullOnDelete();
            $table->text('moderation_reason')->nullable()->after('moderated_by');
            $table->unsignedTinyInteger('active_key')->nullable()->default(1)->after('moderation_reason');
            $table->timestamp('superseded_at')->nullable()->after('active_key');
            $table->index(['book_id', 'moderation_status', 'active_key'], 'reviews_public_lookup_index');
        });

        // Preserve legacy duplicates for audit while selecting the newest row as the active review.
        DB::table('reviews')->orderByDesc('id')->get()->groupBy(fn ($review) => $review->user_id.'-'.$review->book_id)
            ->each(function ($reviews) {
                $reviews->skip(1)->each(fn ($review) => DB::table('reviews')->where('id', $review->id)->update([
                    'active_key' => null,
                    'superseded_at' => now(),
                    'updated_at' => now(),
                ]));
            });

        Schema::table('reviews', function (Blueprint $table) {
            $table->unique(['user_id', 'book_id', 'active_key'], 'reviews_one_active_per_user_book');
        });

        Schema::create('review_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('review_id')->constrained()->cascadeOnDelete();
            $table->foreignId('reporter_id')->constrained('users')->cascadeOnDelete();
            $table->string('reason', 64);
            $table->text('details')->nullable();
            $table->string('status', 32)->default('open');
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
            $table->unique(['review_id', 'reporter_id']);
            $table->index(['status', 'created_at']);
        });

        Schema::create('review_moderation_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('review_id')->constrained()->cascadeOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action', 32);
            $table->string('from_status', 32)->nullable();
            $table->string('to_status', 32)->nullable();
            $table->text('reason')->nullable();
            $table->json('metadata')->nullable();
            $table->string('operation_key', 100)->unique();
            $table->timestamps();
            $table->index(['review_id', 'created_at']);
        });

        Schema::table('book_annotations', function (Blueprint $table) {
            $table->foreignId('book_chapter_id')->nullable()->after('book_id')->constrained('book_chapters')->cascadeOnDelete();
            $table->string('location_key')->nullable()->after('chapter');
            $table->unsignedInteger('position_start')->nullable()->after('page_number');
            $table->unsignedInteger('position_end')->nullable()->after('position_start');
            $table->index(['user_id', 'book_id', 'book_chapter_id'], 'annotations_reader_lookup_index');
        });

        Schema::create('reading_progress', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('book_id')->constrained()->cascadeOnDelete();
            $table->foreignId('purchase_order_id')->nullable()->constrained('orders')->nullOnDelete();
            $table->foreignId('book_chapter_id')->nullable()->constrained('book_chapters')->nullOnDelete();
            $table->string('location_key')->nullable();
            $table->unsignedInteger('current_page')->nullable();
            $table->unsignedInteger('total_pages')->nullable();
            $table->decimal('progress_percent', 5, 2)->default(0);
            $table->unsignedBigInteger('version')->default(1);
            $table->timestamp('last_read_at');
            $table->timestamps();
            $table->unique(['user_id', 'book_id']);
            $table->index(['user_id', 'last_read_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reading_progress');

        Schema::table('book_annotations', function (Blueprint $table) {
            $table->dropIndex('annotations_reader_lookup_index');
            $table->dropConstrainedForeignId('book_chapter_id');
            $table->dropColumn(['location_key', 'position_start', 'position_end']);
        });

        Schema::dropIfExists('review_moderation_events');
        Schema::dropIfExists('review_reports');

        Schema::table('reviews', function (Blueprint $table) {
            $table->dropUnique('reviews_one_active_per_user_book');
            $table->dropIndex('reviews_public_lookup_index');
            $table->dropConstrainedForeignId('purchase_order_id');
            $table->dropConstrainedForeignId('moderated_by');
            $table->dropColumn([
                'verified_purchase', 'moderation_status', 'edited_at', 'moderated_at',
                'moderation_reason', 'active_key', 'superseded_at',
            ]);
        });
    }
};
