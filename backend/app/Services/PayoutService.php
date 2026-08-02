<?php

namespace App\Services;

use App\Models\PayoutLedgerEntry;
use App\Models\PayoutRequest;
use App\Models\PayoutTransition;
use App\Models\User;
use App\Models\Vendor;
use App\Models\VendorFinancialHold;
use App\Models\WalletPayoutAccount;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use LogicException;

class PayoutService
{
    public function __construct(private readonly DemoWalletService $wallets) {}

    public function reserve(Vendor $vendor, array $data, ?User $actor, ?string $operationKey = null): PayoutRequest
    {
        $operationKey ??= (string) Str::uuid();

        return DB::transaction(function () use ($vendor, $data, $actor, $operationKey) {
            $lockedVendor = Vendor::withoutGlobalScopes()->whereKey($vendor->id)->lockForUpdate()->firstOrFail();
            if ($lockedVendor->is_demo) {
                throw new LogicException('Ví đối soát demo không được phép tạo yêu cầu rút tiền thật.');
            }
            if ($lockedVendor->payout_bank_status !== 'verified'
                || blank($lockedVendor->payout_bank_account)
                || blank($lockedVendor->payout_bank_name)
                || blank($lockedVendor->payout_bank_holder)) {
                throw new LogicException('Tài khoản ngân hàng nhận doanh thu chưa được xác minh.');
            }
            $existing = PayoutRequest::withoutGlobalScopes()->where('operation_key', $operationKey)->first();
            if ($existing) {
                if ($existing->vendor_id !== $vendor->id || $existing->amount !== (int) $data['amount']) {
                    throw new LogicException('Khóa thao tác đã được dùng cho yêu cầu rút tiền khác.');
                }

                return $existing;
            }
            $holds = (int) VendorFinancialHold::where('vendor_id', $lockedVendor->id)->where('status', 'active')->sum('amount');
            $before = (int) $lockedVendor->balance;
            if (max(0, $before - $holds) < (int) $data['amount']) {
                throw new LogicException('Số dư khả dụng không đủ để thực hiện yêu cầu này.');
            }
            $payout = PayoutRequest::create([
                'vendor_id' => $lockedVendor->id, 'user_id' => $lockedVendor->user_id,
                'operation_key' => $operationKey, 'amount' => (int) $data['amount'],
                'bank_name' => $lockedVendor->payout_bank_name,
                'account_number' => $lockedVendor->payout_bank_account,
                'account_name' => Str::upper($lockedVendor->payout_bank_holder),
                'status' => 'pending',
            ]);
            $walletReservation = $this->wallets->reservePayout($lockedVendor, $payout);
            PayoutLedgerEntry::create([
                'payout_request_id' => $payout->id, 'vendor_id' => $lockedVendor->id, 'user_id' => $lockedVendor->user_id,
                'demo_wallet_account_id' => $walletReservation->demo_wallet_account_id, 'actor_id' => $actor?->id,
                'entry_type' => 'reservation', 'amount' => $payout->amount, 'balance_before' => $before,
                'balance_after' => $before - $payout->amount, 'operation_key' => "payout:{$payout->id}:reservation",
            ]);
            PayoutTransition::create([
                'payout_request_id' => $payout->id, 'actor_id' => $actor?->id, 'from_status' => null,
                'to_status' => 'pending', 'operation_key' => "payout:{$payout->id}:created",
            ]);

            return $payout;
        });
    }

    public function reserveWallet(User $user, WalletPayoutAccount $payoutAccount, array $data, string $operationKey): PayoutRequest
    {
        return DB::transaction(function () use ($user, $payoutAccount, $data, $operationKey) {
            $account = WalletPayoutAccount::whereKey($payoutAccount->id)->lockForUpdate()->firstOrFail();
            if ((int) $account->user_id !== (int) $user->id || $account->status !== 'verified') {
                throw new LogicException('Tài khoản nhận tiền chưa được Admin xác minh.');
            }
            $existing = PayoutRequest::withoutGlobalScopes()->where('operation_key', $operationKey)->first();
            if ($existing) {
                if ((int) $existing->user_id !== (int) $user->id || (int) $existing->amount !== (int) $data['amount']) {
                    throw new LogicException('Khóa thao tác đã được dùng cho yêu cầu rút tiền khác.');
                }

                return $existing;
            }

            $vendor = Vendor::withoutGlobalScopes()->where('user_id', $user->id)->first();
            if ($vendor?->is_demo) {
                throw new LogicException('Tài khoản demo không được tạo yêu cầu chuyển tiền thật.');
            }
            $wallet = $this->wallets->accountFor($user);
            if ((int) $wallet->balance < (int) $data['amount']) {
                throw new LogicException('Số dư Ví KomiBook không đủ để thực hiện yêu cầu này.');
            }

            $payout = PayoutRequest::create([
                'vendor_id' => $vendor?->id,
                'user_id' => $user->id,
                'wallet_payout_account_id' => $account->id,
                'operation_key' => $operationKey,
                'amount' => (int) $data['amount'],
                'bank_name' => $account->bank_name,
                'account_number' => $account->account_number,
                'account_name' => Str::upper($account->account_name),
                'status' => 'pending',
            ]);
            $reservation = $this->wallets->reserveWithdrawal($user, $payout, $vendor);
            PayoutLedgerEntry::create([
                'payout_request_id' => $payout->id,
                'vendor_id' => $vendor?->id,
                'user_id' => $user->id,
                'demo_wallet_account_id' => $reservation->demo_wallet_account_id,
                'actor_id' => $user->id,
                'entry_type' => 'reservation',
                'amount' => $payout->amount,
                'balance_before' => $reservation->balance_before,
                'balance_after' => $reservation->balance_after,
                'operation_key' => "payout:{$payout->id}:reservation",
                'metadata' => ['owner_type' => $vendor ? 'vendor_or_used_book_seller' : 'customer'],
            ]);
            PayoutTransition::create([
                'payout_request_id' => $payout->id,
                'actor_id' => $user->id,
                'from_status' => null,
                'to_status' => 'pending',
                'operation_key' => "payout:{$payout->id}:created",
            ]);

            return $payout;
        });
    }

    public function transition(PayoutRequest $payout, string $target, User $actor, string $operationKey, array $data = []): PayoutRequest
    {
        return DB::transaction(function () use ($payout, $target, $actor, $operationKey, $data) {
            $existing = PayoutTransition::where('operation_key', $operationKey)->first();
            if ($existing) {
                if ($existing->payout_request_id !== $payout->id || $existing->to_status !== $target) {
                    throw new LogicException('Khóa thao tác đã được dùng cho chuyển trạng thái khác.');
                }

                return PayoutRequest::withoutGlobalScopes()->findOrFail($payout->id);
            }
            $locked = PayoutRequest::withoutGlobalScopes()->whereKey($payout->id)->lockForUpdate()->firstOrFail();
            $allowed = ['pending' => ['approved', 'rejected'], 'approved' => ['processing'], 'processing' => ['completed']];
            if (! in_array($target, $allowed[$locked->status] ?? [], true)) {
                throw new LogicException("Không thể chuyển payout từ {$locked->status} sang {$target}.");
            }
            if (in_array($target, ['approved', 'rejected'], true) && empty($data['reason'])) {
                throw new LogicException('Bắt buộc có lý do duyệt hoặc từ chối.');
            }
            if ($target === 'processing' && empty($data['transfer_reference'])) {
                throw new LogicException('Bắt buộc có mã tham chiếu chuyển khoản.');
            }
            if ($target === 'completed' && (empty($data['transfer_reference']) || empty($data['transfer_evidence']))) {
                throw new LogicException('Bắt buộc có mã tham chiếu và bằng chứng chuyển khoản.');
            }

            $from = $locked->status;
            $updates = ['status' => $target, 'reviewed_by' => $actor->id];
            if (in_array($target, ['approved', 'rejected'], true)) {
                $updates += ['reviewed_at' => now(), 'review_reason' => $data['reason'] ?? null];
            }
            if ($target === 'processing') {
                $updates += ['processing_at' => now(), 'transfer_reference' => $data['transfer_reference']];
            }
            if ($target === 'completed') {
                $updates += ['completed_at' => now(), 'transfer_reference' => $data['transfer_reference'], 'transfer_evidence' => $data['transfer_evidence']];
            }
            if ($target === 'rejected') {
                $updates['rejected_at'] = now();
            }
            $locked->update($updates);

            $user = User::whereKey($locked->user_id ?: $locked->vendor?->user_id)->lockForUpdate()->firstOrFail();
            $vendor = $locked->vendor_id
                ? Vendor::withoutGlobalScopes()->whereKey($locked->vendor_id)->lockForUpdate()->first()
                : null;
            if ($target === 'rejected') {
                $walletEntry = $this->wallets->releaseWithdrawal($user, $locked, $vendor);
                PayoutLedgerEntry::create(['payout_request_id' => $locked->id, 'vendor_id' => $vendor?->id, 'user_id' => $user->id, 'demo_wallet_account_id' => $walletEntry->demo_wallet_account_id, 'actor_id' => $actor->id, 'entry_type' => 'reservation_release', 'amount' => $locked->amount, 'balance_before' => $walletEntry->balance_before, 'balance_after' => $walletEntry->balance_after, 'operation_key' => "payout:{$locked->id}:release"]);
            } elseif ($target === 'completed') {
                $walletEntry = $this->wallets->completeWithdrawal($user, $locked, $vendor);
                $vendor?->increment('total_withdrawn', $locked->amount);
                PayoutLedgerEntry::create(['payout_request_id' => $locked->id, 'vendor_id' => $vendor?->id, 'user_id' => $user->id, 'demo_wallet_account_id' => $walletEntry->demo_wallet_account_id, 'actor_id' => $actor->id, 'entry_type' => 'completed', 'amount' => $locked->amount, 'balance_before' => $walletEntry->balance_before, 'balance_after' => $walletEntry->balance_after, 'operation_key' => "payout:{$locked->id}:completed", 'metadata' => ['transfer_reference' => $data['transfer_reference'], 'transfer_evidence' => $data['transfer_evidence']]]);
            }
            PayoutTransition::create(['payout_request_id' => $locked->id, 'actor_id' => $actor->id, 'from_status' => $from, 'to_status' => $target, 'reason' => $data['reason'] ?? null, 'operation_key' => $operationKey, 'metadata' => array_filter(['transfer_reference' => $data['transfer_reference'] ?? null, 'transfer_evidence' => $data['transfer_evidence'] ?? null])]);

            return $locked->fresh();
        });
    }
}
