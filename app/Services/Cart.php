<?php

namespace App\Services;

use App\Models\ProductVariant;
use Illuminate\Contracts\Session\Session;
use Illuminate\Support\Collection;

/** Session-based shopping cart: [product_variant_id => quantity]. */
class Cart
{
    private const KEY = 'cart';

    public const MAX_QTY_PER_LINE = 50;

    public function __construct(private Session $session)
    {
    }

    public function items(): array
    {
        return $this->session->get(self::KEY, []);
    }

    public function add(int $variantId, int $quantity = 1): void
    {
        $items = $this->items();
        $items[$variantId] = min(self::MAX_QTY_PER_LINE, ($items[$variantId] ?? 0) + max(1, $quantity));
        $this->session->put(self::KEY, $items);
    }

    public function update(int $variantId, int $quantity): void
    {
        $items = $this->items();
        if ($quantity <= 0) {
            unset($items[$variantId]);
        } else {
            $items[$variantId] = min(self::MAX_QTY_PER_LINE, $quantity);
        }
        $this->session->put(self::KEY, $items);
    }

    public function remove(int $variantId): void
    {
        $this->update($variantId, 0);
    }

    public function clear(): void
    {
        $this->session->forget(self::KEY);
    }

    public function count(): int
    {
        return array_sum($this->items());
    }

    public function isEmpty(): bool
    {
        return $this->items() === [];
    }

    /**
     * Cart lines with current prices. Variants that are no longer sold are dropped.
     *
     * @return Collection<int, array{variant: ProductVariant, quantity: int, subtotal_cents: int}>
     */
    public function lines(): Collection
    {
        $items = $this->items();
        if ($items === []) {
            return collect();
        }

        $variants = ProductVariant::with('product')
            ->whereIn('id', array_keys($items))
            ->where('is_active', true)
            ->whereHas('product', fn ($q) => $q->where('status', 'active'))
            ->get()
            ->keyBy('id');

        return collect($items)
            ->filter(fn ($qty, $id) => $variants->has($id))
            ->map(fn ($qty, $id) => [
                'variant' => $variants[$id],
                'quantity' => (int) $qty,
                'subtotal_cents' => $variants[$id]->price_cents * (int) $qty,
            ])
            ->values();
    }

    public function subtotalCents(): int
    {
        return (int) $this->lines()->sum('subtotal_cents');
    }
}
