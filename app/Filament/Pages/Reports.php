<?php

namespace App\Filament\Pages;

use App\Models\Category;
use App\Models\Location;
use App\Models\User;
use App\Services\ReportService;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

/** Management reports with filters by date, market and category, plus CSV export (FR-07). */
class Reports extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChartBar;

    protected static string|UnitEnum|null $navigationGroup = 'Reports';

    protected static ?string $navigationLabel = 'Sales reports';

    protected static ?string $title = 'Sales reports';

    protected string $view = 'filament.pages.reports';

    public array $filters = [];

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->hasRole(User::ROLE_OWNER, User::ROLE_MARKETING);
    }

    public function mount(): void
    {
        $this->filters = [
            'from' => request()->query('from', today()->subDays(27)->toDateString()),
            'to' => request()->query('to', today()->toDateString()),
            'location_id' => request()->query('location_id'),
            'category_id' => request()->query('category_id'),
        ];
    }

    protected function getViewData(): array
    {
        $report = ReportService::fromArray($this->filters);

        return [
            'report' => $report,
            'summary' => $report->summary(),
            'daily' => $report->daily(),
            'markets' => $report->byMarket(),
            'products' => $report->byProduct(),
            'categories' => $report->byCategory(),
            'statuses' => $report->orderStatus(),
            'lowStock' => $report->lowStock(),
            'locationOptions' => Location::where('type', '!=', 'warehouse')->orderBy('name')->pluck('name', 'id'),
            'categoryOptions' => Category::orderBy('sort_order')->pluck('name', 'id'),
            'exportQuery' => http_build_query(array_filter($this->filters)),
        ];
    }
}
