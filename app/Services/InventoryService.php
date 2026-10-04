<?php

namespace App\Services;

use App\Models\Inventory;
use App\Models\Location;
use App\Models\ProductVariant;
use App\Models\StockMovement;
use App\Models\User;
use App\Notifications\LowStockAlert;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

/**
 * The only place stock levels change (report FR-05).
 * Every change writes a StockMovement ledger row, and crossing the
 * low-stock threshold notifies owners and staff (FR-06).
 */
class InventoryService
{
    /**
     * Add (positive) or remove (negative) stock at a location.
     *
     * @param  bool  $allowNegative  Market sales are always recorded – the sale happened –
     *                               even if the system count was wrong; online orders are not.
     */
    public function move(
        ProductVariant|int $variant,
        Location|int $location,
        int $change,
        string $type,
        ?Model $reference = null,
        ?User $user = null,
        ?string $reason = null,
        ?string $transferGroup = null,
        bool $allowNegative = false,
    ): StockMovement {
        $variantId = $variant instanceof ProductVariant ? $variant->id : $variant;
        $locationId = $location instanceof Location ? $location->id : $location;

        $alert = null;

        $movement = DB::transaction(function () use ($variantId, $locationId, $change, $type, $reference, $user, $reason, $transferGroup, $allowNegative, &$alert) {
            Inventory::firstOrCreate(['product_variant_id' => $variantId, 'location_id' => $locationId], ['quantity' => 0]);

            /** @var Inventory $inventory */
            $inventory = Inventory::where('product_variant_id', $variantId)
                ->where('location_id', $locationId)
                ->lockForUpdate()
                ->first();

            $before = $inventory->quantity;
            $after = $before + $change;

            if ($after < 0 && ! $allowNegative) {
                $variant = ProductVariant::with('product')->find($variantId);
                throw new InsufficientStockException(
                    "Not enough stock of {$variant->display_name}: {$before} available, ".abs($change).' needed.'
                );
            }

            $inventory->quantity = $after;
            $inventory->save();

            $threshold = ProductVariant::whereKey($variantId)->value('low_stock_threshold');
            if ($change < 0 && $before > $threshold && $after <= $threshold) {
                $alert = $inventory;
            }

            return StockMovement::create([
                'product_variant_id' => $variantId,
                'location_id' => $locationId,
                'quantity_change' => $change,
                'quantity_after' => $after,
                'type' => $type,
                'reference_type' => $reference?->getMorphClass(),
                'reference_id' => $reference?->getKey(),
                'transfer_group' => $transferGroup,
                'reason' => $reason,
                'user_id' => $user?->id ?? auth()->id(),
            ]);
        });

        if ($alert) {
            $this->sendLowStockAlert($alert->fresh(['variant.product', 'location']));
        }

        return $movement;
    }

    /** Move stock between locations, e.g. warehouse → market before trading, market → warehouse after. */
    public function transfer(ProductVariant|int $variant, Location|int $from, Location|int $to, int $quantity, ?User $user = null, ?string $reason = null): string
    {
        if ($quantity <= 0) {
            throw new \InvalidArgumentException('Transfer quantity must be positive.');
        }
        $fromId = $from instanceof Location ? $from->id : $from;
        $toId = $to instanceof Location ? $to->id : $to;
        if ($fromId === $toId) {
            throw new \InvalidArgumentException('Choose two different locations.');
        }

        $group = (string) Str::uuid();

        DB::transaction(function () use ($variant, $fromId, $toId, $quantity, $user, $reason, $group) {
            $this->move($variant, $fromId, -$quantity, 'transfer_out', null, $user, $reason, $group);
            $this->move($variant, $toId, $quantity, 'transfer_in', null, $user, $reason, $group);
        });

        return $group;
    }

    /** Set the quantity to a physical count (stocktake), recording the difference. */
    public function stocktake(ProductVariant|int $variant, Location|int $location, int $countedQuantity, ?User $user = null, ?string $reason = null): ?StockMovement
    {
        $variantId = $variant instanceof ProductVariant ? $variant->id : $variant;
        $locationId = $location instanceof Location ? $location->id : $location;

        $current = (int) Inventory::where('product_variant_id', $variantId)->where('location_id', $locationId)->value('quantity');
        $difference = $countedQuantity - $current;

        if ($difference === 0) {
            return null;
        }

        return $this->move($variantId, $locationId, $difference, 'stocktake', null, $user, $reason ?? 'Physical count', allowNegative: true);
    }

    public function sendLowStockAlert(Inventory $inventory): void
    {
        $recipients = User::where('is_active', true)
            ->whereIn('role', [User::ROLE_OWNER, User::ROLE_STAFF])
            ->get();

        Notification::send($recipients, new LowStockAlert($inventory));
    }
}
