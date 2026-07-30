<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\PayoutRequest;
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
            $query->whereHas('vendor', fn ($vendor) => $vendor->where('shop_name', 'like', '%'.$request->search.'%'));
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
