<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class VendorProfileResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $viewer = $request->user();
        $private = $viewer && ($viewer->role === 'admin' || $viewer->id === $this->user_id);
        $status = $this->onboarding_status instanceof \BackedEnum ? $this->onboarding_status->value : $this->onboarding_status;

        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'shop_name' => $this->shop_name,
            'slug' => $this->slug,
            'description' => $this->description,
            'logo' => $this->logo ? '/storage/'.$this->logo : null,
            'status' => $this->status,
            'onboarding_status' => $status,
            'application_version' => $this->application_version,
            'business_model' => $this->business_model,
            'primary_organization_id' => $this->primary_organization_id,
            'is_demo' => (bool) $this->is_demo,
            'demo_wallet_code' => $this->when($private, $this->demo_wallet_code),
            'last_review_reason' => $this->last_review_reason,
            'terms_accepted_at' => $this->terms_accepted_at?->toISOString(),
            'submitted_at' => $this->submitted_at?->toISOString(),
            'review_started_at' => $this->review_started_at?->toISOString(),
            'approved_at' => $this->approved_at?->toISOString(),
            'user' => $this->whenLoaded('user', fn () => [
                'id' => $this->user->id,
                'name' => $this->user->name,
                'email' => $this->user->email,
                'phone' => $this->user->phone,
            ]),
            'legal_name' => $this->when($private, $this->legal_name),
            'tax_code' => $this->when($private, $this->tax_code),
            'payout_bank_account' => $this->when($private, $this->payout_bank_account),
            'payout_bank_name' => $this->when($private, $this->payout_bank_name),
            'payout_bank_holder' => $this->when($private, $this->payout_bank_holder),
            'payout_bank_status' => $this->when($private, $this->payout_bank_status),
            'payout_bank_verified_at' => $this->when($private, $this->payout_bank_verified_at?->toISOString()),
            'has_business_registration_document' => $private ? filled($this->business_registration_document) : null,
            'has_representative_identity_document' => $private ? filled($this->representative_identity_document) : null,
            'business_registration_document_url' => $private && $this->business_registration_document ? "/api/vendors/{$this->id}/documents/business" : null,
            'representative_identity_document_url' => $private && $this->representative_identity_document ? "/api/vendors/{$this->id}/documents/representative" : null,
        ];
    }
}
