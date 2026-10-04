<?php

namespace Tests\Concerns;

use App\Models\Category;
use App\Models\Customer;
use App\Models\Location;
use App\Models\MarketDay;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use App\Services\InventoryService;

/** Small, predictable data set for tests. */
trait BuildsNuttyLand
{
    protected Location $warehouse;

    protected Location $market;

    protected MarketDay $marketDay;

    protected ProductVariant $almonds500;

    protected ProductVariant $cashews200;

    protected function buildNuttyLand(): void
    {
        $this->warehouse = Location::create(['name' => 'Warehouse', 'type' => 'warehouse', 'click_collect_enabled' => false]);
        $this->market = Location::create(['name' => 'Bondi Junction Market', 'type' => 'market', 'suburb' => 'Bondi Junction', 'operating_days' => 'Sat', 'default_opens_at' => '08:00', 'default_closes_at' => '14:00']);
        $this->marketDay = MarketDay::create(['location_id' => $this->market->id, 'date' => today()->addDays(3)->toDateString(), 'opens_at' => '08:00', 'closes_at' => '14:00']);

        $nuts = Category::create(['name' => 'Nuts', 'slug' => 'nuts']);
        $almonds = Product::create(['category_id' => $nuts->id, 'name' => 'Almonds', 'slug' => 'almonds', 'roast_style' => 'raw', 'status' => 'active', 'is_featured' => true]);
        $cashews = Product::create(['category_id' => $nuts->id, 'name' => 'Chilli Cashews', 'slug' => 'chilli-cashews', 'flavour' => 'Chilli', 'roast_style' => 'dry_roasted', 'is_organic' => true, 'status' => 'active']);
        Product::create(['category_id' => $nuts->id, 'name' => 'Hidden Draft Nut', 'slug' => 'hidden', 'status' => 'draft']);

        $this->almonds500 = ProductVariant::create(['product_id' => $almonds->id, 'sku' => 'ALM-500', 'weight_grams' => 500, 'price_cents' => 1200, 'low_stock_threshold' => 10]);
        ProductVariant::create(['product_id' => $almonds->id, 'sku' => 'ALM-1000', 'weight_grams' => 1000, 'price_cents' => 2200, 'low_stock_threshold' => 5]);
        $this->cashews200 = ProductVariant::create(['product_id' => $cashews->id, 'sku' => 'CSH-200', 'weight_grams' => 200, 'price_cents' => 700, 'low_stock_threshold' => 10]);

        $stock = app(InventoryService::class);
        $stock->move($this->almonds500, $this->warehouse, 50, 'receipt');
        $stock->move($this->cashews200, $this->warehouse, 30, 'receipt');
        $stock->move($this->almonds500, $this->market, 20, 'receipt');
        $stock->move($this->cashews200, $this->market, 12, 'receipt');
    }

    protected function user(string $role): User
    {
        $user = User::factory()->create(['role' => $role]);
        if ($role === User::ROLE_CUSTOMER) {
            Customer::create(['user_id' => $user->id, 'first_name' => 'Test', 'last_name' => 'Customer', 'email' => $user->email, 'phone' => '0400000000']);
        }

        return $user;
    }
}
