<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\PayoutRequest;
use App\Models\WalletPayoutAccount;
use App\Services\PayoutService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use LogicException;

class ReconciliationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = PayoutRequest::with([
            'vendor:id,shop_name,balance,total_withdrawn',
            'user:id,name,email',
            'ledgerEntries:id,payout_request_id,entry_type,created_at',
            'latestTransition' => fn ($transition) => $transition->select([
                'payout_transitions.id',
                'payout_transitions.payout_request_id',
                'payout_transitions.from_status',
                'payout_transitions.to_status',
                'payout_transitions.reason',
                'payout_transitions.created_at',
            ]),
        ])->latest('id');
        if ($request->filled('status') && $request->status !== 'all') {
            $query->where('status', $request->status);
        }
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function (Builder $owner) use ($search) {
                $owner->whereHas('vendor', fn ($vendor) => $vendor->where('shop_name', 'like', '%'.$search.'%'))
                    ->orWhereHas('user', fn ($user) => $user->where('name', 'like', '%'.$search.'%')->orWhere('email', 'like', '%'.$search.'%'));
            });
        }
        $items = $query->paginate($request->integer('per_page', 15));
        $items->getCollection()->transform(function (PayoutRequest $payout) {
            $issues = $this->integrityIssues($payout);
            $latestTransition = $payout->latestTransition;
            $payout->setAttribute('reconciliation', [
                'is_consistent' => $issues === [],
                'issues' => $issues,
                'ledger_entry_count' => $payout->ledgerEntries->count(),
                'latest_transition' => $latestTransition ? [
                    'from_status' => $latestTransition->from_status,
                    'to_status' => $latestTransition->to_status,
                    'reason' => $latestTransition->reason,
                    'created_at' => $latestTransition->created_at,
                ] : null,
            ]);
            $payout->makeHidden(['ledgerEntries', 'latestTransition']);

            return $payout;
        });

        return response()->json(['status' => 'success', 'data' => [
            'kpi' => [
                'pending_payout' => (int) PayoutRequest::where('status', 'pending')->sum('amount'),
                'approved_payout' => (int) PayoutRequest::whereIn('status', ['approved', 'processing'])->sum('amount'),
                'total_settled' => (int) PayoutRequest::where('status', 'completed')->sum('amount'),
                'unreconciled' => $this->integrityMismatchQuery()->count(),
            ],
            'payout_requests' => $items->items(),
            'payout_accounts' => WalletPayoutAccount::with('user:id,name,email')
                ->whereIn('status', ['unverified', 'rejected'])->latest('updated_at')->limit(100)->get()
                ->map(fn (WalletPayoutAccount $account) => [
                    'id' => $account->id,
                    'user' => $account->user,
                    'bank_name' => $account->bank_name,
                    'masked_account' => str_repeat('•', max(0, mb_strlen($account->account_number) - 4)).mb_substr($account->account_number, -4),
                    'account_name' => $account->account_name,
                    'status' => $account->status,
                    'review_reason' => $account->review_reason,
                    'updated_at' => $account->updated_at,
                ]),
            'meta' => ['current_page' => $items->currentPage(), 'last_page' => $items->lastPage(), 'per_page' => $items->perPage(), 'total' => $items->total()],
        ]]);
    }

    private function integrityMismatchQuery(): Builder
    {
        return PayoutRequest::withoutGlobalScopes()->where(function (Builder $query) {
            $query
                ->whereDoesntHave('ledgerEntries', fn (Builder $ledger) => $ledger->whereIn('entry_type', ['reservation', 'legacy_import']))
                ->orWhereDoesntHave('transitions')
                ->orWhereRaw('(SELECT payout_transitions.to_status FROM payout_transitions WHERE payout_transitions.payout_request_id = payout_requests.id ORDER BY payout_transitions.id DESC LIMIT 1) <> payout_requests.status')
                ->orWhere(function (Builder $completed) {
                    $completed->where('status', 'completed')->where(function (Builder $invalid) {
                        $invalid->whereNull('transfer_reference')
                            ->orWhereNull('transfer_evidence')
                            ->orWhereDoesntHave('ledgerEntries', fn (Builder $ledger) => $ledger->where('entry_type', 'completed'));
                    });
                })
                ->orWhere(function (Builder $rejected) {
                    $rejected->where('status', 'rejected')
                        ->whereDoesntHave('ledgerEntries', fn (Builder $ledger) => $ledger->where('entry_type', 'reservation_release'));
                });
        });
    }

    private function integrityIssues(PayoutRequest $payout): array
    {
        $types = $payout->ledgerEntries->pluck('entry_type');
        $issues = [];

        if (! $types->contains(fn (string $type) => in_array($type, ['reservation', 'legacy_import'], true))) {
            $issues[] = 'missing_source_ledger';
        }
        if (! $payout->latestTransition) {
            $issues[] = 'missing_transition';
        } elseif ($payout->latestTransition->to_status !== $payout->status) {
            $issues[] = 'status_transition_mismatch';
        }
        if ($payout->status === 'completed') {
            if (! $types->contains('completed')) {
                $issues[] = 'missing_completed_ledger';
            }
            if (! $payout->transfer_reference || ! $payout->transfer_evidence) {
                $issues[] = 'missing_transfer_evidence';
            }
        }
        if ($payout->status === 'rejected' && ! $types->contains('reservation_release')) {
            $issues[] = 'missing_reservation_release';
        }

        return $issues;
    }

    public function transition(Request $request, PayoutRequest $payout, PayoutService $payouts): JsonResponse
    {
        $validated = $request->validate([
            'target' => 'required|in:approved,rejected,processing,completed', 'reason' => 'nullable|string|max:1000',
            'transfer_reference' => 'nullable|string|max:120', 'transfer_evidence' => 'nullable|string|max:500',
            'idempotency_key' => 'required|string|max:120',
        ]);
        try {
            $updated = $payouts->transition($payout, $validated['target'], $request->user(), $validated['idempotency_key'], $validated);

            return response()->json(['status' => 'success', 'data' => $updated]);
        } catch (LogicException $exception) {
            return response()->json(['status' => 'error', 'message' => $exception->getMessage()], 422);
        }
    }

    public function reviewPayoutAccount(Request $request, WalletPayoutAccount $account): JsonResponse
    {
        $validated = $request->validate([
            'target' => 'required|in:verified,rejected',
            'reason' => 'required|string|max:1000',
        ]);
        $account->update([
            'status' => $validated['target'],
            'verified_by' => $validated['target'] === 'verified' ? $request->user()->id : null,
            'verified_at' => $validated['target'] === 'verified' ? now() : null,
            'review_reason' => $validated['reason'],
        ]);

        return response()->json(['status' => 'success', 'data' => [
            'id' => $account->id,
            'status' => $account->status,
            'review_reason' => $account->review_reason,
        ]]);
    }

    public function approve(Request $request, int $id, PayoutService $payouts): JsonResponse
    {
        $request->merge(['target' => 'approved', 'idempotency_key' => $request->input('idempotency_key', (string) Str::uuid())]);

        return $this->transition($request, PayoutRequest::withoutGlobalScopes()->findOrFail($id), $payouts);
    }

    public function reject(Request $request, int $id, PayoutService $payouts): JsonResponse
    {
        $request->merge(['target' => 'rejected', 'idempotency_key' => $request->input('idempotency_key', (string) Str::uuid())]);

        return $this->transition($request, PayoutRequest::withoutGlobalScopes()->findOrFail($id), $payouts);
    }
}
