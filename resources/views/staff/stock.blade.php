@extends('layouts.staff', ['title' => 'Stock'])

@section('content')
    <form method="get" class="mb-3 grid gap-2 sm:grid-cols-[1fr_1fr_auto]">
        <select name="location" class="input" onchange="this.form.submit()" aria-label="Location">
            @foreach ($locations as $l)
                <option value="{{ $l->id }}" @selected($location?->id === $l->id)>{{ $l->name }}</option>
            @endforeach
        </select>
        <input name="q" value="{{ request('q') }}" class="input" placeholder="Search product…" aria-label="Search product">
        <label class="flex items-center gap-2 text-sm font-semibold"><input type="checkbox" name="low" value="1" class="h-4 w-4 accent-nut-700" @checked(request()->boolean('low')) onchange="this.form.submit()"> Low stock only</label>
    </form>

    <div class="grid gap-4 lg:grid-cols-[2fr_1fr]">
        <div class="card overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-nut-50 text-left text-xs uppercase text-nut-600"><tr><th class="p-2">Product</th><th class="p-2">Size</th><th class="p-2 text-right">On hand</th><th class="p-2 text-right">Alert at</th></tr></thead>
                <tbody class="divide-y divide-nut-100">
                    @forelse ($variants as $v)
                        <tr class="{{ $v->is_low ? 'bg-red-50' : '' }}">
                            <td class="p-2 font-semibold">{{ $v->product->name }}</td>
                            <td class="p-2">{{ $v->weight_label }}</td>
                            <td class="p-2 text-right font-bold {{ $v->is_low ? 'text-red-700' : '' }}">{{ $v->qty }}</td>
                            <td class="p-2 text-right text-nut-500">{{ $v->low_stock_threshold }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="p-6 text-center text-nut-500">Nothing to show.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="space-y-4">
            @php($variantOptions = \App\Models\ProductVariant::with('product')->where('is_active', true)->get()->sortBy(fn ($v) => $v->product->name.str_pad($v->weight_grams, 5, '0', STR_PAD_LEFT)))
            <form method="post" action="{{ route('staff.stock.transfer') }}" class="card space-y-2 p-4">
                @csrf
                <h2 class="font-extrabold">Transfer stock</h2>
                <p class="text-xs text-nut-600">Warehouse → market before trading, or unsold stock back to the warehouse.</p>
                <select name="product_variant_id" class="input" required aria-label="Product">
                    @foreach ($variantOptions as $v)<option value="{{ $v->id }}">{{ $v->display_name }}</option>@endforeach
                </select>
                <div class="grid grid-cols-2 gap-2">
                    <select name="from_location_id" class="input" aria-label="From">@foreach ($locations as $l)<option value="{{ $l->id }}" @selected($l->isWarehouse())>From: {{ $l->name }}</option>@endforeach</select>
                    <select name="to_location_id" class="input" aria-label="To">@foreach ($locations as $l)<option value="{{ $l->id }}" @selected($location?->id === $l->id && ! $l->isWarehouse())>To: {{ $l->name }}</option>@endforeach</select>
                </div>
                <input type="number" name="quantity" min="1" class="input" placeholder="Quantity" required aria-label="Quantity">
                <button class="btn-primary w-full">Record transfer</button>
            </form>

            <form method="post" action="{{ route('staff.stock.adjust') }}" class="card space-y-2 p-4">
                @csrf
                <h2 class="font-extrabold">Adjust stock at {{ $location?->name }}</h2>
                <input type="hidden" name="location_id" value="{{ $location?->id }}">
                <select name="product_variant_id" class="input" required aria-label="Product">
                    @foreach ($variantOptions as $v)<option value="{{ $v->id }}">{{ $v->display_name }}</option>@endforeach
                </select>
                <select name="type" class="input" aria-label="Adjustment type">
                    <option value="stocktake">Physical count (set quantity to)</option>
                    <option value="damaged">Damaged / unsellable (remove)</option>
                    <option value="receipt">Delivery received (add)</option>
                </select>
                <input type="number" name="quantity" min="0" class="input" placeholder="Quantity" required aria-label="Quantity">
                <input name="reason" class="input" placeholder="Reason (optional)" aria-label="Reason">
                <button class="btn-primary w-full">Save adjustment</button>
            </form>

            <section class="card p-4">
                <h2 class="font-extrabold">Recent movements</h2>
                <ul class="mt-2 divide-y divide-nut-100 text-xs">
                    @forelse ($recent as $m)
                        <li class="py-1.5"><b class="{{ $m->quantity_change < 0 ? 'text-red-700' : 'text-leaf-700' }}">{{ $m->quantity_change > 0 ? '+' : '' }}{{ $m->quantity_change }}</b> {{ $m->variant->display_name }} · {{ \App\Models\StockMovement::TYPES[$m->type] ?? $m->type }} <span class="text-nut-500">· {{ $m->created_at?->diffForHumans() }}</span></li>
                    @empty
                        <li class="py-1.5 text-nut-500">No movements yet.</li>
                    @endforelse
                </ul>
            </section>
        </div>
    </div>
@endsection
