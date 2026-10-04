<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Location;
use App\Models\MarketDay;
use App\Models\Order;
use App\Models\ProductVariant;
use App\Models\User;
use App\Notifications\OrderConfirmed;
use App\Notifications\OrderReadyForCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Click & Collect order workflow (report Figure 4 activity diagram):
 * cart → choose market → order stored (awaiting payment) → payment confirmed → stock reserved
 * → preparing → ready → collected.
 *
 * Online orders are packed from the warehouse, so confirmed orders reduce warehouse stock.
 */
class OrderService
{
    /** Status changes staff/owner may make. */
    public const TRANSITIONS = [
        'confirmed' => ['preparing', 'ready', 'cancelled'],
        'preparing' => ['ready', 'cancelled'],
        'ready' => ['collected', 'cancelled'],
    ];

    public function __construct(private InventoryService $inventory)
    {
    }

    /**
     * @param  array<int,int>  $lines  [product_variant_id => quantity]
     */
    public function placeOrder(Customer $customer, array $lines, MarketDay $marketDay, ?string $notes = null): Order
    {
        if ($lines === []) {
            throw new InvalidArgumentException('Your cart is empty.');
        }
        if (! MarketDay::collectable()->whereKey($marketDay->id)->exists()) {
            throw new InvalidArgumentException('That market day is no longer available for Click & Collect.');
        }

        $variants = ProductVariant::with('product')->whereIn('id', array_keys($lines))->get()->keyBy('id');
        $warehouse = Location::warehouse();

        foreach ($lines as $variantId => $qty) {
            $variant = $variants[$variantId] ?? null;
            if (! $variant || ! $variant->is_active || $variant->product->status !== 'active') {
                throw new InvalidArgumentException('A product in your cart is no longer available.');
            }
            if ($qty < 1) {
                throw new InvalidArgumentException('Quantities must be at least 1.');
            }
            $available = $variant->stockAt($warehouse);
            if ($qty > $available) {
                throw new InsufficientStockException("Only {$available} × {$variant->display_name} available.");
            }
        }

        return DB::transaction(function () use ($customer, $lines, $variants, $marketDay, $notes) {
            $order = Order::create([
                'order_number' => $this->newOrderNumber(),
                'customer_id' => $customer->id,
                'fulfilment_method' => 'click_collect',
                'location_id' => $marketDay->location_id,
                'market_day_id' => $marketDay->id,
                'status' => 'pending_payment',
                'payment_status' => 'unpaid',
                'notes' => $notes,
            ]);

            $subtotal = 0;
            foreach ($lines as $variantId => $qty) {
                $variant = $variants[$variantId];
                $lineTotal = $variant->price_cents * $qty;
                $subtotal += $lineTotal;
                $order->items()->create([
                    'product_variant_id' => $variant->id,
                    'product_name' => $variant->product->name,
                    'weight_grams' => $variant->weight_grams,
                    'quantity' => $qty,
                    'unit_price_cents' => $variant->price_cents,
                    'subtotal_cents' => $lineTotal,
                ]);
            }

            $order->update(['subtotal_cents' => $subtotal, 'total_cents' => $subtotal]);

            return $order;
        });
    }

    /**
     * Payment succeeded. Safe to call more than once with the same order (e.g. a repeated
     * gateway callback): only the first call reserves stock.
     *
     * @throws InsufficientStockException when stock ran out between checkout and payment.
     */
    public function confirmPayment(Order $order, string $paymentReference): Order
    {
        $justConfirmed = false;

        try {
            DB::transaction(function () use ($order, $paymentReference, &$justConfirmed) {
                $locked = Order::whereKey($order->id)->lockForUpdate()->first();

                if ($locked->payment_status === 'paid') {
                    return; // already processed – do not reserve stock twice
                }
                if ($locked->status !== 'pending_payment') {
                    throw new InvalidArgumentException('This order can no longer be paid.');
                }

                $warehouse = Location::warehouse();
                foreach ($locked->items as $item) {
                    $this->inventory->move($item->product_variant_id, $warehouse, -$item->quantity, 'online_order', $locked);
                }

                $locked->update([
                    'status' => 'confirmed',
                    'payment_status' => 'paid',
                    'payment_reference' => $paymentReference,
                    'confirmed_at' => now(),
                ]);
                $justConfirmed = true;
            });
        } catch (InsufficientStockException $e) {
            $order->update(['payment_status' => 'failed', 'notes' => trim($order->notes."\nStock unavailable at payment: ".$e->getMessage())]);
            throw $e;
        }

        $order->refresh();

        if ($justConfirmed) {
            Notification::route('mail', $order->customer->email)->notify(new OrderConfirmed($order));
        }

        return $order;
    }

    /** Payment declined – the order stays open so the customer can retry. */
    public function failPayment(Order $order): Order
    {
        if ($order->status === 'pending_payment') {
            $order->update(['payment_status' => 'failed']);
        }

        return $order;
    }

    public function updateStatus(Order $order, string $status, ?User $user = null): Order
    {
        $allowed = self::TRANSITIONS[$order->status] ?? [];
        if (! in_array($status, $allowed, true)) {
            throw new InvalidArgumentException("Cannot change an order from {$order->status_label} to ".(Order::STATUSES[$status] ?? $status).'.');
        }

        DB::transaction(function () use ($order, $status, $user) {
            if ($status === 'cancelled' && $order->payment_status === 'paid') {
                $warehouse = Location::warehouse();
                foreach ($order->items as $item) {
                    $this->inventory->move($item->product_variant_id, $warehouse, $item->quantity, 'order_cancelled', $order, $user);
                }
                $order->payment_status = 'refunded';
            }

            $order->status = $status;
            if ($status === 'collected') {
                $order->collected_at = now();
            }
            $order->save();
        });

        if ($status === 'ready') {
            Notification::route('mail', $order->customer->email)->notify(new OrderReadyForCollection($order));
        }

        return $order->refresh();
    }

    private function newOrderNumber(): string
    {
        do {
            $number = 'NL-'.now()->format('ymd').'-'.Str::upper(Str::random(4));
        } while (Order::where('order_number', $number)->exists());

        return $number;
    }
}
