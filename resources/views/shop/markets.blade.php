@extends('layouts.shop', ['title' => 'Our markets'])

@section('content')
    <h1 class="text-2xl font-extrabold">Find NuttyLand at a market</h1>
    <p class="mt-1 text-nut-600">Order online and collect at any of these markets.</p>
    <div class="mt-5 grid gap-4 sm:grid-cols-2">
        @foreach ($markets as $market)
            <article class="card p-4">
                <h2 class="text-lg font-extrabold">{{ $market->name }}</h2>
                <p class="text-sm text-nut-600">{{ $market->suburb }} · usually {{ $market->operating_days }}</p>
                <ul class="mt-3 space-y-1 text-sm">
                    @forelse ($market->marketDays as $day)
                        <li class="flex justify-between"><span>{{ $day->date->format('D j M') }}</span><span class="text-nut-600">{{ $day->hours }}</span></li>
                    @empty
                        <li class="text-nut-500">No upcoming dates yet.</li>
                    @endforelse
                </ul>
            </article>
        @endforeach
    </div>
@endsection
