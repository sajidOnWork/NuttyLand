<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\RoleAccess;
use App\Filament\Resources\CustomerResource\Pages;
use App\Models\Customer;
use App\Support\Money;
use BackedEnum;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

/** Customer database (foundation for CRM / loyalty in later phases). */
class CustomerResource extends Resource
{
    use RoleAccess;

    protected static ?string $model = Customer::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserGroup;

    protected static string|UnitEnum|null $navigationGroup = 'Sales';

    protected static ?int $navigationSort = 2;

    protected static bool $marketingCanView = true;

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()->columns(3)->schema([
                TextEntry::make('full_name')->label('Name'),
                TextEntry::make('email'),
                TextEntry::make('phone')->placeholder('—'),
                IconEntry::make('marketing_opt_in')->boolean()->label('Marketing opt-in'),
                TextEntry::make('loyalty_points'),
                TextEntry::make('created_at')->label('Customer since')->date(),
            ]),
            Section::make('Order history')->schema([
                RepeatableEntry::make('orders')->hiddenLabel()->columns(4)->schema([
                    TextEntry::make('order_number'),
                    TextEntry::make('created_at')->date('j M Y'),
                    TextEntry::make('status_label')->label('Status'),
                    TextEntry::make('total_cents')->label('Total')->formatStateUsing(fn ($state) => Money::format($state)),
                ]),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('full_name')->label('Name')->searchable(['first_name', 'last_name'])->weight('bold'),
                TextColumn::make('email')->searchable(),
                TextColumn::make('phone'),
                TextColumn::make('orders_count')->counts('orders')->label('Orders')->sortable(),
                TextColumn::make('orders_sum_total_cents')->sum('orders', 'total_cents')->label('Spent')->formatStateUsing(fn ($state) => Money::format((int) $state)),
                IconColumn::make('marketing_opt_in')->boolean()->label('Opt-in'),
                TextColumn::make('created_at')->label('Joined')->date()->sortable(),
            ])
            ->filters([TernaryFilter::make('marketing_opt_in')->label('Marketing opt-in')])
            ->recordActions([ViewAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCustomers::route('/'),
            'view' => Pages\ViewCustomer::route('/{record}'),
        ];
    }
}
