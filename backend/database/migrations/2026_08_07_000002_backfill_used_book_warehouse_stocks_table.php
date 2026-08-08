<?php

use App\Models\Book;
use App\Models\UsedBookListing;
use App\Models\Vendor;
use App\Models\Warehouse;
use App\Models\WarehouseStock;
use App\Services\UsedBookSellerService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::transaction(function () {
            $sellerService = app(UsedBookSellerService::class);
            $listings = UsedBookListing::with('seller')->get();

            foreach ($listings as $listing) {
                $book = Book::withoutGlobalScopes()->find($listing->book_id);
                if (! $book) {
                    continue;
                }

                $vendor = Vendor::withoutGlobalScopes()->find($book->vendor_id);
                if (! $vendor && $listing->seller) {
                    $profile = $sellerService->profileFor($listing->seller);
                    $vendor = Vendor::withoutGlobalScopes()->find($profile->catalog_vendor_id);
                }

                if (! $vendor) {
                    continue;
                }

                $sellerUser = $listing->seller ?: $vendor->user;
                if (! $sellerUser) {
                    continue;
                }

                $warehouse = $sellerService->ensureVendorWarehouse($vendor, $sellerUser);
                $ws = WarehouseStock::where('book_id', $book->id)->first();
                if (! $ws) {
                    WarehouseStock::create([
                        'warehouse_id' => $warehouse->id,
                        'book_id' => $book->id,
                        'quantity' => max(1, (int) $listing->quantity_available),
                    ]);
                }
            }
        });
    }

    public function down(): void
    {
        // Non-destructive rollback
    }
};
