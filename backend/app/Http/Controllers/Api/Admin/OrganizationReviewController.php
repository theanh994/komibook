<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Book;
use App\Models\Organization;
use App\Models\OrganizationDistributionAgreement;
use App\Models\VendorOrganizationRelationship;
use App\Services\DistributionAgreementService;
use App\Services\OrganizationRelationshipService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class OrganizationReviewController extends Controller
{
    public function index(Request $request)
    {
        $validated = $request->validate([
            'section' => ['nullable', Rule::in(['organizations', 'relationships', 'agreements', 'books'])],
            'search' => ['nullable', 'string', 'max:120'],
            'status' => ['nullable', 'string', 'max:64'],
            'data_mode' => ['nullable', Rule::in(['all', 'real', 'public_reference', 'demo'])],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', Rule::in([10, 25, 50])],
        ]);

        $section = $validated['section'] ?? 'organizations';
        $search = trim($validated['search'] ?? '');
        $status = $validated['status'] ?? null;
        $dataMode = $validated['data_mode'] ?? 'all';
        $perPage = (int) ($validated['per_page'] ?? 10);

        $items = match ($section) {
            'relationships' => VendorOrganizationRelationship::query()
                ->with(['organization', 'vendor:id,shop_name,slug,status,onboarding_status,is_demo'])
                ->whereIn('status', ['submitted', 'changes_requested', 'verified', 'demo_accepted', 'suspended'])
                ->when($search, fn ($query) => $query->where(fn ($searchQuery) => $searchQuery
                    ->where('role', 'like', "%{$search}%")
                    ->orWhere('demo_reference', 'like', "%{$search}%")
                    ->orWhereHas('vendor', fn ($vendor) => $vendor->where('shop_name', 'like', "%{$search}%"))
                    ->orWhereHas('organization', fn ($organization) => $organization->where('display_name', 'like', "%{$search}%"))))
                ->when($status, fn ($query) => $query->where('status', $status))
                ->when($dataMode !== 'all', fn ($query) => $query->where('is_demo', $dataMode === 'demo'))
                ->latest()
                ->paginate($perPage),
            'agreements' => OrganizationDistributionAgreement::query()
                ->with(['publisher', 'distributor'])
                ->whereIn('status', ['submitted', 'changes_requested', 'verified', 'demo_accepted', 'suspended'])
                ->when($search, fn ($query) => $query->where(fn ($searchQuery) => $searchQuery
                    ->where('demo_reference', 'like', "%{$search}%")
                    ->orWhereHas('publisher', fn ($publisher) => $publisher->where('display_name', 'like', "%{$search}%"))
                    ->orWhereHas('distributor', fn ($distributor) => $distributor->where('display_name', 'like', "%{$search}%"))))
                ->when($status, fn ($query) => $query->where('status', $status))
                ->when($dataMode !== 'all', fn ($query) => $query->where('is_demo', $dataMode === 'demo'))
                ->latest()
                ->paginate($perPage),
            'books' => Book::withoutGlobalScopes()
                ->with('vendor:id,shop_name,slug,status,onboarding_status,is_demo')
                ->where('status', 'published')
                ->where(fn ($query) => $query->whereNull('provenance')->orWhere('provenance', '!=', 'used_resale'))
                ->whereDoesntHave('activeCommercialParties')
                ->when($search, fn ($query) => $query->where(fn ($searchQuery) => $searchQuery
                    ->where('title', 'like', "%{$search}%")
                    ->orWhere('slug', 'like', "%{$search}%")
                    ->orWhereHas('vendor', fn ($vendor) => $vendor->withoutGlobalScopes()->where('shop_name', 'like', "%{$search}%"))))
                ->when($dataMode !== 'all', fn ($query) => $query->whereHas('vendor', fn ($vendor) => $vendor
                    ->withoutGlobalScopes()
                    ->where('is_demo', $dataMode === 'demo')))
                ->latest()
                ->paginate($perPage),
            default => Organization::query()
                ->whereIn('status', ['pending_review', 'verified', 'demo_accepted', 'suspended'])
                ->when($search, fn ($query) => $query->where(fn ($searchQuery) => $searchQuery
                    ->where('display_name', 'like', "%{$search}%")
                    ->orWhere('legal_name', 'like', "%{$search}%")
                    ->orWhere('slug', 'like', "%{$search}%")))
                ->when($status, fn ($query) => $query->where('status', $status))
                ->when($dataMode !== 'all', fn ($query) => $query->where('data_mode', $dataMode))
                ->latest()
                ->paginate($perPage),
        };

        return response()->json(['status' => 'success', 'data' => [
            'section' => $section,
            'summary' => [
                'organizations' => Organization::whereIn('status', ['pending_review', 'verified', 'demo_accepted', 'suspended'])->count(),
                'relationships' => VendorOrganizationRelationship::whereIn('status', ['submitted', 'changes_requested', 'verified', 'demo_accepted', 'suspended'])->count(),
                'agreements' => OrganizationDistributionAgreement::whereIn('status', ['submitted', 'changes_requested', 'verified', 'demo_accepted', 'suspended'])->count(),
                'unlinked_books' => Book::withoutGlobalScopes()
                    ->where('status', 'published')
                    ->where(fn ($query) => $query->whereNull('provenance')->orWhere('provenance', '!=', 'used_resale'))
                    ->whereDoesntHave('activeCommercialParties')
                    ->count(),
            ],
            'items' => $items,
        ]]);
    }

    public function transitionOrganization(Request $request, Organization $organization)
    {
        $validated = $request->validate([
            'to_status' => ['required', Rule::in(['verified', 'demo_accepted', 'rejected', 'suspended', 'archived'])],
            'reason' => ['nullable', 'string', 'max:1000'],
        ]);
        if (in_array($validated['to_status'], ['rejected', 'suspended', 'archived'], true) && blank($validated['reason'] ?? null)) {
            return response()->json(['message' => 'Phải nhập lý do cho quyết định này.'], 422);
        }
        if ($validated['to_status'] === 'demo_accepted' && $organization->data_mode !== 'demo') {
            return response()->json(['message' => 'Chỉ hồ sơ dữ liệu demo mới được duyệt mô phỏng.'], 422);
        }
        if ($validated['to_status'] === 'verified' && $organization->data_mode === 'demo') {
            return response()->json(['message' => 'Dữ liệu demo không được đánh dấu là đã xác minh pháp lý.'], 422);
        }
        $updates = [
            'status' => $validated['to_status'],
            'last_review_reason' => $validated['reason'] ?? null,
            'verified_by' => $request->user()->id,
        ];
        if ($validated['to_status'] === 'verified') {
            $updates['verified_at'] = now();
            $updates['suspended_at'] = null;
        } elseif ($validated['to_status'] === 'demo_accepted') {
            $updates['verified_at'] = null;
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
            'to_status' => ['required', Rule::in(['verified', 'demo_accepted', 'changes_requested', 'rejected', 'suspended', 'revoked'])],
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

    public function transitionDistributionAgreement(
        Request $request,
        OrganizationDistributionAgreement $agreement,
        DistributionAgreementService $service,
    ) {
        $validated = $request->validate([
            'to_status' => ['required', Rule::in(['verified', 'demo_accepted', 'changes_requested', 'rejected', 'suspended', 'revoked'])],
            'reason' => ['nullable', 'string', 'max:1000'],
            'operation_key' => ['nullable', 'string', 'max:128'],
        ]);

        return response()->json(['status' => 'success', 'data' => $service->transition(
            $agreement,
            $validated['to_status'],
            $request->user(),
            $validated['reason'] ?? null,
            $validated['operation_key'] ?? 'admin-distribution-agreement:'.Str::uuid(),
        )]);
    }
}
