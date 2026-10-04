<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\OrderService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use InvalidArgumentException;

/** Click & Collect order handling at the stall. */
class StaffOrderController extends Controller
{
    public function index(Request $request): View
    {
        $location = StaffContext::location($request);
        $show = $request->input('show', 'open');

        $orders = Order::with(['customer', 'marketDay.location', 'items'])
            ->when($location && ! $request->boolean('all_markets'), fn ($q) => $q->where('orders.location_id', $location->id))
            ->when($show === 'open', fn ($q) => $q->whereIn('orders.status', ['confirmed', 'preparing', 'ready']))
            ->when($show === 'done', fn ($q) => $q->whereIn('orders.status', ['collected', 'cancelled']))
            ->where('orders.status', '!=', 'pending_payment')
            ->leftJoin('market_days', 'market_days.id', '=', 'orders.market_day_id')
            ->orderBy('market_days.date')->orderBy('orders.id')
            ->select('orders.*')
            ->paginate(30)->withQueryString();

        return view('staff.orders', ['orders' => $orders, 'location' => $location, 'show' => $show]);
    }

    public function show(Order $order): View
    {
        return view('staff.order', [
            'order' => $order->load('items', 'customer', 'marketDay.location'),
            'next' => OrderService::TRANSITIONS[$order->status] ?? [],
        ]);
    }

    public function updateStatus(Request $request, Order $order, OrderService $orders): RedirectResponse
    {
        $status = $request->validate(['status' => ['required', 'in:preparing,ready,collected,cancelled']])['status'];

        try {
            $orders->updateStatus($order, $status, $request->user());
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['status' => $e->getMessage()]);
        }

        return back()->with('status', "Order {$order->order_number} marked ".strtolower(Order::STATUSES[$status]).'.');
    }
}
