<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable implements FilamentUser
{
    /** @use HasFactory<UserFactory> */
    use Auditable, HasFactory, Notifiable;

    public const ROLE_CUSTOMER = 'customer';
    public const ROLE_STAFF = 'staff';
    public const ROLE_MARKETING = 'marketing';
    public const ROLE_OWNER = 'owner';

    public const ROLES = [
        self::ROLE_CUSTOMER => 'Customer',
        self::ROLE_STAFF => 'Market / sales staff',
        self::ROLE_MARKETING => 'Marketing staff',
        self::ROLE_OWNER => 'Owner / manager',
    ];

    /** Mirror the database defaults so new models behave correctly before being re-read. */
    protected $attributes = ['role' => self::ROLE_CUSTOMER, 'is_active' => true];

    protected $fillable = ['name', 'email', 'phone', 'password', 'role', 'is_active'];

    protected $hidden = ['password', 'remember_token'];

    /** Fields never written to the audit log. */
    public array $auditExclude = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
        ];
    }

    public function customer(): HasOne
    {
        return $this->hasOne(Customer::class);
    }

    public function sales(): HasMany
    {
        return $this->hasMany(Sale::class);
    }

    public function hasRole(string ...$roles): bool
    {
        return in_array($this->role, $roles, true);
    }

    public function isOwner(): bool
    {
        return $this->role === self::ROLE_OWNER;
    }

    public function isStaff(): bool
    {
        return $this->role === self::ROLE_STAFF;
    }

    /** Staff screens (market sales, Click & Collect, stock) – staff and owner. */
    public function canUseStaffScreens(): bool
    {
        return $this->is_active && $this->hasRole(self::ROLE_STAFF, self::ROLE_OWNER);
    }

    /** Back-office admin panel – owner (full) and marketing (limited). */
    public function canAccessPanel(Panel $panel): bool
    {
        return $this->is_active && $this->hasRole(self::ROLE_OWNER, self::ROLE_MARKETING);
    }
}
