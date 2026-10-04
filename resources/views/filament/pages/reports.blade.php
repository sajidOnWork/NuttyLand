@php($m = fn ($c) => \App\Support\Money::format((int) $c))
@php($cell = 'padding:6px 8px;border-top:1px solid rgba(120,113,108,.2)')
<x-filament-panels::page>
    <x-filament::section>
        <form method="get" style="display:flex;flex-wrap:wrap;gap:12px;align-items:flex-end">
            <label style="display:grid;gap:4px;font-size:.85rem">From
                <x-filament::input.wrapper><x-filament::input type="date" name="from" value="{{ $filters['from'] }}" /></x-filament::input.wrapper>
            </label>
            <label style="display:grid;gap:4px;font-size:.85rem">To
                <x-filament::input.wrapper><x-filament::input type="date" name="to" value="{{ $filters['to'] }}" /></x-filament::input.wrapper>
            </label>
            <label style="display:grid;gap:4px;font-size:.85rem">Market
                <x-filament::input.wrapper>
                    <x-filament::input.select name="location_id">
                        <option value="">All markets</option>
                        @foreach ($locationOptions as $id => $name)<option value="{{ $id }}" @selected($filters['location_id'] == $id)>{{ $name }}</option>@endforeach
                    </x-filament::input.select>
                </x-filament::input.wrapper>
            </label>
            <label style="display:grid;gap:4px;font-size:.85rem">Category
                <x-filament::input.wrapper>
                    <x-filament::input.select name="category_id">
                        <option value="">All categories</option>
                        @foreach ($categoryOptions as $id => $name)<option value="{{ $id }}" @selected($filters['category_id'] == $id)>{{ $name }}</option>@endforeach
                    </x-filament::input.select>
                </x-filament::input.wrapper>
            </label>
            <x-filament::button type="submit">Apply</x-filament::button>
            <x-filament::button tag="a" color="gray" href="{{ route('reports.export', 'products') }}?{{ $exportQuery }}" icon="heroicon-o-arrow-down-tray">Product sales CSV</x-filament::button>
            <x-filament::button tag="a" color="gray" href="{{ route('reports.export', 'daily') }}?{{ $exportQuery }}" icon="heroicon-o-arrow-down-tray">Daily summary CSV</x-filament::button>
        </form>
        <p style="margin-top:8px;font-size:.8rem;opacity:.7">{{ $report->from->format('j M Y') }} – {{ $report->to->format('j M Y') }}. Market = stall sales recorded by staff; Online = paid Click &amp; Collect orders (by collection market).</p>
    </x-filament::section>

    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(170px,1fr));gap:12px">
        @foreach ([['Total revenue', $m($summary['total_revenue_cents'])], ['Market stalls', $m($summary['market_revenue_cents'])], ['Online revenue', $m($summary['online_revenue_cents'])], ['Units sold', number_format($summary['units'])], ['Stall transactions', number_format($summary['market_transactions'])], ['Online order count', number_format($summary['online_orders'])]] as [$label, $value])
            <x-filament::section compact>
                <div style="font-size:.8rem;opacity:.7">{{ $label }}</div>
                <div style="font-size:1.5rem;font-weight:700">{{ $value }}</div>
            </x-filament::section>
        @endforeach
    </div>

    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(380px,1fr));gap:16px">
        <x-filament::section heading="Market comparison">
            <table style="width:100%;font-size:.875rem;border-collapse:collapse">
                <tr style="text-align:left;opacity:.7"><th style="padding:6px 8px">Market</th><th style="padding:6px 8px;text-align:right">Stall</th><th style="padding:6px 8px;text-align:right">Txns</th><th style="padding:6px 8px;text-align:right">Online</th><th style="padding:6px 8px;text-align:right">Total</th></tr>
                @foreach ($markets as $r)
                    <tr><td style="{{ $cell }};font-weight:600">{{ $r['market'] }}</td><td style="{{ $cell }};text-align:right">{{ $m($r['market_revenue_cents']) }}</td><td style="{{ $cell }};text-align:right">{{ $r['transactions'] }}</td><td style="{{ $cell }};text-align:right">{{ $m($r['online_revenue_cents']) }}</td><td style="{{ $cell }};text-align:right;font-weight:700">{{ $m($r['total_revenue_cents']) }}</td></tr>
                @endforeach
            </table>
        </x-filament::section>

        <x-filament::section heading="Sales by category">
            <table style="width:100%;font-size:.875rem;border-collapse:collapse">
                <tr style="text-align:left;opacity:.7"><th style="padding:6px 8px">Category</th><th style="padding:6px 8px;text-align:right">Units</th><th style="padding:6px 8px;text-align:right">Revenue</th></tr>
                @forelse ($categories as $r)
                    <tr><td style="{{ $cell }};font-weight:600">{{ $r['category'] }}</td><td style="{{ $cell }};text-align:right">{{ $r['units'] }}</td><td style="{{ $cell }};text-align:right">{{ $m($r['revenue_cents']) }}</td></tr>
                @empty
                    <tr><td colspan="3" style="{{ $cell }}">No sales in this period.</td></tr>
                @endforelse
            </table>
        </x-filament::section>
    </div>

    <x-filament::section heading="Product sales" description="Every product and size sold in the period, highest revenue first.">
        <div style="max-height:420px;overflow:auto">
            <table style="width:100%;font-size:.875rem;border-collapse:collapse">
                <tr style="text-align:left;opacity:.7;position:sticky;top:0"><th style="padding:6px 8px">Product</th><th style="padding:6px 8px">Size</th><th style="padding:6px 8px">Category</th><th style="padding:6px 8px;text-align:right">Stall units</th><th style="padding:6px 8px;text-align:right">Online units</th><th style="padding:6px 8px;text-align:right">Revenue</th></tr>
                @forelse ($products as $r)
                    <tr><td style="{{ $cell }};font-weight:600">{{ $r['product'] }}</td><td style="{{ $cell }}">{{ $r['size'] }}</td><td style="{{ $cell }}">{{ $r['category'] }}</td><td style="{{ $cell }};text-align:right">{{ $r['market_units'] }}</td><td style="{{ $cell }};text-align:right">{{ $r['online_units'] }}</td><td style="{{ $cell }};text-align:right;font-weight:700">{{ $m($r['revenue_cents']) }}</td></tr>
                @empty
                    <tr><td colspan="6" style="{{ $cell }}">No sales in this period.</td></tr>
                @endforelse
            </table>
        </div>
    </x-filament::section>

    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(380px,1fr));gap:16px">
        <x-filament::section heading="Daily sales summary">
            <div style="max-height:360px;overflow:auto">
                <table style="width:100%;font-size:.875rem;border-collapse:collapse">
                    <tr style="text-align:left;opacity:.7"><th style="padding:6px 8px">Date</th><th style="padding:6px 8px;text-align:right">Stall</th><th style="padding:6px 8px;text-align:right">Online</th><th style="padding:6px 8px;text-align:right">Total</th></tr>
                    @foreach ($daily->reverse() as $r)
                        @continue($r['total_revenue_cents'] === 0)
                        <tr><td style="{{ $cell }}">{{ \Carbon\Carbon::parse($r['date'])->format('D j M') }}</td><td style="{{ $cell }};text-align:right">{{ $m($r['market_revenue_cents']) }}</td><td style="{{ $cell }};text-align:right">{{ $m($r['online_revenue_cents']) }}</td><td style="{{ $cell }};text-align:right;font-weight:700">{{ $m($r['total_revenue_cents']) }}</td></tr>
                    @endforeach
                </table>
            </div>
        </x-filament::section>

        <div style="display:grid;gap:16px;align-content:start">
            <x-filament::section heading="Order status">
                <table style="width:100%;font-size:.875rem;border-collapse:collapse">
                    @forelse ($statuses as $s)
                        <tr><td style="{{ $cell }}">{{ \App\Models\Order::STATUSES[$s->status] ?? $s->status }}</td><td style="{{ $cell }};text-align:right">{{ $s->orders }}</td><td style="{{ $cell }};text-align:right">{{ $m($s->value_cents) }}</td></tr>
                    @empty
                        <tr><td style="{{ $cell }}">No orders placed in this period.</td></tr>
                    @endforelse
                </table>
            </x-filament::section>
            <x-filament::section heading="Low stock now" :description="$lowStock->count().' item(s) at or below alert level'">
                <div style="max-height:220px;overflow:auto">
                    <table style="width:100%;font-size:.875rem;border-collapse:collapse">
                        @foreach ($lowStock as $s)
                            <tr><td style="{{ $cell }}">{{ $s->product }} {{ \App\Support\Money::weight($s->weight_grams) }}</td><td style="{{ $cell }}">{{ $s->location }}</td><td style="{{ $cell }};text-align:right;color:#dc2626;font-weight:700">{{ $s->quantity }}</td></tr>
                        @endforeach
                    </table>
                </div>
            </x-filament::section>
        </div>
    </div>
</x-filament-panels::page>
