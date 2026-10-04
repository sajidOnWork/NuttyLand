<?php

namespace App\Filament\Widgets;

use App\Services\ReportService;
use Filament\Widgets\ChartWidget;

class MarketComparisonChart extends ChartWidget
{
    protected static ?int $sort = 3;

    protected ?string $heading = 'Revenue by market – last 4 weeks';

    protected ?string $maxHeight = '260px';

    protected function getData(): array
    {
        $rows = (new ReportService(today()->subDays(27), today()))->byMarket()->filter(fn ($r) => $r['total_revenue_cents'] > 0);

        return [
            'datasets' => [
                ['label' => 'Revenue ($)', 'data' => $rows->map(fn ($r) => $r['total_revenue_cents'] / 100)->values(), 'backgroundColor' => '#8a5226'],
            ],
            'labels' => $rows->pluck('market')->values(),
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getOptions(): array
    {
        return ['indexAxis' => 'y', 'plugins' => ['legend' => ['display' => false]]];
    }
}
