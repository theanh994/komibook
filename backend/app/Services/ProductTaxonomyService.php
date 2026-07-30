<?php

namespace App\Services;

use App\Models\Book;
use App\Models\ReturnPolicyVersion;
use Illuminate\Validation\ValidationException;

class ProductTaxonomyService
{
    public function normalize(array $data, ?Book $existing = null): array
    {
        $format = $data['format'] ?? $data['type'] ?? $existing?->format ?? $existing?->type;
        $provenance = $data['provenance'] ?? $existing?->provenance ?? 'publisher_catalog';
        $condition = $data['condition'] ?? $existing?->condition;
        $formatWasProvided = array_key_exists('format', $data) || array_key_exists('type', $data);
        $formatChanged = $existing !== null
            && $formatWasProvided
            && $format !== ($existing->format ?? $existing->type);
        $fulfillment = $data['fulfillment_mode']
            ?? ($formatChanged ? null : $existing?->fulfillment_mode)
            ?? ($format === 'ebook' ? 'digital' : 'vendor_warehouse');

        $errors = [];
        if (! in_array($format, ['ebook', 'physical'], true)) {
            $errors['format'] = 'Định dạng sản phẩm không hợp lệ.';
        }
        if (isset($data['format'], $data['type']) && $data['format'] !== $data['type']) {
            $errors['format'] = 'Format phải đồng bộ với type trong giai đoạn tương thích.';
        }
        if (! in_array($provenance, ['self_published', 'used_resale', 'publisher_catalog'], true)) {
            $errors['provenance'] = 'Nguồn gốc sản phẩm không hợp lệ.';
        }
        if (! in_array($fulfillment, ['digital', 'seller_verified_address', 'vendor_warehouse'], true)) {
            $errors['fulfillment_mode'] = 'Phương thức fulfillment không hợp lệ.';
        }
        if ($format === 'ebook' && ($condition !== null || $fulfillment !== 'digital' || $provenance === 'used_resale')) {
            $errors['taxonomy'] = 'Ebook phải dùng digital fulfillment, không có tình trạng vật lý và không thể là sách cũ.';
        }
        if ($format === 'physical' && $fulfillment === 'digital') {
            $errors['fulfillment_mode'] = 'Sách vật lý không thể dùng digital fulfillment.';
        }
        if ($provenance === 'used_resale') {
            if ($format !== 'physical' || ! in_array($condition, ['like_new', 'good', 'fair'], true)) {
                $errors['condition'] = 'Sách cũ phải là sách vật lý và có tình trạng like_new, good hoặc fair.';
            }
        } elseif ($condition !== null && $condition !== 'new') {
            $errors['condition'] = 'Tình trạng đã qua sử dụng chỉ áp dụng cho used_resale.';
        }

        if ($errors) {
            throw ValidationException::withMessages($errors);
        }

        $policyKey = match (true) {
            $format === 'ebook' => 'ebook_non_returnable',
            $provenance === 'used_resale' => 'used_book_return',
            default => 'physical_standard',
        };
        $policyId = ReturnPolicyVersion::where('policy_key', $policyKey)
            ->whereNull('retired_at')
            ->latest('version')
            ->value('id');

        return [
            ...$data,
            'type' => $format,
            'format' => $format,
            'provenance' => $provenance,
            'condition' => $condition,
            'fulfillment_mode' => $fulfillment,
            'return_policy_version_id' => $policyId,
        ];
    }
}
