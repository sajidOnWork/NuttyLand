@extends('layouts.shop', ['title' => 'Shop'])

@section('content')
    <div class="flex flex-col gap-6 md:flex-row">
        <aside class="md:w-56 md:shrink-0">
            <form method="get" action="{{ route('shop') }}" class="card space-y-4 p-4">
                <input type="hidden" name="q" value="{{ $filters['q'] ?? '' }}">
                <div>
                    <label class="label" for="category">Category</label>
                    <select id="category" name="category" class="input" onchange="this.form.submit()">
                        <option value="">All products</option>
                        @foreach ($categories as $c)
                            <option value="{{ $c->slug }}" @selected(($filters['category'] ?? '') === $c->slug)>{{ $c->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="label" for="roast">Roasting style</label>
                    <select id="roast" name="roast" class="input" onchange="this.form.submit()">
                        <option value="">Any</option>
                        @foreach (\App\Models\Product::ROAST_STYLES as $key => $label)
                            <option value="{{ $key }}" @selected(($filters['roast'] ?? '') === $key)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <label class="flex items-center gap-2 text-sm font-medium">
                    <input type="checkbox" name="organic" value="1" class="h-4 w-4 accent-nut-700" @checked(! empty($filters['organic'])) onchange="this.form.submit()"> Organic only
                </label>
                <div>
                    <label class="label" for="sort">Sort by</label>
                    <select id="sort" name="sort" class="input" onchange="this.form.submit()">
                        <option value="name">Name</option>
                        <option value="price_asc" @selected(($filters['sort'] ?? '') === 'price_asc')>Price: low to high</option>
                        <option value="price_desc" @selected(($filters['sort'] ?? '') === 'price_desc')>Price: high to low</option>
                    </select>
                </div>
                <noscript><button class="btn-primary w-full">Apply</button></noscript>
                @if (array_filter($filters))
                    <a href="{{ route('shop') }}" class="block text-center text-sm font-semibold text-nut-700 hover:underline">Clear filters</a>
                @endif
            </form>
        </aside>

        <section class="flex-1">
            <h1 class="mb-1 text-2xl font-extrabold">
                @if (! empty($filters['q'])) Results for “{{ $filters['q'] }}” @else Shop @endif
            </h1>
            <p class="mb-4 text-sm text-nut-600">{{ $products->total() }} {{ Str::plural('product', $products->total()) }}</p>

            <div class="grid grid-cols-2 gap-3 sm:grid-cols-3">
                @forelse ($products as $product)
                    @include('partials.product-card')
                @empty
                    <p class="col-span-full rounded-xl bg-white p-8 text-center text-nut-600">No products match your search.</p>
                @endforelse
            </div>
            <div class="mt-6">{{ $products->links() }}</div>
        </section>
    </div>
@endsection
