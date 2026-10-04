<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\StockMovement;
use App\Notifications\OrderConfirmed;
use App\Services\InsufficientStockException;
use App\Services\OrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\Concerns\BuildsNuttyLand;
use Tests\TestCase;

class ClickAndCollectOrderTest extends TestCase
{
    use BuildsNuttyLand, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildNuttyLand();
        Notification::fake();
    }

    /** TC-01: Browse → add product → select market → confirm. */
    public function test_tc01_customer_places_click_and_collect_order(): void
    {
        $customer = $this->user('customer');

        $this->actingAs($customer)->get('/products/almonds')->assertOk()->assertSee('Almonds');
        $this->post('/cart', ['product_variant_id' => $this->almonds500->id, 'quantity' => 2])->assertRedirect();
        $this->post('/cart', ['product_variant_id' => $this->cashews200->id])->assertRedirect();
        $this->get('/checkout')->assertOk()->assertSee('Bondi Junction Market');

        $response = $this->post('/checkout', [
            'market_day_id' => $this->marketDay->id,
            'first_name' => 'Sam', 'last_name' => 'Smith', 'phone' => '0400111222',
        ]);

        $order = Order::with('items')->sole();
        $response->assertRedirect(route('orders.pay', $order));

        $this->assertSame('pending_payment', $order->status);
        $this->assertSame($this->market->id, $order->location_id);
        $this->assertSame($this->marketDay->id, $order->market_day_id);
        $this->assertCount(2, $order->items);
        $this->assertSame(2 * 1200 + 700, $order->total_cents);

        $this->post(route('orders.pay.process', $order), ['outcome' => 'approve'])->assertRedirect(route('orders.show', $order));

        $order->refresh();
        $this->assertSame('confirmed', $order->status);
        $this->assertSame('paid', $order->payment_status);
        $this->assertSame(48, $this->almonds500->stockAt($this->warehouse));
        $this->assertSame(29, $this->cashews200->stockAt($this->warehouse));
        $this->assertSame(0, app(\App\Services\Cart::class)->count(), 'cart is emptied');
        Notification::assertSentOnDemand(OrderConfirmed::class);
    }

    public function test_repeated_payment_confirmation_does_not_duplicate_stock_changes(): void
    {
        $customer = $this->user('customer');
        $orders = app(OrderService::class);
        $order = $orders->placeOrder($customer->customer, [$this->almonds500->id => 3], $this->marketDay);

        $orders->confirmPayment($order, 'SIM-1');
        $orders->confirmPayment($order->fresh(), 'SIM-1');

        $this->assertSame(47, $this->almonds500->stockAt($this->warehouse));
        $this->assertSame(1, StockMovement::where('type', 'online_order')->count());
    }

    public function test_declined_payment_keeps_order_open_and_stock_untouched(): void
    {
        $customer = $this->user('customer');
        $order = app(OrderService::class)->placeOrder($customer->customer, [$this->almonds500->id => 1], $this->marketDay);

        $this->actingAs($customer)->post(route('orders.pay.process', $order), ['outcome' => 'decline'])->assertSessionHasErrors('payment');

        $this->assertSame('pending_payment', $order->fresh()->status);
        $this->assertSame('failed', $order->fresh()->payment_status);
        $this->assertSame(50, $this->almonds500->stockAt($this->warehouse));
    }

    public function test_order_cannot_exceed_available_stock(): void
    {
        $this->expectException(InsufficientStockException::class);
        app(OrderService::class)->placeOrder($this->user('customer')->customer, [$this->almonds500->id => 51], $this->marketDay);
    }

    public function test_cancelled_market_day_cannot_be_chosen(): void
    {
        $this->marketDay->update(['status' => 'cancelled']);
        $customer = $this->user('customer');

        $this->actingAs($customer)->post('/cart', ['product_variant_id' => $this->almonds500->id]);
        $this->post('/checkout', ['market_day_id' => $this->marketDay->id, 'first_name' => 'A', 'last_name' => 'B', 'phone' => '1'])
            ->assertSessionHasErrors('checkout');
        $this->assertSame(0, Order::count());
    }

    public function test_staff_progress_order_to_collected_and_cancel_returns_stock(): void
    {
        $orders = app(OrderService::class);
        $customer = $this->user('customer');
        $order = $orders->confirmPayment($orders->placeOrder($customer->customer, [$this->almonds500->id => 2], $this->marketDay), 'SIM-2');
        $staff = $this->user('staff');

        $this->actingAs($staff)->post(route('staff.orders.status', $order), ['status' => 'preparing'])->assertSessionHasNoErrors();
        $this->post(route('staff.orders.status', $order), ['status' => 'ready'])->assertSessionHasNoErrors();
        $this->post(route('staff.orders.status', $order), ['status' => 'collected'])->assertSessionHasNoErrors();
        $this->assertSame('collected', $order->fresh()->status);
        $this->assertNotNull($order->fresh()->collected_at);

        $second = $orders->confirmPayment($orders->placeOrder($customer->customer, [$this->almonds500->id => 5], $this->marketDay), 'SIM-3');
        $this->assertSame(43, $this->almonds500->stockAt($this->warehouse));
        $this->post(route('staff.orders.status', $second), ['status' => 'cancelled']);
        $this->assertSame('refunded', $second->fresh()->payment_status);
        $this->assertSame(48, $this->almonds500->stockAt($this->warehouse));
    }

    public function test_customers_cannot_see_other_customers_orders(): void
    {
        $orders = app(OrderService::class);
        $order = $orders->placeOrder($this->user('customer')->customer, [$this->almonds500->id => 1], $this->marketDay);

        $this->actingAs($this->user('customer'))->get(route('orders.show', $order))->assertForbidden();
        $this->post(route('orders.pay.process', $order), ['outcome' => 'approve'])->assertForbidden();
    }
}
