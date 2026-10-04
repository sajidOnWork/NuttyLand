<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Support\Money;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProductVariant extends Model
{
    use Auditable, HasFactory;

    protected $fillable = ['product_id', 'sku', 'weight_grams', 'price_cents', 'low_stock_threshold', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function inventories(): HasMany
    {
        return $this->hasMany(Inventory::class);
    }

    public function stockAt(Location|int $location): int
    {
        $id = $location instanceof Location ? $location->id : $location;

        return (int) $this->inventories()->where('location_id', $id)->value('quantity');
    }

    public function getWeightLabelAttribute(): string
    {
        return Money::weight($this->weight_grams);
    }

    public function getPriceAttribute(): string
    {
        return Money::format($this->price_cents);
    }

    public function getDisplayNameAttribute(): string
    {
        return $this->product->name.' – '.$this->weight_label;
    }
}
