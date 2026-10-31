<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Enums\EntryStatus;
use App\Enums\EntryType;
use App\Enums\ResearchCategory;
use App\Enums\UserRole;
use App\Filament\Resources\ChallengeEntryResource\Pages;
use App\Filament\Tables\Columns\TitleColumn;
use App\Models\ChallengeEntry;
use App\Models\EntryReview;
use App\Models\InnovationChallenge;
use App\Models\User;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables\Actions\Action;
use Filament\Tables\Actions\ActionGroup;
use Filament\Tables\Actions\BulkAction;
use Filament\Tables\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Challenge entries as seen by the review panel (lecturers and office members) and the office.
 */
class ChallengeEntryResource extends Resource
{
    protected static ?string $model = ChallengeEntry::class;

    protected static ?string $navigationIcon = 'heroicon-o-inbox-stack';

    protected static ?string $navigationGroup = 'Research Office';

    protected static ?string $navigationLabel = 'Challenge entries';

    protected static ?string $modelLabel = 'challenge entry';

    protected static ?int $navigationSort = 2;

    /**
     * Lecturers sit on the review panel; office members and school oversight run the process.
     */
    public static function canViewAny(): bool
    {
        $user = auth()->user();

        return $user !== null && ($user->isFaculty() || $user->managesResearchOffice());
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getNavigationBadge(): ?string
    {
        $awaiting = ChallengeEntry::query()->where('status', EntryStatus::SUBMITTED->value)->count();

        return $awaiting > 0 ? (string) $awaiting : null;
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return 'Entries awaiting a shortlist decision';
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['student:id,name,department', 'challenge:id,edition', 'reviews']);
    }

    /**
     * Office managers record decisions; any lecturer or office member may score, but never their own entry.
     */
    protected static function canScore(ChallengeEntry $entry): bool
    {
        $user = auth()->user();

        return $user !== null
            && $entry->student_id !== $user->id
            && ($user->isFaculty() || $user->managesResearchOffice());
    }

    protected static function canDecide(): bool
    {
        return auth()->user()?->managesResearchOffice() ?? false;
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([
            Section::make()
                ->columns(3)
                ->schema([
                    TextEntry::make('title')->columnSpanFull()->weight('semibold')->size(TextEntry\TextEntrySize::Large),
                    TextEntry::make('student.name')->label('Lead student'),
                    TextEntry::make('team_members')->label('Team')->placeholder('Individual entry'),
                    TextEntry::make('challenge.edition')->label('Call'),
                    TextEntry::make('entry_type')->label('Type')->formatStateUsing(fn (EntryType $state): string => $state->label()),
                    TextEntry::make('category')->label('Subject')->formatStateUsing(fn (ResearchCategory $state): string => $state->label()),
                    TextEntry::make('status')
                        ->badge()
                        ->formatStateUsing(fn (ChallengeEntry $record): string => $record->status->label().($record->award ? ' · '.$record->award : ''))
                        ->color(fn (EntryStatus $state): string => $state->color()),
                ]),
            Section::make('Proposal')
                ->schema([
                    TextEntry::make('problem_statement')->prose(),
                    TextEntry::make('proposed_solution')->label('Proposed research or solution')->prose(),
                    TextEntry::make('expected_impact')->prose(),
                ]),
            Section::make('Review panel')
                ->description(fn (ChallengeEntry $record): string => $record->panelScore() === null
                    ? 'Not scored yet.'
                    : "Mean score {$record->panelScore()} / 20 from {$record->reviews->count()} ".str('reviewer')->plural($record->reviews->count()).'.')
                ->visible(fn (): bool => static::canDecide())
                ->schema([
                    RepeatableEntry::make('reviews')
                        ->hiddenLabel()
                        ->columns(6)
                        ->schema([
                            TextEntry::make('reviewer.name')->label('Reviewer')->columnSpan(2),
                            TextEntry::make('relevance_score')->label('Relevance'),
                            TextEntry::make('innovation_score')->label('Innovation'),
                            TextEntry::make('feasibility_score')->label('Feasibility'),
                            TextEntry::make('impact_score')->label('Impact'),
                            TextEntry::make('comments')->columnSpanFull()->placeholder('No comments'),
                        ]),
                ]),
            Section::make('Office decision')
                ->columns(2)
                ->schema([
                    TextEntry::make('mentor.name')->label('Mentor')->placeholder('Not assigned'),
                    TextEntry::make('office_feedback')->label('Feedback to the student')->placeholder('None yet')->columnSpanFull(),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TitleColumn::make('title')
                    ->searchable()
                    ->description(fn (ChallengeEntry $record): string => $record->entry_type->shortLabel().' · '.$record->category->shortLabel()),
                TextColumn::make('student.name')
                    ->label('Lead student')
                    ->description(fn (ChallengeEntry $record): ?string => $record->team_members)
                    ->searchable(),
                TextColumn::make('challenge.edition')
                    ->label('Call')
                    ->formatStateUsing(fn (ChallengeEntry $record): string => $record->challenge->shortEdition())
                    ->tooltip(fn (ChallengeEntry $record): string => $record->challenge->edition)
                    ->color('gray')
                    ->toggleable(),
                TextColumn::make('panel_score')
                    ->label('Panel score')
                    ->state(fn (ChallengeEntry $record): ?float => $record->panelScore())
                    ->formatStateUsing(fn (?float $state, ChallengeEntry $record): string => $state === null ? '—' : "{$state} / 20 ({$record->reviews->count()})")
                    ->placeholder('Not scored')
                    ->alignCenter(),
                TextColumn::make('my_score')
                    ->label('Your score')
                    ->state(fn (ChallengeEntry $record): ?int => $record->reviews->firstWhere('reviewer_id', auth()->id())?->total())
                    ->placeholder('—')
                    ->alignCenter(),
                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (ChallengeEntry $record): string => $record->award ?? $record->status->label())
                    ->color(fn (EntryStatus $state): string => $state->color()),
                TextColumn::make('created_at')
                    ->label('Submitted')
                    ->date('j M Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('innovation_challenge_id')
                    ->label('Call')
                    ->options(fn (): array => InnovationChallenge::query()->latest('closes_on')->pluck('edition', 'id')->all()),
                SelectFilter::make('status')->options(EntryStatus::options()),
                SelectFilter::make('entry_type')->label('Type')->options(EntryType::options()),
                SelectFilter::make('category')->label('Subject')->options(ResearchCategory::options()),
            ])
            ->defaultSort('created_at', 'desc')
            ->actions([
                Action::make('score')
                    ->label(fn (ChallengeEntry $record): string => $record->reviews->contains('reviewer_id', auth()->id()) ? 'Update score' : 'Score')
                    ->icon('heroicon-o-star')
                    ->color('primary')
                    ->visible(fn (ChallengeEntry $record): bool => static::canScore($record))
                    ->modalHeading(fn (ChallengeEntry $record): string => "Score: {$record->title}")
                    ->modalDescription('Rate each criterion from 1 (weak) to 5 (outstanding).')
                    ->fillForm(fn (ChallengeEntry $record): array => $record->reviews->firstWhere('reviewer_id', auth()->id())?->only([
                        ...array_keys(EntryReview::CRITERIA), 'comments',
                    ]) ?? [])
                    ->form([
                        Grid::make(2)->schema([
                            ...collect(EntryReview::CRITERIA)->map(fn (string $label, string $field): Select => Select::make($field)
                                ->label($label)
                                ->options([1 => '1 · Weak', 2 => '2 · Fair', 3 => '3 · Good', 4 => '4 · Strong', 5 => '5 · Outstanding'])
                                ->required())->values()->all(),
                            Textarea::make('comments')->rows(3)->columnSpanFull(),
                        ]),
                    ])
                    ->action(function (ChallengeEntry $record, array $data): void {
                        abort_unless(static::canScore($record), 403);

                        $record->reviews()->updateOrCreate(['reviewer_id' => auth()->id()], $data);

                        Notification::make()->title('Score saved')->success()->send();
                    }),
                ActionGroup::make([
                    ViewAction::make(),
                    Action::make('decide')
                        ->label('Record decision')
                        ->icon('heroicon-o-flag')
                        ->visible(fn (): bool => static::canDecide())
                        ->fillForm(fn (ChallengeEntry $record): array => [
                            'status' => $record->status->value,
                            'award' => $record->award,
                            'mentor_id' => $record->mentor_id,
                            'office_feedback' => $record->office_feedback,
                        ])
                        ->form([
                            Select::make('status')->options(EntryStatus::options())->required()->live(),
                            TextInput::make('award')
                                ->label('Recognition')
                                ->placeholder('Winner, Runner-up, Commendation')
                                ->maxLength(100)
                                ->visible(fn ($get): bool => $get('status') === EntryStatus::RECOGNISED->value),
                            Select::make('mentor_id')
                                ->label('Mentor (lecturer guiding the project)')
                                ->options(fn (): array => User::role(UserRole::SUPERVISOR->value)->pluck('name', 'id')->all())
                                ->searchable(),
                            Textarea::make('office_feedback')->label('Feedback to the student')->rows(4),
                        ])
                        ->action(function (ChallengeEntry $record, array $data): void {
                            abort_unless(static::canDecide(), 403);

                            $status = EntryStatus::from($data['status']);
                            $changed = $record->status !== $status;

                            $record->update([
                                ...$data,
                                'award' => $status === EntryStatus::RECOGNISED ? ($data['award'] ?? null) : null,
                            ]);

                            if ($changed) {
                                static::notifyStudent($record);
                            }

                            Notification::make()->title('Decision recorded')->success()->send();
                        }),
                    Action::make('conceptNote')
                        ->label('Concept note')
                        ->icon('heroicon-o-document-arrow-down')
                        ->visible(fn (ChallengeEntry $record): bool => filled($record->attachment_path))
                        ->action(fn (ChallengeEntry $record): StreamedResponse => Storage::disk('secure_research')->download(
                            $record->attachment_path,
                            str($record->title)->slug().'.pdf',
                        )),
                ]),
            ])
            ->bulkActions([
                BulkAction::make('shortlist')
                    ->label('Shortlist selected')
                    ->icon('heroicon-o-check-badge')
                    ->visible(fn (): bool => static::canDecide())
                    ->requiresConfirmation()
                    ->action(function (Collection $records): void {
                        $records->each(function (ChallengeEntry $entry): void {
                            if ($entry->status !== EntryStatus::SHORTLISTED) {
                                $entry->update(['status' => EntryStatus::SHORTLISTED]);
                                static::notifyStudent($entry);
                            }
                        });
                    })
                    ->deselectRecordsAfterCompletion(),
            ]);
    }

    /**
     * Tell the lead student where their entry now stands.
     */
    protected static function notifyStudent(ChallengeEntry $entry): void
    {
        $message = match ($entry->status) {
            EntryStatus::SHORTLISTED => 'has been shortlisted. Prepare to present it to the review panel.',
            EntryStatus::RECOGNISED => 'has been recognised'.($entry->award ? " ({$entry->award})" : '').'. Congratulations!',
            EntryStatus::NOT_SHORTLISTED => 'was not shortlisted this time. See the office feedback on the challenge page.',
            EntryStatus::SUBMITTED => 'is back under review.',
        };

        Notification::make()
            ->title('Innovation challenge update')
            ->body("Your entry '{$entry->title}' {$message}")
            ->icon('heroicon-o-trophy')
            ->sendToDatabase($entry->student);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ManageChallengeEntries::route('/'),
        ];
    }
}
