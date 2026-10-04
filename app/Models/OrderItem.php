<?php

namespace App\Models;

use App\Support\Money;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderItem extends Model
{
    protected $fillable = ['order_id', 'product_variant_id', 'product_name', 'weight_grams', 'quantity', 'unit_price_cents', 'subtotal_cents'];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }

    public function getWeightLabelAttribute(): string
    {
        return Money::weight($this->weight_grams);
    }
}
