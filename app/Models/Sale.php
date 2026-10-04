<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** A product-level sale recorded by staff at a market stall (ERD "Sale"). */
class Sale extends Model
{
    protected $fillable = ['client_uuid', 'location_id', 'market_day_id', 'user_id', 'payment_method', 'total_cents', 'sold_at', 'recorded_offline'];

    protected function casts(): array
    {
        return ['sold_at' => 'datetime', 'recorded_offline' => 'boolean'];
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function marketDay(): BelongsTo
    {
        return $this->belongsTo(MarketDay::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(SaleItem::class);
    }
}
