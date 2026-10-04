<?php

namespace App\Filament\Resources\LocationResource\RelationManagers;

use App\Models\Location;
use App\Models\MarketDay;
use Carbon\CarbonImmutable;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/** Market schedule: the trading days customers can pick for Click & Collect. */
class MarketDaysRelationManager extends RelationManager
{
    protected static string $relationship = 'marketDays';

    protected static ?string $title = 'Market schedule';

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            DatePicker::make('date')->required(),
            Select::make('status')->options(['scheduled' => 'Scheduled', 'cancelled' => 'Cancelled', 'completed' => 'Completed'])->default('scheduled')->required(),
            TimePicker::make('opens_at')->seconds(false)->required()->default(fn () => $this->getOwnerRecord()->default_opens_at),
            TimePicker::make('closes_at')->seconds(false)->required()->default(fn () => $this->getOwnerRecord()->default_closes_at),
            TextInput::make('notes')->columnSpanFull()->placeholder('e.g. cancelled due to weather'),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('date')
            ->columns([
                TextColumn::make('date')->date('D j M Y')->sortable(),
                TextColumn::make('hours')->getStateUsing(fn (MarketDay $record) => $record->hours),
                TextColumn::make('status')->badge()->color(fn ($state) => ['scheduled' => 'success', 'cancelled' => 'danger'][$state] ?? 'gray'),
                TextColumn::make('orders_count')->counts('orders')->label('C&C orders'),
                TextColumn::make('notes')->limit(40),
            ])
            ->filters([
                Filter::make('upcoming')->default()->query(fn (Builder $query) => $query->whereDate('date', '>=', today())),
            ])
            ->headerActions([
                CreateAction::make(),
                Action::make('generate')
                    ->label('Generate schedule')
                    ->schema([
                        TextInput::make('weeks')->numeric()->default(8)->minValue(1)->maxValue(26)->required(),
                    ])
                    ->modalDescription(fn () => 'Creates a market day on each of: '.($this->getOwnerRecord()->operating_days ?: '(set operating days on the location first)'))
                    ->action(function (array $data) {
                        $created = self::generate($this->getOwnerRecord(), (int) $data['weeks']);
                        Notification::make()->title("{$created} market days added")->success()->send();
                    }),
            ])
            ->recordActions([EditAction::make()]);
    }

    /** Create market days for the next N weeks from the location's operating days. */
    public static function generate(Location $location, int $weeks): int
    {
        $map = ['mon' => 1, 'tue' => 2, 'wed' => 3, 'thu' => 4, 'fri' => 5, 'sat' => 6, 'sun' => 7];
        $days = collect(explode(',', (string) $location->operating_days))
            ->map(fn ($d) => $map[strtolower(substr(trim($d), 0, 3))] ?? null)->filter()->all();

        $created = 0;
        for ($date = CarbonImmutable::today(); $date->lte(CarbonImmutable::today()->addWeeks($weeks)); $date = $date->addDay()) {
            if (in_array($date->dayOfWeekIso, $days, true)) {
                if ($location->marketDays()->whereDate('date', $date)->exists()) {
                    continue;
                }
                $location->marketDays()->create([
                    'date' => $date->toDateString(),
                    'opens_at' => $location->default_opens_at ?? '08:00',
                    'closes_at' => $location->default_closes_at ?? '14:00',
                    'status' => 'scheduled',
                ]);
                $created++;
            }
        }

        return $created;
    }

    public function isReadOnly(): bool
    {
        return ! auth()->user()?->isOwner();
    }
}
