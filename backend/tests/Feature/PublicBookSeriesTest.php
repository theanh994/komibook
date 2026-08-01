<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\Category;
use App\Models\Series;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicBookSeriesTest extends TestCase
{
    use RefreshDatabase;

    public function test_series_endpoint_returns_the_complete_series_including_current_and_out_of_stock_books(): void
    {
        $vendorUser = User::factory()->create(['role' => 'vendor']);
        $vendor = Vendor::create([
            'user_id' => $vendorUser->id,
            'shop_name' => 'Nhà sách kiểm thử bộ sách',
            'slug' => 'nha-sach-kiem-thu-bo-sach',
            'status' => 'active',
        ]);
        $category = Category::create([
            'name' => 'Bộ sách kiểm thử',
            'slug' => 'bo-sach-kiem-thu',
        ]);
        $series = Series::create(['title' => 'Bộ sách dài tập']);

        $books = collect(range(1, 17))->map(fn (int $volume) => Book::create([
            'vendor_id' => $vendor->id,
            'category_id' => $category->id,
            'series_id' => $series->id,
            'title' => "Bộ sách dài tập - Tập {$volume}",
            'slug' => "bo-sach-dai-tap-{$volume}",
            'author' => 'Tác giả kiểm thử',
            'price' => 50000,
            'stock' => $volume === 7 ? 0 : 10,
            'type' => 'physical',
            'status' => 'published',
        ]));

        $response = $this->getJson("/api/books/{$books[12]->id}/series");

        $response->assertOk()
            ->assertJsonCount(17, 'data')
            ->assertJsonPath('data.0.id', $books->first()->id)
            ->assertJsonPath('data.6.stock', 0)
            ->assertJsonPath('data.12.id', $books[12]->id)
            ->assertJsonPath('data.16.id', $books->last()->id);
    }
}
