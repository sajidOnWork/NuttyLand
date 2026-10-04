<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\Location;
use App\Models\ProductVariant;
use App\Models\StockMovement;
use App\Services\InsufficientStockException;
use App\Services\InventoryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Inventory Module (FR-05/FR-06): stock view, transfers, returns, damaged stock and stocktakes. */
class StaffStockController extends Controller
{
    public function index(Request $request): View
    {
        $locations = Location::active()->orderByRaw("type = 'warehouse' desc")->orderBy('name')->get();
        $location = $request->filled('location')
            ? $locations->firstWhere('id', (int) $request->input('location'))
            : (StaffContext::location($request) ?? $locations->first());

        $search = trim((string) $request->input('q'));

        $variants = ProductVariant::with(['product', 'inventories' => fn ($q) => $q->where('location_id', $location?->id)])
            ->where('is_active', true)
            ->whereHas('product', fn ($q) => $q->where('status', 'active')
                ->when($search, fn ($q) => $q->where('name', 'like', "%{$search}%")))
            ->join('products', 'products.id', '=', 'product_variants.product_id')
            ->orderBy('products.name')->orderBy('weight_grams')
            ->select('product_variants.*')
            ->get()
            ->map(function ($v) {
                $v->qty = (int) optional($v->inventories->first())->quantity;
                $v->is_low = $v->qty <= $v->low_stock_threshold;

                return $v;
            });

        if ($request->boolean('low')) {
            $variants = $variants->where('is_low', true);
        }

        return view('staff.stock', [
            'locations' => $locations,
            'location' => $location,
            'variants' => $variants,
            'recent' => StockMovement::with(['variant.product', 'location', 'user'])->where('location_id', $location?->id)->latest('id')->take(15)->get(),
        ]);
    }

    public function transfer(Request $request, InventoryService $inventory): RedirectResponse
    {
        $data = $request->validate([
            'product_variant_id' => ['required', 'integer', 'exists:product_variants,id'],
            'from_location_id' => ['required', 'integer', 'exists:locations,id', 'different:to_location_id'],
            'to_location_id' => ['required', 'integer', 'exists:locations,id'],
            'quantity' => ['required', 'integer', 'min:1', 'max:10000'],
            'reason' => ['nullable', 'string', 'max:255'],
        ]);

        try {
            $inventory->transfer($data['product_variant_id'], $data['from_location_id'], $data['to_location_id'], $data['quantity'], $request->user(), $data['reason'] ?? null);
        } catch (InsufficientStockException $e) {
            return back()->withErrors(['stock' => $e->getMessage()])->withInput();
        }

        return back()->with('status', 'Transfer recorded.');
    }

    public function adjust(Request $request, InventoryService $inventory): RedirectResponse
    {
        $data = $request->validate([
            'product_variant_id' => ['required', 'integer', 'exists:product_variants,id'],
            'location_id' => ['required', 'integer', 'exists:locations,id'],
            'type' => ['required', 'in:receipt,damaged,stocktake'],
            'quantity' => ['required', 'integer', 'min:0', 'max:100000'],
            'reason' => ['nullable', 'string', 'max:255'],
        ]);

        try {
            match ($data['type']) {
                'receipt' => $inventory->move($data['product_variant_id'], $data['location_id'], $data['quantity'], 'receipt', null, $request->user(), $data['reason'] ?? 'Stock received'),
                'damaged' => $inventory->move($data['product_variant_id'], $data['location_id'], -$data['quantity'], 'damaged', null, $request->user(), $data['reason'] ?? 'Damaged / unsellable'),
                'stocktake' => $inventory->stocktake($data['product_variant_id'], $data['location_id'], $data['quantity'], $request->user(), $data['reason'] ?? null),
            };
        } catch (InsufficientStockException $e) {
            return back()->withErrors(['stock' => $e->getMessage()])->withInput();
        }

        return back()->with('status', 'Stock updated.');
    }
}
