<?php

namespace App\Services;

use App\Models\Book;
use App\Models\FlashSaleBook;
use Illuminate\Validation\ValidationException;

class FlashSalePricingService
{
    /** @return array{unit_price:int,list_unit_price:int,promotion_discount_amount:int,flash_sale_book_id:?int,promotion_snapshot:?array} */
    public function resolve(Book $book, int $quantity): array
    {
        $listPrice = (int) ($book->getRawOriginal('sale_price') ?? $book->price);
        $item = FlashSaleBook::query()
            ->where('book_id', $book->id)->where('status', 'approved')
            ->whereHas('flashSale', fn ($query) => $query->where('status', 'active')->where('is_active', true)->where('start_time', '<=', now())->where('end_time', '>', now()))
            ->with('flashSale')->first();

        if (! $item) {
            return ['unit_price' => $listPrice, 'list_unit_price' => $listPrice, 'promotion_discount_amount' => 0, 'flash_sale_book_id' => null, 'promotion_snapshot' => null];
        }
        if ($item->max_quantity > 0 && $item->sold_quantity + $quantity > $item->max_quantity) {
            throw ValidationException::withMessages(['items' => "Flash Sale for book {$book->id} has insufficient promotional quantity."]);
        }
        $salePrice = (int) ($item->sale_price ?? round($listPrice * (100 - $item->discount_percent) / 100));

        return [
            'unit_price' => $salePrice,
            'list_unit_price' => $listPrice,
            'promotion_discount_amount' => ($listPrice - $salePrice) * $quantity,
            'flash_sale_book_id' => $item->id,
            'promotion_snapshot' => ['flash_sale_id' => $item->flash_sale_id, 'flash_sale_book_id' => $item->id, 'title' => $item->flashSale->title, 'discount_percent' => (float) $item->discount_percent, 'coupon_stacking_policy' => $item->flashSale->coupon_stacking_policy, 'timezone' => $item->flashSale->timezone, 'start_time' => $item->flashSale->start_time->toISOString(), 'end_time' => $item->flashSale->end_time->toISOString()],
        ];
    }

    public function reserve(int $itemId, int $quantity): void
    {
        $updated = FlashSaleBook::whereKey($itemId)->where(function ($query) use ($quantity) {
            $query->where('max_quantity', 0)->orWhereColumn('sold_quantity', '<=', \DB::raw('max_quantity - '.(int) $quantity));
        })->increment('sold_quantity', $quantity);
        if ($updated !== 1) {
            throw ValidationException::withMessages(['items' => 'Promotional quantity was exhausted.']);
        }
    }
}
