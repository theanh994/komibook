<?php

namespace App\Http\Controllers\Api;

use App\Enums\VendorOnboardingStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\VendorProfileResource;
use App\Models\Vendor;
use App\Services\VendorOnboardingService;
use Illuminate\Http\Request;
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
        $vendor ??= new Vendor(['user_id' => $request->user()->id]);
        $bankChanged = ! $vendor->exists
            || $vendor->payout_bank_account !== $validated['payout_bank_account']
            || $vendor->payout_bank_name !== $validated['payout_bank_name']
            || $vendor->payout_bank_holder !== $validated['payout_bank_holder'];
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
            'payout_bank_account' => $validated['payout_bank_account'],
            'payout_bank_name' => $validated['payout_bank_name'],
            'payout_bank_holder' => $validated['payout_bank_holder'],
            'payout_bank_status' => $bankChanged ? 'unverified' : $vendor->payout_bank_status,
            'payout_bank_verified_at' => $bankChanged ? null : $vendor->payout_bank_verified_at,
            'payout_bank_verified_by' => $bankChanged ? null : $vendor->payout_bank_verified_by,
            'terms_accepted_at' => now(),
            'status' => 'inactive',
            'onboarding_status' => $state,
        ]);
        foreach (['business_registration_document', 'representative_identity_document'] as $field) {
            if ($request->hasFile($field)) {
                $vendor->{$field} = $request->file($field)->store('vendors/legal', 'private');
            }
        }
        $isNew = ! $vendor->exists;
        $vendor->save();

        $target = $state === VendorOnboardingStatus::ChangesRequested ? VendorOnboardingStatus::Resubmitted : VendorOnboardingStatus::Submitted;
        $vendor = $service->transition($vendor, $target, $request->user(), operationKey: $this->operationKey($request, 'submit'));

        return response()->json(['status' => 'success', 'message' => 'Hồ sơ nhà bán đã được gửi để kiểm duyệt.', 'data' => new VendorProfileResource($vendor)], $isNew ? 201 : 200);
    }

    public function saveDraft(Request $request)
    {
        $vendor = Vendor::withoutGlobalScopes()->firstOrNew(['user_id' => $request->user()->id]);
        $state = $vendor->exists ? ($vendor->onboarding_status ?? VendorOnboardingStatus::Draft) : VendorOnboardingStatus::Draft;
        if (! in_array($state, [VendorOnboardingStatus::Draft, VendorOnboardingStatus::ChangesRequested], true)) {
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
        foreach (['business_registration_document', 'representative_identity_document'] as $field) {
            if ($request->hasFile($field)) {
                $vendor->{$field} = $request->file($field)->store('vendors/legal', 'private');
            }
        }
        if ($request->boolean('terms_accepted')) {
            $vendor->terms_accepted_at = now();
        }
        if ($bankChanged) {
            $vendor->payout_bank_status = 'unverified';
            $vendor->payout_bank_verified_at = null;
            $vendor->payout_bank_verified_by = null;
        }
        $vendor->status = 'inactive';
        $vendor->onboarding_status = $state;
        $vendor->save();

        return response()->json(['status' => 'success', 'data' => new VendorProfileResource($vendor)]);
    }

    public function submit(Request $request, VendorOnboardingService $service)
    {
        $request->validate(['operation_key' => 'nullable|string|max:100']);
        $vendor = Vendor::withoutGlobalScopes()->where('user_id', $request->user()->id)->firstOrFail();
        $target = $vendor->onboarding_status === VendorOnboardingStatus::ChangesRequested ? VendorOnboardingStatus::Resubmitted : VendorOnboardingStatus::Submitted;
        $vendor = $service->transition($vendor, $target, $request->user(), operationKey: $this->operationKey($request, 'submit'));

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

        return [
            'shop_name' => [$presence, 'string', 'max:255'],
            'slug' => [$presence, 'string', 'max:255', Rule::unique('vendors', 'slug')->ignore($vendor?->id)],
            'description' => ['nullable', 'string', 'max:5000'],
            'business_model' => [$partial ? 'sometimes' : 'nullable', Rule::in(['direct_publisher', 'bookstore', 'distributor', 'mixed'])],
            'legal_name' => [$presence, 'string', 'max:255'],
            'tax_code' => [$presence, 'string', 'max:64'],
            'business_registration_document' => [$partial ? 'sometimes' : 'nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
            'representative_identity_document' => [$partial ? 'sometimes' : 'nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
            'payout_bank_account' => [$presence, 'string', 'max:64'],
            'payout_bank_name' => [$presence, 'string', 'max:255'],
            'payout_bank_holder' => [$presence, 'string', 'max:255'],
            'terms_accepted' => [$partial ? 'sometimes' : 'accepted'],
            'operation_key' => ['nullable', 'string', 'max:100'],
        ];
    }

    private function operationKey(Request $request, string $action): string
    {
        return $request->input('operation_key') ?? $request->header('Idempotency-Key') ?? "vendor:{$request->user()->id}:{$action}:".Str::uuid();
    }
}
