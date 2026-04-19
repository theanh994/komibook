<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('books', function (Blueprint $table) {
            $table->id();

            // Foreign keys
            $table->foreignId('vendor_id')->constrained('vendors')->onDelete('cascade');
            $table->foreignId('category_id')->constrained('categories')->onDelete('restrict');

            // Book info
            $table->string('title');
            $table->string('slug')->unique();
            $table->string('author');
            $table->text('description')->nullable();
            $table->string('cover_image')->nullable();
            $table->string('isbn')->nullable()->unique();

            // Pricing
            $table->decimal('price', 12, 0);          // Giá bán (VND không cần phần thập phân)
            $table->decimal('sale_price', 12, 0)->nullable(); // Giá khuyến mãi

            // Inventory & type
            $table->unsignedInteger('stock')->default(0);
            $table->enum('type', ['physical', 'ebook'])->default('physical');

            // Status
            $table->enum('status', ['draft', 'published', 'out_of_stock'])->default('draft');

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('books');
    }
};
