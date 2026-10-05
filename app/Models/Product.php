<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends Model
{
    use Auditable, HasFactory;

    public const ROAST_STYLES = [
        'raw' => 'Raw',
        'dry_roasted' => 'Dry roasted',
        'roasted_salted' => 'Roasted & salted',
        'activated' => 'Activated',
        'other' => 'Other',
    ];

    protected $fillable = [
        'category_id', 'supplier_id', 'name', 'slug', 'description', 'ingredients', 'allergen_info',
        'flavour', 'roast_style', 'is_organic', 'country_of_origin', 'image_path', 'is_featured', 'status',
        'allergen_approved_by', 'allergen_approved_at',
    ];

    protected function casts(): array
    {
        return [
            'is_organic' => 'boolean',
            'is_featured' => 'boolean',
            'allergen_approved_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        // Changing ingredients or allergen text invalidates the owner's approval.
        static::updating(function (Product $product) {
            if ($product->isDirty(['ingredients', 'allergen_info']) && ! $product->isDirty('allergen_approved_at')) {
                $product->allergen_approved_at = null;
                $product->allergen_approved_by = null;
            }
        });
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class)->orderBy('weight_grams');
    }

    public function activeVariants(): HasMany
    {
        return $this->variants()->where('is_active', true);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }

    public function getImageUrlAttribute(): ?string
    {
        return $this->image_path ? route('media', ['path' => $this->image_path]) : null;
    }

    public function getRoastLabelAttribute(): ?string
    {
        return $this->roast_style ? self::ROAST_STYLES[$this->roast_style] ?? $this->roast_style : null;
    }

    public function getFromPriceCentsAttribute(): ?int
    {
        return $this->activeVariants->min('price_cents');
    }
}
