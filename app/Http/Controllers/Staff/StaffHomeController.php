<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\Location;
use App\Models\MarketDay;
use App\Models\Order;
use App\Models\Sale;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class StaffHomeController extends Controller
{
    public function index(Request $request): View
    {
        if ($request->filled('location')) {
            $request->session()->put('staff_location_id', (int) $request->input('location'));
        }
        $location = StaffContext::location($request);

        return view('staff.home', [
            'location' => $location,
            'locations' => Location::active()->where('type', '!=', 'warehouse')->orderBy('name')->get(),
            'todayMarkets' => MarketDay::with('location')->whereDate('date', today())->where('status', 'scheduled')->get(),
            'todaySales' => $location ? Sale::where('location_id', $location->id)->whereDate('sold_at', today())->selectRaw('count(*) as n, coalesce(sum(total_cents),0) as total')->first() : null,
            'ordersToPrepare' => Order::whereIn('status', ['confirmed', 'preparing', 'ready'])
                ->when($location, fn ($q) => $q->where('location_id', $location->id))->count(),
            'alerts' => $request->user()->unreadNotifications()->latest()->take(10)->get(),
        ]);
    }

    public function markAlertsRead(Request $request): RedirectResponse
    {
        $request->user()->unreadNotifications->markAsRead();

        return back();
    }
}
