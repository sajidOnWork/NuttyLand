<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\RoleAccess;
use App\Filament\Resources\LocationResource\Pages;
use App\Filament\Resources\LocationResource\RelationManagers\MarketDaysRelationManager;
use App\Models\Location;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

/** Markets (ERD "Market"), the warehouse, and – later – a permanent store. */
class LocationResource extends Resource
{
    use RoleAccess;

    protected static ?string $model = Location::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedMapPin;

    protected static string|UnitEnum|null $navigationGroup = 'Markets & stock';

    protected static ?string $navigationLabel = 'Markets & schedule';

    protected static ?string $modelLabel = 'location';

    protected static ?int $navigationSort = 1;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()->columns(2)->schema([
                TextInput::make('name')->required(),
                Select::make('type')->options(['market' => 'Market stall', 'warehouse' => 'Warehouse', 'store' => 'Permanent store'])->default('market')->required(),
                TextInput::make('suburb'),
                TextInput::make('address'),
                TextInput::make('operating_days')->placeholder('e.g. Sat, Sun')->helperText('Comma-separated: Mon, Tue, Wed, Thu, Fri, Sat, Sun'),
                TimePicker::make('default_opens_at')->seconds(false),
                TimePicker::make('default_closes_at')->seconds(false),
                Toggle::make('is_confirmed')->label('Market confirmed')->default(true),
                Toggle::make('click_collect_enabled')->label('Offer Click & Collect')->default(true),
                Toggle::make('is_active')->default(true),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('type')
            ->columns([
                TextColumn::make('name')->weight('bold')->searchable(),
                TextColumn::make('type')->badge(),
                TextColumn::make('suburb'),
                TextColumn::make('operating_days')->label('Days'),
                TextColumn::make('upcoming')->label('Upcoming days')
                    ->getStateUsing(fn (Location $record) => $record->marketDays()->where('status', 'scheduled')->whereDate('date', '>=', today())->count()),
                IconColumn::make('click_collect_enabled')->boolean()->label('C&C'),
                IconColumn::make('is_confirmed')->boolean()->label('Confirmed'),
                IconColumn::make('is_active')->boolean(),
            ])
            ->recordActions([EditAction::make()]);
    }

    public static function getRelations(): array
    {
        return [MarketDaysRelationManager::class];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListLocations::route('/'),
            'create' => Pages\CreateLocation::route('/create'),
            'edit' => Pages\EditLocation::route('/{record}/edit'),
        ];
    }
}
