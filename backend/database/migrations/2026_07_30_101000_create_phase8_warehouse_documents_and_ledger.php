<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('warehouse_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vendor_id')->constrained()->cascadeOnDelete();
            $table->string('document_code', 64)->unique();
            $table->string('type', 24)->index();
            $table->foreignId('source_warehouse_id')->nullable()->constrained('warehouses')->restrictOnDelete();
            $table->foreignId('destination_warehouse_id')->nullable()->constrained('warehouses')->restrictOnDelete();
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status', 24)->default('draft')->index();
            $table->text('reason')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('posted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('posted_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->string('operation_key', 128)->unique();
            $table->timestamps();
            $table->index(['vendor_id', 'status', 'type'], 'warehouse_document_lookup');
        });

        Schema::create('warehouse_document_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('warehouse_document_id')->constrained('warehouse_documents')->cascadeOnDelete();
            $table->foreignId('book_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('quantity')->default(0);
            $table->unsignedInteger('expected_quantity')->nullable();
            $table->unsignedInteger('actual_quantity')->nullable();
            $table->string('shelf_location')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->unique(['warehouse_document_id', 'book_id']);
        });

        Schema::create('warehouse_stock_ledgers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('warehouse_document_id')->constrained('warehouse_documents')->restrictOnDelete();
            $table->foreignId('warehouse_document_line_id')->constrained('warehouse_document_lines')->restrictOnDelete();
            $table->foreignId('warehouse_id')->constrained()->restrictOnDelete();
            $table->foreignId('book_id')->constrained()->restrictOnDelete();
            $table->integer('quantity_delta');
            $table->unsignedInteger('balance_after');
            $table->foreignId('actor_id')->constrained('users')->restrictOnDelete();
            $table->string('operation_key', 160)->unique();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->index(['warehouse_id', 'book_id', 'created_at'], 'warehouse_stock_ledger_lookup');
        });

        Schema::create('warehouse_document_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('warehouse_document_id')->constrained('warehouse_documents')->cascadeOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('from_status', 24);
            $table->string('to_status', 24);
            $table->text('reason')->nullable();
            $table->string('operation_key', 128)->unique();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('warehouse_document_events');
        Schema::dropIfExists('warehouse_stock_ledgers');
        Schema::dropIfExists('warehouse_document_lines');
        Schema::dropIfExists('warehouse_documents');
    }
};
