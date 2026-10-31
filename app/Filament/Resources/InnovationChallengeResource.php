<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Enums\ChallengeStatus;
use App\Enums\UserRole;
use App\Filament\Resources\InnovationChallengeResource\Pages;
use App\Filament\Tables\Columns\TitleColumn;
use App\Models\InnovationChallenge;
use App\Models\User;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables\Actions\Action;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

/**
 * Semester calls of the university-wide Research and Innovation Challenge.
 */
class InnovationChallengeResource extends Resource
{
    protected static ?string $model = InnovationChallenge::class;

    protected static ?string $navigationIcon = 'heroicon-o-trophy';

    protected static ?string $navigationGroup = 'Research Office';

    protected static ?string $navigationLabel = 'Innovation challenge';

    protected static ?string $modelLabel = 'challenge call';

    protected static ?int $navigationSort = 1;

    /**
     * The Office of Research and Innovation coordinates its own programmes; deans and directors oversee them.
     */
    public static function canViewAny(): bool
    {
        return auth()->user()?->managesResearchOffice() ?? false;
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Grid::make(2)->schema([
                TextInput::make('title')
                    ->required()
                    ->maxLength(255)
                    ->default('Mulungushi University Research and Innovation Challenge')
                    ->columnSpanFull(),
                TextInput::make('edition')
                    ->required()
                    ->maxLength(255)
                    ->placeholder('Semester 1, 2027'),
                TextInput::make('theme')
                    ->maxLength(255)
                    ->placeholder('Research, Innovation and Knowledge Sharing for National Development'),
                Textarea::make('description')
                    ->label('Call for proposals')
                    ->required()
                    ->rows(5)
                    ->columnSpanFull(),
                Textarea::make('criteria')
                    ->label('Eligibility and guidance')
                    ->helperText('Shown with the standard scoring rubric: relevance, innovation, feasibility and impact.')
                    ->rows(3)
                    ->columnSpanFull(),
                Select::make('status')
                    ->options(ChallengeStatus::options())
                    ->default(ChallengeStatus::DRAFT->value)
                    ->required(),
                Select::make('coordinator_id')
                    ->label('Coordinator')
                    ->options(fn (): array => User::role(UserRole::RESEARCH_DIRECTOR->value)->pluck('name', 'id')->all())
                    ->default(fn (): ?int => auth()->user()?->hasRole(UserRole::RESEARCH_DIRECTOR->value) ? auth()->id() : null)
                    ->searchable(),
                DatePicker::make('opens_on')->native(false),
                DatePicker::make('closes_on')->label('Entries close')->native(false)->afterOrEqual('opens_on'),
                DatePicker::make('presentation_on')->label('Shortlist presentations')->native(false)->afterOrEqual('closes_on'),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TitleColumn::make('edition')
                    ->formatStateUsing(fn (InnovationChallenge $record): string => $record->shortEdition())
                    ->description(fn (InnovationChallenge $record): ?string => $record->theme)
                    ->tooltip(fn (InnovationChallenge $record): string => trim($record->edition.' — '.$record->theme, ' —'))
                    ->searchable(),
                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (ChallengeStatus $state): string => $state->label())
                    ->color(fn (ChallengeStatus $state): string => $state->color()),
                TextColumn::make('entries_count')
                    ->counts('entries')
                    ->label('Entries')
                    ->alignCenter(),
                TextColumn::make('closes_on')
                    ->label('Entries close')
                    ->date('j M Y')
                    ->sortable(),
                TextColumn::make('presentation_on')
                    ->label('Presentations')
                    ->date('j M Y'),
                TextColumn::make('coordinator.name')
                    ->label('Coordinator')
                    ->placeholder('Unassigned'),
            ])
            ->filters([
                SelectFilter::make('status')->options(ChallengeStatus::options()),
            ])
            ->defaultSort('closes_on', 'desc')
            ->actions([
                Action::make('entries')
                    ->label('Entries')
                    ->icon('heroicon-o-inbox-stack')
                    ->url(fn (InnovationChallenge $record): string => ChallengeEntryResource::getUrl('index', [
                        'tableFilters' => ['innovation_challenge_id' => ['value' => $record->id]],
                    ])),
                Action::make('public')
                    ->label('Public page')
                    ->icon('heroicon-o-arrow-top-right-on-square')
                    ->color('gray')
                    ->url(fn (InnovationChallenge $record): string => route('innovation.challenge', $record))
                    ->openUrlInNewTab()
                    ->visible(fn (InnovationChallenge $record): bool => $record->status->isPublished()),
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ManageInnovationChallenges::route('/'),
        ];
    }
}
