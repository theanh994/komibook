<?php

namespace Tests\Feature;

use App\Http\Resources\BookResource;
use App\Models\Book;
use App\Models\Category;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;
use App\Models\Vendor;
use App\Support\PublicMediaUrl;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Phase7OrderCoverUrlTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_media_url_normalizes_supported_cover_formats(): void
    {
        $this->assertSame('/storage/books/relative.jpg', PublicMediaUrl::storage('books/relative.jpg'));
        $this->assertSame('/storage/books/existing.jpg', PublicMediaUrl::storage('/storage/books/existing.jpg'));
        $this->assertSame('https://cdn.example.test/cover.jpg', PublicMediaUrl::storage('https://cdn.example.test/cover.jpg'));
        $this->assertSame('http://cdn.example.test/cover.jpg', PublicMediaUrl::storage('http://cdn.example.test/cover.jpg'));
        $this->assertNull(PublicMediaUrl::storage(null));
        $this->assertNull(PublicMediaUrl::storage('  '));
        $this->assertSame('/storage/evil.example/cover.jpg', PublicMediaUrl::storage('//evil.example/cover.jpg'));
        $this->assertSame('/storage/books/relative.jpg?v=123', PublicMediaUrl::versionedStorage('books/relative.jpg', 123));
        $this->assertSame('/storage/books/existing.jpg?size=large&v=123', PublicMediaUrl::versionedStorage('/storage/books/existing.jpg?size=large', 123));
        $this->assertSame('https://cdn.example.test/cover.jpg', PublicMediaUrl::versionedStorage('https://cdn.example.test/cover.jpg', 123));
    }

    public function test_order_detail_vendor_order_book_resource_and_library_share_the_same_cover_contract(): void
    {
        $customer = User::factory()->create(['role' => 'customer']);
        $vendorUser = User::factory()->create(['role' => 'vendor']);
        $vendor = Vendor::withoutGlobalScopes()->create([
            'user_id' => $vendorUser->id,
            'shop_name' => 'Cover Shop',
            'slug' => 'cover-shop',
            'status' => 'active',
            'onboarding_status' => 'approved',
        ]);
        $category = Category::create(['name' => 'Bìa sách', 'slug' => 'bia-sach']);
        $book = Book::withoutGlobalScopes()->create([
            'vendor_id' => $vendor->id,
            'category_id' => $category->id,
            'title' => 'Sách có bìa',
            'slug' => 'sach-co-bia',
            'author' => 'KomiBook',
            'description' => 'Kiểm tra URL bìa.',
            'cover_image' => '/storage/books/covers/verified.jpg',
            'gallery_images' => ['/storage/books/gallery/existing.jpg', 'books/gallery/relative.jpg'],
            'price' => 50000,
            'stock' => 1,
            'type' => 'ebook',
            'status' => 'published',
            'publishing_status' => 'published',
        ]);
        $order = Order::withoutGlobalScopes()->create([
            'order_code' => 'P7-COVER-001',
            'user_id' => $customer->id,
            'vendor_id' => $vendor->id,
            'total_amount' => 50000,
            'status' => 'completed',
            'payment_status' => 'paid',
            'payment_method' => 'online',
            'shipping_address' => 'KomiBook test',
            'phone' => '0900000000',
        ]);
        OrderItem::create([
            'order_id' => $order->id,
            'book_id' => $book->id,
            'quantity' => 1,
            'price' => 50000,
        ]);

        $this->actingAs($customer)->getJson('/api/my-orders')
            ->assertOk()
            ->assertJsonPath('data.0.items.0.book.cover_image', '/storage/books/covers/verified.jpg');

        $this->actingAs($customer)->getJson("/api/my-orders/{$order->id}")
            ->assertOk()
            ->assertJsonPath('data.items.0.book.cover_image', '/storage/books/covers/verified.jpg');

        $this->actingAs($customer)->getJson('/api/my-library')
            ->assertOk()
            ->assertJsonPath('data.0.book.cover_image', '/storage/books/covers/verified.jpg');

        $this->actingAs($vendorUser)->getJson("/api/vendor/orders/{$order->id}")
            ->assertOk()
            ->assertJsonPath('data.items.0.book.cover_image', '/storage/books/covers/verified.jpg');

        $bookPayload = (new BookResource($book))->resolve(request());
        $mediaVersion = $book->updated_at->getTimestamp();
        $this->assertSame("/storage/books/covers/verified.jpg?v={$mediaVersion}", $bookPayload['cover_image']);
        $this->assertSame([
            "/storage/books/gallery/existing.jpg?v={$mediaVersion}",
            "/storage/books/gallery/relative.jpg?v={$mediaVersion}",
        ], $bookPayload['gallery_images']);
    }
}
