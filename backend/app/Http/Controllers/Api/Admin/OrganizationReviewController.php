<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Models\VendorOrganizationRelationship;
use App\Services\OrganizationRelationshipService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class OrganizationReviewController extends Controller
{
    public function index()
    {
        return response()->json(['status' => 'success', 'data' => [
            'organizations' => Organization::whereIn('status', ['pending_review', 'verified', 'suspended'])
                ->latest()->paginate(25),
            'relationships' => VendorOrganizationRelationship::with(['organization', 'vendor:id,shop_name,slug'])
                ->whereIn('status', ['submitted', 'changes_requested', 'verified', 'suspended'])
                ->latest()->paginate(25),
        ]]);
    }

    public function transitionOrganization(Request $request, Organization $organization)
    {
        $validated = $request->validate([
            'to_status' => ['required', Rule::in(['verified', 'rejected', 'suspended', 'archived'])],
            'reason' => ['nullable', 'string', 'max:1000'],
        ]);
        if (in_array($validated['to_status'], ['rejected', 'suspended', 'archived'], true) && blank($validated['reason'] ?? null)) {
            return response()->json(['message' => 'Phải nhập lý do cho quyết định này.'], 422);
        }
        $updates = [
            'status' => $validated['to_status'],
            'last_review_reason' => $validated['reason'] ?? null,
            'verified_by' => $request->user()->id,
        ];
        if ($validated['to_status'] === 'verified') {
            $updates['verified_at'] = now();
            $updates['suspended_at'] = null;
        } elseif ($validated['to_status'] === 'suspended') {
            $updates['suspended_at'] = now();
        } elseif ($validated['to_status'] === 'archived') {
            $updates['archived_at'] = now();
        }
        $organization->update($updates);

        return response()->json(['status' => 'success', 'data' => $organization->fresh()]);
    }

    public function transitionRelationship(
        Request $request,
        VendorOrganizationRelationship $relationship,
        OrganizationRelationshipService $service,
    ) {
        $validated = $request->validate([
            'to_status' => ['required', Rule::in(['verified', 'changes_requested', 'rejected', 'suspended', 'revoked'])],
            'reason' => ['nullable', 'string', 'max:1000'],
            'operation_key' => ['nullable', 'string', 'max:128'],
        ]);

        return response()->json(['status' => 'success', 'data' => $service->transition(
            $relationship,
            $validated['to_status'],
            $request->user(),
            $validated['reason'] ?? null,
            $validated['operation_key'] ?? 'admin-relationship:'.Str::uuid(),
        )]);
    }
}
