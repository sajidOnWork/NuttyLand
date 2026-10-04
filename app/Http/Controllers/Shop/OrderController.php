<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Customer account: profile and order history. */
class OrderController extends Controller
{
    public function index(Request $request): View
    {
        $customer = $request->user()->customer;

        return view('shop.account', [
            'customer' => $customer,
            'orders' => $customer ? $customer->orders()->with('marketDay.location')->latest()->paginate(10) : collect(),
        ]);
    }

    public function updateProfile(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'phone' => ['nullable', 'string', 'max:30'],
            'marketing_opt_in' => ['nullable', 'boolean'],
        ]);
        $data['marketing_opt_in'] = $request->boolean('marketing_opt_in');

        $user = $request->user();
        $user->customer()->updateOrCreate(['user_id' => $user->id], [...$data, 'email' => $user->email]);
        $user->update(['name' => $data['first_name'].' '.$data['last_name'], 'phone' => $data['phone']]);

        return back()->with('status', 'Profile updated.');
    }

    public function show(Request $request, Order $order): View
    {
        $user = $request->user();
        abort_unless($order->customer->user_id === $user->id || $user->canUseStaffScreens(), 403);

        return view('shop.order', ['order' => $order->load('items', 'marketDay.location', 'location')]);
    }
}
