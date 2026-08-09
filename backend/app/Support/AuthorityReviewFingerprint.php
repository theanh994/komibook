<?php

namespace App\Support;

use App\Models\Organization;
use App\Models\OrganizationDistributionAgreement;
use App\Models\VendorOrganizationRelationship;

class AuthorityReviewFingerprint
{
    public static function organization(Organization $model): string
    {
        return self::hash(['legal_name' => $model->legal_name, 'display_name' => $model->display_name, 'slug' => $model->slug, 'organization_types' => $model->organization_types, 'tax_code' => $model->tax_code, 'license_number' => $model->license_number, 'data_mode' => $model->data_mode, 'verification_document' => $model->verification_document, 'submitted_at' => self::datetime($model->submitted_at)]);
    }

    public static function relationship(VendorOrganizationRelationship $model): string
    {
        return self::hash(['vendor_id' => $model->vendor_id, 'organization_id' => $model->organization_id, 'role' => $model->role, 'is_demo' => $model->is_demo, 'evidence_mode' => $model->evidence_mode, 'evidence_document' => $model->evidence_document, 'demo_reference' => $model->demo_reference, 'submitted_at' => self::datetime($model->submitted_at), 'effective_from' => self::date($model->effective_from), 'effective_until' => self::date($model->effective_until)]);
    }

    public static function agreement(OrganizationDistributionAgreement $model): string
    {
        return self::hash(['publisher_organization_id' => $model->publisher_organization_id, 'distributor_organization_id' => $model->distributor_organization_id, 'is_demo' => $model->is_demo, 'evidence_mode' => $model->evidence_mode, 'evidence_document' => $model->evidence_document, 'demo_reference' => $model->demo_reference, 'scope' => $model->scope, 'submitted_at' => self::datetime($model->submitted_at), 'effective_from' => self::date($model->effective_from), 'effective_until' => self::date($model->effective_until)]);
    }

    private static function hash(array $value): string
    {
        return hash('sha256', json_encode(self::normalize($value), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
    }

    private static function date(mixed $value): ?string
    {
        return $value instanceof \DateTimeInterface ? $value->format('Y-m-d') : ($value ?: null);
    }

    private static function datetime(mixed $value): ?string
    {
        return $value instanceof \DateTimeInterface ? (clone $value)->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s') : ($value ?: null);
    }

    private static function normalize(mixed $value): mixed
    {
        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d\TH:i:sP');
        }
        if (is_bool($value)) {
            return $value ? true : false;
        }
        if (! is_array($value)) {
            return $value;
        }
        foreach ($value as $key => $item) {
            $value[$key] = self::normalize($item);
        }
        if (array_is_list($value)) {
            sort($value);
        } else {
            ksort($value);
        }

        return $value;
    }
}
