<?php

namespace App\Filament\Widgets;

use App\Services\ReportService;
use Filament\Widgets\Widget;

class TopProducts extends Widget
{
    protected static ?int $sort = 4;

    protected string $view = 'filament.widgets.top-products';

    protected function getViewData(): array
    {
        return ['rows' => (new ReportService(today()->subDays(27), today()))->byProduct(8)];
    }
}
