@extends('layouts.shop', ['title' => 'Order '.$order->order_number])

@section('content')
    @if ($order->status !== 'pending_payment')
        @include('partials.checkout-steps', ['step' => 4])
    @endif
    <div class="mb-4 flex flex-wrap items-center gap-3">
        <h1 class="text-2xl font-extrabold">Order {{ $order->order_number }}</h1>
        @include('partials.order-status')
    </div>

    @php($steps = ['confirmed' => 'Confirmed', 'preparing' => 'Being packed', 'ready' => 'Ready to collect', 'collected' => 'Collected'])
    @php($reached = array_search($order->status, array_keys($steps)))
    @if ($reached !== false)
        <ol class="card mb-6 grid grid-cols-4 gap-1 p-4 text-center text-xs font-semibold">
            @foreach ($steps as $key => $label)
                <li class="{{ $loop->index <= $reached ? 'text-leaf-700' : 'text-nut-400' }}">
                    <span class="mx-auto mb-1 block h-2 rounded-full {{ $loop->index <= $reached ? 'bg-leaf-600' : 'bg-nut-100' }}"></span>{{ $label }}
                </li>
            @endforeach
        </ol>
    @endif

    <div class="grid gap-6 md:grid-cols-2">
        <section class="card p-4">
            <h2 class="font-extrabold">Collection</h2>
            @if ($order->marketDay)
                <p class="mt-2 text-lg font-bold">{{ $order->marketDay->location->name }}</p>
                <p class="text-nut-700">{{ $order->marketDay->date->format('l j F Y') }} · {{ $order->marketDay->hours }}</p>
                <p class="mt-1 text-sm text-nut-600">{{ $order->marketDay->location->suburb }}</p>
            @endif
            <p class="mt-3 rounded-lg bg-nut-50 p-3 text-sm">Show order number <b>{{ $order->order_number }}</b> at the NuttyLand stall.</p>
            @if ($order->status === 'pending_payment')
                <a href="{{ route('orders.pay', $order) }}" class="btn-primary mt-3 w-full">Complete payment</a>
            @endif
        </section>
        <section class="card p-4">
            <h2 class="font-extrabold">Items</h2>
            <ul class="mt-2 divide-y divide-nut-100 text-sm">
                @foreach ($order->items as $item)
                    <li class="flex justify-between py-2"><span>{{ $item->quantity }} × {{ $item->product_name }} {{ $item->weight_label }}</span><span>{{ \App\Support\Money::format($item->subtotal_cents) }}</span></li>
                @endforeach
            </ul>
            <div class="flex justify-between border-t border-nut-100 pt-2 font-extrabold"><span>Total</span><span>{{ $order->total }}</span></div>
            <p class="mt-1 text-xs text-nut-500">Payment: {{ ucfirst($order->payment_status) }} {{ $order->payment_reference ? '· Ref '.$order->payment_reference : '' }}</p>
        </section>
    </div>
    <a href="{{ route('account') }}" class="mt-6 inline-block text-sm font-semibold text-nut-700 hover:underline">← All my orders</a>
@endsection
