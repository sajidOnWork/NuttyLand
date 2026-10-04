<?php

namespace App\Models;

use App\Support\Money;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Order extends Model
{
    public const STATUSES = [
        'pending_payment' => 'Awaiting payment',
        'confirmed' => 'Confirmed',
        'preparing' => 'Preparing',
        'ready' => 'Ready to collect',
        'collected' => 'Collected',
        'cancelled' => 'Cancelled',
    ];

    protected $fillable = [
        'order_number', 'customer_id', 'fulfilment_method', 'location_id', 'market_day_id', 'status',
        'payment_status', 'payment_reference', 'subtotal_cents', 'total_cents', 'notes', 'confirmed_at', 'collected_at',
    ];

    protected function casts(): array
    {
        return ['confirmed_at' => 'datetime', 'collected_at' => 'datetime'];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function marketDay(): BelongsTo
    {
        return $this->belongsTo(MarketDay::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function getStatusLabelAttribute(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }

    public function getTotalAttribute(): string
    {
        return Money::format($this->total_cents);
    }

    public function isOpen(): bool
    {
        return in_array($this->status, ['confirmed', 'preparing', 'ready'], true);
    }
}
