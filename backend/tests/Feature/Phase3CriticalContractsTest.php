<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\Category;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Phase3CriticalContractsTest extends TestCase
{
    use RefreshDatabase;

    protected $category;

    protected $vendor;

    protected function setUp(): void
    {
        parent::setUp();

        $this->category = Category::create([
            'name' => 'Kinh Tế Test',
            'slug' => 'kinh-te-test-'.uniqid(),
        ]);

        $vendorUser = User::factory()->create(['role' => 'vendor']);
        $this->vendor = Vendor::create([
            'user_id' => $vendorUser->id,
            'shop_name' => 'Shop Test '.uniqid(),
            'slug' => 'shop-test-'.uniqid(),
            'status' => 'active',
        ]);
    }

    protected function createTestBook(array $attributes = []): Book
    {
        return Book::create(array_merge([
            'vendor_id' => $this->vendor->id,
            'category_id' => $this->category->id,
            'title' => 'Test Book '.uniqid(),
            'slug' => 'test-book-'.uniqid(),
            'author' => 'Test Author',
            'type' => 'ebook',
            'price' => 100000,
            'file_path' => 'ebooks/sample.pdf',
        ], $attributes));
    }

    protected function createTestOrder(User $user, array $attributes = []): Order
    {
        return Order::create(array_merge([
            'user_id' => $user->id,
            'vendor_id' => $this->vendor->id,
            'status' => 'processing',
            'payment_status' => 'paid',
            'total' => 100000,
            'total_amount' => 100000,
            'subtotal' => 100000,
            'tax' => 0,
            'shipping_address' => '123 Street',
            'phone' => '0900000000',
            'name' => $user->name,
            'payment_method' => 'online',
        ], $attributes));
    }

    public function test_paid_ebook_owner_returns_ownership_true_and_order_id()
    {
        $user = User::factory()->create();
        $ebook = $this->createTestBook(['type' => 'ebook']);
        $order = $this->createTestOrder($user, [
            'status' => 'processing',
            'payment_status' => 'paid',
        ]);
        OrderItem::create([
            'order_id' => $order->id,
            'book_id' => $ebook->id,
            'price' => 100000,
            'quantity' => 1,
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson("/api/books/{$ebook->id}/check-ownership");

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'success',
                'data' => [
                    'owned' => true,
                    'order_id' => $order->id,
                    'book_id' => $ebook->id,
                ],
            ]);
    }

    public function test_completed_ebook_owner_can_access()
    {
        $user = User::factory()->create();
        $ebook = $this->createTestBook(['type' => 'ebook']);
        $order = $this->createTestOrder($user, [
            'status' => 'completed',
            'payment_status' => 'unpaid',
        ]);
        OrderItem::create([
            'order_id' => $order->id,
            'book_id' => $ebook->id,
            'price' => 100000,
            'quantity' => 1,
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson("/api/books/{$ebook->id}/check-ownership");

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'success',
                'data' => [
                    'owned' => true,
                    'order_id' => $order->id,
                    'book_id' => $ebook->id,
                ],
            ]);
    }

    public function test_unpaid_or_pending_ebook_returns_owned_false_and_link_generation_forbidden()
    {
        $user = User::factory()->create();
        $ebook = $this->createTestBook(['type' => 'ebook']);
        $order = $this->createTestOrder($user, [
            'status' => 'pending',
            'payment_status' => 'unpaid',
        ]);
        OrderItem::create([
            'order_id' => $order->id,
            'book_id' => $ebook->id,
            'price' => 100000,
            'quantity' => 1,
        ]);

        $ownershipRes = $this->actingAs($user, 'sanctum')
            ->getJson("/api/books/{$ebook->id}/check-ownership");

        $ownershipRes->assertStatus(200)
            ->assertJson([
                'status' => 'success',
                'data' => [
                    'owned' => false,
                    'order_id' => null,
                    'book_id' => $ebook->id,
                ],
            ]);

        $linkRes = $this->actingAs($user, 'sanctum')
            ->getJson("/api/orders/{$order->id}/ebooks/{$ebook->id}/generate-link");

        $linkRes->assertStatus(403);
    }

    public function test_cancelled_or_refunded_order_denies_ebook_access()
    {
        $user = User::factory()->create();
        $ebook = $this->createTestBook(['type' => 'ebook']);
        $order = $this->createTestOrder($user, [
            'status' => 'cancelled',
            'payment_status' => 'paid',
        ]);
        OrderItem::create([
            'order_id' => $order->id,
            'book_id' => $ebook->id,
            'price' => 100000,
            'quantity' => 1,
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson("/api/books/{$ebook->id}/check-ownership");

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'success',
                'data' => [
                    'owned' => false,
                    'order_id' => null,
                    'book_id' => $ebook->id,
                ],
            ]);
    }

    public function test_physical_book_order_does_not_grant_ebook_ownership()
    {
        $user = User::factory()->create();
        $physicalBook = $this->createTestBook(['type' => 'physical']);
        $order = $this->createTestOrder($user, [
            'status' => 'completed',
            'payment_status' => 'paid',
        ]);
        OrderItem::create([
            'order_id' => $order->id,
            'book_id' => $physicalBook->id,
            'price' => 100000,
            'quantity' => 1,
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson("/api/books/{$physicalBook->id}/check-ownership");

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'success',
                'data' => [
                    'owned' => false,
                    'order_id' => null,
                    'book_id' => $physicalBook->id,
                ],
            ]);
    }

    public function test_user_cannot_access_or_leak_other_user_ebook_order()
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();
        $ebook = $this->createTestBook(['type' => 'ebook']);
        $orderA = $this->createTestOrder($userA, [
            'status' => 'completed',
            'payment_status' => 'paid',
        ]);
        OrderItem::create([
            'order_id' => $orderA->id,
            'book_id' => $ebook->id,
            'price' => 100000,
            'quantity' => 1,
        ]);

        $response = $this->actingAs($userB, 'sanctum')
            ->getJson("/api/books/{$ebook->id}/check-ownership");

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'success',
                'data' => [
                    'owned' => false,
                    'order_id' => null,
                    'book_id' => $ebook->id,
                ],
            ]);

        $linkRes = $this->actingAs($userB, 'sanctum')
            ->getJson("/api/orders/{$orderA->id}/ebooks/{$ebook->id}/generate-link");

        $linkRes->assertStatus(403);
    }

    public function test_my_library_does_not_grant_invalid_ebook_reading_and_omits_private_filepath()
    {
        $user = User::factory()->create();
        $ebook = $this->createTestBook(['type' => 'ebook', 'file_path' => 'private/secret.pdf']);
        $unpaidOrder = $this->createTestOrder($user, [
            'status' => 'pending',
            'payment_status' => 'unpaid',
        ]);
        OrderItem::create([
            'order_id' => $unpaidOrder->id,
            'book_id' => $ebook->id,
            'price' => 100000,
            'quantity' => 1,
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/my-library');

        $response->assertStatus(200);
        $data = $response->json('data');

        $this->assertNotEmpty($data);
        $item = $data[0];
        $this->assertNull($item['order_id']);
        $this->assertFalse($item['has_access']);
        $this->assertArrayNotHasKey('file_path', $item['book']);
    }

    public function test_annotations_index_store_recent_reject_non_owner()
    {
        $user = User::factory()->create();
        $ebook = $this->createTestBook(['type' => 'ebook']);

        $storeRes = $this->actingAs($user, 'sanctum')
            ->postJson('/api/annotations', [
                'book_id' => $ebook->id,
                'type' => 'note',
                'note_content' => 'Test note',
            ]);
        $storeRes->assertStatus(403);

        $indexRes = $this->actingAs($user, 'sanctum')
            ->getJson("/api/annotations?book_id={$ebook->id}");
        $indexRes->assertStatus(403);

        $recentRes = $this->actingAs($user, 'sanctum')
            ->getJson("/api/books/{$ebook->id}/recent-annotations");
        $recentRes->assertStatus(403);
    }

    public function test_valid_annotation_returns_book_id_and_order_id()
    {
        $user = User::factory()->create();
        $ebook = $this->createTestBook(['type' => 'ebook']);
        $order = $this->createTestOrder($user, [
            'status' => 'completed',
            'payment_status' => 'paid',
        ]);
        OrderItem::create([
            'order_id' => $order->id,
            'book_id' => $ebook->id,
            'price' => 100000,
            'quantity' => 1,
        ]);

        $storeRes = $this->actingAs($user, 'sanctum')
            ->postJson('/api/annotations', [
                'book_id' => $ebook->id,
                'type' => 'note',
                'note_content' => 'Valid annotation content',
                'page_number' => 1,
            ]);

        $storeRes->assertStatus(201)
            ->assertJson([
                'status' => 'success',
                'data' => [
                    'book_id' => $ebook->id,
                    'order_id' => $order->id,
                ],
            ]);
    }

    public function test_customer_order_detail_returns_own_order()
    {
        $user = User::factory()->create();
        $book = $this->createTestBook(['title' => 'Sách Kiểm Thử Real Data']);
        $order = $this->createTestOrder($user, [
            'order_code' => 'ORD-TEST-99',
            'total_amount' => 150000,
        ]);
        OrderItem::create([
            'order_id' => $order->id,
            'book_id' => $book->id,
            'price' => 150000,
            'quantity' => 1,
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson("/api/my-orders/{$order->id}");

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'success',
                'data' => [
                    'id' => $order->id,
                    'order_code' => 'ORD-TEST-99',
                    'total_amount' => 150000,
                    'items' => [
                        [
                            'book_id' => $book->id,
                            'price' => 150000,
                            'quantity' => 1,
                            'book' => [
                                'title' => 'Sách Kiểm Thử Real Data',
                            ],
                        ],
                    ],
                ],
            ]);
    }

    public function test_customer_order_detail_blocks_other_user_order()
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();
        $orderA = $this->createTestOrder($userA);

        $response = $this->actingAs($userB, 'sanctum')
            ->getJson("/api/my-orders/{$orderA->id}");

        $response->assertStatus(404);
    }
}
