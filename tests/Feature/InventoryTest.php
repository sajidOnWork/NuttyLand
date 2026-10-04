<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Product;
use App\Models\StockMovement;
use App\Services\InsufficientStockException;
use App\Services\InventoryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsNuttyLand;
use Tests\TestCase;

class InventoryTest extends TestCase
{
    use BuildsNuttyLand, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildNuttyLand();
    }

    public function test_transfer_moves_stock_between_locations_with_paired_ledger_entries(): void
    {
        $group = app(InventoryService::class)->transfer($this->almonds500, $this->warehouse, $this->market, 15);

        $this->assertSame(35, $this->almonds500->stockAt($this->warehouse));
        $this->assertSame(35, $this->almonds500->stockAt($this->market));
        $this->assertEqualsCanonicalizing([-15, 15], StockMovement::where('transfer_group', $group)->pluck('quantity_change')->all());
    }

    public function test_transfer_cannot_take_more_than_is_available(): void
    {
        try {
            app(InventoryService::class)->transfer($this->almonds500, $this->warehouse, $this->market, 51);
            $this->fail('Expected InsufficientStockException');
        } catch (InsufficientStockException) {
            $this->assertSame(50, $this->almonds500->stockAt($this->warehouse), 'nothing moved');
            $this->assertSame(20, $this->almonds500->stockAt($this->market));
        }
    }

    public function test_stocktake_sets_quantity_and_records_difference(): void
    {
        $movement = app(InventoryService::class)->stocktake($this->almonds500, $this->warehouse, 46);

        $this->assertSame(46, $this->almonds500->stockAt($this->warehouse));
        $this->assertSame(-4, $movement->quantity_change);
        $this->assertNull(app(InventoryService::class)->stocktake($this->almonds500, $this->warehouse, 46), 'no change, no entry');
    }

    public function test_inventory_equals_sum_of_ledger(): void
    {
        $svc = app(InventoryService::class);
        $svc->move($this->almonds500, $this->market, -3, 'market_sale', allowNegative: true);
        $svc->move($this->almonds500, $this->market, -2, 'damaged');
        $svc->transfer($this->almonds500, $this->market, $this->warehouse, 5);

        $ledger = (int) StockMovement::where('product_variant_id', $this->almonds500->id)->where('location_id', $this->market->id)->sum('quantity_change');
        $this->assertSame($ledger, $this->almonds500->stockAt($this->market));
        $this->assertSame(10, $ledger);
    }

    public function test_staff_stock_screen_records_damaged_stock(): void
    {
        $this->actingAs($this->user('staff'))->post(route('staff.stock.adjust'), [
            'product_variant_id' => $this->cashews200->id, 'location_id' => $this->market->id, 'type' => 'damaged', 'quantity' => 2, 'reason' => 'Torn bags',
        ])->assertSessionHasNoErrors();

        $this->assertSame(10, $this->cashews200->stockAt($this->market));
        $this->assertSame('Torn bags', StockMovement::latest('id')->first()->reason);
    }

    public function test_product_changes_are_audited_and_allergen_edits_clear_approval(): void
    {
        $owner = $this->user('owner');
        $this->actingAs($owner);
        $product = Product::where('slug', 'almonds')->first();
        $product->update(['allergen_approved_at' => now(), 'allergen_approved_by' => $owner->id]);

        $product->update(['allergen_info' => 'Contains tree nuts.']);

        $this->assertNull($product->fresh()->allergen_approved_at);
        $log = AuditLog::where('auditable_type', Product::class)->where('event', 'updated')->latest('id')->first();
        $this->assertSame($owner->id, $log->user_id);
        $this->assertArrayHasKey('allergen_info', $log->new_values);
    }
}
