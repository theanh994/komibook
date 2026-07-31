<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Models\OrganizationDistributionAgreement;
use App\Models\OrganizationDistributionAgreementEvent;
use App\Models\OrganizationMembership;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class OrganizationPortalController extends Controller
{
    public function index(Request $request)
    {
        $memberships = OrganizationMembership::with('organization')
            ->where('user_id', $request->user()->id)
            ->where('status', 'active')
            ->get();
        $organizationIds = $memberships->pluck('organization_id');
        $agreements = OrganizationDistributionAgreement::with(['publisher', 'distributor'])
            ->where(function ($query) use ($organizationIds) {
                $query->whereIn('publisher_organization_id', $organizationIds)
                    ->orWhereIn('distributor_organization_id', $organizationIds);
            })
            ->latest()
            ->get();

        return response()->json(['status' => 'success', 'data' => compact('memberships', 'agreements')]);
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
            'verification_document' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
        ]);
        $documentPath = $request->file('verification_document')
            ?->store('organizations/verification', 'private');

        try {
            $result = DB::transaction(function () use ($validated, $documentPath, $request) {
                $organization = Organization::create([
                    ...$validated,
                    'verification_document' => $documentPath,
                    'status' => 'pending_review',
                    'submitted_at' => now(),
                ]);
                $membership = OrganizationMembership::create([
                    'user_id' => $request->user()->id,
                    'organization_id' => $organization->id,
                    'role' => 'owner',
                    'status' => 'active',
                ]);

                return compact('organization', 'membership');
            });
        } catch (\Throwable $exception) {
            if ($documentPath) {
                Storage::disk('private')->delete($documentPath);
            }
            throw $exception;
        }

        return response()->json(['status' => 'success', 'data' => $result], 201);
    }

    public function storeAgreement(Request $request)
    {
        $validated = $request->validate([
            'publisher_organization_id' => ['required', 'integer', 'exists:organizations,id', 'different:distributor_organization_id'],
            'distributor_organization_id' => ['required', 'integer', 'exists:organizations,id'],
            'scope.coverage' => ['required', Rule::in(['catalog', 'books'])],
            'scope.book_ids' => ['nullable', 'array'],
            'scope.book_ids.*' => ['integer', 'exists:books,id'],
            'effective_from' => ['nullable', 'date'],
            'effective_until' => ['nullable', 'date', 'after_or_equal:effective_from'],
            'evidence_document' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
        ]);
        $managedIds = OrganizationMembership::where('user_id', $request->user()->id)
            ->where('status', 'active')
            ->whereIn('role', ['owner', 'admin'])
            ->pluck('organization_id');
        abort_unless(
            $managedIds->contains((int) $validated['publisher_organization_id'])
                || $managedIds->contains((int) $validated['distributor_organization_id']),
            403,
        );
        $organizations = Organization::whereIn('id', [
            $validated['publisher_organization_id'],
            $validated['distributor_organization_id'],
        ])->get()->keyBy('id');
        abort_unless($organizations->count() === 2, 422);
        abort_unless(in_array('publisher', $organizations[$validated['publisher_organization_id']]->organization_types ?? [], true), 422);
        abort_unless(
            count(array_intersect(
                ['supplier', 'distributor'],
                $organizations[$validated['distributor_organization_id']]->organization_types ?? [],
            )) > 0,
            422,
        );
        if ($organizations->contains(fn (Organization $organization) => ! $organization->isVerified())) {
            throw ValidationException::withMessages([
                'organizations' => 'Cả Nhà xuất bản và Nhà phân phối phải được xác minh trước khi gửi thỏa thuận.',
            ]);
        }

        $documentPath = $request->file('evidence_document')->store('organizations/distribution-agreements', 'private');
        try {
            $agreement = DB::transaction(function () use ($validated, $documentPath, $request) {
                $operationKey = 'distribution-agreement:'.Str::uuid();
                $agreement = OrganizationDistributionAgreement::create([
                    ...$validated,
                    'evidence_document' => $documentPath,
                    'status' => 'submitted',
                    'submitted_at' => now(),
                    'operation_key' => $operationKey,
                ]);
                OrganizationDistributionAgreementEvent::create([
                    'organization_distribution_agreement_id' => $agreement->id,
                    'actor_id' => $request->user()->id,
                    'from_status' => 'draft',
                    'to_status' => 'submitted',
                    'operation_key' => $operationKey.':submitted',
                ]);

                return $agreement;
            });
        } catch (\Throwable $exception) {
            Storage::disk('private')->delete($documentPath);
            throw $exception;
        }

        return response()->json([
            'status' => 'success',
            'data' => $agreement->load(['publisher', 'distributor']),
        ], 201);
    }
}
