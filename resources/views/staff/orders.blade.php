@extends('layouts.staff', ['title' => 'Click & Collect'])

@section('content')
    <div class="mb-4 flex flex-wrap items-center gap-2">
        <h1 class="mr-auto text-xl font-extrabold">Click &amp; Collect – {{ request()->boolean('all_markets') || ! $location ? 'all markets' : $location->name }}</h1>
        @foreach (['open' => 'Open', 'done' => 'Completed', 'all' => 'All'] as $key => $label)
            <a href="{{ route('staff.orders', array_merge(request()->query(), ['show' => $key])) }}" class="rounded-lg px-3 py-1.5 text-sm font-semibold {{ $show === $key ? 'bg-nut-800 text-white' : 'bg-white text-nut-700' }}">{{ $label }}</a>
        @endforeach
        @if ($location)
            <a href="{{ route('staff.orders', array_merge(request()->query(), ['all_markets' => request()->boolean('all_markets') ? 0 : 1])) }}" class="text-sm font-semibold text-nut-700 underline">{{ request()->boolean('all_markets') ? 'This market only' : 'All markets' }}</a>
        @endif
    </div>
    <div class="card divide-y divide-nut-100">
        @forelse ($orders as $order)
            <a href="{{ route('staff.orders.show', $order) }}" class="flex flex-wrap items-center justify-between gap-2 p-3 hover:bg-nut-50">
                <span>
                    <b>{{ $order->order_number }}</b> · {{ $order->customer->full_name }}
                    <span class="block text-xs text-nut-600">{{ $order->marketDay?->location->name }} · {{ $order->marketDay?->date->format('D j M') }} · {{ $order->items->sum('quantity') }} items</span>
                </span>
                <span class="flex items-center gap-2">@include('partials.order-status') <b>{{ $order->total }}</b></span>
            </a>
        @empty
            <p class="p-6 text-center text-sm text-nut-500">No orders.</p>
        @endforelse
    </div>
    <div class="mt-3">{{ $orders->links() }}</div>
@endsection
