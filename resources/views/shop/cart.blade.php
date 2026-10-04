@extends('layouts.shop', ['title' => 'Your cart'])

@section('content')
    <h1 class="mb-4 text-2xl font-extrabold">Your cart</h1>

    @if ($lines->isEmpty())
        <div class="card p-10 text-center">
            <p class="text-nut-600">Your cart is empty.</p>
            <a href="{{ route('shop') }}" class="btn-primary mt-4">Start shopping</a>
        </div>
    @else
        <div class="grid gap-6 md:grid-cols-[2fr_1fr]">
            <ul class="card divide-y divide-nut-100">
                @foreach ($lines as $line)
                    @php($v = $line['variant'])
                    <li class="flex gap-3 p-3">
                        @include('partials.product-image', ['product' => $v->product, 'class' => 'h-20 w-20 shrink-0 rounded-lg'])
                        <div class="flex flex-1 flex-col">
                            <div class="flex justify-between gap-2">
                                <a href="{{ route('products.show', $v->product) }}" class="font-bold hover:underline">{{ $v->product->name }}</a>
                                <span class="font-bold">{{ \App\Support\Money::format($line['subtotal_cents']) }}</span>
                            </div>
                            <span class="text-sm text-nut-600">{{ $v->weight_label }} · {{ $v->price }} each</span>
                            <div class="mt-auto flex items-center gap-2 pt-2">
                                <form method="post" action="{{ route('cart.update', $v) }}" class="flex items-center gap-1">
                                    @csrf @method('patch')
                                    <button name="quantity" value="{{ $line['quantity'] - 1 }}" class="btn-secondary h-8 w-8 p-0" aria-label="Decrease quantity">−</button>
                                    <span class="w-8 text-center font-semibold" aria-label="Quantity">{{ $line['quantity'] }}</span>
                                    <button name="quantity" value="{{ $line['quantity'] + 1 }}" class="btn-secondary h-8 w-8 p-0" aria-label="Increase quantity">+</button>
                                </form>
                                <form method="post" action="{{ route('cart.remove', $v) }}" class="ml-auto">
                                    @csrf @method('delete')
                                    <button class="text-sm font-semibold text-red-700 hover:underline">Remove</button>
                                </form>
                            </div>
                        </div>
                    </li>
                @endforeach
            </ul>

            <aside class="card h-fit p-4">
                <div class="flex justify-between text-lg font-extrabold"><span>Subtotal</span><span>{{ \App\Support\Money::format($subtotal) }}</span></div>
                <p class="mt-1 text-sm text-nut-600">Click &amp; Collect from a NuttyLand market – no delivery fee.</p>
                <a href="{{ route('checkout') }}" class="btn-primary mt-4 w-full py-3">Proceed to checkout</a>
                <a href="{{ route('shop') }}" class="btn-secondary mt-2 w-full">← Continue shopping</a>
            </aside>
        </div>
    @endif
@endsection
