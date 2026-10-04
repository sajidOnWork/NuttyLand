@extends('layouts.staff', ['title' => $order->order_number])

@section('content')
    <a href="{{ route('staff.orders') }}" class="text-sm font-semibold text-nut-700 hover:underline">← Orders</a>
    <div class="mt-2 mb-4 flex flex-wrap items-center gap-3">
        <h1 class="text-2xl font-extrabold">{{ $order->order_number }}</h1>
        @include('partials.order-status')
    </div>
    <div class="grid gap-4 md:grid-cols-2">
        <section class="card p-4">
            <h2 class="font-extrabold">Pick list</h2>
            <ul class="mt-2 divide-y divide-nut-100">
                @foreach ($order->items as $item)
                    <li class="flex items-center gap-3 py-2"><span class="flex h-9 w-9 items-center justify-center rounded-lg bg-nut-100 font-extrabold">{{ $item->quantity }}</span><span>{{ $item->product_name }} <b>{{ $item->weight_label }}</b></span></li>
                @endforeach
            </ul>
            <p class="mt-2 text-right font-extrabold">{{ $order->total }} <span class="text-xs font-normal text-nut-500">({{ $order->payment_status }})</span></p>
        </section>
        <section class="card p-4 text-sm">
            <h2 class="font-extrabold">Customer</h2>
            <p class="mt-1">{{ $order->customer->full_name }} · {{ $order->customer->phone }}</p>
            <p class="text-nut-600">{{ $order->customer->email }}</p>
            <p class="mt-3"><b>Collect:</b> {{ $order->marketDay?->label }}</p>
            @if ($order->notes)<p class="mt-2 rounded bg-amber-50 p-2">{{ $order->notes }}</p>@endif

            <div class="mt-4 grid grid-cols-2 gap-2">
                @foreach ($next as $status)
                    <form method="post" action="{{ route('staff.orders.status', $order) }}" @if ($status === 'cancelled') onsubmit="return confirm('Cancel this order and return stock to the warehouse?')" @endif>
                        @csrf
                        <button name="status" value="{{ $status }}" class="{{ $status === 'cancelled' ? 'btn-secondary text-red-700' : ($status === 'collected' ? 'btn-success' : 'btn-primary') }} w-full py-3">
                            {{ ['preparing' => 'Start packing', 'ready' => 'Mark ready', 'collected' => 'Mark collected', 'cancelled' => 'Cancel order'][$status] }}
                        </button>
                    </form>
                @endforeach
            </div>
        </section>
    </div>
@endsection
