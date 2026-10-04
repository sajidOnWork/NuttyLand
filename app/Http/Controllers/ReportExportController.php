<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\ReportService;
use App\Support\Money;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** CSV exports of the management reports (owner and marketing only). */
class ReportExportController extends Controller
{
    public function __invoke(Request $request, string $type): StreamedResponse
    {
        abort_unless($request->user()?->hasRole(User::ROLE_OWNER, User::ROLE_MARKETING), 403);

        $filters = $request->validate([
            'from' => ['nullable', 'date'], 'to' => ['nullable', 'date'],
            'location_id' => ['nullable', 'integer'], 'category_id' => ['nullable', 'integer'],
        ]);
        $report = ReportService::fromArray($filters);

        [$header, $rows] = match ($type) {
            'products' => [
                ['Product', 'Size', 'SKU', 'Category', 'Stall units', 'Online units', 'Total units', 'Revenue (AUD)'],
                $report->byProduct()->map(fn ($r) => [$r['product'], $r['size'], $r['sku'], $r['category'], $r['market_units'], $r['online_units'], $r['units'], number_format($r['revenue_cents'] / 100, 2, '.', '')]),
            ],
            'daily' => [
                ['Date', 'Stall revenue (AUD)', 'Online revenue (AUD)', 'Total (AUD)'],
                $report->daily()->map(fn ($r) => [$r['date'], number_format($r['market_revenue_cents'] / 100, 2, '.', ''), number_format($r['online_revenue_cents'] / 100, 2, '.', ''), number_format($r['total_revenue_cents'] / 100, 2, '.', '')]),
            ],
            default => abort(404),
        };

        $filename = "nuttyland-{$type}-{$report->from->toDateString()}-to-{$report->to->toDateString()}.csv";

        return response()->streamDownload(function () use ($header, $rows) {
            $out = fopen('php://output', 'w');
            fputcsv($out, $header);
            foreach ($rows as $row) {
                fputcsv($out, $row);
            }
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv']);
    }
}
