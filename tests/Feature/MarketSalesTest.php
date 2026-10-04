<?php

namespace Tests\Feature;

use App\Models\Sale;
use App\Notifications\LowStockAlert;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Tests\Concerns\BuildsNuttyLand;
use Tests\TestCase;

class MarketSalesTest extends TestCase
{
    use BuildsNuttyLand, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildNuttyLand();
    }

    private function salePayload(array $items, ?string $uuid = null, bool $offline = false): array
    {
        return ['sales' => [[
            'client_uuid' => $uuid ?? (string) Str::uuid(),
            'location_id' => $this->market->id,
            'payment_method' => 'eftpos',
            'sold_at' => now()->toIso8601String(),
            'recorded_offline' => $offline,
            'items' => $items,
        ]]];
    }

    /** TC-02: Select market → select product → enter quantity → save. */
    public function test_tc02_product_level_market_sale_is_stored_and_reduces_market_stock(): void
    {
        $staff = $this->user('staff');

        $this->actingAs($staff)->postJson(route('staff.api.sales'), $this->salePayload([
            ['product_variant_id' => $this->almonds500->id, 'quantity' => 3],
            ['product_variant_id' => $this->cashews200->id, 'quantity' => 1],
        ]))->assertOk()->assertJsonPath('results.0.status', 'created');

        $sale = Sale::with('items')->sole();
        $this->assertSame($this->market->id, $sale->location_id);
        $this->assertSame($staff->id, $sale->user_id);
        $this->assertSame(today()->toDateString(), $sale->sold_at->toDateString());
        $this->assertSame(3 * 1200 + 700, $sale->total_cents);
        $this->assertEqualsCanonicalizing(
            [[$this->almonds500->id, 3], [$this->cashews200->id, 1]],
            $sale->items->map(fn ($i) => [$i->product_variant_id, $i->quantity])->all()
        );

        $this->assertSame(17, $this->almonds500->stockAt($this->market));
        $this->assertSame(11, $this->cashews200->stockAt($this->market));
        $this->assertSame(50, $this->almonds500->stockAt($this->warehouse), 'warehouse untouched');
    }

    /** TC-03: Record sales until the threshold is reached → authorised users are alerted. */
    public function test_tc03_low_stock_alert_is_sent_once_when_threshold_is_crossed(): void
    {
        Notification::fake();
        $staff = $this->user('staff');
        $owner = $this->user('owner');
        $customer = $this->user('customer');

        // 20 in stock, threshold 10: selling 9 leaves 11 – no alert yet.
        $this->actingAs($staff)->postJson(route('staff.api.sales'), $this->salePayload([['product_variant_id' => $this->almonds500->id, 'quantity' => 9]]));
        Notification::assertNothingSent();

        // Selling 2 more leaves 9 – crosses the threshold.
        $this->postJson(route('staff.api.sales'), $this->salePayload([['product_variant_id' => $this->almonds500->id, 'quantity' => 2]]));
        Notification::assertSentTo([$owner, $staff], LowStockAlert::class);
        Notification::assertNotSentTo($customer, LowStockAlert::class);

        // Already below – further sales do not repeat the alert.
        $this->postJson(route('staff.api.sales'), $this->salePayload([['product_variant_id' => $this->almonds500->id, 'quantity' => 1]]));
        Notification::assertSentToTimes($owner, LowStockAlert::class, 1);
    }

    /** TC-05: Disconnect → record sale offline → reconnect → sale is kept once. */
    public function test_tc05_offline_sale_synced_twice_is_stored_once(): void
    {
        $staff = $this->user('staff');
        $uuid = (string) Str::uuid();
        $payload = $this->salePayload([['product_variant_id' => $this->almonds500->id, 'quantity' => 2]], $uuid, offline: true);

        $this->actingAs($staff)->postJson(route('staff.api.sales'), $payload)->assertJsonPath('results.0.status', 'created');
        // The device did not get the response (connection dropped) and sends the same sale again.
        $this->postJson(route('staff.api.sales'), $payload)->assertJsonPath('results.0.status', 'duplicate');

        $this->assertSame(1, Sale::count());
        $this->assertTrue(Sale::sole()->recorded_offline);
        $this->assertSame(18, $this->almonds500->stockAt($this->market));
    }

    public function test_sale_is_recorded_even_if_system_stock_was_wrong(): void
    {
        $this->actingAs($this->user('staff'))->postJson(route('staff.api.sales'), $this->salePayload([['product_variant_id' => $this->cashews200->id, 'quantity' => 15]]))
            ->assertJsonPath('results.0.status', 'created');

        $this->assertSame(-3, $this->cashews200->stockAt($this->market), 'negative stock flags a count to reconcile');
    }

    public function test_invalid_sales_are_rejected(): void
    {
        $this->actingAs($this->user('staff'))->postJson(route('staff.api.sales'), ['sales' => [['client_uuid' => 'not-a-uuid', 'location_id' => $this->market->id, 'payment_method' => 'bitcoin', 'items' => []]]])
            ->assertUnprocessable();

        $this->postJson(route('staff.api.sales'), [
            'sales' => [[
                'client_uuid' => (string) Str::uuid(), 'location_id' => $this->warehouse->id, 'payment_method' => 'cash',
                'items' => [['product_variant_id' => $this->almonds500->id, 'quantity' => 1]],
            ]],
        ])->assertOk()->assertJsonPath('results.0.status', 'error');

        $this->assertSame(0, Sale::count());
    }

    public function test_staff_catalogue_endpoint_lists_only_active_products(): void
    {
        $this->actingAs($this->user('staff'))->getJson(route('staff.api.catalogue'))
            ->assertOk()
            ->assertJsonCount(2, 'products')
            ->assertJsonMissing(['name' => 'Hidden Draft Nut'])
            ->assertJsonStructure(['csrf_token', 'markets' => [['id', 'name', 'trading_today']]]);
    }
}
