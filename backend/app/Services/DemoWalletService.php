<?php

namespace App\Services;

use App\Models\DemoWalletAccount;
use App\Models\DemoWalletLedgerEntry;
use App\Models\Order;
use App\Models\PaymentTransaction;
use App\Models\PayoutRequest;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Support\Facades\DB;
use LogicException;

/**
 * Unified KomiBook wallet ledger.
 *
 * The legacy class/table names are intentionally retained for migration
 * compatibility. They are not a statement that the wallet is demo-only.
 */
class DemoWalletService
{
    public function accountFor(User $user): DemoWalletAccount
    {
        return DemoWalletAccount::firstOrCreate(
            ['user_id' => $user->id],
            ['balance' => 0, 'reserved_balance' => 0, 'currency' => 'VND', 'status' => 'active'],
        );
    }

    public function debit(User $user, PaymentTransaction $transaction): DemoWalletLedgerEntry
    {
        return DB::transaction(function () use ($user, $transaction) {
            $operationKey = "komibook-wallet:payment:{$transaction->id}:debit";
            if ($existing = DemoWalletLedgerEntry::where('operation_key', $operationKey)->first()) {
                return $existing;
            }

            $account = $this->lockActiveAccount($user);
            if ((int) $account->balance < (int) $transaction->amount) {
                throw new LogicException('Số dư Ví KomiBook không đủ để thanh toán.');
            }

            return $this->debitAccount($account, (int) $transaction->amount, [
                'payment_transaction_id' => $transaction->id,
                'entry_type' => 'payment_debit',
                'operation_key' => $operationKey,
                'metadata' => ['checkout_session_id' => $transaction->checkout_session_id],
            ]);
        });
    }

    public function creditRefund(
        User $user,
        ?PaymentTransaction $transaction,
        Order $order,
        int $amount,
        int $returnRequestId
    ): DemoWalletLedgerEntry {
        return DB::transaction(function () use ($user, $transaction, $order, $amount, $returnRequestId) {
            $operationKey = "komibook-wallet:refund:{$returnRequestId}:credit";
            if ($existing = DemoWalletLedgerEntry::where('operation_key', $operationKey)->first()) {
                return $existing;
            }

            return $this->creditAccount($this->lockActiveAccount($user), max(0, $amount), [
                'payment_transaction_id' => $transaction?->id,
                'order_id' => $order->id,
                'return_request_id' => $returnRequestId,
                'entry_type' => 'refund_credit',
                'operation_key' => $operationKey,
                'metadata' => [
                    'original_payment_method' => $order->payment_method,
                    'refund_destination' => 'komibook_wallet',
                ],
            ]);
        });
    }

    public function creditVendorEarning(Vendor $vendor, Order $order, int $amount): DemoWalletLedgerEntry
    {
        return DB::transaction(function () use ($vendor, $order, $amount) {
            $operationKey = "komibook-wallet:vendor-earning:{$order->id}:credit";
            if ($existing = DemoWalletLedgerEntry::where('operation_key', $operationKey)->first()) {
                return $existing;
            }

            $lockedVendor = Vendor::withoutGlobalScopes()->whereKey($vendor->id)->lockForUpdate()->firstOrFail();
            $user = User::whereKey($lockedVendor->user_id)->firstOrFail();
            $entry = $this->creditAccount($this->lockActiveAccount($user), max(0, $amount), [
                'order_id' => $order->id,
                'vendor_id' => $lockedVendor->id,
                'entry_type' => 'vendor_earning_credit',
                'operation_key' => $operationKey,
                'metadata' => ['source' => 'completed_order'],
            ]);
            $lockedVendor->increment('balance', max(0, $amount));

            return $entry;
        });
    }

    public function debitVendorRefund(Vendor $vendor, Order $order, int $amount, int $returnRequestId): DemoWalletLedgerEntry
    {
        return DB::transaction(function () use ($vendor, $order, $amount, $returnRequestId) {
            $operationKey = "komibook-wallet:vendor-refund:{$returnRequestId}:debit";
            if ($existing = DemoWalletLedgerEntry::where('operation_key', $operationKey)->first()) {
                return $existing;
            }

            $lockedVendor = Vendor::withoutGlobalScopes()->whereKey($vendor->id)->lockForUpdate()->firstOrFail();
            $user = User::whereKey($lockedVendor->user_id)->firstOrFail();
            $account = $this->ensureVendorProjectionImported($lockedVendor, $this->lockActiveAccount($user));
            $debit = min(max(0, $amount), (int) $account->balance);
            $entry = $this->debitAccount($account, $debit, [
                'order_id' => $order->id,
                'vendor_id' => $lockedVendor->id,
                'return_request_id' => $returnRequestId,
                'entry_type' => 'vendor_refund_debit',
                'operation_key' => $operationKey,
                'metadata' => ['requested_amount' => max(0, $amount)],
            ]);
            $lockedVendor->balance = max(0, (int) $lockedVendor->balance - $debit);
            $lockedVendor->save();

            return $entry;
        });
    }

    public function reservePayout(Vendor $vendor, PayoutRequest $payout): DemoWalletLedgerEntry
    {
        return $this->reserveWithdrawal(User::findOrFail($vendor->user_id), $payout, $vendor);
    }

    public function reserveWithdrawal(User $user, PayoutRequest $payout, ?Vendor $vendor = null): DemoWalletLedgerEntry
    {
        return DB::transaction(function () use ($user, $payout, $vendor) {
            $operationKey = "komibook-wallet:payout:{$payout->id}:reserve";
            if ($existing = DemoWalletLedgerEntry::where('operation_key', $operationKey)->first()) {
                return $existing;
            }

            $lockedVendor = $vendor
                ? Vendor::withoutGlobalScopes()->whereKey($vendor->id)->lockForUpdate()->firstOrFail()
                : null;
            $account = $this->lockActiveAccount($user);
            if ($lockedVendor) {
                $account = $this->ensureVendorProjectionImported($lockedVendor, $account);
            }
            if ((int) $account->balance < (int) $payout->amount) {
                throw new LogicException('Số dư Ví KomiBook không đủ để tạo yêu cầu rút tiền.');
            }
            $before = (int) $account->balance;
            $account->balance = $before - (int) $payout->amount;
            $account->reserved_balance = (int) $account->reserved_balance + (int) $payout->amount;
            $account->save();
            $vendorProjectionReserved = $lockedVendor ? min((int) $lockedVendor->balance, (int) $payout->amount) : 0;
            if ($vendorProjectionReserved > 0) {
                $lockedVendor->decrement('balance', $vendorProjectionReserved);
            }

            return DemoWalletLedgerEntry::create([
                'demo_wallet_account_id' => $account->id,
                'vendor_id' => $lockedVendor?->id,
                'payout_request_id' => $payout->id,
                'entry_type' => 'payout_reservation',
                'amount' => $payout->amount,
                'balance_before' => $before,
                'balance_after' => $account->balance,
                'operation_key' => $operationKey,
                'metadata' => [
                    'reserved_balance_after' => $account->reserved_balance,
                    'vendor_projection_reserved' => $vendorProjectionReserved,
                ],
            ]);
        });
    }

    public function releasePayout(Vendor $vendor, PayoutRequest $payout): DemoWalletLedgerEntry
    {
        return $this->settleWithdrawal(User::findOrFail($vendor->user_id), $payout, true, $vendor);
    }

    public function completePayout(Vendor $vendor, PayoutRequest $payout): DemoWalletLedgerEntry
    {
        return $this->settleWithdrawal(User::findOrFail($vendor->user_id), $payout, false, $vendor);
    }

    public function releaseWithdrawal(User $user, PayoutRequest $payout, ?Vendor $vendor = null): DemoWalletLedgerEntry
    {
        return $this->settleWithdrawal($user, $payout, true, $vendor);
    }

    public function completeWithdrawal(User $user, PayoutRequest $payout, ?Vendor $vendor = null): DemoWalletLedgerEntry
    {
        return $this->settleWithdrawal($user, $payout, false, $vendor);
    }

    private function settleWithdrawal(User $user, PayoutRequest $payout, bool $release, ?Vendor $vendor): DemoWalletLedgerEntry
    {
        return DB::transaction(function () use ($user, $vendor, $payout, $release) {
            $action = $release ? 'release' : 'complete';
            $operationKey = "komibook-wallet:payout:{$payout->id}:{$action}";
            if ($existing = DemoWalletLedgerEntry::where('operation_key', $operationKey)->first()) {
                return $existing;
            }

            $lockedVendor = $vendor
                ? Vendor::withoutGlobalScopes()->whereKey($vendor->id)->lockForUpdate()->firstOrFail()
                : null;
            $account = $this->lockActiveAccount($user);
            if ((int) $account->reserved_balance < (int) $payout->amount) {
                throw new LogicException('Số tiền đang giữ của Ví KomiBook không khớp yêu cầu rút.');
            }
            $before = (int) $account->balance;
            $account->reserved_balance = (int) $account->reserved_balance - (int) $payout->amount;
            if ($release) {
                $account->balance = $before + (int) $payout->amount;
                $reservation = DemoWalletLedgerEntry::where('payout_request_id', $payout->id)
                    ->where('entry_type', 'payout_reservation')->first();
                $vendorProjectionReserved = (int) ($reservation?->metadata['vendor_projection_reserved'] ?? 0);
                if ($lockedVendor && $vendorProjectionReserved > 0) {
                    $lockedVendor->increment('balance', $vendorProjectionReserved);
                }
            }
            $account->save();

            return DemoWalletLedgerEntry::create([
                'demo_wallet_account_id' => $account->id,
                'vendor_id' => $lockedVendor?->id,
                'payout_request_id' => $payout->id,
                'entry_type' => $release ? 'payout_release' : 'payout_completed',
                'amount' => $payout->amount,
                'balance_before' => $before,
                'balance_after' => $account->balance,
                'operation_key' => $operationKey,
                'metadata' => ['reserved_balance_after' => $account->reserved_balance],
            ]);
        });
    }

    private function lockActiveAccount(User $user): DemoWalletAccount
    {
        $this->accountFor($user);
        $account = DemoWalletAccount::where('user_id', $user->id)->lockForUpdate()->firstOrFail();
        if ($account->status !== 'active' || $account->currency !== 'VND') {
            throw new LogicException('Ví KomiBook chưa sẵn sàng.');
        }

        return $account;
    }

    private function ensureVendorProjectionImported(Vendor $vendor, DemoWalletAccount $account): DemoWalletAccount
    {
        $operationKey = "komibook-wallet:vendor:{$vendor->id}:runtime-balance-import";
        $hasVendorLedger = DemoWalletLedgerEntry::where('vendor_id', $vendor->id)->exists();
        $amount = max(0, (int) $vendor->balance);
        if ($hasVendorLedger || $amount === 0) {
            return $account;
        }

        $this->creditAccount($account, $amount, [
            'vendor_id' => $vendor->id,
            'entry_type' => 'vendor_balance_import',
            'operation_key' => $operationKey,
            'metadata' => ['source' => 'vendors.balance', 'runtime_compatibility' => true],
        ]);

        return $account->fresh();
    }

    private function creditAccount(DemoWalletAccount $account, int $amount, array $attributes): DemoWalletLedgerEntry
    {
        $before = (int) $account->balance;
        $account->balance = $before + $amount;
        $account->save();

        return DemoWalletLedgerEntry::create($attributes + [
            'demo_wallet_account_id' => $account->id,
            'amount' => $amount,
            'balance_before' => $before,
            'balance_after' => $account->balance,
        ]);
    }

    private function debitAccount(DemoWalletAccount $account, int $amount, array $attributes): DemoWalletLedgerEntry
    {
        if ($amount < 0 || (int) $account->balance < $amount) {
            throw new LogicException('Số dư Ví KomiBook không hợp lệ.');
        }
        $before = (int) $account->balance;
        $account->balance = $before - $amount;
        $account->save();

        return DemoWalletLedgerEntry::create($attributes + [
            'demo_wallet_account_id' => $account->id,
            'amount' => $amount,
            'balance_before' => $before,
            'balance_after' => $account->balance,
        ]);
    }
}
