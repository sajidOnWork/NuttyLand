@extends('layouts.shop')

@section('content')
    <section class="relative overflow-hidden rounded-2xl bg-nut-800 text-white">
        <svg class="absolute -right-10 -bottom-10 h-72 w-72 opacity-30 md:h-96 md:w-96" viewBox="0 0 100 100" aria-hidden="true">
            @foreach ([[30, 38, 12], [55, 30, 10], [70, 52, 13], [42, 60, 11], [62, 72, 9], [25, 66, 8], [80, 30, 8]] as [$cx, $cy, $r])
                <ellipse cx="{{ $cx }}" cy="{{ $cy }}" rx="{{ $r }}" ry="{{ $r * 0.72 }}" fill="#c08b55" transform="rotate({{ ($cx * 7) % 60 - 30 }} {{ $cx }} {{ $cy }})"/>
            @endforeach
        </svg>
        <div class="relative max-w-xl px-6 py-10 md:px-10 md:py-14">
            <h1 class="text-3xl font-extrabold leading-tight md:text-5xl">Quality nuts for a healthier tomorrow</h1>
            <p class="mt-3 text-nut-100">Fresh · Natural · Great value. Order online, then pick up at your local NuttyLand market stall.</p>
            <div class="mt-6 flex flex-wrap gap-3">
                <a href="{{ route('shop') }}" class="btn bg-white text-nut-800 hover:bg-nut-100">Shop now</a>
                <a href="{{ route('markets') }}" class="btn border border-white/40 text-white hover:bg-white/10">Find a market</a>
            </div>
        </div>
    </section>

    <section class="mt-8">
        <h2 class="sr-only">Categories</h2>
        <div class="flex gap-2 overflow-x-auto pb-1">
            @foreach ($categories as $category)
                <a href="{{ route('shop', ['category' => $category->slug]) }}" class="shrink-0 rounded-full border border-nut-200 bg-white px-4 py-2 text-sm font-semibold text-nut-800 hover:border-nut-400">
                    {{ $category->name }} <span class="text-nut-400">{{ $category->products_count }}</span>
                </a>
            @endforeach
        </div>
    </section>

    <section class="mt-8">
        <div class="mb-3 flex items-end justify-between">
            <h2 class="text-xl font-extrabold">Popular products</h2>
            <a href="{{ route('shop') }}" class="text-sm font-semibold text-nut-700 hover:underline">View all →</a>
        </div>
        <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4">
            @foreach ($featured as $product)
                @include('partials.product-card')
            @endforeach
        </div>
    </section>

    <section class="mt-10 grid gap-4 md:grid-cols-[1fr_2fr]">
        <div class="card bg-leaf-50 p-5">
            <h2 class="text-lg font-extrabold text-leaf-700">How Click &amp; Collect works</h2>
            <ol class="mt-3 space-y-2 text-sm text-nut-800">
                <li><b>1.</b> Add products to your cart.</li>
                <li><b>2.</b> Choose the market and day you'll visit.</li>
                <li><b>3.</b> Pay online – we pack your order.</li>
                <li><b>4.</b> Show your order number at the stall.</li>
            </ol>
        </div>
        <div class="card p-5">
            <h2 class="text-lg font-extrabold">Next markets</h2>
            <ul class="mt-3 divide-y divide-nut-100">
                @forelse ($nextMarkets as $day)
                    <li class="flex items-center justify-between py-2 text-sm">
                        <span class="font-semibold">{{ $day->location->name }}</span>
                        <span class="text-nut-600">{{ $day->date->format('D j M') }} · {{ $day->hours }}</span>
                    </li>
                @empty
                    <li class="py-2 text-sm text-nut-600">No upcoming markets scheduled yet.</li>
                @endforelse
            </ul>
        </div>
    </section>
@endsection
