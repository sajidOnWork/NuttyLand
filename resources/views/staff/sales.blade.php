@extends('layouts.staff', ['title' => 'Sales log'])

@section('content')
    <form method="get" class="mb-4 flex items-end gap-3">
        <div><label class="label" for="date">Date</label><input id="date" type="date" name="date" value="{{ $date->toDateString() }}" class="input" onchange="this.form.submit()"></div>
        <p class="pb-2 text-sm text-nut-600">{{ $location?->name ?? 'All markets' }} · {{ $sales->count() }} sales · <b>{{ \App\Support\Money::format((int) $sales->sum('total_cents')) }}</b></p>
    </form>
    <div class="card divide-y divide-nut-100">
        @forelse ($sales as $sale)
            <div class="p-3 text-sm">
                <div class="flex justify-between">
                    <b>{{ $sale->sold_at->format('g:i a') }} · {{ strtoupper($sale->payment_method) }}</b>
                    <b>{{ \App\Support\Money::format($sale->total_cents) }}</b>
                </div>
                <p class="text-nut-600">{{ $sale->items->map(fn ($i) => $i->quantity.' × '.$i->variant->product->name.' '.$i->variant->weight_label)->join(', ') }}</p>
                <p class="text-xs text-nut-500">{{ $sale->user?->name }} @if ($sale->recorded_offline)· <span class="text-amber-700">recorded offline</span>@endif</p>
            </div>
        @empty
            <p class="p-6 text-center text-sm text-nut-500">No sales recorded for this day.</p>
        @endforelse
    </div>
@endsection
