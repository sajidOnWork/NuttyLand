<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Location;
use App\Models\MarketDay;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Customer catalogue (FR-01). */
class CatalogueController extends Controller
{
    public function home(): View
    {
        return view('shop.home', [
            'featured' => Product::active()->where('is_featured', true)->with(['activeVariants', 'category'])->take(8)->get(),
            'categories' => Category::where('is_active', true)->orderBy('sort_order')->withCount(['products' => fn ($q) => $q->active()])->get(),
            'nextMarkets' => MarketDay::collectable()->with('location')->orderBy('date')->take(4)->get(),
        ]);
    }

    public function index(Request $request): View
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'category' => ['nullable', 'string', 'max:100'],
            'organic' => ['nullable', 'boolean'],
            'roast' => ['nullable', 'in:'.implode(',', array_keys(Product::ROAST_STYLES))],
            'sort' => ['nullable', 'in:name,price_asc,price_desc'],
        ]);

        $products = $this->search($filters)
            ->with(['activeVariants', 'category'])
            ->paginate(24)
            ->withQueryString();

        return view('shop.index', [
            'products' => $products,
            'categories' => Category::where('is_active', true)->orderBy('sort_order')->get(),
            'filters' => $filters,
        ]);
    }

    /** Shared search logic – also covered by unit tests. */
    public function search(array $filters)
    {
        $query = Product::active()->whereHas('activeVariants');

        if ($term = trim($filters['q'] ?? '')) {
            $query->where(function ($q) use ($term) {
                $q->where('name', 'like', "%{$term}%")
                    ->orWhere('flavour', 'like', "%{$term}%")
                    ->orWhere('description', 'like', "%{$term}%")
                    ->orWhereHas('category', fn ($c) => $c->where('name', 'like', "%{$term}%"));
            });
        }
        if (! empty($filters['category'])) {
            $query->whereHas('category', fn ($c) => $c->where('slug', $filters['category']));
        }
        if (! empty($filters['organic'])) {
            $query->where('is_organic', true);
        }
        if (! empty($filters['roast'])) {
            $query->where('roast_style', $filters['roast']);
        }

        $minPrice = '(select min(price_cents) from product_variants where product_variants.product_id = products.id and product_variants.is_active = 1)';

        return match ($filters['sort'] ?? 'name') {
            'price_asc' => $query->orderByRaw("{$minPrice} asc"),
            'price_desc' => $query->orderByRaw("{$minPrice} desc"),
            default => $query->orderBy('name'),
        };
    }

    public function show(Product $product): View
    {
        abort_unless($product->status === 'active', 404);

        $warehouse = Location::warehouse();
        $product->load(['activeVariants.inventories' => fn ($q) => $q->where('location_id', $warehouse->id), 'category']);

        return view('shop.product', [
            'product' => $product,
            'related' => Product::active()->where('category_id', $product->category_id)->whereKeyNot($product->id)->with('activeVariants')->take(4)->get(),
        ]);
    }

    public function markets(): View
    {
        return view('shop.markets', [
            'markets' => Location::markets()->where('is_confirmed', true)->active()
                ->with(['marketDays' => fn ($q) => $q->where('status', 'scheduled')->whereDate('date', '>=', today())->orderBy('date')->take(4)])
                ->orderBy('name')->get(),
        ]);
    }
}
