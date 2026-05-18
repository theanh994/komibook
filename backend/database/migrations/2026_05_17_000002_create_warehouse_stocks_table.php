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
        Schema::create('warehouse_stocks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('warehouse_id')->constrained('warehouses')->onDelete('cascade');
            $table->foreignId('book_id')->constrained('books')->onDelete('cascade');
            $table->unsignedInteger('quantity')->default(0);
            $table->string('shelf_location')->nullable();
            $table->timestamps();

            // Ràng buộc duy nhất: Một cuốn sách chỉ có một dòng tồn kho tại một kho cụ thể
            $table->unique(['warehouse_id', 'book_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('warehouse_stocks');
    }
};
