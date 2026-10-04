<?php

namespace Tests\Feature;

use App\Services\Cart;
use App\Services\OrderService;
use App\Services\ReportService;
use App\Services\SaleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Concerns\BuildsNuttyLand;
use Tests\TestCase;

class CatalogueAndReportsTest extends TestCase
{
    use BuildsNuttyLand, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildNuttyLand();
    }

    public function test_catalogue_search_and_filters(): void
    {
        $this->get('/shop?q=cashew')->assertOk()->assertSee('Chilli Cashews')->assertDontSee('>Almonds<', false);
        $this->get('/shop?q=chilli')->assertSee('Chilli Cashews');
        $this->get('/shop?organic=1')->assertSee('Chilli Cashews')->assertDontSee('Almonds');
        $this->get('/shop?roast=raw')->assertSee('Almonds')->assertDontSee('Chilli Cashews');
        $this->get('/shop')->assertDontSee('Hidden Draft Nut');
        $this->get('/products/hidden')->assertNotFound();
    }

    public function test_cart_totals_handle_variations_and_quantities(): void
    {
        $cart = app(Cart::class);
        $cart->add($this->almonds500->id, 2);
        $cart->add($this->almonds500->id);
        $cart->add($this->cashews200->id, 4);

        $this->assertSame(7, $cart->count());
        $this->assertSame(3 * 1200 + 4 * 700, $cart->subtotalCents());

        $cart->update($this->cashews200->id, 0);
        $this->assertSame(3600, $cart->subtotalCents());

        $cart->add($this->almonds500->id, 999);
        $this->assertSame(Cart::MAX_QTY_PER_LINE, $cart->items()[$this->almonds500->id]);
    }

    public function test_reports_use_the_same_records_as_sales_and_orders(): void
    {
        $staff = $this->user('staff');
        app(SaleService::class)->record([
            'client_uuid' => (string) Str::uuid(), 'location_id' => $this->market->id, 'payment_method' => 'cash',
            'items' => [['product_variant_id' => $this->almonds500->id, 'quantity' => 2], ['product_variant_id' => $this->cashews200->id, 'quantity' => 1]],
        ], $staff);

        $orders = app(OrderService::class);
        $orders->confirmPayment($orders->placeOrder($this->user('customer')->customer, [$this->almonds500->id => 1], $this->marketDay), 'SIM-R');
        $orders->placeOrder($this->user('customer')->customer, [$this->almonds500->id => 5], $this->marketDay); // unpaid – excluded

        $report = new ReportService(today(), today());
        $summary = $report->summary();

        $this->assertSame(2 * 1200 + 700, $summary['market_revenue_cents']);
        $this->assertSame(1200, $summary['online_revenue_cents']);
        $this->assertSame(4, $summary['units']);

        $almonds = $report->byProduct()->firstWhere('sku', 'ALM-500');
        $this->assertSame(2, $almonds['market_units']);
        $this->assertSame(1, $almonds['online_units']);

        $bondi = $report->byMarket()->firstWhere('market', 'Bondi Junction Market');
        $this->assertSame($summary['total_revenue_cents'], $bondi['total_revenue_cents']);
        $this->assertSame($summary['total_revenue_cents'], $report->daily()->sum('total_revenue_cents'));
    }

    public function test_market_schedule_generator_creates_days_once(): void
    {
        $generate = fn () => \App\Filament\Resources\LocationResource\RelationManagers\MarketDaysRelationManager::generate($this->market, 4);

        $created = $generate();
        $this->assertGreaterThanOrEqual(4, $created);
        $this->assertSame(0, $generate(), 'running again adds nothing');
        $this->assertTrue($this->market->marketDays()->whereKeyNot($this->marketDay->id)->get()->every(fn ($d) => $d->date->isSaturday()));
    }
}
