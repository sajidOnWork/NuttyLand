<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\Location;
use App\Models\MarketDay;
use App\Models\Product;
use App\Models\Sale;
use App\Services\SaleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Throwable;

/**
 * Staff Sales Module (FR-04): product-level sales at the stall.
 * The sell screen works offline: sales are queued on the device and posted
 * to sync() when the connection returns (TC-05).
 */
class StaffSalesController extends Controller
{
    public function sell(Request $request): View
    {
        return view('staff.sell', ['location' => StaffContext::location($request)]);
    }

    public function catalogue(): JsonResponse
    {
        $products = Product::active()->with(['activeVariants', 'category'])->orderBy('name')->get()
            ->map(fn (Product $p) => [
                'id' => $p->id,
                'name' => $p->name,
                'category' => $p->category->name,
                'variants' => $p->activeVariants->map(fn ($v) => [
                    'id' => $v->id,
                    'sku' => $v->sku,
                    'label' => $v->weight_label,
                    'price_cents' => $v->price_cents,
                ])->values(),
            ])->filter(fn ($p) => count($p['variants']) > 0)->values();

        $todayIds = MarketDay::whereDate('date', today())->where('status', 'scheduled')->pluck('location_id');

        return response()->json([
            'generated_at' => now()->toIso8601String(),
            'csrf_token' => csrf_token(),
            'products' => $products,
            'markets' => Location::markets()->active()->orderBy('name')->get()
                ->map(fn ($l) => ['id' => $l->id, 'name' => $l->name, 'trading_today' => $todayIds->contains($l->id)]),
        ]);
    }

    public function sync(Request $request, SaleService $sales): JsonResponse
    {
        $data = $request->validate([
            'sales' => ['required', 'array', 'min:1', 'max:200'],
            'sales.*.client_uuid' => ['required', 'uuid'],
            'sales.*.location_id' => ['required', 'integer', 'exists:locations,id'],
            'sales.*.payment_method' => ['required', 'in:cash,eftpos'],
            'sales.*.sold_at' => ['nullable', 'date'],
            'sales.*.recorded_offline' => ['nullable', 'boolean'],
            'sales.*.items' => ['required', 'array', 'min:1'],
            'sales.*.items.*.product_variant_id' => ['required', 'integer', 'exists:product_variants,id'],
            'sales.*.items.*.quantity' => ['required', 'integer', 'min:1', 'max:100'],
        ]);

        $results = [];
        foreach ($data['sales'] as $sale) {
            try {
                [$record, $created] = $sales->record($sale, $request->user());
                $results[] = ['client_uuid' => $sale['client_uuid'], 'status' => $created ? 'created' : 'duplicate', 'sale_id' => $record->id, 'total_cents' => $record->total_cents];
            } catch (Throwable $e) {
                report($e);
                $results[] = ['client_uuid' => $sale['client_uuid'], 'status' => 'error', 'message' => $e->getMessage()];
            }
        }

        return response()->json(['results' => $results]);
    }

    public function index(Request $request): View
    {
        $location = StaffContext::location($request);
        $date = $request->date('date') ?? today();

        $sales = Sale::with(['items.variant.product', 'user'])
            ->when($location, fn ($q) => $q->where('location_id', $location->id))
            ->whereDate('sold_at', $date)
            ->latest('sold_at')
            ->get();

        return view('staff.sales', ['location' => $location, 'sales' => $sales, 'date' => $date]);
    }
}
