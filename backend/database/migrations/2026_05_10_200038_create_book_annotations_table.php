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
        Schema::create('book_annotations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('book_id')->constrained()->onDelete('cascade');
            $table->string('chapter')->nullable();
            $table->text('highlighted_text')->nullable(); // For highlights
            $table->text('note_content')->nullable(); // For user's personal note
            $table->string('type')->default('note'); // highlight, note, bookmark
            $table->string('color')->nullable(); // color code for highlight
            $table->integer('page_number')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('book_annotations');
    }
};
