<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\Category;
use App\Models\CheckoutSessionOrder;
use App\Models\CommerceFeeSchedule;
use App\Models\CommerceFeeScheduleEvent;
use App\Models\User;
use App\Models\Vendor;
use App\Services\CheckoutService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use LogicException;
use Tests\TestCase;

class Phase4CommerceFeeHistoryTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
        $this->admin = User::factory()->create(['role' => 'admin']);
    }

    public function test_admin_only_validation_preview_and_effective_history(): void
    {
        $payload = ['commission_rate' => 12.5, 'service_fee_rate' => 2, 'effective_at' => now()->subHour()->toISOString(), 'reason' => 'Điều chỉnh chính sách', 'operation_key' => 'fee-v1'];
        $this->actingAs(User::factory()->create())->postJson('/api/admin/fee-schedules', $payload)->assertForbidden();
        $this->actingAs($this->admin)->postJson('/api/admin/fee-schedules', [...$payload, 'commission_rate' => 101])->assertUnprocessable()->assertJsonValidationErrors('commission_rate');
        $this->actingAs($this->admin)->postJson('/api/admin/fee-schedules', [...$payload, 'service_fee_rate' => -1])->assertUnprocessable()->assertJsonValidationErrors('service_fee_rate');

        $this->actingAs($this->admin)->postJson('/api/admin/fee-schedules', $payload)->assertCreated();
        $this->actingAs($this->admin)->postJson('/api/admin/fee-schedules', [
            ...$payload, 'commission_rate' => 20, 'effective_at' => now()->addDay()->toISOString(), 'operation_key' => 'fee-v2',
        ])->assertCreated();
        $this->actingAs($this->admin)->getJson('/api/admin/fee-schedules')
            ->assertOk()->assertJsonPath('data.effective.commission_rate', 12.5)->assertJsonCount(2, 'data.history.data');

        $before = CommerceFeeSchedule::count();
        $this->actingAs($this->admin)->postJson('/api/admin/fee-schedules/preview', [
            'base_amount' => 100000, 'commission_rate' => 15, 'service_fee_rate' => 3,
        ])->assertOk()->assertJsonPath('data.commission_amount', 15000)->assertJsonPath('data.service_fee_amount', 3000)->assertJsonPath('data.total_amount', 103000);
        $this->assertSame($before, CommerceFeeSchedule::count());
    }

    public function test_schedule_and_audit_are_immutable_and_conflicting_operation_rolls_back(): void
    {
        $payload = ['commission_rate' => 10, 'service_fee_rate' => 1, 'effective_at' => now()->subMinute()->toISOString(), 'reason' => 'Khởi tạo', 'operation_key' => 'immutable-fee'];
        $this->actingAs($this->admin)->postJson('/api/admin/fee-schedules', $payload)->assertCreated();
        $schedule = CommerceFeeSchedule::firstOrFail();
        $event = CommerceFeeScheduleEvent::firstOrFail();

        try {
            $schedule->update(['commission_rate' => 99]);
            $this->fail('Expected immutable fee schedule failure.');
        } catch (LogicException) {
            $this->assertSame('10.00', $schedule->fresh()->commission_rate);
        }
        try {
            $event->delete();
            $this->fail('Expected append-only event failure.');
        } catch (LogicException) {
            $this->assertDatabaseHas('commerce_fee_schedule_events', ['id' => $event->id]);
        }

        $this->actingAs($this->admin)->postJson('/api/admin/fee-schedules', [
            ...$payload, 'commission_rate' => 11, 'effective_at' => now()->addHour()->toISOString(),
        ])->assertUnprocessable()->assertJsonValidationErrors('operation_key');
        $this->assertSame(1, CommerceFeeSchedule::count());
        $this->assertSame(1, CommerceFeeScheduleEvent::count());
    }

    public function test_checkout_snapshots_effective_rates_and_later_change_does_not_rewrite_history(): void
    {
        $this->actingAs($this->admin)->postJson('/api/admin/fee-schedules', [
            'commission_rate' => 10, 'service_fee_rate' => 2, 'effective_at' => now()->subHour()->toISOString(), 'reason' => 'V1', 'operation_key' => 'checkout-fee-v1',
        ])->assertCreated();
        $book = $this->sellableBook();
        $first = User::factory()->create();
        app(CheckoutService::class)->processCheckout([['book_id' => $book->id, 'quantity' => 1]], ['shipping_address' => 'Hà Nội', 'phone' => '0900000000'], $first->id);
        $snapshot = CheckoutSessionOrder::firstOrFail();
        $this->assertSame('10.00', $snapshot->commission_rate);
        $this->assertSame('2.00', $snapshot->service_fee_rate);
        $this->assertSame(10000, $snapshot->commission_amount);
        $this->assertSame(2000, $snapshot->fee_amount);
        $this->assertSame(102000, $snapshot->total_amount);
        $this->assertNotNull($snapshot->commerce_fee_schedule_id);

        $this->actingAs($this->admin)->postJson('/api/admin/fee-schedules', [
            'commission_rate' => 15, 'service_fee_rate' => 3, 'effective_at' => now()->addSecond()->toISOString(), 'reason' => 'V2', 'operation_key' => 'checkout-fee-v2',
        ])->assertCreated();
        $this->travel(2)->seconds();
        $second = User::factory()->create();
        app(CheckoutService::class)->processCheckout([['book_id' => $book->id, 'quantity' => 1]], ['shipping_address' => 'Đà Nẵng', 'phone' => '0911111111'], $second->id);
        $newSnapshot = CheckoutSessionOrder::latest('id')->firstOrFail();
        $this->assertSame('15.00', $newSnapshot->commission_rate);
        $this->assertSame('3.00', $newSnapshot->service_fee_rate);
        $this->assertSame(15000, $newSnapshot->commission_amount);
        $this->assertSame(3000, $newSnapshot->fee_amount);
        $this->assertSame('10.00', $snapshot->fresh()->commission_rate);
        $this->assertSame(10000, $snapshot->fresh()->commission_amount);
    }

    private function sellableBook(): Book
    {
        $vendorUser = User::factory()->create(['role' => 'vendor']);
        $vendor = Vendor::withoutGlobalScopes()->create(['user_id' => $vendorUser->id, 'shop_name' => 'Fee Shop', 'slug' => 'fee-shop', 'status' => 'active', 'onboarding_status' => 'approved']);
        $category = Category::create(['name' => 'Fee Books', 'slug' => 'fee-books']);

        return Book::withoutGlobalScopes()->create([
            'vendor_id' => $vendor->id, 'category_id' => $category->id, 'title' => 'Fee Snapshot Book', 'slug' => 'fee-snapshot-book',
            'author' => 'Author', 'description' => 'Description', 'cover_image' => 'cover.jpg', 'type' => 'ebook', 'file_path' => 'book.pdf',
            'price' => 100000, 'stock' => 0, 'status' => 'published', 'publishing_status' => 'published',
        ]);
    }
}
