<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\RoleAccess;
use App\Filament\Resources\ProductResource\Pages;
use App\Filament\Resources\ProductResource\RelationManagers\VariantsRelationManager;
use App\Models\Product;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Support\Str;
use UnitEnum;

/** Product master data: catalogue, variations, allergen approval (report §5.2, §13.1). */
class ProductResource extends Resource
{
    use RoleAccess;

    protected static ?string $model = Product::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShoppingBag;

    protected static string|UnitEnum|null $navigationGroup = 'Catalogue';

    protected static ?int $navigationSort = 1;

    protected static ?string $recordTitleAttribute = 'name';

    protected static bool $marketingCanView = true;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Product')->columns(2)->schema([
                TextInput::make('name')->required()->maxLength(150)
                    ->live(onBlur: true)
                    ->afterStateUpdated(fn ($state, callable $set, $operation) => $operation === 'create' ? $set('slug', Str::slug($state)) : null),
                TextInput::make('slug')->required()->unique(ignoreRecord: true)->helperText('Used in the web address.'),
                Select::make('category_id')->relationship('category', 'name')->required()->preload(),
                Select::make('status')->options(['draft' => 'Draft (hidden)', 'active' => 'Active (on sale)', 'inactive' => 'Inactive'])->default('draft')->required(),
                Textarea::make('description')->rows(3)->columnSpanFull(),
                FileUpload::make('image_path')->label('Photo')->image()->disk('public')->directory('products')->imageEditor()->maxSize(4096)->columnSpanFull(),
            ]),
            Section::make('Attributes')->columns(3)->schema([
                Select::make('roast_style')->options(Product::ROAST_STYLES),
                TextInput::make('flavour'),
                TextInput::make('country_of_origin'),
                Toggle::make('is_organic')->label('Organic'),
                Toggle::make('is_featured')->label('Show on home page'),
                Select::make('supplier_id')->relationship('supplier', 'name')->label('Supplier (optional)'),
            ]),
            Section::make('Ingredients & allergens')
                ->description('Changing these clears the approval – the owner must re-approve before relying on the information.')
                ->schema([
                    Textarea::make('ingredients')->rows(2),
                    Textarea::make('allergen_info')->label('Allergen statement')->rows(2),
                    Placeholder::make('approval')->label('Approval status')
                        ->content(fn (?Product $record) => $record?->allergen_approved_at
                            ? 'Approved '.$record->allergen_approved_at->format('j M Y g:ia')
                            : 'Not yet approved'),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('name')
            ->columns([
                ImageColumn::make('image_path')->label('')->disk('public')->square()->size(40),
                TextColumn::make('name')->searchable()->sortable()->weight('bold'),
                TextColumn::make('category.name')->sortable(),
                TextColumn::make('variants_count')->counts('variants')->label('Sizes'),
                TextColumn::make('roast_style')->formatStateUsing(fn ($state) => Product::ROAST_STYLES[$state] ?? $state)->toggleable(),
                IconColumn::make('is_organic')->boolean()->label('Organic')->toggleable(),
                TextColumn::make('status')->badge()->color(fn ($state) => ['active' => 'success', 'draft' => 'gray', 'inactive' => 'danger'][$state] ?? 'gray'),
                IconColumn::make('allergen_approved_at')->label('Allergens approved')->boolean()
                    ->getStateUsing(fn (Product $record) => (bool) $record->allergen_approved_at),
            ])
            ->filters([
                SelectFilter::make('category')->relationship('category', 'name'),
                SelectFilter::make('status')->options(['draft' => 'Draft', 'active' => 'Active', 'inactive' => 'Inactive']),
                TernaryFilter::make('is_organic')->label('Organic'),
                TernaryFilter::make('allergen_approved_at')->label('Allergens approved')->nullable(),
            ])
            ->recordActions([
                ViewAction::make()->visible(fn () => ! static::isOwner()),
                EditAction::make(),
                static::approveAllergensAction(),
            ]);
    }

    public static function approveAllergensAction(): Action
    {
        return Action::make('approveAllergens')
            ->label('Approve allergens')
            ->icon(Heroicon::OutlinedShieldCheck)
            ->color('success')
            ->requiresConfirmation()
            ->modalDescription('Confirm the ingredients and allergen statement have been checked against the supplier label.')
            ->visible(fn (Product $record) => static::isOwner() && ! $record->allergen_approved_at)
            ->action(function (Product $record) {
                $record->update(['allergen_approved_at' => now(), 'allergen_approved_by' => auth()->id()]);
                Notification::make()->title('Allergen information approved')->success()->send();
            });
    }

    public static function getRelations(): array
    {
        return [VariantsRelationManager::class];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListProducts::route('/'),
            'create' => Pages\CreateProduct::route('/create'),
            'view' => Pages\ViewProduct::route('/{record}'),
            'edit' => Pages\EditProduct::route('/{record}/edit'),
        ];
    }
}
