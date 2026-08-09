<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\UsedBookListingResource;
use App\Models\UsedBookListing;
use App\Services\UsedBookInventoryService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminUsedBookController extends Controller
{
    public function index(Request $request)
    {
        $query = UsedBookListing::query()->with(['book.category', 'seller', 'fulfillmentAddress'])->latest();
        if (($status = $request->query('status', 'pending')) !== 'all') {
            $query->where('status', $status);
        }

        return UsedBookListingResource::collection($query->paginate(20));
    }

    public function approve(Request $request, UsedBookListing $listing, UsedBookInventoryService $inventory)
    {
        DB::transaction(function () use ($listing, $inventory) {
            $locked = UsedBookListing::whereKey($listing->id)->lockForUpdate()->firstOrFail();
            $check = $inventory->inspect($locked, true);
            abort_unless($check['valid'], 422, 'Used-book inventory is incoherent: '.$check['reason_code']);
            $zero = (int) $check['stock']->quantity === 0;
            $check['book']->update(['stock' => (int) $check['stock']->quantity, 'status' => $zero ? 'draft' : 'published', 'publishing_status' => $zero ? 'submitted_for_review' : 'published']);
            $locked->update(['status' => $zero ? 'sold_out' : 'active', 'rejection_reason' => null]);
        });
        $listing = $listing->fresh()->load(['book.category', 'seller', 'fulfillmentAddress']);

        return response()->json(['status' => 'success', 'message' => 'Used-book listing approved.', 'data' => new UsedBookListingResource($listing)]);
    }

    public function reject(Request $request, UsedBookListing $listing)
    {
        $validated = $request->validate(['rejection_reason' => 'required|string|max:2000']);
        DB::transaction(function () use ($listing, $validated) {
            $locked = UsedBookListing::whereKey($listing->id)->lockForUpdate()->firstOrFail();
            $locked->update(['status' => 'rejected', 'rejection_reason' => $validated['rejection_reason']]);
            $locked->book()->withoutGlobalScopes()->update(['status' => 'archived', 'publishing_status' => 'changes_requested']);
        });

        return response()->json(['status' => 'success', 'message' => 'Used-book listing rejected.', 'data' => new UsedBookListingResource($listing->fresh()->load(['book.category', 'seller', 'fulfillmentAddress']))]);
    }
}
