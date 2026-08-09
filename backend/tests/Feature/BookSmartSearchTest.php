<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\Category;
use App\Models\Series;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BookSmartSearchTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
    }

    public function test_smart_search_finds_book_with_shortened_and_interrupted_keywords(): void
    {
        // 1. Arrange: Tạo Vendor & Category & Book
        $user = User::factory()->create(['role' => 'vendor']);
        $vendor = Vendor::create([
            'user_id' => $user->id,
            'shop_name' => 'Komi Store',
            'status' => 'active',
        ]);

        $category = Category::create([
            'name' => 'Manga - Comic',
            'slug' => 'manga-comic',
        ]);

        $book = Book::create([
            'vendor_id' => $vendor->id,
            'category_id' => $category->id,
            'title' => 'Komi - Nữ thần giao tiếp - Tập 1',
            'slug' => 'komi-nu-than-giao-tiep-tap-1',
            'author' => 'Oda Tomohito',
            'price' => 50000,
            'stock' => 10,
            'status' => 'published',
            'publishing_status' => 'published',
            'type' => 'physical',
        ]);

        // 2. Act & Assert: Tìm kiếm từ khóa "komi tập 1"
        $response = $this->getJson('/api/books?search='.urlencode('komi tập 1'));
        $response->assertStatus(200);
        $data = $response->json('data');

        $this->assertNotEmpty($data, 'Phải tìm thấy sách với từ khóa rút gọn "komi tập 1"');
        $this->assertEquals($book->id, $data[0]['id']);
        $this->assertEquals('Komi - Nữ thần giao tiếp - Tập 1', $data[0]['title']);

        // 3. Act & Assert: Tìm kiếm từ khóa "komi 1"
        $response2 = $this->getJson('/api/books?search='.urlencode('komi 1'));
        $response2->assertStatus(200);
        $data2 = $response2->json('data');
        $this->assertNotEmpty($data2, 'Phải tìm thấy sách với từ khóa "komi 1"');

        // 4. Act & Assert: Tìm kiếm từ khóa "nữ thần giao tiếp 1"
        $response3 = $this->getJson('/api/books?search='.urlencode('nữ thần giao tiếp 1'));
        $response3->assertStatus(200);
        $data3 = $response3->json('data');
        $this->assertNotEmpty($data3, 'Phải tìm thấy sách với từ khóa "nữ thần giao tiếp 1"');

        // 5. Act & Assert: Tìm kiếm từ khóa có dấu phân cách "Komi - Tập 1"
        $response4 = $this->getJson('/api/books?search='.urlencode('Komi - Tập 1'));
        $response4->assertStatus(200);
        $data4 = $response4->json('data');
        $this->assertNotEmpty($data4, 'Phải tìm thấy sách khi nhập dấu gạch ngang "Komi - Tập 1"');
    }

    public function test_smart_search_finds_book_by_series_name(): void
    {
        $user = User::factory()->create(['role' => 'vendor']);
        $vendor = Vendor::create([
            'user_id' => $user->id,
            'shop_name' => 'Komi Store 2',
            'status' => 'active',
        ]);
        $category = Category::create([
            'name' => 'Manga',
            'slug' => 'manga',
        ]);
        $series = Series::create([
            'title' => 'Komi-san Can Not Communicate',
            'description' => 'Bộ truyện Komi-san',
        ]);

        $book = Book::create([
            'vendor_id' => $vendor->id,
            'category_id' => $category->id,
            'series_id' => $series->id,
            'title' => 'Tập 1: Bạn học mới',
            'slug' => 'tap-1-ban-hoc-moi',
            'author' => 'Oda Tomohito',
            'price' => 45000,
            'stock' => 5,
            'status' => 'published',
            'publishing_status' => 'published',
            'type' => 'physical',
        ]);

        // Tìm từ khóa gộp tên Series và Tập: "komi tập 1"
        $response = $this->getJson('/api/books?search='.urlencode('komi tập 1'));
        $response->assertStatus(200);
        $data = $response->json('data');

        $this->assertNotEmpty($data, 'Phải tìm thấy sách khi kết hợp từ khóa tên Series ("Komi-san") và tên Tập ("Tập 1")');
        $this->assertEquals($book->id, $data[0]['id']);
    }

    public function test_smart_search_finds_book_with_concatenated_keywords(): void
    {
        $user = User::factory()->create(['role' => 'vendor']);
        $vendor = Vendor::create([
            'user_id' => $user->id,
            'shop_name' => '86 Store',
            'status' => 'active',
        ]);
        $category = Category::create([
            'name' => 'Light Novel',
            'slug' => 'light-novel',
        ]);

        $book = Book::create([
            'vendor_id' => $vendor->id,
            'category_id' => $category->id,
            'title' => '86 - Eighty Six - Tập 1',
            'slug' => '86-eighty-six-tap-1',
            'author' => 'Asato Asato',
            'price' => 105000,
            'stock' => 20,
            'status' => 'published',
            'publishing_status' => 'published',
            'type' => 'physical',
        ]);

        // 1. Tìm từ khóa bị dính "eightysix"
        $response = $this->getJson('/api/books?search='.urlencode('eightysix'));
        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertNotEmpty($data, 'Phải tìm thấy sách "86 - Eighty Six" khi nhập từ khóa dính liền "eightysix"');
        $this->assertEquals($book->id, $data[0]['id']);

        // 2. Tìm từ khóa bị dính "86eightysix"
        $response2 = $this->getJson('/api/books?search='.urlencode('86eightysix'));
        $response2->assertStatus(200);
        $data2 = $response2->json('data');
        $this->assertNotEmpty($data2, 'Phải tìm thấy sách "86 - Eighty Six" khi nhập từ khóa dính liền "86eightysix"');
    }

    public function test_ordinary_browse_hides_out_of_stock_books_but_explicit_search_marks_them_search_only(): void
    {
        $user = User::factory()->create(['role' => 'vendor']);
        $vendor = Vendor::create([
            'user_id' => $user->id,
            'shop_name' => 'Out of stock Store',
            'status' => 'active',
        ]);
        $category = Category::create([
            'name' => 'Manga',
            'slug' => 'manga-out-of-stock',
        ]);

        // Tạo 1 cuốn sách hết hàng (stock = 0)
        $outOfStockBook = Book::create([
            'vendor_id' => $vendor->id,
            'category_id' => $category->id,
            'title' => 'Chainsaw Man - Tập 1 (Hết hàng)',
            'slug' => 'chainsaw-man-tap-1-het-hang',
            'author' => 'Tatsuki Fujimoto',
            'price' => 45000,
            'stock' => 0, // Hết hàng
            'status' => 'published',
            'publishing_status' => 'published',
            'type' => 'physical',
        ]);

        // Tìm kiếm từ khóa "Chainsaw Man"
        $this->getJson('/api/books')
            ->assertOk()
            ->assertJsonMissing(['id' => $outOfStockBook->id]);

        $response = $this->getJson('/api/books?search='.urlencode('Chainsaw Man'));
        $response->assertStatus(200);
        $data = $response->json('data');

        $match = collect($data)->firstWhere('id', $outOfStockBook->id);
        $this->assertNotNull($match);
        $this->assertSame($outOfStockBook->title, $match['title']);
        $this->assertSame('search_only', $match['discoverability']);
        $this->assertFalse($match['is_purchasable']);
    }
}
