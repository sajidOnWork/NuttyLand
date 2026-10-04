<?php

namespace App\Filament\Concerns;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Role-based access for admin resources (report §13.1).
 * Owners manage everything; marketing staff may view the resources listed in $marketingCanView.
 */
trait RoleAccess
{
    protected static function user(): ?User
    {
        return auth()->user();
    }

    protected static function isOwner(): bool
    {
        return (bool) static::user()?->isOwner();
    }

    public static function canViewAny(): bool
    {
        return static::isOwner() || (static::$marketingCanView ?? false) && static::user()?->hasRole(User::ROLE_MARKETING);
    }

    public static function canView(Model $record): bool
    {
        return static::canViewAny();
    }

    public static function canCreate(): bool
    {
        return static::isOwner();
    }

    public static function canEdit(Model $record): bool
    {
        return static::isOwner();
    }

    public static function canDelete(Model $record): bool
    {
        return static::isOwner();
    }

    public static function canDeleteAny(): bool
    {
        return static::isOwner();
    }
}
