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
        ?int $returnRequestId = null
    ): DemoWalletLedgerEntry {
        return DB::transaction(function () use ($user, $transaction, $order, $amount, $returnRequestId) {
            if ($amount < 0) {
                throw new LogicException('Số tiền hoàn Ví KomiBook không hợp lệ.');
            }
            $opId = $returnRequestId ? "return:{$returnRequestId}" : "order:{$order->id}";
            $operationKey = "komibook-wallet:refund:{$opId}:credit";
            $expectedAccount = $this->lockActiveAccount($user);
            $keys = [$operationKey];
            if ($returnRequestId) {
                $keys[] = "komibook-wallet:refund:{$returnRequestId}:credit";
            }
            $existing = DemoWalletLedgerEntry::whereIn('operation_key', $keys)->lockForUpdate()->get();
            $allReturnCredits = $returnRequestId
                ? DemoWalletLedgerEntry::where('return_request_id', $returnRequestId)->where('entry_type', 'refund_credit')->lockForUpdate()->get()
                : collect();
            if ($returnRequestId && ($allReturnCredits->count() !== $existing->count() || $allReturnCredits->count() > 1)) {
                throw new LogicException('Bằng chứng hoàn Ví KomiBook có khóa tùy ý hoặc bị nhân bản.');
            }
            if ($existing->count() > 1) {
                throw new LogicException('Phát hiện hai bằng chứng hoàn Ví KomiBook trùng lặp giữa khóa hiện hành và khóa lịch sử.');
            }
            if ($existing->isNotEmpty()) {
                $entry = $existing->sole();
                if ((int) $entry->demo_wallet_account_id !== (int) $expectedAccount->id
                    || (int) $entry->payment_transaction_id !== (int) ($transaction?->id ?? 0)
                    || (int) $entry->order_id !== (int) $order->id
                    || (int) $entry->return_request_id !== (int) ($returnRequestId ?? 0)
                    || $entry->entry_type !== 'refund_credit'
                    || (int) $entry->amount !== $amount) {
                    throw new LogicException('Bằng chứng idempotency hoàn Ví KomiBook không nhất quán.');
                }
                $this->assertRefundCreditApplied($entry, $expectedAccount, $amount);

                return $entry;
            }

            return $this->creditAccount($expectedAccount, $amount, [
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
            if ($amount < 0) {
                throw new LogicException('Số tiền khấu trừ hoàn trả của nhà bán không hợp lệ.');
            }
            $operationKey = "komibook-wallet:vendor-refund:{$returnRequestId}:debit";
            $returnDebits = DemoWalletLedgerEntry::where('return_request_id', $returnRequestId)
                ->where('entry_type', 'vendor_refund_debit')->lockForUpdate()->get();
            if ($returnDebits->count() > 1) {
                throw new LogicException('Bằng chứng khấu trừ hoàn trả của nhà bán có khóa tùy ý hoặc bị nhân bản.');
            }
            $lockedVendor = Vendor::withoutGlobalScopes()->whereKey($vendor->id)->lockForUpdate()->firstOrFail();
            $user = User::whereKey($lockedVendor->user_id)->firstOrFail();
            $account = $this->ensureVendorProjectionImported($lockedVendor, $this->lockActiveAccount($user));
            if ($returnDebits->isNotEmpty()) {
                $existing = $returnDebits->sole();
                $this->assertVendorRefundDebitEvidence(
                    $existing,
                    $lockedVendor,
                    $account,
                    $order,
                    $amount,
                    $returnRequestId,
                );

                return $existing;
            }
            if (DemoWalletLedgerEntry::where('operation_key', $operationKey)->lockForUpdate()->exists()) {
                throw new LogicException('Bằng chứng idempotency khấu trừ hoàn trả của nhà bán không nhất quán.');
            }
            if ((int) $account->balance < $amount || (int) $lockedVendor->balance < $amount) {
                throw new LogicException('Số dư Ví KomiBook hoặc số dư nhà bán không đủ để hoàn trả đầy đủ.');
            }
            $vendorBalanceBefore = (int) $lockedVendor->balance;
            $entry = $this->debitAccount($account, $amount, [
                'order_id' => $order->id,
                'vendor_id' => $lockedVendor->id,
                'return_request_id' => $returnRequestId,
                'entry_type' => 'vendor_refund_debit',
                'operation_key' => $operationKey,
                'metadata' => [
                    'requested_amount' => $amount,
                    'vendor_balance_before' => $vendorBalanceBefore,
                    'vendor_balance_after' => $vendorBalanceBefore - $amount,
                ],
            ]);
            $lockedVendor->balance = $vendorBalanceBefore - $amount;
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

    public function assertVendorRefundDebitEvidence(
        DemoWalletLedgerEntry $entry,
        Vendor $vendor,
        DemoWalletAccount $account,
        Order $order,
        int $amount,
        int $returnRequestId,
    ): void {
        $metadata = $entry->metadata;
        if ((int) $account->user_id !== (int) $vendor->user_id
            || $account->status !== 'active'
            || $account->currency !== 'VND'
            || (int) $entry->demo_wallet_account_id !== (int) $account->id
            || (int) $entry->vendor_id !== (int) $vendor->id
            || (int) $entry->order_id !== (int) $order->id
            || (int) $entry->return_request_id !== $returnRequestId
            || $entry->entry_type !== 'vendor_refund_debit'
            || $entry->operation_key !== "komibook-wallet:vendor-refund:{$returnRequestId}:debit"
            || (int) $entry->amount !== $amount
            || ! is_array($metadata)
            || ! array_key_exists('requested_amount', $metadata)
            || ! array_key_exists('vendor_balance_before', $metadata)
            || ! array_key_exists('vendor_balance_after', $metadata)
            || (int) ($metadata['requested_amount'] ?? -1) !== $amount
            || (int) ($metadata['vendor_balance_before'] ?? -1) - $amount !== (int) ($metadata['vendor_balance_after'] ?? -1)) {
            throw new LogicException('Bằng chứng khấu trừ hoàn trả của nhà bán không nhất quán.');
        }
        $this->assertVendorRefundDebitApplied($entry, $account, $amount);
        $this->assertVendorBalanceProjection($vendor, $account);
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

    private function assertRefundCreditApplied(
        DemoWalletLedgerEntry $entry,
        DemoWalletAccount $account,
        int $amount
    ): void {
        if ((int) $entry->balance_before + $amount !== (int) $entry->balance_after) {
            throw new LogicException('Bằng chứng hoàn Ví KomiBook chưa chứng minh số dư đã được cộng chính xác.');
        }

        $this->assertAccountLedgerProjection($account);
    }

    private function assertVendorRefundDebitApplied(
        DemoWalletLedgerEntry $entry,
        DemoWalletAccount $account,
        int $amount
    ): void {
        if ((int) $entry->balance_before - $amount !== (int) $entry->balance_after) {
            throw new LogicException('Bằng chứng khấu trừ hoàn trả của nhà bán chưa chứng minh số dư đã được trừ chính xác.');
        }

        $this->assertAccountLedgerProjection($account);
    }

    private function assertVendorBalanceProjection(Vendor $vendor, DemoWalletAccount $account): void
    {
        $entries = DemoWalletLedgerEntry::where('vendor_id', $vendor->id)->orderBy('id')->lockForUpdate()->get();
        if ($entries->isEmpty()) {
            throw new LogicException('Projection số dư nhà bán không có chuỗi sổ cái để xác minh.');
        }

        $expectedBalance = 0;
        $seenImport = false;
        $payouts = [];
        $earningOrders = [];
        $refundReturns = [];
        foreach ($entries as $index => $ledgerEntry) {
            if ((int) $ledgerEntry->demo_wallet_account_id !== (int) $account->id || (int) $ledgerEntry->amount < 0) {
                throw new LogicException('Projection số dư nhà bán có bằng chứng sổ cái không nhất quán.');
            }
            $metadata = $ledgerEntry->metadata;
            if (! is_array($metadata)) {
                throw new LogicException('Projection số dư nhà bán có metadata sổ cái không nhất quán.');
            }

            switch ($ledgerEntry->entry_type) {
                case 'vendor_balance_import':
                    if ($seenImport || $index !== 0
                        || $ledgerEntry->operation_key !== "komibook-wallet:vendor:{$vendor->id}:runtime-balance-import"
                        || ($metadata['source'] ?? null) !== 'vendors.balance'
                        || ($metadata['runtime_compatibility'] ?? null) !== true) {
                        throw new LogicException('Projection số dư nhà bán có import lịch sử không nhất quán.');
                    }
                    $seenImport = true;
                    $expectedBalance += (int) $ledgerEntry->amount;

                    break;

                case 'vendor_earning_credit':
                    $orderId = (int) $ledgerEntry->order_id;
                    if ($orderId <= 0 || isset($earningOrders[$orderId])
                        || $ledgerEntry->operation_key !== "komibook-wallet:vendor-earning:{$ledgerEntry->order_id}:credit"
                        || ($metadata['source'] ?? null) !== 'completed_order') {
                        throw new LogicException('Projection số dư nhà bán có doanh thu không nhất quán.');
                    }
                    $earningOrders[$orderId] = true;
                    $expectedBalance += (int) $ledgerEntry->amount;

                    break;

                case 'vendor_refund_debit':
                    $returnId = (int) $ledgerEntry->return_request_id;
                    if (! $ledgerEntry->order_id || $returnId <= 0 || isset($refundReturns[$returnId])
                        || $ledgerEntry->operation_key !== "komibook-wallet:vendor-refund:{$ledgerEntry->return_request_id}:debit"
                        || ! array_key_exists('requested_amount', $metadata)
                        || ! array_key_exists('vendor_balance_before', $metadata)
                        || ! array_key_exists('vendor_balance_after', $metadata)
                        || (int) $metadata['requested_amount'] !== (int) $ledgerEntry->amount
                        || (int) $metadata['vendor_balance_before'] - (int) $ledgerEntry->amount !== (int) $metadata['vendor_balance_after']) {
                        throw new LogicException('Projection số dư nhà bán có hoàn trả không nhất quán.');
                    }
                    $refundReturns[$returnId] = true;
                    $expectedBalance -= (int) $ledgerEntry->amount;

                    break;

                case 'payout_reservation':
                    $payoutId = (int) $ledgerEntry->payout_request_id;
                    $reserved = (int) ($metadata['vendor_projection_reserved'] ?? -1);
                    if ($payoutId <= 0 || isset($payouts[$payoutId])
                        || $ledgerEntry->operation_key !== "komibook-wallet:payout:{$payoutId}:reserve"
                        || ! array_key_exists('reserved_balance_after', $metadata)
                        || $reserved < 0 || $reserved > (int) $ledgerEntry->amount) {
                        throw new LogicException('Projection số dư nhà bán có khoản giữ chi trả không nhất quán.');
                    }
                    $payouts[$payoutId] = ['amount' => (int) $ledgerEntry->amount, 'reserved' => $reserved, 'settled' => false];
                    $expectedBalance -= $reserved;

                    break;

                case 'payout_release':
                case 'payout_completed':
                    $payoutId = (int) $ledgerEntry->payout_request_id;
                    $payout = $payouts[$payoutId] ?? null;
                    $action = $ledgerEntry->entry_type === 'payout_release' ? 'release' : 'complete';
                    if (! $payout || $payout['settled']
                        || $ledgerEntry->operation_key !== "komibook-wallet:payout:{$payoutId}:{$action}"
                        || ! array_key_exists('reserved_balance_after', $metadata)
                        || (int) $ledgerEntry->amount !== $payout['amount']) {
                        throw new LogicException('Projection số dư nhà bán có đối soát chi trả không nhất quán.');
                    }
                    $payouts[$payoutId]['settled'] = true;
                    if ($action === 'release') {
                        $expectedBalance += $payout['reserved'];
                    }

                    break;

                default:
                    throw new LogicException('Projection số dư nhà bán có loại bằng chứng chưa được xác minh.');
            }
        }

        if ((int) $vendor->balance !== $expectedBalance) {
            throw new LogicException('Projection số dư nhà bán hiện tại không khớp chuỗi sổ cái.');
        }
    }

    private function assertAccountLedgerProjection(DemoWalletAccount $account): void
    {
        $entries = DemoWalletLedgerEntry::where('demo_wallet_account_id', $account->id)
            ->orderBy('id')->lockForUpdate()->get();
        if ($entries->isEmpty()) {
            throw new LogicException('Ví KomiBook không có chuỗi sổ cái để xác minh số dư.');
        }

        $previousAfter = null;
        foreach ($entries as $entry) {
            if ($previousAfter !== null && $previousAfter !== (int) $entry->balance_before) {
                throw new LogicException('Chuỗi sổ cái Ví KomiBook không liên tục.');
            }
            $previousAfter = (int) $entry->balance_after;
        }
        if ($previousAfter === null || (int) $account->balance !== $previousAfter) {
            throw new LogicException('Số dư Ví KomiBook không khớp với sổ cái.');
        }
    }
}
