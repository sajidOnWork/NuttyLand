<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Current stock of one product variant at one location. Change it only through InventoryService. */
class Inventory extends Model
{
    protected $fillable = ['product_variant_id', 'location_id', 'quantity'];

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function isLow(): bool
    {
        return $this->quantity <= $this->variant->low_stock_threshold;
    }
}
