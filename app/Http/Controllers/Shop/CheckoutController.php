<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\MarketDay;
use App\Models\Order;
use App\Services\Cart;
use App\Services\InsufficientStockException;
use App\Services\OrderService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;
use InvalidArgumentException;

/** Click & Collect checkout (FR-03) with simulated payment (Phase 1 – no live payments). */
class CheckoutController extends Controller
{
    public function __construct(private Cart $cart, private OrderService $orders)
    {
    }

    public function show(Request $request): View|RedirectResponse
    {
        if ($this->cart->isEmpty()) {
            return redirect()->route('cart');
        }

        return view('shop.checkout', [
            'lines' => $this->cart->lines(),
            'subtotal' => $this->cart->subtotalCents(),
            'marketDays' => MarketDay::collectable()->with('location')->orderBy('date')->take(30)->get()->groupBy('location.name'),
            'customer' => $this->customerFor($request),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'market_day_id' => ['required', 'integer', 'exists:market_days,id'],
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'phone' => ['required', 'string', 'max:30'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $customer = $this->customerFor($request);
        $customer->fill(collect($data)->only(['first_name', 'last_name', 'phone'])->all())->save();

        $lines = collect($this->cart->lines())->mapWithKeys(fn ($l) => [$l['variant']->id => $l['quantity']])->all();

        try {
            $order = $this->orders->placeOrder($customer, $lines, MarketDay::findOrFail($data['market_day_id']), $data['notes'] ?? null);
        } catch (InvalidArgumentException|InsufficientStockException $e) {
            return back()->withInput()->withErrors(['checkout' => $e->getMessage()]);
        }

        $this->cart->clear();

        return redirect()->route('orders.pay', $order);
    }

    public function payment(Request $request, Order $order): View|RedirectResponse
    {
        $this->authorizeOwner($request, $order);
        if ($order->payment_status === 'paid') {
            return redirect()->route('orders.show', $order);
        }

        return view('shop.payment', ['order' => $order->load('items', 'marketDay.location')]);
    }

    /**
     * Simulated payment gateway. In a later phase this is replaced by Stripe
     * (or similar) – OrderService::confirmPayment() stays the same.
     */
    public function processPayment(Request $request, Order $order): RedirectResponse
    {
        $this->authorizeOwner($request, $order);
        $outcome = $request->validate(['outcome' => ['required', 'in:approve,decline']])['outcome'];

        if ($outcome === 'decline') {
            $this->orders->failPayment($order);

            return back()->withErrors(['payment' => 'Payment was declined (simulated). You can try again.']);
        }

        try {
            $this->orders->confirmPayment($order, 'SIM-'.Str::upper(Str::random(10)));
        } catch (InsufficientStockException $e) {
            return back()->withErrors(['payment' => $e->getMessage().' Please contact NuttyLand or place a new order.']);
        } catch (InvalidArgumentException $e) {
            return redirect()->route('orders.show', $order)->withErrors(['payment' => $e->getMessage()]);
        }

        return redirect()->route('orders.show', $order)->with('status', 'Payment approved – your order is confirmed!');
    }

    private function customerFor(Request $request): Customer
    {
        $user = $request->user();

        return $user->customer ?? Customer::create([
            'user_id' => $user->id,
            'first_name' => Str::before($user->name, ' '),
            'last_name' => Str::after($user->name, ' ') ?: '-',
            'email' => $user->email,
            'phone' => $user->phone,
        ]);
    }

    private function authorizeOwner(Request $request, Order $order): void
    {
        abort_unless($order->customer->user_id === $request->user()->id, 403);
    }
}
