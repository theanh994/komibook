<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('books', function (Blueprint $table): void {
            $table->unsignedInteger('print_edition')->default(1)->after('isbn');
            $table->index(['vendor_id', 'isbn', 'print_edition'], 'books_vendor_isbn_print_edition');
        });

        Schema::table('warehouses', function (Blueprint $table): void {
            $table->string('province')->nullable()->after('address');
            $table->string('district')->nullable()->after('province');
        });

        Schema::table('vendors', function (Blueprint $table): void {
            $table->foreignId('primary_warehouse_id')
                ->nullable()
                ->after('primary_organization_id')
                ->constrained('warehouses')
                ->nullOnDelete();
        });

        Schema::table('warehouse_documents', function (Blueprint $table): void {
            $table->string('origin', 32)->default('manual')->after('type')->index();
            $table->string('receipt_mode', 32)->nullable()->after('origin');
            $table->string('external_counterparty_name')->nullable()->after('receipt_mode');
            $table->json('snapshot')->nullable()->after('external_counterparty_name');
        });
    }

    public function down(): void
    {
        Schema::table('warehouse_documents', function (Blueprint $table): void {
            $table->dropIndex(['origin']);
            $table->dropColumn(['origin', 'receipt_mode', 'external_counterparty_name', 'snapshot']);
        });

        Schema::table('vendors', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('primary_warehouse_id');
        });

        Schema::table('warehouses', function (Blueprint $table): void {
            $table->dropColumn(['province', 'district']);
        });

        Schema::table('books', function (Blueprint $table): void {
            $table->dropIndex('books_vendor_isbn_print_edition');
            $table->dropColumn('print_edition');
        });
    }
};
