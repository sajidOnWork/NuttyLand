<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\RoleAccess;
use App\Filament\Resources\AuditLogResource\Pages;
use App\Models\AuditLog;
use BackedEnum;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\KeyValueEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

/** Read-only history of administrative changes (report §13.1). */
class AuditLogResource extends Resource
{
    use RoleAccess;

    protected static ?string $model = AuditLog::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShieldCheck;

    protected static string|UnitEnum|null $navigationGroup = 'Administration';

    protected static ?string $navigationLabel = 'Audit log';

    protected static ?int $navigationSort = 2;

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

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            TextEntry::make('created_at')->dateTime(),
            TextEntry::make('user.name')->label('By')->placeholder('System'),
            TextEntry::make('event'),
            TextEntry::make('subject_label')->label('Record'),
            KeyValueEntry::make('old_values')->label('Before')->columnSpanFull(),
            KeyValueEntry::make('new_values')->label('After')->columnSpanFull(),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with('user'))
            ->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('created_at')->label('When')->dateTime('j M Y g:ia'),
                TextColumn::make('user.name')->label('By')->placeholder('System'),
                TextColumn::make('event')->badge()->color(fn ($state) => ['created' => 'success', 'updated' => 'warning', 'deleted' => 'danger'][$state] ?? 'gray'),
                TextColumn::make('auditable_type')->label('Type')->formatStateUsing(fn ($state) => class_basename($state)),
                TextColumn::make('auditable_id')->label('ID'),
                TextColumn::make('changed')->label('Fields')->limit(60)
                    ->getStateUsing(fn (AuditLog $record) => implode(', ', array_keys($record->new_values ?? $record->old_values ?? []))),
            ])
            ->filters([
                SelectFilter::make('event')->options(['created' => 'Created', 'updated' => 'Updated', 'deleted' => 'Deleted']),
                SelectFilter::make('auditable_type')->label('Type')->options(fn () => AuditLog::query()->distinct()->pluck('auditable_type')->mapWithKeys(fn ($t) => [$t => class_basename($t)])),
            ])
            ->recordActions([ViewAction::make()]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ManageAuditLogs::route('/')];
    }
}
