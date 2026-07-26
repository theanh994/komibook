<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\Category;
use App\Models\CheckoutSessionOrder;
use App\Models\Coupon;
use App\Models\FlashSale;
use App\Models\FlashSaleBook;
use App\Models\FlashSaleEvent;
use App\Models\OrderItem;
use App\Models\User;
use App\Models\Vendor;
use App\Services\CheckoutService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class Phase4PromotionIntegrityTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $vendorUser;

    private Vendor $vendor;

    private Book $book;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
        $this->admin = User::factory()->create(['role' => 'admin']);
        $this->vendorUser = User::factory()->create(['role' => 'vendor']);
        $this->vendor = Vendor::withoutGlobalScopes()->create(['user_id' => $this->vendorUser->id, 'shop_name' => 'Promo Shop', 'slug' => 'promo-shop', 'status' => 'active', 'onboarding_status' => 'approved']);
        $category = Category::create(['name' => 'Promotions', 'slug' => 'promotions']);
        $this->book = Book::withoutGlobalScopes()->create(['vendor_id' => $this->vendor->id, 'category_id' => $category->id, 'title' => 'Promo Book', 'slug' => 'promo-book', 'author' => 'Author', 'description' => 'Description', 'cover_image' => 'cover.jpg', 'type' => 'ebook', 'file_path' => 'book.pdf', 'price' => 100000, 'stock' => 0, 'status' => 'published', 'publishing_status' => 'published']);
    }

    public function test_admin_lifecycle_vendor_ownership_decision_audit_and_public_visibility(): void
    {
        $sale = $this->campaign('deny');
        $this->getJson('/api/flash-sales/active')->assertJsonPath('data', null);
        $this->transition($sale, 'enrollment_open', 'open')->assertOk()->assertJsonPath('data.status', 'enrollment_open');

        $otherVendor = User::factory()->create(['role' => 'vendor']);
        Vendor::withoutGlobalScopes()->create(['user_id' => $otherVendor->id, 'shop_name' => 'Other', 'slug' => 'other', 'status' => 'active', 'onboarding_status' => 'approved']);
        $this->actingAs($otherVendor)->postJson("/api/vendor/flash-sales/{$sale->id}/register", ['book_ids' => [$this->book->id], 'discount_percent' => 20, 'max_quantity' => 1])->assertForbidden();

        $item = $this->enroll($sale, 20, 1);
        $this->actingAs($this->admin)->putJson("/api/admin/flash-sales/items/{$item->id}/approve", ['operation_key' => 'approve-item'])->assertOk()->assertJsonPath('data.status', 'approved')->assertJsonPath('data.sale_price', 80000);
        $this->transition($sale, 'active', 'activate')->assertOk();
        $this->getJson('/api/flash-sales/active')->assertOk()->assertJsonPath('data.status', 'active')->assertJsonCount(1, 'data.items');
        $this->assertDatabaseHas('flash_sale_events', ['operation_key' => 'approve-item', 'to_status' => 'approved']);
        $this->assertGreaterThanOrEqual(3, FlashSaleEvent::count());
    }

    public function test_overlapping_approval_is_rejected(): void
    {
        $first = $this->campaign('allow');
        $this->transition($first, 'enrollment_open', 'first-open');
        $firstItem = $this->enroll($first, 20, 2);
        $this->actingAs($this->admin)->putJson("/api/admin/flash-sales/items/{$firstItem->id}/approve", ['operation_key' => 'first-approve'])->assertOk();

        $second = $this->campaign('allow', 'Second sale');
        $this->transition($second, 'enrollment_open', 'second-open');
        $secondItem = $this->enroll($second, 25, 2);
        $this->actingAs($this->admin)->putJson("/api/admin/flash-sales/items/{$secondItem->id}/approve", ['operation_key' => 'second-approve'])
            ->assertUnprocessable()->assertJsonValidationErrors('book');
        $this->assertSame('pending', $secondItem->fresh()->status);
    }

    public function test_stacking_policy_quantity_and_checkout_snapshot_are_enforced(): void
    {
        $deny = $this->campaign('deny');
        $this->transition($deny, 'enrollment_open', 'deny-open');
        $item = $this->enroll($deny, 20, 1);
        $this->actingAs($this->admin)->putJson("/api/admin/flash-sales/items/{$item->id}/approve", ['operation_key' => 'deny-approve'])->assertOk();
        $this->transition($deny, 'active', 'deny-active');
        $coupon = Coupon::create(['code' => 'STACK10', 'discount_percent' => 10, 'min_order_value' => 0, 'max_discount_amount' => 0, 'usage_limit' => 10, 'used_count' => 0, 'start_time' => now()->subHour(), 'end_time' => now()->addHour()]);

        $buyer = User::factory()->create();
        try {
            app(CheckoutService::class)->processCheckout([['book_id' => $this->book->id, 'quantity' => 1]], ['shipping_address' => 'Huế', 'phone' => '0900000000'], $buyer->id, $coupon->code);
            $this->fail('Expected denied promotion stacking.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('coupon', $exception->errors());
        }
        $orders = app(CheckoutService::class)->processCheckout([['book_id' => $this->book->id, 'quantity' => 1]], ['shipping_address' => 'Huế', 'phone' => '0900000000'], $buyer->id);
        $line = OrderItem::where('order_id', $orders[0]->id)->firstOrFail();
        $this->assertSame(100000, $line->list_unit_price);
        $this->assertSame(80000, $line->price);
        $this->assertSame(20000, $line->promotion_discount_amount);
        $this->assertSame($item->id, $line->flash_sale_book_id);
        $this->assertSame('deny', $line->promotion_snapshot['coupon_stacking_policy']);
        $this->assertSame(80000, CheckoutSessionOrder::where('order_id', $orders[0]->id)->value('total_amount'));
        $this->assertSame(1, $item->fresh()->sold_quantity);

        $secondBuyer = User::factory()->create();
        $this->expectException(ValidationException::class);
        app(CheckoutService::class)->processCheckout([['book_id' => $this->book->id, 'quantity' => 1]], ['shipping_address' => 'Huế', 'phone' => '0911111111'], $secondBuyer->id);
    }

    public function test_allow_stacking_applies_coupon_after_flash_price(): void
    {
        $sale = $this->campaign('allow');
        $this->transition($sale, 'enrollment_open', 'allow-open');
        $item = $this->enroll($sale, 20, 5);
        $this->actingAs($this->admin)->putJson("/api/admin/flash-sales/items/{$item->id}/approve", ['operation_key' => 'allow-approve'])->assertOk();
        $this->transition($sale, 'active', 'allow-active')->assertOk();
        Coupon::create(['code' => 'ALLOW10', 'discount_percent' => 10, 'min_order_value' => 0, 'max_discount_amount' => 0, 'usage_limit' => 10, 'used_count' => 0, 'start_time' => now()->subDay(), 'end_time' => now()->addDay()]);

        $buyer = User::factory()->create();
        $orders = app(CheckoutService::class)->processCheckout([['book_id' => $this->book->id, 'quantity' => 1]], ['shipping_address' => 'Hà Nội', 'phone' => '0922222222'], $buyer->id, 'ALLOW10');
        $snapshot = CheckoutSessionOrder::where('order_id', $orders[0]->id)->firstOrFail();
        $this->assertSame(8000, $snapshot->discount_amount);
        $this->assertSame(72000, $snapshot->total_amount);
        $this->assertSame(8000, $orders[0]->invoiceSnapshot->coupon_discount_amount);
    }

    private function campaign(string $stacking, string $title = 'Campaign'): FlashSale
    {
        $response = $this->actingAs($this->admin)->postJson('/api/admin/flash-sales', ['title' => $title, 'start_time' => now()->subDay()->toISOString(), 'end_time' => now()->addDay()->toISOString(), 'timezone' => 'Asia/Ho_Chi_Minh', 'coupon_stacking_policy' => $stacking, 'priority' => 10]);
        $response->assertCreated();

        return FlashSale::findOrFail($response->json('data.id'));
    }

    private function transition(FlashSale $sale, string $status, string $key)
    {
        return $this->actingAs($this->admin)->patchJson("/api/admin/flash-sales/{$sale->id}/transition", ['to_status' => $status, 'operation_key' => $key]);
    }

    private function enroll(FlashSale $sale, int $discount, int $quantity): FlashSaleBook
    {
        $response = $this->actingAs($this->vendorUser)->postJson("/api/vendor/flash-sales/{$sale->id}/register", ['book_ids' => [$this->book->id], 'discount_percent' => $discount, 'max_quantity' => $quantity]);
        $response->assertCreated();

        return FlashSaleBook::findOrFail($response->json('data.0.id'));
    }
}
