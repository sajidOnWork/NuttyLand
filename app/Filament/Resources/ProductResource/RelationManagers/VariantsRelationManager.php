<?php

namespace App\Filament\Resources\ProductResource\RelationManagers;

use App\Models\Location;
use App\Models\ProductVariant;
use App\Support\Money;
use Filament\Actions\CreateAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/** Pack sizes / weights and prices for a product. */
class VariantsRelationManager extends RelationManager
{
    protected static string $relationship = 'variants';

    protected static ?string $title = 'Sizes & prices';

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('weight_grams')->label('Weight (g)')->numeric()->required()->minValue(1),
            TextInput::make('price')->label('Price ($)')->numeric()->required()->minValue(0)->step(0.05)
                ->formatStateUsing(fn (?ProductVariant $record) => $record ? number_format($record->price_cents / 100, 2, '.', '') : null),
            TextInput::make('sku')->required()->unique(ignoreRecord: true),
            TextInput::make('low_stock_threshold')->numeric()->default(10)->required()->helperText('Alert when stock at a location falls to this level.'),
            Toggle::make('is_active')->default(true),
        ]);
    }

    public function table(Table $table): Table
    {
        $warehouse = Location::where('type', 'warehouse')->value('id');

        return $table
            ->defaultSort('weight_grams')
            ->columns([
                TextColumn::make('weight_grams')->label('Size')->formatStateUsing(fn ($state) => Money::weight($state)),
                TextColumn::make('price_cents')->label('Price')->formatStateUsing(fn ($state) => Money::format($state)),
                TextColumn::make('sku'),
                TextColumn::make('warehouse_stock')->label('Warehouse stock')
                    ->getStateUsing(fn (ProductVariant $record) => $record->stockAt($warehouse)),
                TextColumn::make('low_stock_threshold')->label('Alert at'),
                IconColumn::make('is_active')->boolean(),
            ])
            ->headerActions([
                CreateAction::make()->using(fn (array $data) => $this->save(null, $data)),
            ])
            ->recordActions([
                EditAction::make()->using(fn (Model $record, array $data) => $this->save($record, $data)),
            ]);
    }

    /** Prices are entered in dollars and stored as cents. */
    private function save(?ProductVariant $record, array $data): ProductVariant
    {
        $data['price_cents'] = Money::toCents($data['price'] ?? 0);
        unset($data['price']);

        if ($record) {
            $record->update($data);

            return $record;
        }

        return $this->getOwnerRecord()->variants()->create($data);
    }

    public function isReadOnly(): bool
    {
        return ! auth()->user()?->isOwner();
    }
}
