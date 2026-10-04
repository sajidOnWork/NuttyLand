<?php

namespace App\Filament\Widgets;

use App\Models\Inventory;
use App\Models\Order;
use App\Services\ReportService;
use App\Support\Money;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class StatsOverview extends StatsOverviewWidget
{
    protected static ?int $sort = 1;

    protected function getStats(): array
    {
        $today = (new ReportService(today(), today()))->summary();
        $week = (new ReportService(today()->subDays(6), today()))->summary();
        $prev = (new ReportService(today()->subDays(13), today()->subDays(7)))->summary();
        $change = $prev['total_revenue_cents'] > 0
            ? round(($week['total_revenue_cents'] - $prev['total_revenue_cents']) / $prev['total_revenue_cents'] * 100)
            : null;

        $low = Inventory::join('product_variants', 'product_variants.id', '=', 'inventories.product_variant_id')
            ->whereColumn('inventories.quantity', '<=', 'product_variants.low_stock_threshold')->count();

        return [
            Stat::make('Sales today', Money::format($today['total_revenue_cents']))
                ->description($today['market_transactions'].' stall sales · '.$today['online_orders'].' online orders'),
            Stat::make('Last 7 days', Money::format($week['total_revenue_cents']))
                ->description($change === null ? 'No previous week to compare' : ($change >= 0 ? "+{$change}%" : "{$change}%").' vs previous 7 days')
                ->color($change === null ? 'gray' : ($change >= 0 ? 'success' : 'danger')),
            Stat::make('Click & Collect to prepare', (string) Order::whereIn('status', ['confirmed', 'preparing'])->count())
                ->description(Order::where('status', 'ready')->count().' ready for collection'),
            Stat::make('Low-stock items', (string) $low)
                ->description('At or below their alert level')
                ->color($low ? 'danger' : 'success')
                ->url(\App\Filament\Resources\InventoryResource::getUrl('index', ['filters' => ['low' => ['isActive' => true]]])),
        ];
    }
}
