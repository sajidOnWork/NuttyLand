<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\RoleAccess;
use App\Filament\Resources\UserResource\Pages;
use App\Models\User;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

/** User and role management – owner only (report §13.1 access review). */
class UserResource extends Resource
{
    use RoleAccess;

    protected static ?string $model = User::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUsers;

    protected static string|UnitEnum|null $navigationGroup = 'Administration';

    protected static ?string $navigationLabel = 'Users & roles';

    protected static ?int $navigationSort = 1;

    public static function canDelete(Model $record): bool
    {
        return false; // deactivate instead, so history is kept
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->required(),
            TextInput::make('email')->email()->required()->unique(ignoreRecord: true),
            TextInput::make('phone')->tel(),
            Select::make('role')->options(User::ROLES)->required()->default(User::ROLE_STAFF)
                ->disabled(fn (?User $record) => $record?->is(auth()->user()))
                ->helperText('You cannot change your own role.'),
            TextInput::make('password')->password()->revealable()
                ->required(fn (string $operation) => $operation === 'create')
                ->minLength(8)
                ->dehydrated(fn ($state) => filled($state))
                ->helperText('Leave blank to keep the current password.'),
            Toggle::make('is_active')->default(true)->label('Active (can log in)')
                ->disabled(fn (?User $record) => $record?->is(auth()->user())),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('role')
            ->columns([
                TextColumn::make('name')->searchable()->weight('bold'),
                TextColumn::make('email')->searchable(),
                TextColumn::make('role')->badge()->formatStateUsing(fn ($state) => User::ROLES[$state] ?? $state)
                    ->color(fn ($state) => ['owner' => 'danger', 'staff' => 'warning', 'marketing' => 'info'][$state] ?? 'gray'),
                IconColumn::make('is_active')->boolean()->label('Active'),
                TextColumn::make('created_at')->date()->sortable(),
            ])
            ->filters([SelectFilter::make('role')->options(User::ROLES)])
            ->recordActions([EditAction::make()]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ManageUsers::route('/')];
    }
}
