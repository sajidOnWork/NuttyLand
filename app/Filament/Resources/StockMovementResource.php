<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\RoleAccess;
use App\Filament\Resources\StockMovementResource\Pages;
use App\Models\Location;
use App\Models\StockMovement;
use App\Support\Money;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

/** Read-only stock ledger: every sale, transfer, adjustment and stocktake. */
class StockMovementResource extends Resource
{
    use RoleAccess;

    protected static ?string $model = StockMovement::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedQueueList;

    protected static string|UnitEnum|null $navigationGroup = 'Markets & stock';

    protected static ?string $navigationLabel = 'Stock movements';

    protected static ?int $navigationSort = 3;

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with(['variant.product', 'location', 'user']))
            ->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('created_at')->label('When')->dateTime('j M Y g:ia')->sortable(),
                TextColumn::make('variant.product.name')->label('Product')->searchable(),
                TextColumn::make('variant.weight_grams')->label('Size')->formatStateUsing(fn ($state) => Money::weight($state)),
                TextColumn::make('location.name')->label('Location'),
                TextColumn::make('type')->badge()->formatStateUsing(fn ($state) => StockMovement::TYPES[$state] ?? $state),
                TextColumn::make('quantity_change')->label('Change')->weight('bold')
                    ->formatStateUsing(fn ($state) => $state > 0 ? "+{$state}" : (string) $state)
                    ->color(fn ($state) => $state < 0 ? 'danger' : 'success'),
                TextColumn::make('quantity_after')->label('After'),
                TextColumn::make('user.name')->label('By')->placeholder('System'),
                TextColumn::make('reason')->limit(30)->toggleable(),
            ])
            ->filters([
                SelectFilter::make('type')->options(StockMovement::TYPES)->multiple(),
                SelectFilter::make('location_id')->label('Location')->options(fn () => Location::orderBy('name')->pluck('name', 'id')),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ManageStockMovements::route('/')];
    }
}
