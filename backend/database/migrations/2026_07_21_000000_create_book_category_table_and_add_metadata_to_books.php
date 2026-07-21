<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Pivot table for Books <-> Categories (Many-to-Many)
        if (!Schema::hasTable('book_category')) {
            Schema::create('book_category', function (Blueprint $table) {
                $table->id();
                $table->foreignId('book_id')->constrained('books')->onDelete('cascade');
                $table->foreignId('category_id')->constrained('categories')->onDelete('cascade');
                $table->timestamps();
            });

            // Migrate existing category_id relationships
            $books = DB::table('books')->whereNotNull('category_id')->get();
            foreach ($books as $book) {
                DB::table('book_category')->insert([
                    'book_id'     => $book->id,
                    'category_id' => $book->category_id,
                    'created_at'  => now(),
                    'updated_at'  => now(),
                ]);
            }
        }

        // 2. Add metadata & gallery columns to books table
        Schema::table('books', function (Blueprint $table) {
            if (!Schema::hasColumn('books', 'dimensions')) {
                $table->string('dimensions')->nullable()->after('isbn');
            }
            if (!Schema::hasColumn('books', 'cover_format')) {
                $table->string('cover_format')->nullable()->after('dimensions');
            }
            if (!Schema::hasColumn('books', 'weight')) {
                $table->string('weight')->nullable()->after('cover_format');
            }
            if (!Schema::hasColumn('books', 'language')) {
                $table->string('language')->nullable()->after('weight');
            }
            if (!Schema::hasColumn('books', 'target_age')) {
                $table->string('target_age')->nullable()->after('language');
            }
            if (!Schema::hasColumn('books', 'gallery_images')) {
                $table->json('gallery_images')->nullable()->after('cover_image');
            }
            if (!Schema::hasColumn('books', 'views')) {
                $table->unsignedBigInteger('views')->default(0)->after('stock');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('book_category');

        Schema::table('books', function (Blueprint $table) {
            $table->dropColumn([
                'dimensions',
                'cover_format',
                'weight',
                'language',
                'target_age',
                'gallery_images',
                'views',
            ]);
        });
    }
};
