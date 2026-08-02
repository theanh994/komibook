<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PayoutRequest;
use App\Models\WalletPayoutAccount;
use App\Services\DemoWalletService;
use App\Services\PayoutService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use LogicException;
use Throwable;

class WalletController extends Controller
{
    public function show(Request $request, DemoWalletService $wallets): JsonResponse
    {
        $user = $request->user();
        $wallet = $wallets->accountFor($user);
        $account = $this->payoutAccountFor($user);
        $payouts = PayoutRequest::withoutGlobalScopes()
            ->where('user_id', $user->id)->latest('id')->limit(50)->get();

        return response()->json(['status' => 'success', 'data' => [
            'wallet' => [
                'balance' => (int) $wallet->balance,
                'reserved_balance' => (int) $wallet->reserved_balance,
                'currency' => $wallet->currency,
                'status' => $wallet->status,
                'can_top_up' => false,
            ],
            'payout_account' => $this->accountData($account),
            'entries' => $wallet->entries()->latest('id')->limit(100)->get([
                'id', 'entry_type', 'amount', 'balance_before', 'balance_after', 'metadata', 'created_at',
            ]),
            'payout_requests' => $payouts->map(fn (PayoutRequest $payout) => [
                'id' => $payout->id,
                'code' => 'RT-'.str_pad((string) $payout->id, 6, '0', STR_PAD_LEFT),
                'amount' => (int) $payout->amount,
                'status' => $payout->status,
                'bank_name' => $payout->bank_name,
                'masked_account' => $this->mask($payout->account_number),
                'review_reason' => $payout->review_reason,
                'created_at' => $payout->created_at?->toISOString(),
            ]),
            'policy' => [
                'minimum_withdrawal' => 50000,
                'external_top_up_enabled' => false,
                'income_sources' => ['refund_cod', 'refund_vnpay', 'used_book_sale', 'vendor_earning'],
                'notice' => 'Ví chỉ nhận tiền từ hoàn trả và doanh thu hợp lệ trong KomiBook; hệ thống không hỗ trợ nạp tiền ngoài.',
            ],
        ]]);
    }

    public function savePayoutAccount(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'bank_name' => 'required|string|max:255',
            'account_number' => ['required', 'string', 'min:6', 'max:64', 'regex:/^[0-9]+$/'],
            'account_name' => 'required|string|max:255',
        ]);
        $user = $request->user();
        $current = WalletPayoutAccount::where('user_id', $user->id)->first();
        $changed = ! $current
            || $current->bank_name !== trim($validated['bank_name'])
            || $current->account_number !== trim($validated['account_number'])
            || $current->account_name !== Str::upper(trim($validated['account_name']));
        $account = WalletPayoutAccount::updateOrCreate(['user_id' => $user->id], [
            'bank_name' => trim($validated['bank_name']),
            'account_number' => trim($validated['account_number']),
            'account_name' => Str::upper(trim($validated['account_name'])),
            'status' => $changed ? 'unverified' : $current->status,
            'verified_by' => $changed ? null : $current->verified_by,
            'verified_at' => $changed ? null : $current->verified_at,
            'review_reason' => null,
        ]);

        return response()->json([
            'status' => 'success',
            'message' => $changed ? 'Đã lưu tài khoản nhận tiền và chuyển sang chờ Admin xác minh.' : 'Thông tin tài khoản không thay đổi.',
            'data' => $this->accountData($account),
        ]);
    }

    public function requestWithdrawal(Request $request, PayoutService $payouts): JsonResponse
    {
        $validated = $request->validate([
            'amount' => 'required|integer|min:50000',
            'idempotency_key' => 'required|string|max:100',
        ]);
        $account = WalletPayoutAccount::where('user_id', $request->user()->id)->first();
        if (! $account) {
            return response()->json(['message' => 'Bạn chưa khai báo tài khoản nhận tiền.'], 422);
        }
        try {
            $payout = $payouts->reserveWallet(
                $request->user(),
                $account,
                $validated,
                $validated['idempotency_key'],
            );

            return response()->json([
                'status' => 'success',
                'message' => 'Yêu cầu rút tiền đã được giữ số dư và chuyển sang chờ Admin duyệt.',
                'data' => $payout,
            ], $payout->wasRecentlyCreated ? 201 : 200);
        } catch (LogicException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        } catch (Throwable $exception) {
            report($exception);

            return response()->json(['message' => 'Không thể tạo yêu cầu rút tiền lúc này.'], 500);
        }
    }

    private function payoutAccountFor($user): ?WalletPayoutAccount
    {
        $account = WalletPayoutAccount::where('user_id', $user->id)->first();
        $vendor = $user->vendor;
        if (! $account && $vendor && $vendor->payout_bank_status === 'verified'
            && filled($vendor->payout_bank_name) && filled($vendor->payout_bank_account) && filled($vendor->payout_bank_holder)) {
            $account = WalletPayoutAccount::create([
                'user_id' => $user->id,
                'bank_name' => $vendor->payout_bank_name,
                'account_number' => $vendor->payout_bank_account,
                'account_name' => Str::upper($vendor->payout_bank_holder),
                'status' => 'verified',
                'verified_by' => $vendor->payout_bank_verified_by,
                'verified_at' => $vendor->payout_bank_verified_at,
            ]);
        }

        return $account;
    }

    private function accountData(?WalletPayoutAccount $account): ?array
    {
        return $account ? [
            'id' => $account->id,
            'bank_name' => $account->bank_name,
            'masked_account' => $this->mask($account->account_number),
            'account_name' => $account->account_name,
            'status' => $account->status,
            'review_reason' => $account->review_reason,
        ] : null;
    }

    private function mask(?string $number): ?string
    {
        return $number ? str_repeat('•', max(0, mb_strlen($number) - 4)).mb_substr($number, -4) : null;
    }
}
