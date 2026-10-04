<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/** Ledger entry for every stock change. Inventory.quantity is the running total of these. */
class StockMovement extends Model
{
    public const UPDATED_AT = null;

    public const TYPES = [
        'receipt' => 'Stock received',
        'market_sale' => 'Market sale',
        'online_order' => 'Online order',
        'order_cancelled' => 'Order cancelled (returned)',
        'transfer_out' => 'Transfer out',
        'transfer_in' => 'Transfer in',
        'return' => 'Unsold stock returned',
        'damaged' => 'Damaged / written off',
        'stocktake' => 'Stocktake correction',
        'adjustment' => 'Manual adjustment',
    ];

    protected $fillable = [
        'product_variant_id', 'location_id', 'quantity_change', 'quantity_after', 'type',
        'reference_type', 'reference_id', 'transfer_group', 'reason', 'user_id',
    ];

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function reference(): MorphTo
    {
        return $this->morphTo();
    }
}
