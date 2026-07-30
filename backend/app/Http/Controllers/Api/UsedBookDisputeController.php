<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\OrderItem;
use App\Models\UsedBookDispute;
use App\Models\UsedBookListing;
use Illuminate\Http\Request;

class UsedBookDisputeController extends Controller
{
    public function store(Request $request)
    {
        $validated = $request->validate([
            'order_item_id' => 'required|integer|exists:order_items,id', 'type' => 'required|in:counterfeit,misdescription',
            'description' => 'required|string|min:20|max:3000', 'evidence' => 'nullable|array|max:8',
            'evidence.*' => 'file|mimes:jpg,jpeg,png,webp,pdf|max:10240',
        ]);
        $item = OrderItem::with('order')->findOrFail($validated['order_item_id']);
        abort_unless($item->order->user_id === $request->user()->id, 403);
        abort_unless(($item->product_taxonomy_snapshot['provenance'] ?? null) === 'used_resale', 422, 'Chỉ sách cũ mới dùng luồng tranh chấp này.');
        $listing = UsedBookListing::where('book_id', $item->book_id)->firstOrFail();
        $paths = collect($request->file('evidence', []))->map(fn ($file) => $file->store('used-books/disputes', 'private'))->all();
        $dispute = UsedBookDispute::create([
            ...$validated, 'reporter_id' => $request->user()->id, 'used_book_listing_id' => $listing->id,
            'evidence' => $paths, 'held_amount' => $item->price * $item->quantity, 'status' => 'submitted', 'hold_status' => 'active',
        ]);
        return response()->json(['status' => 'success', 'data' => $dispute], 201);
    }

    public function resolve(Request $request, UsedBookDispute $dispute)
    {
        $validated = $request->validate([
            'decision' => 'required|in:confirmed,dismissed', 'resolution' => 'required|string|max:3000',
            'sanction' => 'nullable|in:warning,suspend_listing,suspend_author',
        ]);
        $confirmed = $validated['decision'] === 'confirmed';
        $dispute->update([
            'status' => $validated['decision'], 'resolution' => $validated['resolution'],
            'sanction' => $confirmed ? ($validated['sanction'] ?? 'warning') : null,
            'hold_status' => $confirmed ? 'consumed' : 'released', 'resolved_by' => $request->user()->id, 'resolved_at' => now(),
        ]);
        if ($confirmed && (($validated['sanction'] ?? null) !== null)) {
            UsedBookListing::whereKey($dispute->used_book_listing_id)->update(['status' => 'suspended']);
        }
        return response()->json(['status' => 'success', 'data' => $dispute->fresh()]);
    }
}
