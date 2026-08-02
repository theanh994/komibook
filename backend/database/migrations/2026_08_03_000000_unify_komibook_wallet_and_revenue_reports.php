<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('demo_wallet_ledger_entries', function (Blueprint $table) {
            $table->foreignId('order_id')->nullable()->after('payment_transaction_id')->constrained()->nullOnDelete();
            $table->foreignId('vendor_id')->nullable()->after('order_id')->constrained()->nullOnDelete();
            $table->foreignId('payout_request_id')->nullable()->after('vendor_id')->constrained()->nullOnDelete();
            $table->foreignId('return_request_id')->nullable()->after('payout_request_id')->constrained()->nullOnDelete();
            $table->index(['vendor_id', 'created_at'], 'wallet_entries_vendor_created_index');
            $table->index(['entry_type', 'created_at'], 'wallet_entries_type_created_index');
        });

        Schema::create('revenue_report_snapshots', function (Blueprint $table) {
            $table->id();
            $table->date('period_month')->unique();
            $table->unsignedBigInteger('gross_revenue')->default(0);
            $table->unsignedInteger('completed_orders')->default(0);
            $table->unsignedBigInteger('commission_amount')->default(0);
            $table->unsignedBigInteger('vendor_net_amount')->default(0);
            $table->unsignedBigInteger('refund_amount')->default(0);
            $table->string('currency', 3)->default('VND');
            $table->timestamp('generated_at');
            $table->foreignId('generated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        // Import the legacy vendor projection into the unified wallet exactly once.
        DB::table('vendors')->orderBy('id')->get()->each(function (object $vendor): void {
            $amount = max(0, (int) $vendor->balance);
            $operationKey = "komibook-wallet:vendor:{$vendor->id}:legacy-balance-import";
            if ($amount === 0 || DB::table('demo_wallet_ledger_entries')->where('operation_key', $operationKey)->exists()) {
                return;
            }

            $account = DB::table('demo_wallet_accounts')->where('user_id', $vendor->user_id)->first();
            if (! $account) {
                $accountId = DB::table('demo_wallet_accounts')->insertGetId([
                    'user_id' => $vendor->user_id,
                    'balance' => 0,
                    'reserved_balance' => 0,
                    'currency' => 'VND',
                    'status' => 'active',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                $before = 0;
            } else {
                $accountId = $account->id;
                $before = (int) $account->balance;
            }

            DB::table('demo_wallet_accounts')->where('id', $accountId)->update([
                'balance' => $before + $amount,
                'updated_at' => now(),
            ]);
            DB::table('demo_wallet_ledger_entries')->insert([
                'demo_wallet_account_id' => $accountId,
                'vendor_id' => $vendor->id,
                'entry_type' => 'vendor_balance_import',
                'amount' => $amount,
                'balance_before' => $before,
                'balance_after' => $before + $amount,
                'operation_key' => $operationKey,
                'metadata' => json_encode(['source' => 'vendors.balance'], JSON_THROW_ON_ERROR),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('revenue_report_snapshots');

        Schema::table('demo_wallet_ledger_entries', function (Blueprint $table) {
            $table->dropIndex('wallet_entries_vendor_created_index');
            $table->dropIndex('wallet_entries_type_created_index');
            $table->dropConstrainedForeignId('return_request_id');
            $table->dropConstrainedForeignId('payout_request_id');
            $table->dropConstrainedForeignId('vendor_id');
            $table->dropConstrainedForeignId('order_id');
        });
    }
};
