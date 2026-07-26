<?php

namespace App\Http\Controllers\Api\Admin;

use App\Enums\AuthorOnboardingStatus;
use App\Enums\VendorOnboardingStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\AuthorProfileResource;
use App\Http\Resources\VendorProfileResource;
use App\Models\Author;
use App\Models\Vendor;
use App\Services\AuthorOnboardingService;
use App\Services\VendorOnboardingService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class VendorApprovalController extends Controller
{
    public function index()
    {
        $vendors = Vendor::withoutGlobalScopes()->with('user')->whereIn('onboarding_status', [
            VendorOnboardingStatus::Submitted->value,
            VendorOnboardingStatus::Resubmitted->value,
            VendorOnboardingStatus::UnderReview->value,
        ])->get();
        $authors = Author::with('user')->whereIn('onboarding_status', [
            AuthorOnboardingStatus::Submitted->value,
            AuthorOnboardingStatus::Resubmitted->value,
            AuthorOnboardingStatus::UnderReview->value,
        ])->get();

        return response()->json(['status' => 'success', 'data' => [
            'vendors' => VendorProfileResource::collection($vendors),
            'authors' => AuthorProfileResource::collection($authors),
        ]]);
    }

    public function transitionVendor(Request $request, Vendor $vendor, VendorOnboardingService $service)
    {
        $validated = $request->validate([
            'to_status' => 'required|string|in:under_review,approved,changes_requested,rejected,suspended,revoked',
            'reason' => 'nullable|string|max:1000',
            'operation_key' => 'nullable|string|max:100',
        ]);
        $updated = $service->transition(
            $vendor,
            VendorOnboardingStatus::from($validated['to_status']),
            $request->user(),
            $validated['reason'] ?? null,
            $validated['operation_key'] ?? $request->header('Idempotency-Key') ?? 'admin-vendor:'.Str::uuid(),
        );

        return response()->json(['status' => 'success', 'data' => new VendorProfileResource($updated)]);
    }

    public function approveVendor(Request $request, $id, VendorOnboardingService $service)
    {
        $vendor = Vendor::withoutGlobalScopes()->findOrFail($id);
        $baseKey = $request->header('Idempotency-Key') ?? 'admin-vendor-approve:'.Str::uuid();
        if (in_array($vendor->onboarding_status, [VendorOnboardingStatus::Submitted, VendorOnboardingStatus::Resubmitted], true)) {
            $vendor = $service->transition($vendor, VendorOnboardingStatus::UnderReview, $request->user(), operationKey: $baseKey.':review');
        }
        $vendor = $service->transition($vendor, VendorOnboardingStatus::Approved, $request->user(), operationKey: $baseKey.':approve');

        return response()->json(['status' => 'success', 'message' => 'Phê duyệt hồ sơ nhà bán thành công.', 'data' => new VendorProfileResource($vendor)]);
    }

    public function transitionAuthor(Request $request, Author $author, AuthorOnboardingService $service)
    {
        $validated = $request->validate([
            'to_status' => 'required|string|in:under_review,approved,changes_requested,rejected,suspended,revoked',
            'reason' => 'nullable|string|max:1000',
            'operation_key' => 'nullable|string|max:100',
        ]);

        $updated = $service->transition(
            $author,
            AuthorOnboardingStatus::from($validated['to_status']),
            $request->user(),
            $validated['reason'] ?? null,
            $validated['operation_key'] ?? $request->header('Idempotency-Key') ?? 'admin-author:'.Str::uuid(),
        );

        return response()->json(['status' => 'success', 'data' => new AuthorProfileResource($updated)]);
    }

    public function approveAuthor(Request $request, $id, AuthorOnboardingService $service)
    {
        $author = Author::findOrFail($id);
        $baseKey = $request->header('Idempotency-Key') ?? 'admin-author-approve:'.Str::uuid();
        if (in_array($author->onboarding_status, [AuthorOnboardingStatus::Submitted, AuthorOnboardingStatus::Resubmitted], true)) {
            $author = $service->transition($author, AuthorOnboardingStatus::UnderReview, $request->user(), operationKey: $baseKey.':review');
        }
        $author = $service->transition($author, AuthorOnboardingStatus::Approved, $request->user(), operationKey: $baseKey.':approve');

        return response()->json([
            'status' => 'success',
            'message' => 'Phê duyệt hồ sơ tác giả thành công. Quyền nhà bán không bị thay đổi.',
            'data' => new AuthorProfileResource($author),
        ]);
    }

    public function reject(Request $request, $type, $id, AuthorOnboardingService $authorService, VendorOnboardingService $vendorService)
    {
        $validated = $request->validate(['reason' => 'required|string|max:500']);
        if ($type === 'vendor') {
            $vendor = Vendor::withoutGlobalScopes()->findOrFail($id);
            $baseKey = $request->header('Idempotency-Key') ?? 'admin-vendor-reject:'.Str::uuid();
            if (in_array($vendor->onboarding_status, [VendorOnboardingStatus::Submitted, VendorOnboardingStatus::Resubmitted], true)) {
                $vendor = $vendorService->transition($vendor, VendorOnboardingStatus::UnderReview, $request->user(), operationKey: $baseKey.':review');
            }
            $vendorService->transition($vendor, VendorOnboardingStatus::Rejected, $request->user(), $validated['reason'], $baseKey.':reject');
        } elseif ($type === 'author') {
            $author = Author::findOrFail($id);
            $baseKey = $request->header('Idempotency-Key') ?? 'admin-author-reject:'.Str::uuid();
            if (in_array($author->onboarding_status, [AuthorOnboardingStatus::Submitted, AuthorOnboardingStatus::Resubmitted], true)) {
                $author = $authorService->transition($author, AuthorOnboardingStatus::UnderReview, $request->user(), operationKey: $baseKey.':review');
            }
            $authorService->transition($author, AuthorOnboardingStatus::Rejected, $request->user(), $validated['reason'], $baseKey.':reject');
        } else {
            return response()->json(['status' => 'error', 'message' => 'Loại đối tác không hợp lệ.'], 400);
        }

        return response()->json(['status' => 'success', 'message' => 'Đã từ chối hồ sơ và lưu lý do.']);
    }
}
