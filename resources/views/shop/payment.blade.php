@extends('layouts.shop', ['title' => 'Payment'])

@section('content')
    <h1 class="mb-4 text-2xl font-extrabold">Payment</h1>
    @include('partials.checkout-steps', ['step' => 3])

    <div class="grid gap-6 md:grid-cols-[2fr_1fr]">
        <section class="card p-5">
            <div class="mb-4 rounded-lg border border-amber-200 bg-amber-50 p-3 text-sm text-amber-900">
                <b>Test mode.</b> This prototype uses a simulated payment gateway – no card is charged. Choose an outcome to continue.
            </div>
            <div class="grid gap-3 opacity-60" aria-hidden="true">
                <div><span class="label">Card number</span><div class="input">4242 4242 4242 4242</div></div>
                <div class="grid grid-cols-2 gap-3"><div><span class="label">Expiry</span><div class="input">12 / 30</div></div><div><span class="label">CVC</span><div class="input">123</div></div></div>
            </div>
            <form method="post" action="{{ route('orders.pay.process', $order) }}" class="mt-5 grid gap-2 sm:grid-cols-2">
                @csrf
                <button name="outcome" value="approve" class="btn-success py-3">Pay {{ $order->total }}</button>
                <button name="outcome" value="decline" class="btn-secondary py-3">Simulate declined card</button>
            </form>
        </section>

        <aside class="card h-fit p-4 text-sm">
            <h2 class="font-extrabold">Order {{ $order->order_number }}</h2>
            <p class="mt-1 text-nut-600">Collect: {{ $order->marketDay?->label }}</p>
            <ul class="mt-3 space-y-1">
                @foreach ($order->items as $item)
                    <li class="flex justify-between"><span>{{ $item->quantity }} × {{ $item->product_name }} {{ $item->weight_label }}</span><span>{{ \App\Support\Money::format($item->subtotal_cents) }}</span></li>
                @endforeach
            </ul>
            <div class="mt-3 flex justify-between border-t border-nut-100 pt-3 text-base font-extrabold"><span>Total</span><span>{{ $order->total }}</span></div>
        </aside>
    </div>
@endsection
