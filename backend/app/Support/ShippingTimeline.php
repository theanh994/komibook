<?php

namespace App\Support;

use App\Models\Order;

class ShippingTimeline
{
    /** @return array<int, array<string, mixed>> */
    public static function forOrder(Order $order): array
    {
        $events = [[
            'code' => 'ordered',
            'label' => 'Đã đặt hàng',
            'description' => 'KomiBook đã tiếp nhận đơn hàng.',
            'location' => 'Hệ thống KomiBook',
            'occurred_at' => $order->created_at?->toISOString(),
        ]];

        if (! $order->relationLoaded('transitionOperations')) {
            return $events;
        }

        $labels = [
            'pending_pickup' => ['Nhà bán đã bàn giao đơn', 'Đang chờ KomiBook Express tiếp nhận kiện hàng.'],
            'picked_up' => ['Đơn vị vận chuyển đã nhận hàng', 'KomiBook Express đã tiếp nhận kiện hàng từ nhà bán.'],
            'delivering' => ['Đang giao hàng', 'Kiện hàng đang được vận chuyển đến người nhận.'],
            'awaiting_customer_confirmation' => ['Đã giao tới khách', 'Đơn vị vận chuyển đã giao kiện hàng và đang chờ khách hàng xác nhận.'],
            'delivered' => ['Khách hàng đã nhận hàng', 'Người nhận đã xác nhận nhận được kiện hàng.'],
            'failed' => ['Giao hàng chưa thành công', 'Kiện hàng cần được nhà bán kiểm tra và hỗ trợ.'],
        ];

        foreach ($order->transitionOperations->sortBy('occurred_at') as $operation) {
            $code = $operation->transition_kind === 'order' && $operation->to_state === 'shipped'
                ? 'pending_pickup'
                : $operation->to_state;
            if (! isset($labels[$code])) {
                continue;
            }

            $events[] = [
                'code' => $code,
                'label' => $labels[$code][0],
                'description' => $labels[$code][1],
                'location' => $order->shipping_carrier ?: 'KomiBook Express (mô phỏng)',
                'occurred_at' => $operation->occurred_at?->toISOString(),
            ];
        }

        return $events;
    }
}
