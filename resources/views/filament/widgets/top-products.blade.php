@php($cell = 'padding:6px 8px;border-top:1px solid rgba(120,113,108,.2)')
<x-filament-widgets::widget>
    <x-filament::section heading="Top products – last 4 weeks">
        <table style="width:100%;font-size:.875rem;border-collapse:collapse">
            <tr style="text-align:left;opacity:.7"><th style="padding:6px 8px">Product</th><th style="padding:6px 8px">Size</th><th style="padding:6px 8px;text-align:right">Units</th><th style="padding:6px 8px;text-align:right">Revenue</th></tr>
            @forelse ($rows as $row)
                <tr>
                    <td style="{{ $cell }};font-weight:600">{{ $row['product'] }}</td>
                    <td style="{{ $cell }}">{{ $row['size'] }}</td>
                    <td style="{{ $cell }};text-align:right">{{ $row['units'] }}</td>
                    <td style="{{ $cell }};text-align:right">{{ \App\Support\Money::format($row['revenue_cents']) }}</td>
                </tr>
            @empty
                <tr><td colspan="4" style="{{ $cell }};text-align:center;opacity:.7">No sales yet.</td></tr>
            @endforelse
        </table>
    </x-filament::section>
</x-filament-widgets::widget>
