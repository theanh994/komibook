<?php

namespace App\Http\Controllers\Api\Vendor;

use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Models\Book;
use App\Models\VendorOrganizationRelationship;
use App\Services\CommercialPartyService;
use App\Services\OrganizationRelationshipService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class OrganizationController extends Controller
{
    public function index(Request $request)
    {
        $vendor = $request->user()->vendor()->withoutGlobalScopes()->firstOrFail();
        $relationships = $vendor->organizationRelationships()->with('organization')->latest()->get();

        return response()->json(['status' => 'success', 'data' => [
            'business_model' => $vendor->business_model,
            'primary_organization_id' => $vendor->primary_organization_id,
            'relationships' => $relationships,
        ]]);
    }

    public function storeOrganization(Request $request)
    {
        $validated = $request->validate([
            'legal_name' => ['required', 'string', 'max:255'],
            'display_name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', 'unique:organizations,slug'],
            'organization_types' => ['required', 'array', 'min:1'],
            'organization_types.*' => [Rule::in(['publisher', 'supplier', 'distributor', 'bookstore'])],
            'tax_code' => ['nullable', 'string', 'max:64'],
            'license_number' => ['nullable', 'string', 'max:128'],
            'description' => ['nullable', 'string', 'max:5000'],
            'website' => ['nullable', 'url', 'max:255'],
            'logo' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'verification_document' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
        ]);
        $vendor = $request->user()->vendor()->withoutGlobalScopes()->firstOrFail();

        $logoPath = $request->file('logo')?->store('organizations/logos', 'public');
        $verificationPath = $request->file('verification_document')
            ?->store('organizations/verification', 'private');
        $validated['logo'] = $logoPath;
        $validated['verification_document'] = $verificationPath;

        try {
            $result = DB::transaction(function () use ($validated, $vendor) {
                $organization = Organization::create([...$validated, 'status' => 'pending_review', 'submitted_at' => now()]);
                $relationship = VendorOrganizationRelationship::create([
                    'vendor_id' => $vendor->id,
                    'organization_id' => $organization->id,
                    'role' => 'self_legal_entity',
                    'status' => 'submitted',
                    'submitted_at' => now(),
                    'operation_key' => 'organization-self:'.Str::uuid(),
                ]);
                if (! $vendor->primary_organization_id) {
                    $vendor->update(['primary_organization_id' => $organization->id]);
                }

                return compact('organization', 'relationship');
            });
        } catch (\Throwable $exception) {
            if ($logoPath) {
                Storage::disk('public')->delete($logoPath);
            }
            if ($verificationPath) {
                Storage::disk('private')->delete($verificationPath);
            }

            throw $exception;
        }

        return response()->json(['status' => 'success', 'data' => $result], 201);
    }

    public function storeRelationship(Request $request)
    {
        $validated = $request->validate([
            'organization_id' => ['required', 'integer', 'exists:organizations,id'],
            'role' => ['required', Rule::in(['publisher_partner', 'supplier_partner', 'authorized_distributor'])],
            'scope' => ['nullable', 'array'],
            'effective_from' => ['nullable', 'date'],
            'effective_until' => ['nullable', 'date', 'after_or_equal:effective_from'],
            'evidence_document' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
            'operation_key' => ['nullable', 'string', 'max:128', 'unique:vendor_organization_relationships,operation_key'],
        ]);
        $vendor = $request->user()->vendor()->withoutGlobalScopes()->firstOrFail();
        $validated['evidence_document'] = $request->file('evidence_document')
            ->store('organizations/relationships', 'private');
        $relationship = VendorOrganizationRelationship::create([
            ...$validated,
            'vendor_id' => $vendor->id,
            'status' => 'submitted',
            'submitted_at' => now(),
            'operation_key' => $validated['operation_key'] ?? 'vendor-relationship:'.Str::uuid(),
        ]);

        return response()->json(['status' => 'success', 'data' => $relationship->load('organization')], 201);
    }

    public function submit(
        Request $request,
        VendorOrganizationRelationship $relationship,
        OrganizationRelationshipService $service,
    ) {
        $vendor = $request->user()->vendor()->withoutGlobalScopes()->firstOrFail();
        abort_unless($relationship->vendor_id === $vendor->id, 403);
        $request->validate(['operation_key' => ['nullable', 'string', 'max:128']]);

        return response()->json(['status' => 'success', 'data' => $service->transition(
            $relationship,
            'submitted',
            $request->user(),
            operationKey: $request->input('operation_key'),
        )]);
    }

    public function assignBookParties(
        Request $request,
        Book $book,
        CommercialPartyService $service,
    ) {
        $validated = $request->validate([
            'publisher_relationship_id' => ['required', 'integer', 'exists:vendor_organization_relationships,id'],
            'supplier_relationship_id' => ['required', 'integer', 'exists:vendor_organization_relationships,id'],
            'responsible_relationship_id' => ['required', 'integer', 'exists:vendor_organization_relationships,id'],
        ]);
        $updated = $service->assign($book, [
            'publisher' => $validated['publisher_relationship_id'],
            'supplier' => $validated['supplier_relationship_id'],
            'responsible_organization' => $validated['responsible_relationship_id'],
        ], $request->user());

        return response()->json(['status' => 'success', 'data' => $updated]);
    }
}
