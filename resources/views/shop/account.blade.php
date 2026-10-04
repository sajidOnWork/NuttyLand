@extends('layouts.shop', ['title' => 'My account'])

@section('content')
    <div class="mb-4 flex items-center justify-between">
        <h1 class="text-2xl font-extrabold">My account</h1>
        <form method="post" action="{{ route('logout') }}">@csrf<button class="btn-secondary">Log out</button></form>
    </div>

    <div class="grid gap-6 md:grid-cols-[2fr_1fr]">
        <section class="card p-4">
            <h2 class="font-extrabold">My orders</h2>
            @if ($orders->isEmpty())
                <p class="mt-3 text-sm text-nut-600">No orders yet. <a href="{{ route('shop') }}" class="font-semibold underline">Start shopping</a>.</p>
            @else
                <ul class="mt-2 divide-y divide-nut-100">
                    @foreach ($orders as $order)
                        <li>
                            <a href="{{ route('orders.show', $order) }}" class="flex flex-wrap items-center justify-between gap-2 py-3 hover:bg-nut-50">
                                <span>
                                    <b>{{ $order->order_number }}</b>
                                    <span class="block text-xs text-nut-600">{{ $order->created_at->format('j M Y') }} · Collect {{ $order->marketDay?->location->name }} {{ $order->marketDay?->date->format('D j M') }}</span>
                                </span>
                                <span class="flex items-center gap-3">@include('partials.order-status') <b>{{ $order->total }}</b></span>
                            </a>
                        </li>
                    @endforeach
                </ul>
                <div class="mt-3">{{ $orders->links() }}</div>
            @endif
        </section>

        <section class="card h-fit p-4">
            <h2 class="font-extrabold">My details</h2>
            <form method="post" action="{{ route('account.update') }}" class="mt-3 space-y-3">
                @csrf @method('put')
                <div><label class="label" for="first_name">First name</label><input id="first_name" name="first_name" class="input" required value="{{ old('first_name', $customer?->first_name) }}"></div>
                <div><label class="label" for="last_name">Last name</label><input id="last_name" name="last_name" class="input" required value="{{ old('last_name', $customer?->last_name) }}"></div>
                <div><label class="label" for="phone">Mobile</label><input id="phone" name="phone" class="input" value="{{ old('phone', $customer?->phone) }}"></div>
                <label class="flex items-start gap-2 text-sm"><input type="checkbox" name="marketing_opt_in" value="1" class="mt-0.5 h-4 w-4 accent-nut-700" @checked($customer?->marketing_opt_in)> Send me NuttyLand news and market updates</label>
                <button class="btn-primary w-full">Save</button>
            </form>
            @if ($customer)
                <p class="mt-4 rounded-lg bg-nut-50 p-3 text-xs text-nut-600">Loyalty points: <b>{{ $customer->loyalty_points }}</b> – the loyalty program is coming in a later phase.</p>
            @endif
        </section>
    </div>
@endsection
