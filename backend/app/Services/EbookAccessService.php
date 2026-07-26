<?php

namespace App\Services;

use App\Models\Order;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class EbookAccessService
{
    /**
     * Tìm đơn hàng hợp lệ để cấp quyền truy cập Ebook cho User.
     *
     * Điều kiện:
     * - Order thuộc user
     * - Order chứa book_id được yêu cầu
     * - Sách có type === 'ebook'
     * - Order không bị cancelled hoặc refunded
     * - payment_status === 'paid' HOẶC status === 'completed'
     *
     * Sắp xếp ưu tiên order mới nhất (created_at DESC, id DESC).
     */
    public function getValidOrder(User $user, int $bookId): ?Order
    {
        return $this->validOrdersQuery($user)
            ->whereHas('orderItems', function ($itemQuery) use ($bookId) {
                $itemQuery->where('book_id', $bookId)
                    ->whereHas('book', function ($bQuery) {
                        $bQuery->withoutGlobalScopes()->where('type', 'ebook');
                    });
            })
            ->orderBy('created_at', 'desc')
            ->orderBy('id', 'desc')
            ->first();
    }

    /**
     * Resolve the newest valid order for each requested ebook in a bounded query set.
     *
     * @param  iterable<int>  $bookIds
     * @return array<int, Order>
     */
    public function getValidOrdersForBooks(User $user, iterable $bookIds): array
    {
        $normalizedBookIds = collect($bookIds)
            ->map(fn ($bookId) => (int) $bookId)
            ->filter(fn ($bookId) => $bookId > 0)
            ->unique()
            ->values();

        if ($normalizedBookIds->isEmpty()) {
            return [];
        }

        $orders = $this->validOrdersQuery($user)
            ->whereHas('orderItems', function ($itemQuery) use ($normalizedBookIds) {
                $itemQuery->whereIn('book_id', $normalizedBookIds)
                    ->whereHas('book', function ($bookQuery) {
                        $bookQuery->withoutGlobalScopes()->where('type', 'ebook');
                    });
            })
            ->with(['orderItems' => function ($itemQuery) use ($normalizedBookIds) {
                $itemQuery->whereIn('book_id', $normalizedBookIds)
                    ->whereHas('book', function ($bookQuery) {
                        $bookQuery->withoutGlobalScopes()->where('type', 'ebook');
                    });
            }])
            ->orderBy('created_at', 'desc')
            ->orderBy('id', 'desc')
            ->get();

        $validOrdersByBook = [];

        foreach ($orders as $order) {
            foreach ($order->orderItems as $orderItem) {
                $validOrdersByBook[$orderItem->book_id] ??= $order;
            }
        }

        return $validOrdersByBook;
    }

    /**
     * Kiểm tra user có quyền truy cập Ebook hay không.
     */
    public function checkAccess(User $user, int $bookId): bool
    {
        return $this->getValidOrder($user, $bookId) !== null;
    }

    /**
     * Lấy dữ liệu ownership chuẩn hóa cho Ebook.
     * Trả về { owned: bool, order_id: int|null, book_id: int }
     */
    public function getOwnershipData(User $user, int $bookId): array
    {
        $order = $this->getValidOrder($user, $bookId);

        if ($order) {
            return [
                'owned' => true,
                'order_id' => $order->id,
                'book_id' => (int) $bookId,
            ];
        }

        return [
            'owned' => false,
            'order_id' => null,
            'book_id' => (int) $bookId,
        ];
    }

    private function validOrdersQuery(User $user): Builder
    {
        return Order::withoutGlobalScopes()
            ->where('user_id', $user->id)
            ->whereNotIn('status', ['cancelled', 'refunded'])
            ->where(function ($query) {
                $query->where('payment_status', 'paid')
                    ->orWhere('status', 'completed');
            });
    }
}
