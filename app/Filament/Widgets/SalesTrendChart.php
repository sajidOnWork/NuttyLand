<?php

namespace App\Filament\Widgets;

use App\Services\ReportService;
use Carbon\Carbon;
use Filament\Widgets\ChartWidget;

class SalesTrendChart extends ChartWidget
{
    protected static ?int $sort = 2;

    protected ?string $heading = 'Daily sales – last 4 weeks';

    protected int|string|array $columnSpan = 'full';

    protected ?string $maxHeight = '260px';

    protected function getData(): array
    {
        $daily = (new ReportService(today()->subDays(27), today()))->daily();

        return [
            'datasets' => [
                ['label' => 'Market stalls ($)', 'data' => $daily->map(fn ($d) => $d['market_revenue_cents'] / 100), 'backgroundColor' => '#a86b35', 'borderColor' => '#a86b35'],
                ['label' => 'Online orders ($)', 'data' => $daily->map(fn ($d) => $d['online_revenue_cents'] / 100), 'backgroundColor' => '#2f7a3b', 'borderColor' => '#2f7a3b'],
            ],
            'labels' => $daily->map(fn ($d) => Carbon::parse($d['date'])->format('D j M')),
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getOptions(): array
    {
        return ['scales' => ['x' => ['stacked' => true], 'y' => ['stacked' => true]]];
    }
}
