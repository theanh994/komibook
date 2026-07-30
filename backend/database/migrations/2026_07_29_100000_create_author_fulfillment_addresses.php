<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('author_fulfillment_addresses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('author_id')->constrained()->cascadeOnDelete();
            $table->string('recipient_name');
            $table->text('phone');
            $table->text('address_line');
            $table->string('ward')->nullable();
            $table->string('district')->nullable();
            $table->string('province');
            $table->string('postal_code', 20)->nullable();
            $table->string('status', 20)->default('draft');
            $table->timestamp('verified_at')->nullable();
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('retired_at')->nullable();
            $table->timestamps();

            $table->index(['author_id', 'status']);
        });

        Schema::table('warehouses', function (Blueprint $table) {
            $table->foreignId('author_fulfillment_address_id')
                ->nullable()
                ->after('vendor_id')
                ->unique()
                ->constrained('author_fulfillment_addresses')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('warehouses', function (Blueprint $table) {
            $table->dropUnique(['author_fulfillment_address_id']);
            $table->dropForeign(['author_fulfillment_address_id']);
            $table->dropColumn('author_fulfillment_address_id');
        });
        Schema::dropIfExists('author_fulfillment_addresses');
    }
};
