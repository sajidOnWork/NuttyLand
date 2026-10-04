<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\RoleAccess;
use App\Filament\Resources\InventoryResource\Pages;
use App\Models\Inventory;
use App\Models\Location;
use App\Services\InsufficientStockException;
use App\Services\InventoryService;
use App\Support\Money;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/** Warehouse and market stock levels (FR-05, FR-06). Changes go through InventoryService. */
class InventoryResource extends Resource
{
    use RoleAccess;

    protected static ?string $model = Inventory::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArchiveBox;

    protected static string|UnitEnum|null $navigationGroup = 'Markets & stock';

    protected static ?string $navigationLabel = 'Stock levels';

    protected static ?string $modelLabel = 'stock level';

    protected static ?int $navigationSort = 2;

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getNavigationBadge(): ?string
    {
        $low = Inventory::join('product_variants', 'product_variants.id', '=', 'inventories.product_variant_id')
            ->whereColumn('inventories.quantity', '<=', 'product_variants.low_stock_threshold')->count();

        return $low ? (string) $low : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'danger';
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['variant.product', 'location'])
                ->join('product_variants', 'product_variants.id', '=', 'inventories.product_variant_id')
                ->join('products', 'products.id', '=', 'product_variants.product_id')
                ->select('inventories.*', 'product_variants.low_stock_threshold'))
            ->defaultSort('products.name')
            ->columns([
                TextColumn::make('variant.product.name')->label('Product')->searchable(query: fn (Builder $query, string $search) => $query->where('products.name', 'like', "%{$search}%"))->weight('bold'),
                TextColumn::make('variant.weight_grams')->label('Size')->formatStateUsing(fn ($state) => Money::weight($state)),
                TextColumn::make('variant.sku')->label('SKU')->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('location.name')->label('Location')->sortable(),
                TextColumn::make('quantity')->sortable()->weight('bold')
                    ->color(fn (Inventory $record) => $record->quantity <= $record->low_stock_threshold ? 'danger' : null),
                TextColumn::make('low_stock_threshold')->label('Alert at'),
                TextColumn::make('updated_at')->label('Last updated')->since()->sortable(),
            ])
            ->filters([
                SelectFilter::make('location_id')->label('Location')->options(fn () => Location::orderBy('name')->pluck('name', 'id')),
                Filter::make('low')->label('Low stock only')->query(fn (Builder $query) => $query->whereColumn('inventories.quantity', '<=', 'product_variants.low_stock_threshold')),
            ])
            ->recordActions([
                Action::make('transfer')->icon(Heroicon::OutlinedArrowsRightLeft)
                    ->schema(fn (Inventory $record) => [
                        Select::make('to_location_id')->label('Transfer to')->required()
                            ->options(Location::where('is_active', true)->whereKeyNot($record->location_id)->pluck('name', 'id')),
                        TextInput::make('quantity')->numeric()->minValue(1)->required(),
                        TextInput::make('reason'),
                    ])
                    ->action(fn (Inventory $record, array $data) => static::run(fn () => app(InventoryService::class)
                        ->transfer($record->product_variant_id, $record->location_id, (int) $data['to_location_id'], (int) $data['quantity'], auth()->user(), $data['reason'] ?? null), 'Transfer recorded')),
                Action::make('adjust')->icon(Heroicon::OutlinedPencilSquare)
                    ->schema([
                        Select::make('type')->required()->default('stocktake')->options([
                            'stocktake' => 'Physical count – set quantity to',
                            'receipt' => 'Delivery received – add',
                            'damaged' => 'Damaged / unsellable – remove',
                        ]),
                        TextInput::make('quantity')->numeric()->minValue(0)->required(),
                        TextInput::make('reason'),
                    ])
                    ->action(function (Inventory $record, array $data) {
                        $svc = app(InventoryService::class);
                        $qty = (int) $data['quantity'];
                        static::run(fn () => match ($data['type']) {
                            'stocktake' => $svc->stocktake($record->product_variant_id, $record->location_id, $qty, auth()->user(), $data['reason'] ?? null),
                            'receipt' => $svc->move($record->product_variant_id, $record->location_id, $qty, 'receipt', null, auth()->user(), $data['reason'] ?? 'Stock received'),
                            'damaged' => $svc->move($record->product_variant_id, $record->location_id, -$qty, 'damaged', null, auth()->user(), $data['reason'] ?? 'Damaged'),
                        }, 'Stock updated');
                    }),
            ]);
    }

    private static function run(callable $fn, string $success): void
    {
        try {
            $fn();
            Notification::make()->title($success)->success()->send();
        } catch (InsufficientStockException|\InvalidArgumentException $e) {
            Notification::make()->title($e->getMessage())->danger()->send();
        }
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ManageInventories::route('/')];
    }
}
