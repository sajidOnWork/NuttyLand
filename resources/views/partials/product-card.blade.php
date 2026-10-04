@php($default = $product->activeVariants->firstWhere('weight_grams', 500) ?? $product->activeVariants->first())
<article class="card flex flex-col overflow-hidden">
    <a href="{{ route('products.show', $product) }}" class="block">
        @include('partials.product-image', ['product' => $product, 'class' => 'aspect-[4/3] w-full'])
    </a>
    <div class="flex flex-1 flex-col p-3">
        <div class="mb-1 flex flex-wrap gap-1">
            @if ($product->is_organic)<span class="badge bg-leaf-50 text-leaf-700">Organic</span>@endif
            @if ($product->roast_label)<span class="badge bg-nut-100 text-nut-700">{{ $product->roast_label }}</span>@endif
        </div>
        <h3 class="font-bold leading-snug"><a href="{{ route('products.show', $product) }}" class="hover:underline">{{ $product->name }}</a></h3>
        @if ($default)
            <p class="mt-0.5 text-sm text-nut-600">{{ $default->price }} / {{ $default->weight_label }}</p>
            <form method="post" action="{{ route('cart.add') }}" class="mt-auto pt-3">
                @csrf
                <input type="hidden" name="product_variant_id" value="{{ $default->id }}">
                <button class="btn-primary w-full py-2">Add to cart</button>
            </form>
        @endif
    </div>
</article>
