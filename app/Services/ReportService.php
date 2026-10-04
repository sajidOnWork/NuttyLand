<?php

namespace App\Services;

use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Management reporting (FR-07, report §7.4): daily sales summary, product sales,
 * market comparison, category sales, order status and low stock.
 *
 * "Market" revenue = sales recorded by staff at the stall.
 * "Online" revenue  = paid Click & Collect orders (by confirmation date, against the collection market).
 */
class ReportService
{
    public CarbonImmutable $from;

    public CarbonImmutable $to;

    public function __construct(
        CarbonImmutable|string|null $from = null,
        CarbonImmutable|string|null $to = null,
        public ?int $locationId = null,
        public ?int $categoryId = null,
    ) {
        $this->to = ($to ? CarbonImmutable::parse($to) : CarbonImmutable::today())->endOfDay();
        $this->from = ($from ? CarbonImmutable::parse($from) : $this->to->subDays(27))->startOfDay();
    }

    public static function fromArray(array $filters): self
    {
        return new self(
            $filters['from'] ?? null,
            $filters['to'] ?? null,
            ! empty($filters['location_id']) ? (int) $filters['location_id'] : null,
            ! empty($filters['category_id']) ? (int) $filters['category_id'] : null,
        );
    }

    /** Line items sold at market stalls. */
    private function marketLines(): Builder
    {
        return DB::table('sale_items')
            ->join('sales', 'sales.id', '=', 'sale_items.sale_id')
            ->join('product_variants', 'product_variants.id', '=', 'sale_items.product_variant_id')
            ->join('products', 'products.id', '=', 'product_variants.product_id')
            ->whereBetween('sales.sold_at', [$this->from, $this->to])
            ->when($this->locationId, fn ($q) => $q->where('sales.location_id', $this->locationId))
            ->when($this->categoryId, fn ($q) => $q->where('products.category_id', $this->categoryId));
    }

    /** Line items from paid, not-cancelled online orders. */
    private function onlineLines(): Builder
    {
        return DB::table('order_items')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->join('product_variants', 'product_variants.id', '=', 'order_items.product_variant_id')
            ->join('products', 'products.id', '=', 'product_variants.product_id')
            ->where('orders.payment_status', 'paid')
            ->where('orders.status', '!=', 'cancelled')
            ->whereBetween('orders.confirmed_at', [$this->from, $this->to])
            ->when($this->locationId, fn ($q) => $q->where('orders.location_id', $this->locationId))
            ->when($this->categoryId, fn ($q) => $q->where('products.category_id', $this->categoryId));
    }

    public function summary(): array
    {
        $market = $this->marketLines()->selectRaw('coalesce(sum(sale_items.subtotal_cents),0) as revenue, coalesce(sum(sale_items.quantity),0) as units, count(distinct sales.id) as transactions')->first();
        $online = $this->onlineLines()->selectRaw('coalesce(sum(order_items.subtotal_cents),0) as revenue, coalesce(sum(order_items.quantity),0) as units, count(distinct orders.id) as transactions')->first();

        return [
            'market_revenue_cents' => (int) $market->revenue,
            'online_revenue_cents' => (int) $online->revenue,
            'total_revenue_cents' => (int) $market->revenue + (int) $online->revenue,
            'units' => (int) $market->units + (int) $online->units,
            'market_transactions' => (int) $market->transactions,
            'online_orders' => (int) $online->transactions,
        ];
    }

    /** Units and revenue per product variant, highest revenue first. */
    public function byProduct(?int $limit = null): Collection
    {
        $select = 'products.name as product, categories.name as category, product_variants.weight_grams, product_variants.sku, product_variants.id as variant_id';

        $market = $this->marketLines()->join('categories', 'categories.id', '=', 'products.category_id')
            ->selectRaw("{$select}, sum(sale_items.quantity) as units, sum(sale_items.subtotal_cents) as revenue")
            ->groupBy('product_variants.id', 'products.name', 'categories.name', 'product_variants.weight_grams', 'product_variants.sku')
            ->get()->keyBy('variant_id');

        $online = $this->onlineLines()->join('categories', 'categories.id', '=', 'products.category_id')
            ->selectRaw("{$select}, sum(order_items.quantity) as units, sum(order_items.subtotal_cents) as revenue")
            ->groupBy('product_variants.id', 'products.name', 'categories.name', 'product_variants.weight_grams', 'product_variants.sku')
            ->get()->keyBy('variant_id');

        $rows = $market->keys()->merge($online->keys())->unique()->map(function ($id) use ($market, $online) {
            $base = $market[$id] ?? $online[$id];

            return [
                'product' => $base->product,
                'category' => $base->category,
                'size' => \App\Support\Money::weight((int) $base->weight_grams),
                'sku' => $base->sku,
                'market_units' => (int) ($market[$id]->units ?? 0),
                'online_units' => (int) ($online[$id]->units ?? 0),
                'units' => (int) ($market[$id]->units ?? 0) + (int) ($online[$id]->units ?? 0),
                'revenue_cents' => (int) ($market[$id]->revenue ?? 0) + (int) ($online[$id]->revenue ?? 0),
            ];
        })->sortByDesc('revenue_cents')->values();

        return $limit ? $rows->take($limit) : $rows;
    }

    /** Market comparison: stall sales and Click & Collect revenue per market. */
    public function byMarket(): Collection
    {
        $market = $this->marketLines()->selectRaw('sales.location_id, sum(sale_items.subtotal_cents) as revenue, sum(sale_items.quantity) as units, count(distinct sales.id) as transactions')
            ->groupBy('sales.location_id')->get()->keyBy('location_id');
        $online = $this->onlineLines()->selectRaw('orders.location_id, sum(order_items.subtotal_cents) as revenue, count(distinct orders.id) as orders')
            ->groupBy('orders.location_id')->get()->keyBy('location_id');

        return DB::table('locations')->where('type', 'market')->orderBy('name')->get()
            ->map(fn ($l) => [
                'market' => $l->name,
                'market_revenue_cents' => (int) ($market[$l->id]->revenue ?? 0),
                'transactions' => (int) ($market[$l->id]->transactions ?? 0),
                'units' => (int) ($market[$l->id]->units ?? 0),
                'online_revenue_cents' => (int) ($online[$l->id]->revenue ?? 0),
                'online_orders' => (int) ($online[$l->id]->orders ?? 0),
                'total_revenue_cents' => (int) ($market[$l->id]->revenue ?? 0) + (int) ($online[$l->id]->revenue ?? 0),
            ])
            ->filter(fn ($row) => $row['total_revenue_cents'] > 0 || ! $this->locationId)
            ->sortByDesc('total_revenue_cents')->values();
    }

    public function byCategory(): Collection
    {
        return $this->byProduct()->groupBy('category')
            ->map(fn ($rows, $category) => ['category' => $category, 'units' => $rows->sum('units'), 'revenue_cents' => $rows->sum('revenue_cents')])
            ->sortByDesc('revenue_cents')->values();
    }

    /** Daily sales summary. */
    public function daily(): Collection
    {
        $market = $this->marketLines()->selectRaw('date(sales.sold_at) as day, sum(sale_items.subtotal_cents) as revenue')->groupByRaw('date(sales.sold_at)')->pluck('revenue', 'day');
        $online = $this->onlineLines()->selectRaw('date(orders.confirmed_at) as day, sum(order_items.subtotal_cents) as revenue')->groupByRaw('date(orders.confirmed_at)')->pluck('revenue', 'day');

        $rows = collect();
        for ($d = $this->from; $d->lte($this->to); $d = $d->addDay()) {
            $key = $d->toDateString();
            $rows->push([
                'date' => $key,
                'market_revenue_cents' => (int) ($market[$key] ?? 0),
                'online_revenue_cents' => (int) ($online[$key] ?? 0),
                'total_revenue_cents' => (int) ($market[$key] ?? 0) + (int) ($online[$key] ?? 0),
            ]);
        }

        return $rows;
    }

    /** Orders placed in the period, by status (order-status report). */
    public function orderStatus(): Collection
    {
        return DB::table('orders')
            ->whereBetween('created_at', [$this->from, $this->to])
            ->when($this->locationId, fn ($q) => $q->where('location_id', $this->locationId))
            ->selectRaw('status, count(*) as orders, sum(total_cents) as value_cents')
            ->groupBy('status')->get();
    }

    /** Current low-stock items across active locations (not date-filtered). */
    public function lowStock(): Collection
    {
        return DB::table('inventories')
            ->join('product_variants', 'product_variants.id', '=', 'inventories.product_variant_id')
            ->join('products', 'products.id', '=', 'product_variants.product_id')
            ->join('locations', 'locations.id', '=', 'inventories.location_id')
            ->where('locations.is_active', true)
            ->where('product_variants.is_active', true)
            ->where('products.status', 'active')
            ->whereColumn('inventories.quantity', '<=', 'product_variants.low_stock_threshold')
            ->when($this->locationId, fn ($q) => $q->where('inventories.location_id', $this->locationId))
            ->when($this->categoryId, fn ($q) => $q->where('products.category_id', $this->categoryId))
            ->orderBy('inventories.quantity')
            ->get(['products.name as product', 'product_variants.weight_grams', 'product_variants.sku', 'locations.name as location', 'inventories.quantity', 'product_variants.low_stock_threshold as threshold']);
    }
}
