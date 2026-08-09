<?php

namespace App\Http\Controllers\Api;

use App\Enums\VendorOnboardingStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\VendorProfileResource;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\User;
use App\Models\Vendor;
use App\Models\VendorOrganizationRelationship;
use App\Services\VendorOnboardingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class VendorOnboardingController extends Controller
{
    public function register(Request $request, VendorOnboardingService $service)
    {
        $vendor = Vendor::withoutGlobalScopes()->where('user_id', $request->user()->id)->first();
        $validated = $request->validate($this->rules($vendor, false));
        $isDemo = (bool) $vendor?->is_demo;
        $bankChanged = ! $isDemo && (! $vendor?->exists
            || $vendor?->payout_bank_account !== ($validated['payout_bank_account'] ?? null)
            || $vendor?->payout_bank_name !== ($validated['payout_bank_name'] ?? null)
            || $vendor?->payout_bank_holder !== ($validated['payout_bank_holder'] ?? null));
        $vendor ??= new Vendor(['user_id' => $request->user()->id]);
        $state = $vendor->exists ? ($vendor->onboarding_status ?? VendorOnboardingStatus::Draft) : VendorOnboardingStatus::Draft;
        if (! in_array($state, [VendorOnboardingStatus::Draft, VendorOnboardingStatus::ChangesRequested], true)) {
            throw ValidationException::withMessages(['profile' => 'Hồ sơ nhà bán không thể được gửi lại ở trạng thái này.']);
        }

        $vendor->fill([
            'shop_name' => $validated['shop_name'],
            'slug' => $validated['slug'],
            'description' => $validated['description'] ?? null,
            'business_model' => $validated['business_model'] ?? $vendor->business_model ?? 'bookstore',
            'legal_name' => $validated['legal_name'],
            'tax_code' => $validated['tax_code'],
            'payout_bank_account' => $validated['payout_bank_account'] ?? $vendor->payout_bank_account,
            'payout_bank_name' => $validated['payout_bank_name'] ?? $vendor->payout_bank_name,
            'payout_bank_holder' => $validated['payout_bank_holder'] ?? $vendor->payout_bank_holder,
            'payout_bank_status' => $vendor->is_demo ? 'demo_disabled' : ($bankChanged ? 'unverified' : $vendor->payout_bank_status),
            'payout_bank_verified_at' => $bankChanged ? null : $vendor->payout_bank_verified_at,
            'payout_bank_verified_by' => $bankChanged ? null : $vendor->payout_bank_verified_by,
            'terms_accepted_at' => now(),
            'status' => 'inactive',
            'onboarding_status' => $state,
        ]);
        $isNew = ! $vendor->exists;
        $target = $state === VendorOnboardingStatus::ChangesRequested ? VendorOnboardingStatus::Resubmitted : VendorOnboardingStatus::Submitted;
        $this->assertPrimaryOrganizationCanSynchronize($vendor, $request->user());
        $newDocuments = $this->storeDocuments($request);

        try {
            $vendor = DB::transaction(function () use ($vendor, $newDocuments, $target, $service, $request) {
                foreach ($newDocuments as $field => $path) {
                    $vendor->{$field} = $path;
                }
                $vendor->save();
                $this->syncPrimaryOrganization($vendor, $request->user(), 'submitted', $newDocuments);

                return $service->transition($vendor, $target, $request->user(), operationKey: $this->operationKey($request, 'submit'));
            });
        } catch (\Throwable $exception) {
            $this->deleteStoredDocuments($newDocuments);

            throw $exception;
        }

        return response()->json(['status' => 'success', 'message' => 'Hồ sơ nhà bán đã được gửi để kiểm duyệt.', 'data' => new VendorProfileResource($vendor)], $isNew ? 201 : 200);
    }

    public function saveDraft(Request $request)
    {
        $vendor = Vendor::withoutGlobalScopes()->firstOrNew(['user_id' => $request->user()->id]);
        $state = $vendor->exists ? ($vendor->onboarding_status ?? VendorOnboardingStatus::Draft) : VendorOnboardingStatus::Draft;
        if (! in_array($state, [VendorOnboardingStatus::Draft, VendorOnboardingStatus::ChangesRequested, VendorOnboardingStatus::Approved], true)) {
            throw ValidationException::withMessages(['profile' => 'Hồ sơ nhà bán hiện không thể chỉnh sửa.']);
        }
        $validated = $request->validate($this->rules($vendor, true));
        $bankChanged = collect(['payout_bank_account', 'payout_bank_name', 'payout_bank_holder'])
            ->contains(fn ($field) => array_key_exists($field, $validated) && $vendor->{$field} !== $validated[$field]);
        foreach (['shop_name', 'slug', 'description', 'business_model', 'legal_name', 'tax_code', 'payout_bank_account', 'payout_bank_name', 'payout_bank_holder'] as $field) {
            if (array_key_exists($field, $validated)) {
                $vendor->{$field} = $validated[$field];
            }
        }
        if ($request->boolean('terms_accepted')) {
            $vendor->terms_accepted_at = now();
        }
        if ($bankChanged) {
            $vendor->payout_bank_status = $vendor->is_demo ? 'demo_disabled' : 'unverified';
            $vendor->payout_bank_verified_at = null;
            $vendor->payout_bank_verified_by = null;
        }
        if ($state !== VendorOnboardingStatus::Approved) {
            $vendor->status = 'inactive';
            $vendor->onboarding_status = $state;
        }
        $this->assertPrimaryOrganizationCanSynchronize($vendor, $request->user());
        $newDocuments = $this->storeDocuments($request);

        try {
            DB::transaction(function () use ($vendor, $newDocuments, $request): void {
                foreach ($newDocuments as $field => $path) {
                    $vendor->{$field} = $path;
                }
                $vendor->save();
                $this->syncPrimaryOrganization($vendor, $request->user(), 'draft', $newDocuments);
            });
        } catch (\Throwable $exception) {
            $this->deleteStoredDocuments($newDocuments);

            throw $exception;
        }

        return response()->json(['status' => 'success', 'data' => new VendorProfileResource($vendor)]);
    }

    public function submit(Request $request, VendorOnboardingService $service)
    {
        $request->validate(['operation_key' => 'nullable|string|max:100']);
        $vendor = Vendor::withoutGlobalScopes()->where('user_id', $request->user()->id)->firstOrFail();
        $target = $vendor->onboarding_status === VendorOnboardingStatus::ChangesRequested ? VendorOnboardingStatus::Resubmitted : VendorOnboardingStatus::Submitted;
        $this->assertPrimaryOrganizationCanSynchronize($vendor, $request->user());
        $vendor = DB::transaction(function () use ($vendor, $target, $service, $request) {
            $this->syncPrimaryOrganization($vendor, $request->user(), 'submitted');

            return $service->transition($vendor, $target, $request->user(), operationKey: $this->operationKey($request, 'submit'));
        });

        return response()->json(['status' => 'success', 'data' => new VendorProfileResource($vendor)]);
    }

    public function status(Request $request)
    {
        $vendor = Vendor::withoutGlobalScopes()->where('user_id', $request->user()->id)->first();

        return response()->json(['status' => 'success', 'data' => $vendor ? new VendorProfileResource($vendor) : null]);
    }

    public function downloadDocument(Request $request, Vendor $vendor, string $type)
    {
        if ($request->user()->role !== 'admin' && $request->user()->id !== $vendor->user_id) {
            abort(403);
        }
        $path = match ($type) {
            'business' => $vendor->business_registration_document,
            'representative' => $vendor->representative_identity_document,
            default => null,
        };
        if (! $path || ! Storage::disk('private')->exists($path)) {
            abort(404);
        }

        return response()->file(Storage::disk('private')->path($path), ['X-Content-Type-Options' => 'nosniff']);
    }

    private function rules(?Vendor $vendor, bool $partial): array
    {
        $presence = $partial ? 'sometimes' : 'required';
        $financialPresence = $vendor?->is_demo ? 'nullable' : $presence;

        return [
            'shop_name' => [$presence, 'string', 'max:255'],
            'slug' => [$presence, 'string', 'max:255', Rule::unique('vendors', 'slug')->ignore($vendor?->id)],
            'description' => ['nullable', 'string', 'max:5000'],
            'business_model' => [$partial ? 'sometimes' : 'nullable', Rule::in(['direct_publisher', 'bookstore', 'distributor', 'mixed'])],
            'legal_name' => [$presence, 'string', 'max:255'],
            'tax_code' => [$presence, 'string', 'max:64'],
            'business_registration_document' => [$partial ? 'sometimes' : 'nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
            'representative_identity_document' => [$partial ? 'sometimes' : 'nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
            'payout_bank_account' => [$financialPresence, 'string', 'max:64'],
            'payout_bank_name' => [$financialPresence, 'string', 'max:255'],
            'payout_bank_holder' => [$financialPresence, 'string', 'max:255'],
            'terms_accepted' => [$partial ? 'sometimes' : 'accepted'],
            'operation_key' => ['nullable', 'string', 'max:100'],
        ];
    }

    private function assertPrimaryOrganizationCanSynchronize(Vendor $vendor, User $user): void
    {
        if (! $vendor->legal_name && ! $vendor->shop_name) {
            return;
        }

        $orgSlug = $vendor->slug ?: Str::slug($vendor->shop_name ?: 'vendor-'.$vendor->id);
        if (! $vendor->primary_organization_id) {
            if (Organization::query()->where('slug', $orgSlug)->exists()) {
                throw ValidationException::withMessages(['slug' => 'Organization slug is already reserved by another legal entity.']);
            }

            return;
        }

        $organization = Organization::query()->find($vendor->primary_organization_id);
        $membership = $organization
            ? OrganizationMembership::query()->where('user_id', $user->id)->where('organization_id', $organization->id)->where('role', 'owner')->where('status', 'active')->first()
            : null;
        $relationship = $organization
            ? VendorOrganizationRelationship::query()->where('vendor_id', $vendor->id)->where('organization_id', $organization->id)->where('role', 'self_legal_entity')->first()
            : null;
        if (! $organization || ! $membership || ! $relationship) {
            throw ValidationException::withMessages(['organization' => 'The primary organization is not an owned self legal entity.']);
        }
        if (Organization::query()->where('slug', $orgSlug)->whereKeyNot($organization->id)->exists()) {
            throw ValidationException::withMessages(['slug' => 'Organization slug is already reserved by another legal entity.']);
        }
    }

    private function syncPrimaryOrganization(Vendor $vendor, User $user, string $target, array $documents = []): void
    {
        if (! $vendor->legal_name && ! $vendor->shop_name) {
            return;
        }

        $orgSlug = $vendor->slug ?: Str::slug($vendor->shop_name ?: 'vendor-'.$vendor->id);
        $organizationTypes = [$vendor->business_model === 'direct_publisher' ? 'publisher' : 'supplier'];
        if (! $vendor->primary_organization_id) {
            $organization = Organization::create([
                'slug' => $orgSlug,
                'legal_name' => $vendor->legal_name ?: $vendor->shop_name,
                'display_name' => $vendor->shop_name ?: $vendor->legal_name,
                'tax_code' => $vendor->tax_code,
                'organization_types' => $organizationTypes,
                'status' => $target === 'submitted' ? 'pending_review' : 'draft',
                'data_mode' => $vendor->is_demo ? 'demo' : 'live',
                'submitted_at' => $target === 'submitted' ? now() : null,
                'verification_document' => $vendor->is_demo ? null : ($documents['business_registration_document'] ?? null),
            ]);
            $vendor->update(['primary_organization_id' => $organization->id]);

            OrganizationMembership::create([
                'user_id' => $user->id,
                'organization_id' => $organization->id,
                'role' => 'owner',
                'status' => 'active',
            ]);
            VendorOrganizationRelationship::create([
                'vendor_id' => $vendor->id,
                'organization_id' => $organization->id,
                'role' => 'self_legal_entity',
                'status' => $target === 'submitted' ? 'submitted' : 'draft',
                'is_demo' => (bool) $vendor->is_demo,
                'evidence_mode' => $vendor->is_demo ? 'demo_statement' : 'real_document',
                'evidence_document' => $vendor->is_demo ? null : ($documents['business_registration_document'] ?? null),
                'demo_reference' => $vendor->is_demo ? 'DEMO-SELF-'.$vendor->id : null,
                'submitted_at' => $target === 'submitted' ? now() : null,
                'operation_key' => 'organization-self:'.Str::uuid(),
            ]);

            return;
        }

        $organization = Organization::query()->findOrFail($vendor->primary_organization_id);
        $relationship = VendorOrganizationRelationship::query()
            ->where('vendor_id', $vendor->id)
            ->where('organization_id', $organization->id)
            ->where('role', 'self_legal_entity')
            ->firstOrFail();
        if (! $this->isMutableBootstrap($organization, $relationship)) {
            return;
        }
        $organization->update([
            'slug' => $orgSlug,
            'legal_name' => $vendor->legal_name ?: $organization->legal_name,
            'display_name' => $vendor->shop_name ?: $organization->display_name,
            'tax_code' => $vendor->tax_code ?: $organization->tax_code,
            'organization_types' => $organizationTypes,
            ...(! $vendor->is_demo && array_key_exists('business_registration_document', $documents) ? ['verification_document' => $documents['business_registration_document']] : []),
            ...($target === 'submitted' ? ['status' => 'pending_review', 'submitted_at' => now()] : []),
        ]);
        if ($target === 'submitted') {
            $relationship->update([
                'status' => 'submitted',
                'submitted_at' => now(),
                ...(! $vendor->is_demo && array_key_exists('business_registration_document', $documents) ? ['evidence_document' => $documents['business_registration_document']] : []),
            ]);
        } elseif (! $vendor->is_demo && array_key_exists('business_registration_document', $documents)) {
            $relationship->update(['evidence_document' => $documents['business_registration_document']]);
        }
    }

    private function isMutableBootstrap(Organization $organization, VendorOrganizationRelationship $relationship): bool
    {
        return in_array($organization->status, ['draft', 'changes_requested'], true)
            && in_array($relationship->status, ['draft', 'changes_requested'], true);
    }

    private function storeDocuments(Request $request): array
    {
        $paths = [];
        try {
            foreach (['business_registration_document', 'representative_identity_document'] as $field) {
                if ($request->hasFile($field)) {
                    $paths[$field] = $request->file($field)->store('vendors/legal', 'private');
                }
            }
        } catch (\Throwable $exception) {
            $this->deleteStoredDocuments($paths);

            throw $exception;
        }

        return $paths;
    }

    private function deleteStoredDocuments(array $paths): void
    {
        foreach ($paths as $path) {
            Storage::disk('private')->delete($path);
        }
    }

    private function operationKey(Request $request, string $action): string
    {
        return $request->input('operation_key') ?? $request->header('Idempotency-Key') ?? "vendor:{$request->user()->id}:{$action}:".Str::uuid();
    }
}
