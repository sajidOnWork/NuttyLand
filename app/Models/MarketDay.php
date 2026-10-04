<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** One scheduled trading day at a market (market schedule). */
class MarketDay extends Model
{
    use Auditable, HasFactory;

    protected $fillable = ['location_id', 'date', 'opens_at', 'closes_at', 'status', 'notes'];

    protected function casts(): array
    {
        return ['date' => 'date'];
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    /** Market days customers can choose for Click & Collect (tomorrow onwards, scheduled). */
    public function scopeCollectable(Builder $query): Builder
    {
        return $query->where('status', 'scheduled')
            ->whereDate('date', '>', today())
            ->whereHas('location', fn ($q) => $q->where('is_active', true)->where('click_collect_enabled', true)->where('is_confirmed', true));
    }

    public function getLabelAttribute(): string
    {
        return $this->location->name.' – '.$this->date->format('D j M').' ('.$this->hours.')';
    }

    public function getHoursAttribute(): string
    {
        return Carbon::parse($this->opens_at)->format('g:ia').' – '.Carbon::parse($this->closes_at)->format('g:ia');
    }
}
