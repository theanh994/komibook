<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wallet_payout_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('bank_name', 255);
            $table->string('account_number', 64);
            $table->string('account_name', 255);
            $table->string('status', 24)->default('unverified');
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('verified_at')->nullable();
            $table->text('review_reason')->nullable();
            $table->timestamps();
        });

        Schema::table('payout_requests', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->after('vendor_id')->constrained()->restrictOnDelete();
            $table->foreignId('wallet_payout_account_id')->nullable()->after('user_id')->constrained()->nullOnDelete();
            $table->index(['user_id', 'created_at'], 'payout_requests_user_created_index');
        });
        DB::table('payout_requests')->orderBy('id')->each(function (object $payout): void {
            $userId = DB::table('vendors')->where('id', $payout->vendor_id)->value('user_id');
            DB::table('payout_requests')->where('id', $payout->id)->update(['user_id' => $userId]);
        });
        Schema::table('payout_requests', function (Blueprint $table) {
            $table->foreignId('vendor_id')->nullable()->change();
        });

        Schema::table('payout_ledger_entries', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->after('vendor_id')->constrained()->restrictOnDelete();
            $table->foreignId('demo_wallet_account_id')->nullable()->after('user_id')->constrained()->nullOnDelete();
        });
        DB::table('payout_ledger_entries')->orderBy('id')->each(function (object $entry): void {
            $userId = DB::table('vendors')->where('id', $entry->vendor_id)->value('user_id');
            $walletId = DB::table('demo_wallet_accounts')->where('user_id', $userId)->value('id');
            DB::table('payout_ledger_entries')->where('id', $entry->id)->update([
                'user_id' => $userId,
                'demo_wallet_account_id' => $walletId,
            ]);
        });
        Schema::table('payout_ledger_entries', function (Blueprint $table) {
            $table->foreignId('vendor_id')->nullable()->change();
            $table->index(['user_id', 'created_at'], 'payout_ledger_user_created_index');
        });
    }

    public function down(): void
    {
        DB::table('payout_requests')->whereNull('vendor_id')->delete();
        Schema::table('payout_ledger_entries', function (Blueprint $table) {
            $table->dropIndex('payout_ledger_user_created_index');
            $table->dropConstrainedForeignId('demo_wallet_account_id');
            $table->dropConstrainedForeignId('user_id');
            $table->foreignId('vendor_id')->nullable(false)->change();
        });
        Schema::table('payout_requests', function (Blueprint $table) {
            $table->dropIndex('payout_requests_user_created_index');
            $table->dropConstrainedForeignId('wallet_payout_account_id');
            $table->dropConstrainedForeignId('user_id');
            $table->foreignId('vendor_id')->nullable(false)->change();
        });
        Schema::dropIfExists('wallet_payout_accounts');
    }
};
