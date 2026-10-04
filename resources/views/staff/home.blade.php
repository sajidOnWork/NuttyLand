@extends('layouts.staff', ['title' => 'Today'])

@section('content')
    <section class="card p-4">
        <form method="get" action="{{ route('staff.home') }}" class="flex flex-wrap items-end gap-3">
            <div class="flex-1">
                <label class="label" for="location">I'm working at</label>
                <select id="location" name="location" class="input" onchange="this.form.submit()">
                    <option value="">Choose a market…</option>
                    @foreach ($locations as $l)
                        <option value="{{ $l->id }}" @selected($location?->id === $l->id)>{{ $l->name }}{{ $todayMarkets->contains('location_id', $l->id) ? ' – trading today' : '' }}</option>
                    @endforeach
                </select>
            </div>
            <noscript><button class="btn-primary">Set</button></noscript>
        </form>
        @if ($todayMarkets->isNotEmpty())
            <p class="mt-2 text-xs text-nut-600">Trading today: {{ $todayMarkets->map(fn ($d) => $d->location->name.' ('.$d->hours.')')->join(', ') }}</p>
        @else
            <p class="mt-2 text-xs text-nut-600">No markets are scheduled for today.</p>
        @endif
    </section>

    <div class="mt-4 grid grid-cols-2 gap-3 md:grid-cols-4">
        <a href="{{ route('staff.sell') }}" class="card flex flex-col items-center justify-center bg-nut-800 p-5 text-center text-white hover:bg-nut-900">
            <span class="text-2xl font-extrabold">Sell</span><span class="text-xs text-nut-200">Record stall sales</span>
        </a>
        <a href="{{ route('staff.orders') }}" class="card p-5 text-center hover:border-nut-300">
            <span class="block text-3xl font-extrabold">{{ $ordersToPrepare }}</span><span class="text-xs text-nut-600">Click &amp; Collect orders open</span>
        </a>
        <a href="{{ route('staff.sales') }}" class="card p-5 text-center hover:border-nut-300">
            <span class="block text-3xl font-extrabold">{{ $todaySales ? \App\Support\Money::format((int) $todaySales->total) : '–' }}</span><span class="text-xs text-nut-600">{{ $todaySales?->n ?? 0 }} sales today here</span>
        </a>
        <a href="{{ route('staff.stock', ['low' => 1]) }}" class="card p-5 text-center hover:border-nut-300">
            <span class="block text-3xl font-extrabold">Stock</span><span class="text-xs text-nut-600">Transfers, counts, low stock</span>
        </a>
    </div>

    <section class="card mt-4 p-4">
        <div class="flex items-center justify-between">
            <h2 class="font-extrabold">Alerts</h2>
            @if ($alerts->isNotEmpty())
                <form method="post" action="{{ route('staff.alerts.read') }}">@csrf<button class="text-sm font-semibold text-nut-700 hover:underline">Mark all read</button></form>
            @endif
        </div>
        <ul class="mt-2 divide-y divide-nut-100 text-sm">
            @forelse ($alerts as $alert)
                <li class="py-2"><span class="badge mr-2 bg-amber-100 text-amber-800">{{ $alert->data['title'] ?? 'Alert' }}</span>{{ $alert->data['body'] ?? '' }} <span class="text-xs text-nut-500">· {{ $alert->created_at->diffForHumans() }}</span></li>
            @empty
                <li class="py-2 text-nut-500">No new alerts.</li>
            @endforelse
        </ul>
    </section>
@endsection
