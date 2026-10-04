<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Customer;
use App\Models\Location;
use App\Models\MarketDay;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use App\Services\InventoryService;
use App\Services\OrderService;
use App\Services\SaleService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * SAMPLE DATA ONLY. Product prices, market days/times and sales history are
 * illustrative – NuttyLand has not yet supplied its product master list,
 * market schedules or transaction data (report §14.1).
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        mt_srand(42);

        DB::transaction(function () {
            $users = $this->users();
            auth()->login($users['owner']); // so the audit log records who seeded the data

            $this->catalogue($users['owner']);
            $locations = $this->locations();
            $this->schedule($locations);
            $this->openingStock($locations, $users['owner']);
            $this->salesHistory($locations, $users['staff']);
            $this->sampleOrder($users['customer']);

            auth()->logout();
        });
    }

    private function users(): array
    {
        $make = fn (string $role, string $name, string $email) => User::create([
            'name' => $name, 'email' => $email, 'password' => 'password', 'role' => $role, 'email_verified_at' => now(),
        ]);

        $customer = $make(User::ROLE_CUSTOMER, 'Sam Customer', 'customer@nuttyland.test');
        Customer::create([
            'user_id' => $customer->id, 'first_name' => 'Sam', 'last_name' => 'Customer',
            'email' => $customer->email, 'phone' => '0400 000 000', 'marketing_opt_in' => true,
        ]);

        return [
            'owner' => $make(User::ROLE_OWNER, 'NuttyLand Owner', 'owner@nuttyland.test'),
            'staff' => $make(User::ROLE_STAFF, 'Market Staff', 'staff@nuttyland.test'),
            'marketing' => $make(User::ROLE_MARKETING, 'Marketing Staff', 'marketing@nuttyland.test'),
            'customer' => $customer,
        ];
    }

    private function catalogue(User $owner): void
    {
        $treeNuts = 'Contains tree nuts. Packed in a facility that also handles peanuts, sesame, milk and gluten.';
        $peanuts = 'Contains peanuts. Packed in a facility that also handles tree nuts, sesame, milk and gluten.';
        $facility = 'Packed in a facility that handles tree nuts, peanuts, sesame, milk and gluten.';

        // [category, name, roast_style, organic, flavour, price per 500g, origin, allergen, featured]
        $catalogue = [
            'Nuts' => [
                ['Almonds', 'raw', false, null, 12.00, 'Australia', $treeNuts, true],
                ['Roasted Salted Almonds', 'roasted_salted', false, 'Sea salt', 13.00, 'Australia', $treeNuts, false],
                ['Organic Almonds', 'raw', true, null, 16.00, 'Australia', $treeNuts, false],
                ['Tamari Almonds', 'dry_roasted', false, 'Tamari', 15.00, 'Australia', $treeNuts.' Contains soy.', false],
                ['Cashews', 'raw', false, null, 14.00, 'Vietnam', $treeNuts, true],
                ['Roasted Salted Cashews', 'roasted_salted', false, 'Sea salt', 15.00, 'Vietnam', $treeNuts, false],
                ['Chilli Lime Cashews', 'dry_roasted', false, 'Chilli & lime', 16.00, 'Vietnam', $treeNuts, false],
                ['Pistachios', 'roasted_salted', false, 'Sea salt', 16.00, 'USA', $treeNuts, true],
                ['Macadamias', 'raw', false, null, 22.00, 'Australia', $treeNuts, false],
                ['Walnut Halves', 'raw', false, null, 12.00, 'Australia', $treeNuts, false],
                ['Brazil Nuts', 'raw', false, null, 13.00, 'Bolivia', $treeNuts, false],
                ['Pecans', 'raw', false, null, 18.00, 'Australia', $treeNuts, false],
                ['Hazelnuts', 'dry_roasted', false, null, 15.00, 'Turkey', $treeNuts, false],
                ['Roasted Salted Peanuts', 'roasted_salted', false, 'Sea salt', 6.00, 'Australia', $peanuts, false],
                ['Honey Roasted Peanuts', 'dry_roasted', false, 'Honey', 7.00, 'Australia', $peanuts, false],
            ],
            'Dried Fruits' => [
                ['Dried Apricots', null, false, null, 11.00, 'Australia', $facility.' Contains sulphites.', false],
                ['Medjool Dates', null, false, null, 12.00, 'Australia', $facility, true],
                ['Sultanas', null, false, null, 6.00, 'Australia', $facility, false],
                ['Dried Mango', null, false, null, 15.00, 'Philippines', $facility.' Contains sulphites.', false],
                ['Dried Cranberries', null, false, null, 9.00, 'Canada', $facility, false],
                ['Dried Figs', null, false, null, 13.00, 'Turkey', $facility, false],
                ['Organic Goji Berries', null, true, null, 18.00, 'China', $facility, false],
            ],
            'Mixes' => [
                ['Mixed Nuts', 'dry_roasted', false, null, 10.00, 'Australia', $treeNuts.' Contains peanuts.', true],
                ['Deluxe Mixed Nuts', 'roasted_salted', false, 'Sea salt', 16.00, 'Australia', $treeNuts, false],
                ['Classic Trail Mix', null, false, null, 10.00, 'Australia', $treeNuts.' Contains peanuts.', false],
                ['Fruit & Nut Mix', null, false, null, 11.00, 'Australia', $treeNuts, false],
            ],
            'Seeds' => [
                ['Pumpkin Seeds', 'raw', false, null, 9.00, 'China', $facility, false],
                ['Sunflower Kernels', 'raw', false, null, 5.00, 'Australia', $facility, false],
                ['Organic Chia Seeds', 'raw', true, null, 8.00, 'Australia', $facility, false],
            ],
            'Snacks & Treats' => [
                ['Yoghurt Coated Almonds', null, false, 'Yoghurt', 14.00, 'Australia', $treeNuts.' Contains milk.', false],
                ['Dark Chocolate Almonds', null, false, 'Dark chocolate', 16.00, 'Australia', $treeNuts.' Contains milk and soy.', false],
                ['Wasabi Peas', null, false, 'Wasabi', 7.00, 'Thailand', $facility.' Contains wheat and soy.', false],
                ['Banana Chips', null, false, null, 7.00, 'Philippines', $facility, false],
            ],
        ];

        $weights = [100 => 0.25, 200 => 0.45, 500 => 1.0, 1000 => 1.85];
        $sort = 0;

        foreach ($catalogue as $categoryName => $products) {
            $category = Category::create([
                'name' => $categoryName, 'slug' => Str::slug($categoryName), 'sort_order' => $sort++, 'is_active' => true,
            ]);

            foreach ($products as [$name, $roast, $organic, $flavour, $price500, $origin, $allergen, $featured]) {
                $product = Product::create([
                    'category_id' => $category->id,
                    'name' => $name,
                    'slug' => Str::slug($name),
                    'description' => "Fresh {$name} from NuttyLand – packed in Sydney and sold at our markets.",
                    'ingredients' => $name.($flavour ? ", {$flavour} seasoning" : '').'.',
                    'allergen_info' => $allergen,
                    'flavour' => $flavour,
                    'roast_style' => $roast,
                    'is_organic' => $organic,
                    'country_of_origin' => $origin,
                    'is_featured' => $featured,
                    'status' => 'active',
                    'allergen_approved_by' => $owner->id,
                    'allergen_approved_at' => now(),
                ]);

                $code = Str::upper(Str::substr(Str::slug($name, ''), 0, 6));
                foreach ($weights as $grams => $factor) {
                    ProductVariant::create([
                        'product_id' => $product->id,
                        'sku' => "NL-{$product->id}-{$code}-{$grams}",
                        'weight_grams' => $grams,
                        // round to the nearest 50c
                        'price_cents' => (int) (round($price500 * $factor * 2) / 2 * 100),
                        'low_stock_threshold' => $grams >= 1000 ? 5 : 10,
                    ]);
                }
            }
        }
    }

    private function locations(): array
    {
        $warehouse = Location::create(['name' => 'NuttyLand Warehouse', 'type' => 'warehouse', 'suburb' => 'Sydney (address TBC)', 'click_collect_enabled' => false]);

        $markets = [
            ['Bondi Junction Market', 'Bondi Junction', 'Sat', '08:00', '14:00', true],
            ['Wollongong Market', 'Wollongong', 'Fri', '09:00', '15:00', true],
            ['Ramsgate Market', 'Ramsgate', 'Sun', '08:00', '13:00', true],
            ['Marrickville Market', 'Marrickville', 'Sun', '08:00', '15:00', true],
            ['Market 5 (to be confirmed)', null, null, '08:00', '14:00', false],
            ['Market 6 (to be confirmed)', null, null, '08:00', '14:00', false],
        ];

        $created = [];
        foreach ($markets as [$name, $suburb, $days, $open, $close, $confirmed]) {
            $created[] = Location::create([
                'name' => $name, 'type' => 'market', 'suburb' => $suburb, 'operating_days' => $days,
                'default_opens_at' => $open, 'default_closes_at' => $close,
                'is_confirmed' => $confirmed, 'click_collect_enabled' => $confirmed, 'is_active' => $confirmed,
            ]);
        }

        return ['warehouse' => $warehouse, 'markets' => $created];
    }

    /** Four weeks of past market days (for sales history) and six weeks ahead (for Click & Collect). */
    private function schedule(array $locations): void
    {
        $dayMap = ['Mon' => 1, 'Tue' => 2, 'Wed' => 3, 'Thu' => 4, 'Fri' => 5, 'Sat' => 6, 'Sun' => 7];
        $start = CarbonImmutable::today()->subDays(28);

        foreach ($locations['markets'] as $market) {
            if (! $market->operating_days) {
                continue;
            }
            $days = array_map(fn ($d) => $dayMap[trim($d)], explode(',', $market->operating_days));
            for ($date = $start; $date->lte(CarbonImmutable::today()->addDays(42)); $date = $date->addDay()) {
                if (in_array($date->dayOfWeekIso, $days, true)) {
                    MarketDay::create([
                        'location_id' => $market->id,
                        'date' => $date->toDateString(),
                        'opens_at' => $market->default_opens_at,
                        'closes_at' => $market->default_closes_at,
                        'status' => $date->isPast() && ! $date->isToday() ? 'completed' : 'scheduled',
                    ]);
                }
            }
        }
    }

    private function openingStock(array $locations, User $owner): void
    {
        $inventory = app(InventoryService::class);
        $variants = ProductVariant::all();

        foreach ($variants as $variant) {
            $qty = $variant->weight_grams >= 1000 ? mt_rand(8, 30) : mt_rand(40, 160);
            $inventory->move($variant, $locations['warehouse'], $qty, 'receipt', null, $owner, 'Opening stock');
        }

        // A few items deliberately low so the low-stock report has something to show.
        foreach ($variants->random(5) as $variant) {
            $inventory->stocktake($variant, $locations['warehouse'], mt_rand(2, 5), $owner, 'Opening stock count');
        }

        // Stock each confirmed market with the popular 200g/500g packs.
        foreach ($locations['markets'] as $market) {
            if (! $market->is_confirmed) {
                continue;
            }
            foreach ($variants->whereIn('weight_grams', [200, 500]) as $variant) {
                $inventory->move($variant, $market, mt_rand(25, 45), 'receipt', null, $owner, 'Opening market stock');
            }
        }
    }

    private function salesHistory(array $locations, User $staff): void
    {
        $sales = app(SaleService::class);
        $variants = ProductVariant::whereIn('weight_grams', [200, 500])->with('product')->get();
        // Popular products sell more often – gives the dashboard realistic-looking variation.
        $weighted = $variants->flatMap(fn ($v) => array_fill(0, $v->product->is_featured ? 4 : 1, $v));

        $pastDays = MarketDay::where('status', 'completed')->with('location')->get();
        foreach ($pastDays as $day) {
            $count = mt_rand(6, 14);
            for ($i = 0; $i < $count; $i++) {
                $items = [];
                foreach (range(1, mt_rand(1, 3)) as $_) {
                    $items[] = ['product_variant_id' => $weighted->random()->id, 'quantity' => mt_rand(1, 2)];
                }
                $sales->record([
                    'client_uuid' => (string) Str::uuid(),
                    'location_id' => $day->location_id,
                    'payment_method' => mt_rand(0, 3) ? 'eftpos' : 'cash',
                    'sold_at' => $day->date->copy()->setTimeFromTimeString($day->opens_at)->addMinutes(mt_rand(0, 300))->toIso8601String(),
                    'items' => $items,
                ], $staff);
            }
        }
    }

    private function sampleOrder(User $customerUser): void
    {
        $day = MarketDay::collectable()->orderBy('date')->first();
        if (! $day) {
            return;
        }
        $orders = app(OrderService::class);
        $variants = ProductVariant::where('weight_grams', 500)->whereHas('product', fn ($q) => $q->where('is_featured', true))->take(2)->get();
        $order = $orders->placeOrder($customerUser->customer, $variants->mapWithKeys(fn ($v) => [$v->id => 1])->all(), $day);
        $orders->confirmPayment($order, 'SIM-SEED-0001');
    }
}
