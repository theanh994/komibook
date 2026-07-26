<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AuthorProfileResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $viewer = $request->user();
        $mayViewPrivate = $viewer && ($viewer->role === 'admin' || $viewer->id === $this->user_id);
        $status = $this->onboarding_status instanceof \BackedEnum
            ? $this->onboarding_status->value
            : $this->onboarding_status;

        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'pen_name' => $this->pen_name,
            'bio' => $this->bio,
            'onboarding_status' => $status,
            'status' => $this->status,
            'application_version' => $this->application_version,
            'last_review_reason' => $this->last_review_reason,
            'has_identity_document' => $this->has_identity_document,
            'identity_document_url' => $mayViewPrivate ? $this->identity_document_url : null,
            'phone_verified_at' => $this->phone_verified_at?->toISOString(),
            'terms_accepted_at' => $this->terms_accepted_at?->toISOString(),
            'submitted_at' => $this->submitted_at?->toISOString(),
            'review_started_at' => $this->review_started_at?->toISOString(),
            'approved_at' => $this->approved_at?->toISOString(),
            'user' => $this->whenLoaded('user', fn () => [
                'id' => $this->user->id,
                'name' => $this->user->name,
                'email' => $this->user->email,
            ]),
            'bank_account_number' => $this->when($mayViewPrivate, $this->bank_account_number),
            'bank_name' => $this->when($mayViewPrivate, $this->bank_name),
            'bank_holder_name' => $this->when($mayViewPrivate, $this->bank_holder_name),
        ];
    }
}
