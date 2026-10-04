@extends('layouts.shop', ['title' => $product->name])

@section('content')
    <nav class="mb-4 text-sm text-nut-600"><a href="{{ route('shop') }}" class="hover:underline">Shop</a> / <a href="{{ route('shop', ['category' => $product->category->slug]) }}" class="hover:underline">{{ $product->category->name }}</a></nav>

    <div class="grid gap-6 md:grid-cols-2">
        @include('partials.product-image', ['product' => $product, 'class' => 'aspect-square w-full rounded-2xl'])

        <div>
            <div class="mb-2 flex flex-wrap gap-1.5">
                @if ($product->is_organic)<span class="badge bg-leaf-50 text-leaf-700">Organic</span>@endif
                @if ($product->roast_label)<span class="badge bg-nut-100 text-nut-700">{{ $product->roast_label }}</span>@endif
                @if ($product->flavour)<span class="badge bg-amber-50 text-amber-800">{{ $product->flavour }}</span>@endif
            </div>
            <h1 class="text-3xl font-extrabold">{{ $product->name }}</h1>
            <p class="mt-2 text-nut-700">{{ $product->description }}</p>

            <form method="post" action="{{ route('cart.add') }}" class="mt-5 space-y-4">
                @csrf
                <fieldset>
                    <legend class="label">Pack size</legend>
                    <div class="grid grid-cols-2 gap-2 sm:grid-cols-4">
                        @foreach ($product->activeVariants as $variant)
                            @php($stock = (int) optional($variant->inventories->first())->quantity)
                            <label class="cursor-pointer">
                                <input type="radio" name="product_variant_id" value="{{ $variant->id }}" class="peer sr-only" @checked($loop->index === min(2, $product->activeVariants->count() - 1)) @disabled($stock <= 0)>
                                <span class="block rounded-lg border-2 border-nut-200 bg-white p-2 text-center peer-checked:border-nut-700 peer-checked:bg-nut-50 peer-disabled:opacity-40 peer-focus-visible:ring-2 peer-focus-visible:ring-nut-400">
                                    <span class="block text-sm font-bold">{{ $variant->weight_label }}</span>
                                    <span class="block text-sm">{{ $variant->price }}</span>
                                    <span class="block text-[11px] {{ $stock <= 0 ? 'text-red-600' : ($stock <= $variant->low_stock_threshold ? 'text-amber-700' : 'text-leaf-700') }}">
                                        {{ $stock <= 0 ? 'Sold out' : ($stock <= $variant->low_stock_threshold ? 'Only '.$stock.' left' : 'In stock') }}
                                    </span>
                                </span>
                            </label>
                        @endforeach
                    </div>
                </fieldset>
                <div class="flex items-end gap-3">
                    <div class="w-24">
                        <label class="label" for="quantity">Qty</label>
                        <input id="quantity" type="number" name="quantity" value="1" min="1" max="50" class="input">
                    </div>
                    <button class="btn-primary flex-1 py-3">Add to cart</button>
                </div>
            </form>

            <dl class="mt-6 divide-y divide-nut-100 rounded-xl border border-nut-100 bg-white text-sm">
                <div class="grid grid-cols-3 gap-2 p-3"><dt class="font-semibold">Ingredients</dt><dd class="col-span-2">{{ $product->ingredients ?: '—' }}</dd></div>
                <div class="grid grid-cols-3 gap-2 p-3"><dt class="font-semibold">Allergens</dt><dd class="col-span-2">{{ $product->allergen_info ?: '—' }}</dd></div>
                <div class="grid grid-cols-3 gap-2 p-3"><dt class="font-semibold">Origin</dt><dd class="col-span-2">{{ $product->country_of_origin ?: '—' }}</dd></div>
            </dl>
            <p class="mt-2 text-xs text-nut-500">Always check the pack label if you have a food allergy.</p>
        </div>
    </div>

    @if ($related->isNotEmpty())
        <section class="mt-10">
            <h2 class="mb-3 text-xl font-extrabold">You might also like</h2>
            <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
                @foreach ($related as $product)
                    @include('partials.product-card')
                @endforeach
            </div>
        </section>
    @endif
@endsection
