<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** The ERD "Market" entity. The warehouse is stored as a location of type "warehouse". */
class Location extends Model
{
    use Auditable, HasFactory;

    protected $fillable = [
        'name', 'type', 'suburb', 'address', 'operating_days', 'default_opens_at', 'default_closes_at',
        'click_collect_enabled', 'is_confirmed', 'is_active',
    ];

    protected function casts(): array
    {
        return ['click_collect_enabled' => 'boolean', 'is_confirmed' => 'boolean', 'is_active' => 'boolean'];
    }

    public static function warehouse(): self
    {
        return static::where('type', 'warehouse')->orderBy('id')->firstOrFail();
    }

    public function scopeMarkets(Builder $query): Builder
    {
        return $query->where('type', 'market');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function marketDays(): HasMany
    {
        return $this->hasMany(MarketDay::class);
    }

    public function inventories(): HasMany
    {
        return $this->hasMany(Inventory::class);
    }

    public function sales(): HasMany
    {
        return $this->hasMany(Sale::class);
    }

    public function isWarehouse(): bool
    {
        return $this->type === 'warehouse';
    }
}
