<?php

namespace App\Http\Controllers\Api;

use App\Enums\AuthorOnboardingStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\AuthorProfileResource;
use App\Models\Author;
use App\Services\AuthorOnboardingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AuthorController extends Controller
{
    public function register(Request $request, AuthorOnboardingService $service)
    {
        $validated = $request->validate([
            'pen_name' => 'required|string|max:255',
            'bio' => 'nullable|string|max:5000',
            'bank_account_number' => 'required|string|max:50',
            'bank_name' => 'required|string|max:100',
            'bank_holder_name' => 'required|string|max:255',
            'identity_document' => 'nullable|image|max:5120',
            'terms_accepted' => 'accepted',
            'operation_key' => 'nullable|string|max:100',
        ]);

        $user = $request->user();
        $author = Author::firstOrNew(['user_id' => $user->id]);
        $isNew = ! $author->exists;
        $current = $author->exists ? $author->onboarding_status : AuthorOnboardingStatus::Draft;
        if ($current instanceof AuthorOnboardingStatus && ! in_array($current, [AuthorOnboardingStatus::Draft, AuthorOnboardingStatus::ChangesRequested], true)) {
            throw ValidationException::withMessages(['profile' => 'Hồ sơ hiện tại không thể được gửi lại ở trạng thái này.']);
        }

        $profile = [
            'pen_name' => $validated['pen_name'],
            'bio' => $validated['bio'] ?? null,
            'bank_account_number' => $validated['bank_account_number'],
            'bank_name' => $validated['bank_name'],
            'bank_holder_name' => $validated['bank_holder_name'],
            'terms_accepted_at' => now(),
            'status' => 'pending',
            'onboarding_status' => $current,
        ];
        if ($request->hasFile('identity_document')) {
            $profile['identity_document'] = $request->file('identity_document')->store('authors/cccd', 'private');
        }
        $author->fill($profile)->save();

        $target = $current === AuthorOnboardingStatus::ChangesRequested
            ? AuthorOnboardingStatus::Resubmitted
            : AuthorOnboardingStatus::Submitted;
        $author = $service->transition(
            $author,
            $target,
            $user,
            operationKey: $this->operationKey($request, 'submit')
        );

        return response()->json([
            'status' => 'success',
            'message' => 'Hồ sơ tác giả đã được gửi để kiểm duyệt.',
            'data' => new AuthorProfileResource($author),
        ], $isNew ? 201 : 200);
    }

    public function saveDraft(Request $request)
    {
        $validated = $request->validate([
            'pen_name' => 'sometimes|required|string|max:255',
            'bio' => 'nullable|string|max:5000',
            'bank_account_number' => 'sometimes|required|string|max:50',
            'bank_name' => 'sometimes|required|string|max:100',
            'bank_holder_name' => 'sometimes|required|string|max:255',
            'identity_document' => 'sometimes|required|image|max:5120',
            'terms_accepted' => 'sometimes|accepted',
        ]);

        $author = Author::firstOrNew(['user_id' => $request->user()->id]);
        $state = $author->exists ? $author->onboarding_status : AuthorOnboardingStatus::Draft;
        if (! in_array($state, [AuthorOnboardingStatus::Draft, AuthorOnboardingStatus::ChangesRequested], true)) {
            throw ValidationException::withMessages(['profile' => 'Hồ sơ hiện tại không thể chỉnh sửa.']);
        }

        foreach (['pen_name', 'bio', 'bank_account_number', 'bank_name', 'bank_holder_name'] as $field) {
            if (array_key_exists($field, $validated)) {
                $author->{$field} = $validated[$field];
            }
        }
        if ($request->hasFile('identity_document')) {
            $author->identity_document = $request->file('identity_document')->store('authors/cccd', 'private');
        }
        if ($request->boolean('terms_accepted')) {
            $author->terms_accepted_at = now();
        }
        $author->status ??= 'pending';
        $author->onboarding_status = $state;
        $author->save();

        return response()->json(['status' => 'success', 'data' => new AuthorProfileResource($author)]);
    }

    public function submit(Request $request, AuthorOnboardingService $service)
    {
        $request->validate(['operation_key' => 'nullable|string|max:100']);
        $author = Author::where('user_id', $request->user()->id)->firstOrFail();
        $target = $author->onboarding_status === AuthorOnboardingStatus::ChangesRequested
            ? AuthorOnboardingStatus::Resubmitted
            : AuthorOnboardingStatus::Submitted;

        $author = $service->transition($author, $target, $request->user(), operationKey: $this->operationKey($request, 'submit'));

        return response()->json(['status' => 'success', 'data' => new AuthorProfileResource($author)]);
    }

    public function downloadIdentityDocument(Request $request, $id)
    {
        $author = Author::findOrFail($id);
        if ($request->user()->role !== 'admin' && $request->user()->id !== $author->user_id) {
            return response()->json(['status' => 'error', 'message' => 'Bạn không có quyền truy cập tài liệu này.'], 403);
        }

        $path = $author->identity_document;
        if (! $path) {
            return response()->json(['status' => 'error', 'message' => 'Tài liệu không tồn tại.'], 404);
        }
        foreach (['private', 'public'] as $disk) {
            if (Storage::disk($disk)->exists($path)) {
                return response()->file(Storage::disk($disk)->path($path), ['X-Content-Type-Options' => 'nosniff']);
            }
        }

        return response()->json(['status' => 'error', 'message' => 'Tài liệu không tồn tại.'], 404);
    }

    public function status(Request $request)
    {
        $author = Author::where('user_id', $request->user()->id)->first();

        return response()->json(['status' => 'success', 'data' => $author ? new AuthorProfileResource($author) : null]);
    }

    public function dashboardStats(Request $request)
    {
        $author = Author::where('user_id', $request->user()->id)->first();
        if (! $author || $author->onboarding_status !== AuthorOnboardingStatus::Approved) {
            return response()->json(['status' => 'error', 'message' => 'Bạn chưa phải là tác giả được phê duyệt.'], 403);
        }

        return response()->json(['status' => 'success', 'data' => [
            'pen_name' => $author->pen_name,
            'onboarding_status' => $author->onboarding_status->value,
            'total_books' => 0,
            'total_ebooks' => 0,
            'total_physical' => 0,
            'balance' => 0,
            'total_withdrawn' => 0,
        ]]);
    }

    private function operationKey(Request $request, string $action): string
    {
        return $request->input('operation_key')
            ?? $request->header('Idempotency-Key')
            ?? "author:{$request->user()->id}:{$action}:".Str::uuid();
    }
}
