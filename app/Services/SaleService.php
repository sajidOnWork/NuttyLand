<?php

namespace App\Services;

use App\Models\Location;
use App\Models\MarketDay;
use App\Models\ProductVariant;
use App\Models\Sale;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Records product-level market sales (FR-04) and reduces stock at that market (FR-05).
 * Each sale carries a client-generated UUID, so a sale saved offline and synced
 * twice is only stored once (TC-05).
 */
class SaleService
{
    public function __construct(private InventoryService $inventory)
    {
    }

    /**
     * @param  array{client_uuid:string, location_id:int, payment_method:string, sold_at?:string,
     *               recorded_offline?:bool, items: array<int, array{product_variant_id:int, quantity:int}>}  $data
     * @return array{0: Sale, 1: bool} the sale, and whether it was newly created
     */
    public function record(array $data, User $user): array
    {
        if ($existing = Sale::where('client_uuid', $data['client_uuid'])->first()) {
            return [$existing, false];
        }

        $location = Location::findOrFail($data['location_id']);
        if ($location->isWarehouse()) {
            throw new InvalidArgumentException('Sales are recorded against a market, not the warehouse.');
        }

        $soldAt = isset($data['sold_at']) ? Carbon::parse($data['sold_at'])->setTimezone(config('app.timezone')) : now();
        if ($soldAt->isFuture()) {
            $soldAt = now();
        }

        $items = collect($data['items'])->groupBy('product_variant_id')
            ->map(fn ($rows) => (int) $rows->sum('quantity'))
            ->filter(fn ($qty) => $qty > 0);

        if ($items->isEmpty()) {
            throw new InvalidArgumentException('A sale needs at least one item.');
        }

        $variants = ProductVariant::whereIn('id', $items->keys())->get()->keyBy('id');
        if ($variants->count() !== $items->count()) {
            throw new InvalidArgumentException('Unknown product in sale.');
        }

        try {
            $sale = DB::transaction(function () use ($data, $user, $location, $soldAt, $items, $variants) {
                $sale = Sale::create([
                    'client_uuid' => $data['client_uuid'],
                    'location_id' => $location->id,
                    'market_day_id' => MarketDay::where('location_id', $location->id)->whereDate('date', $soldAt->toDateString())->value('id'),
                    'user_id' => $user->id,
                    'payment_method' => $data['payment_method'],
                    'sold_at' => $soldAt,
                    'recorded_offline' => (bool) ($data['recorded_offline'] ?? false),
                ]);

                $total = 0;
                foreach ($items as $variantId => $qty) {
                    $price = $variants[$variantId]->price_cents;
                    $sale->items()->create([
                        'product_variant_id' => $variantId,
                        'quantity' => $qty,
                        'unit_price_cents' => $price,
                        'subtotal_cents' => $price * $qty,
                    ]);
                    $total += $price * $qty;

                    $this->inventory->move($variantId, $location, -$qty, 'market_sale', $sale, $user, allowNegative: true);
                }

                $sale->update(['total_cents' => $total]);

                return $sale;
            });
        } catch (UniqueConstraintViolationException) {
            // Same sale arrived twice at the same moment – keep the first copy.
            return [Sale::where('client_uuid', $data['client_uuid'])->firstOrFail(), false];
        }

        return [$sale->load('items'), true];
    }
}
