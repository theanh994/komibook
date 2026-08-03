<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MembershipTier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MembershipController extends Controller
{
    public function overview(Request $request): JsonResponse
    {
        $user = $request->user();
        $points = max(0, (int) $user->points);
        $tiers = MembershipTier::query()->orderBy('min_points')->get();
        $eligibleTier = $tiers->where('min_points', '<=', $points)->last();
        $currentTier = $tiers->firstWhere('id', $user->membership_tier_id) ?? $eligibleTier;
        $nextTier = $tiers->first(fn (MembershipTier $tier) => $tier->min_points > $points);
        $currentFloor = (int) ($currentTier?->min_points ?? 0);
        $nextFloor = (int) ($nextTier?->min_points ?? $currentFloor);
        $progress = $nextTier && $nextFloor > $currentFloor
            ? min(100, max(0, (int) round((($points - $currentFloor) / ($nextFloor - $currentFloor)) * 100)))
            : 100;

        return response()->json([
            'status' => 'success',
            'data' => [
                'points' => $points,
                'current_tier_id' => $currentTier?->id,
                'eligible_tier_id' => $eligibleTier?->id,
                'next_tier_id' => $nextTier?->id,
                'points_to_next_tier' => $nextTier ? max(0, $nextTier->min_points - $points) : 0,
                'progress_percent' => $progress,
                'earning_rule' => [
                    'label' => 'Tích KomiPoint khi đơn hoàn tất',
                    'description' => 'Nhận 1 KomiPoint cho mỗi 10.000 VNĐ giá trị đơn hàng hoàn tất.',
                    'operational' => true,
                ],
                'tiers' => $tiers->map(fn (MembershipTier $tier) => [
                    'id' => $tier->id,
                    'name' => $tier->name,
                    'min_points' => $tier->min_points,
                    'discount_percent' => $tier->discount_percent,
                    'operational_benefits' => array_values(array_filter([
                        $tier->discount_percent > 0 ? [
                            'code' => 'checkout_discount',
                            'label' => "Giảm {$tier->discount_percent}% khi thanh toán",
                            'description' => 'Mức giảm được hệ thống tính trực tiếp sau ưu đãi coupon hợp lệ tại checkout.',
                        ] : null,
                        [
                            'code' => 'loyalty_points',
                            'label' => 'Tích điểm tự động',
                            'description' => 'Điểm chỉ được ghi nhận khi đơn hàng hoàn tất và có sổ cái chống cộng trùng.',
                        ],
                    ])),
                    'program_description' => $tier->benefits,
                ])->values(),
            ],
        ]);
    }
}
