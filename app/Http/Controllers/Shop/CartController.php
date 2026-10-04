<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Models\ProductVariant;
use App\Services\Cart;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Shopping cart (FR-02). */
class CartController extends Controller
{
    public function __construct(private Cart $cart)
    {
    }

    public function show(): View
    {
        return view('shop.cart', ['lines' => $this->cart->lines(), 'subtotal' => $this->cart->subtotalCents()]);
    }

    public function add(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'product_variant_id' => ['required', 'integer', 'exists:product_variants,id'],
            'quantity' => ['nullable', 'integer', 'min:1', 'max:'.Cart::MAX_QTY_PER_LINE],
        ]);

        $variant = ProductVariant::with('product')->findOrFail($data['product_variant_id']);
        abort_unless($variant->is_active && $variant->product->status === 'active', 404);

        $this->cart->add($variant->id, $data['quantity'] ?? 1);

        return back()->with('status', "Added {$variant->display_name} to your cart.");
    }

    public function update(Request $request, ProductVariant $variant): RedirectResponse
    {
        $data = $request->validate(['quantity' => ['required', 'integer', 'min:0', 'max:'.Cart::MAX_QTY_PER_LINE]]);
        $this->cart->update($variant->id, $data['quantity']);

        return redirect()->route('cart');
    }

    public function remove(ProductVariant $variant): RedirectResponse
    {
        $this->cart->remove($variant->id);

        return redirect()->route('cart')->with('status', 'Item removed.');
    }
}
