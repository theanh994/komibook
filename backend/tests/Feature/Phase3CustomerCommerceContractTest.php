<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Book;
use App\Models\Category;
use App\Models\Coupon;
use App\Models\User;
use App\Models\Vendor;
use App\Services\CheckoutService;
use Exception;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Mockery\MockInterface;
use Tests\TestCase;

class Phase3CustomerCommerceContractTest extends TestCase
{
    use RefreshDatabase;

    public function test_checkout_success_and_business_error_use_canonical_status_envelope(): void
    {
        $user = User::factory()->create();
        $book = $this->createBook();
        Sanctum::actingAs($user);

        $checkoutCalls = 0;
        $this->mock(CheckoutService::class, function (MockInterface $mock) use (&$checkoutCalls): void {
            $mock->shouldReceive('processCheckout')
                ->twice()
                ->andReturnUsing(function () use (&$checkoutCalls): array {
                    $checkoutCalls++;
                    if ($checkoutCalls === 1) {
                        return [['id' => 101]];
                    }

                    throw new Exception('Business rule rejected checkout.');
                });
        });

        $success = $this->postJson('/api/checkout', [
            'items' => [['book_id' => $book->id, 'quantity' => 1]],
            'shipping_address' => '123 Contract Street',
            'phone' => '0900000000',
            'payment_method' => 'COD',
        ]);

        $success->assertCreated()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.0.id', 101);

        $failure = $this->postJson('/api/checkout', [
            'items' => [['book_id' => $book->id, 'quantity' => 1]],
            'shipping_address' => '123 Contract Street',
            'phone' => '0900000000',
            'payment_method' => 'COD',
        ]);

        $failure->assertStatus(400)
            ->assertJsonPath('status', 'error')
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Business rule rejected checkout.');
    }

    public function test_coupon_success_and_not_found_error_use_canonical_status_envelope(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        Coupon::create([
            'code' => 'PHASE3D1',
            'discount_percent' => 10,
            'min_order_value' => 0,
            'usage_limit' => 0,
            'used_count' => 0,
        ]);

        $this->postJson('/api/coupons/apply', [
            'code' => 'PHASE3D1',
            'total_amount' => 100000,
        ])->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.code', 'PHASE3D1')
            ->assertJsonPath('data.discount_amount', 10000);

        $this->postJson('/api/coupons/apply', [
            'code' => 'MISSING',
            'total_amount' => 100000,
        ])->assertNotFound()
            ->assertJsonPath('status', 'error')
            ->assertJsonPath('success', false);
    }

    public function test_public_flash_sale_reads_use_canonical_status_envelope(): void
    {
        $this->getJson('/api/flash-sales')
            ->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('success', true)
            ->assertJsonPath('data', []);

        $this->getJson('/api/flash-sales/active')
            ->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('success', true)
            ->assertJsonPath('data', null);
    }

    public function test_wishlist_list_toggle_and_check_use_canonical_status_envelope(): void
    {
        $user = User::factory()->create();
        $book = $this->createBook();
        Sanctum::actingAs($user);

        $this->getJson('/api/wishlist')
            ->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data', []);

        $this->postJson("/api/wishlist/{$book->id}/toggle")
            ->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.state', 'added')
            ->assertJsonPath('data.is_favorite', true);

        $this->getJson("/api/wishlist/{$book->id}/check")
            ->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.is_favorite', true);

        $this->postJson("/api/wishlist/{$book->id}/toggle")
            ->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.state', 'removed')
            ->assertJsonPath('data.is_favorite', false);
    }

    public function test_empty_customer_order_and_library_reads_use_canonical_collections(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->getJson('/api/my-orders')
            ->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data', []);

        $this->getJson('/api/my-library')
            ->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data', []);
    }

    private function createBook(): Book
    {
        $vendorUser = User::factory()->create(['role' => 'vendor']);
        $vendor = Vendor::create([
            'user_id' => $vendorUser->id,
            'shop_name' => 'Contract Shop '.uniqid(),
            'slug' => 'contract-shop-'.uniqid(),
            'status' => 'active',
        ]);
        $category = Category::create([
            'name' => 'Contract Category '.uniqid(),
            'slug' => 'contract-category-'.uniqid(),
        ]);

        return Book::create([
            'vendor_id' => $vendor->id,
            'category_id' => $category->id,
            'title' => 'Contract Book '.uniqid(),
            'slug' => 'contract-book-'.uniqid(),
            'author' => 'Contract Author',
            'price' => 100000,
            'stock' => 10,
            'type' => 'physical',
            'status' => 'published',
        ]);
    }
}
