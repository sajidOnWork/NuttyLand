<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\RoleAccess;
use App\Filament\Resources\OrderResource\Pages;
use App\Models\Order;
use App\Services\OrderService;
use App\Support\Money;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Select;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use InvalidArgumentException;
use UnitEnum;

/** Online / Click & Collect orders (order-status report, §7.4). */
class OrderResource extends Resource
{
    use RoleAccess;

    protected static ?string $model = Order::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    protected static string|UnitEnum|null $navigationGroup = 'Sales';

    protected static ?string $navigationLabel = 'Click & Collect orders';

    protected static ?int $navigationSort = 1;

    protected static ?string $recordTitleAttribute = 'order_number';

    protected static bool $marketingCanView = true;

    public static function canCreate(): bool
    {
        return false; // orders come from the website
    }

    public static function getNavigationBadge(): ?string
    {
        $open = Order::whereIn('status', ['confirmed', 'preparing'])->count();

        return $open ? (string) $open : null;
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Order')->columns(3)->schema([
                TextEntry::make('order_number'),
                TextEntry::make('status')->formatStateUsing(fn ($state) => Order::STATUSES[$state] ?? $state)->badge(),
                TextEntry::make('payment_status')->badge(),
                TextEntry::make('customer.full_name')->label('Customer'),
                TextEntry::make('customer.email')->label('Email'),
                TextEntry::make('customer.phone')->label('Phone'),
                TextEntry::make('marketDay.label')->label('Collection'),
                TextEntry::make('total_cents')->label('Total')->formatStateUsing(fn ($state) => Money::format($state)),
                TextEntry::make('payment_reference')->placeholder('—'),
                TextEntry::make('notes')->placeholder('—')->columnSpanFull(),
            ]),
            Section::make('Items')->schema([
                RepeatableEntry::make('items')->hiddenLabel()->columns(4)->schema([
                    TextEntry::make('product_name')->label('Product'),
                    TextEntry::make('weight_label')->label('Size'),
                    TextEntry::make('quantity')->label('Qty'),
                    TextEntry::make('subtotal_cents')->label('Subtotal')->formatStateUsing(fn ($state) => Money::format($state)),
                ]),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('order_number')->searchable()->weight('bold'),
                TextColumn::make('created_at')->label('Placed')->dateTime('j M g:ia')->sortable(),
                TextColumn::make('customer.full_name')->label('Customer')
                    ->searchable(['first_name', 'last_name', 'email']),
                TextColumn::make('location.name')->label('Collect at'),
                TextColumn::make('marketDay.date')->label('On')->date('D j M')->sortable(),
                TextColumn::make('total_cents')->label('Total')->formatStateUsing(fn ($state) => Money::format($state))->sortable(),
                TextColumn::make('status')->badge()->formatStateUsing(fn ($state) => Order::STATUSES[$state] ?? $state)
                    ->color(fn ($state) => ['pending_payment' => 'warning', 'confirmed' => 'info', 'preparing' => 'info', 'ready' => 'success', 'collected' => 'gray', 'cancelled' => 'danger'][$state] ?? 'gray'),
                TextColumn::make('payment_status')->badge()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')->options(Order::STATUSES)->multiple(),
                SelectFilter::make('location')->relationship('location', 'name')->label('Market'),
            ])
            ->recordActions([
                ViewAction::make(),
                static::changeStatusAction(),
            ]);
    }

    public static function changeStatusAction(): Action
    {
        return Action::make('changeStatus')
            ->label('Update status')
            ->icon(Heroicon::OutlinedArrowPath)
            ->visible(fn (Order $record) => static::isOwner() && isset(OrderService::TRANSITIONS[$record->status]))
            ->schema(fn (Order $record) => [
                Select::make('status')->required()
                    ->options(collect(OrderService::TRANSITIONS[$record->status] ?? [])->mapWithKeys(fn ($s) => [$s => Order::STATUSES[$s]])),
            ])
            ->action(function (Order $record, array $data) {
                try {
                    app(OrderService::class)->updateStatus($record, $data['status'], auth()->user());
                    Notification::make()->title('Order updated')->success()->send();
                } catch (InvalidArgumentException $e) {
                    Notification::make()->title($e->getMessage())->danger()->send();
                }
            });
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListOrders::route('/'),
            'view' => Pages\ViewOrder::route('/{record}'),
        ];
    }
}
