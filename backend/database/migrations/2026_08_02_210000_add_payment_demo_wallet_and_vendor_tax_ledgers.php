<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_provider_settings', function (Blueprint $table) {
            $table->id();
            $table->string('provider', 32)->unique();
            $table->boolean('enabled_by_admin')->default(false);
            $table->string('mode', 16)->default('disabled');
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('reason', 1000)->nullable();
            $table->timestamps();
        });

        Schema::create('demo_wallet_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('balance')->default(0);
            $table->unsignedBigInteger('reserved_balance')->default(0);
            $table->string('currency', 3)->default('VND');
            $table->string('status', 16)->default('active');
            $table->timestamps();
        });

        Schema::create('demo_wallet_ledger_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('demo_wallet_account_id')->constrained()->cascadeOnDelete();
            $table->foreignId('payment_transaction_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('entry_type', 32);
            $table->unsignedBigInteger('amount');
            $table->unsignedBigInteger('balance_before');
            $table->unsignedBigInteger('balance_after');
            $table->string('operation_key', 128)->unique();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('vendor_tax_schedules', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('tax_year');
            $table->json('brackets');
            $table->timestamp('effective_at');
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('reason', 2000);
            $table->string('operation_key', 128)->unique();
            $table->timestamps();
            $table->unique(['tax_year', 'effective_at']);
        });

        Schema::create('vendor_tax_ledger_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vendor_id')->constrained()->restrictOnDelete();
            $table->foreignId('order_id')->constrained()->restrictOnDelete();
            $table->foreignId('vendor_tax_schedule_id')->nullable()->constrained()->restrictOnDelete();
            $table->unsignedSmallInteger('tax_year');
            $table->string('entry_type', 24)->default('earning');
            $table->bigInteger('taxable_revenue');
            $table->bigInteger('tax_amount');
            $table->json('calculation_snapshot');
            $table->string('operation_key', 128)->unique();
            $table->timestamps();
            $table->index(['vendor_id', 'tax_year']);
        });

        Schema::table('vendor_earning_ledgers', function (Blueprint $table) {
            $table->unsignedBigInteger('tax_amount')->default(0)->after('commission_amount');
            $table->foreignId('vendor_tax_schedule_id')->nullable()->after('tax_amount')->constrained()->restrictOnDelete();
        });

        Schema::table('vendor_earning_reversals', function (Blueprint $table) {
            $table->unsignedBigInteger('tax_amount')->default(0)->after('commission_amount');
        });
    }

    public function down(): void
    {
        Schema::table('vendor_earning_reversals', function (Blueprint $table) {
            $table->dropColumn('tax_amount');
        });

        Schema::table('vendor_earning_ledgers', function (Blueprint $table) {
            $table->dropForeign(['vendor_tax_schedule_id']);
            $table->dropColumn(['tax_amount', 'vendor_tax_schedule_id']);
        });
        Schema::dropIfExists('vendor_tax_ledger_entries');
        Schema::dropIfExists('vendor_tax_schedules');
        Schema::dropIfExists('demo_wallet_ledger_entries');
        Schema::dropIfExists('demo_wallet_accounts');
        Schema::dropIfExists('payment_provider_settings');
    }
};
