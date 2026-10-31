<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Enums\EventType;
use App\Enums\UserRole;
use App\Filament\Resources\AssociationEventResource\Pages;
use App\Filament\Tables\Columns\TitleColumn;
use App\Models\AssociationEvent;
use App\Models\User;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables\Actions\Action;
use Filament\Tables\Actions\ActionGroup;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Debates, workshops, seminars and field visits run by the Office of Research and Innovation.
 */
class AssociationEventResource extends Resource
{
    protected static ?string $model = AssociationEvent::class;

    protected static ?string $navigationIcon = 'heroicon-o-megaphone';

    protected static ?string $navigationGroup = 'Research Office';

    protected static ?string $navigationLabel = 'Debates & events';

    protected static ?string $modelLabel = 'event';

    protected static ?int $navigationSort = 3;

    public static function canViewAny(): bool
    {
        return auth()->user()?->managesResearchOffice() ?? false;
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Grid::make(2)->schema([
                TextInput::make('title')->required()->maxLength(255)->columnSpanFull(),
                Select::make('event_type')
                    ->label('Type')
                    ->options(EventType::options())
                    ->required()
                    ->live(),
                DateTimePicker::make('starts_at')->label('Starts')->required()->seconds(false)->native(false),
                TextInput::make('motion')
                    ->label(fn ($get): string => $get('event_type') === EventType::DEBATE->value ? 'Motion' : 'Theme')
                    ->placeholder('Should artificial intelligence tools be allowed in university assessments?')
                    ->maxLength(255)
                    ->columnSpanFull(),
                Textarea::make('description')->required()->rows(4)->columnSpanFull(),
                TextInput::make('venue')->maxLength(255),
                Select::make('coordinator_id')
                    ->label('Coordinator')
                    ->options(fn (): array => User::role(UserRole::RESEARCH_DIRECTOR->value)->pluck('name', 'id')->all())
                    ->default(fn (): ?int => auth()->user()?->hasRole(UserRole::RESEARCH_DIRECTOR->value) ? auth()->id() : null)
                    ->searchable(),
                TextInput::make('capacity')->label('Seats')->numeric()->minValue(1)->helperText('Leave empty for no limit.'),
                TextInput::make('speaker_slots')
                    ->label(fn ($get): string => $get('event_type') === EventType::DEBATE->value ? 'Debater slots' : 'Speaker slots')
                    ->numeric()
                    ->minValue(1),
                Toggle::make('is_published')->label('Show on the innovation page')->default(true)->columnSpanFull(),
                Textarea::make('outcomes')
                    ->label('Outcomes and recommendations')
                    ->helperText('Record resolutions and practical recommendations after the event.')
                    ->rows(4)
                    ->columnSpanFull(),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TitleColumn::make('title')
                    ->searchable()
                    ->description(fn (AssociationEvent $record): ?string => $record->motion),
                TextColumn::make('event_type')
                    ->label('Type')
                    ->badge()
                    ->formatStateUsing(fn (EventType $state): string => $state->label())
                    ->color(fn (EventType $state): string => $state->color()),
                TextColumn::make('starts_at')->label('Date')->dateTime('j M Y, H:i')->sortable(),
                TextColumn::make('attendees_count')
                    ->counts('attendees')
                    ->label('Registered')
                    ->formatStateUsing(fn (int $state, AssociationEvent $record): string => $record->capacity ? "{$state} / {$record->capacity}" : (string) $state)
                    ->alignCenter(),
                IconColumn::make('outcomes')
                    ->label('Outcomes')
                    ->state(fn (AssociationEvent $record): bool => filled($record->outcomes))
                    ->boolean()
                    ->alignCenter(),
                IconColumn::make('is_published')->label('Public')->boolean()->alignCenter(),
            ])
            ->filters([
                SelectFilter::make('event_type')->label('Type')->options(EventType::options()),
                TernaryFilter::make('upcoming')
                    ->queries(
                        true: fn (Builder $query) => $query->where('starts_at', '>=', now()),
                        false: fn (Builder $query) => $query->where('starts_at', '<', now()),
                    ),
            ])
            ->defaultSort('starts_at', 'desc')
            ->actions([
                Action::make('registrations')
                    ->icon('heroicon-o-users')
                    ->color('gray')
                    ->modalHeading(fn (AssociationEvent $record): string => "Registrations: {$record->title}")
                    ->modalContent(fn (AssociationEvent $record) => view('filament.office.event-registrations', [
                        'event' => $record,
                        'attendees' => $record->attendees()->orderByPivot('wants_to_speak', 'desc')->orderBy('name')->get(),
                    ]))
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Close'),
                ActionGroup::make([
                    Action::make('outcomes')
                        ->label('Record outcomes')
                        ->icon('heroicon-o-clipboard-document-check')
                        ->fillForm(fn (AssociationEvent $record): array => ['outcomes' => $record->outcomes])
                        ->form([
                            Textarea::make('outcomes')
                                ->label('Outcomes and recommendations')
                                ->required()
                                ->rows(6),
                        ])
                        ->action(function (AssociationEvent $record, array $data): void {
                            $record->update($data);

                            Notification::make()->title('Outcomes published')->success()->send();
                        }),
                    Action::make('public')
                        ->label('Public page')
                        ->icon('heroicon-o-arrow-top-right-on-square')
                        ->url(fn (AssociationEvent $record): string => route('innovation.event', $record))
                        ->openUrlInNewTab()
                        ->visible(fn (AssociationEvent $record): bool => $record->is_published),
                    EditAction::make(),
                    DeleteAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ManageAssociationEvents::route('/'),
        ];
    }
}
