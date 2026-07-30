<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('used_book_listings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('book_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('author_id')->constrained()->cascadeOnDelete();
            $table->foreignId('fulfillment_address_id')->constrained('author_fulfillment_addresses')->restrictOnDelete();
            $table->string('condition', 20);
            $table->json('actual_photos');
            $table->text('defects')->nullable();
            $table->unsignedInteger('quantity_available');
            $table->unsignedInteger('quantity_reserved')->default(0);
            $table->unsignedInteger('quantity_sold')->default(0);
            $table->unsignedInteger('quantity_returned')->default(0);
            $table->timestamp('authenticity_attested_at');
            $table->string('status', 20)->default('draft');
            $table->timestamps();
            $table->index(['author_id', 'status']);
        });

        Schema::create('used_book_disputes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_item_id')->constrained()->restrictOnDelete();
            $table->foreignId('reporter_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('used_book_listing_id')->constrained()->restrictOnDelete();
            $table->string('type', 30);
            $table->text('description');
            $table->json('evidence')->nullable();
            $table->string('status', 30)->default('submitted');
            $table->unsignedBigInteger('held_amount')->default(0);
            $table->string('hold_status', 20)->default('active');
            $table->text('resolution')->nullable();
            $table->string('sanction')->nullable();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
            $table->unique(['order_item_id', 'reporter_id']);
            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('used_book_disputes');
        Schema::dropIfExists('used_book_listings');
    }
};
