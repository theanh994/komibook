<?php

namespace App\Http\Controllers\Api\Admin;

use App\Enums\CopyrightClaimStatus;
use App\Http\Controllers\Controller;
use App\Models\CopyrightClaim;
use App\Services\CopyrightClaimService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class CopyrightClaimController extends Controller
{
    public function index(Request $request)
    {
        $claims = CopyrightClaim::with(['book', 'ownerAuthor.user', 'participants.user'])
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->input('status')))
            ->latest()->paginate(20);

        return response()->json(['status' => 'success', 'data' => $claims]);
    }

    public function transition(Request $request, CopyrightClaim $claim, CopyrightClaimService $service)
    {
        $validated = $request->validate([
            'to_status' => 'required|in:under_review,verified,changes_requested,rejected,disputed,revoked',
            'reason' => 'nullable|string|max:1000',
            'operation_key' => 'nullable|string|max:100',
        ]);
        $updated = $service->transition(
            $claim,
            CopyrightClaimStatus::from($validated['to_status']),
            $request->user(),
            $validated['reason'] ?? null,
            $validated['operation_key'] ?? $request->header('Idempotency-Key') ?? 'admin-copyright:'.Str::uuid(),
        );

        return response()->json(['status' => 'success', 'data' => $updated]);
    }
}
