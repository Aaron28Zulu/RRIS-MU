<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\Resources\ResearchDomainResource\Pages;
use App\Models\ResearchDomain;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Forms\Set;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables\Actions\Action;
use Filament\Tables\Actions\ActionGroup;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Thematic areas that research gaps are filed under. Gap analysis adds areas as it reads completed studies;
 * the Research Office (led by the research director) curates them: adding, renaming and merging near-duplicates.
 */
class ResearchDomainResource extends Resource
{
    protected static ?string $model = ResearchDomain::class;

    protected static ?string $navigationIcon = 'heroicon-o-squares-2x2';

    protected static ?string $navigationGroup = 'Research Office';

    protected static ?string $navigationLabel = 'Thematic areas';

    protected static ?string $modelLabel = 'thematic area';

    protected static ?int $navigationSort = 5;

    protected static ?string $recordTitleAttribute = 'name';

    public static function canViewAny(): bool
    {
        return auth()->user()?->managesResearchOffice() ?? false;
    }

    /**
     * An area with gaps filed under it is merged into another rather than deleted, so no gap loses its area.
     */
    public static function canDelete(Model $record): bool
    {
        return static::canViewAny() && ! $record->researchGaps()->exists();
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            TextInput::make('name')
                ->required()
                ->maxLength(255)
                ->unique(ignoreRecord: true)
                ->placeholder('Climate-smart agriculture')
                ->live(onBlur: true)
                ->afterStateUpdated(function (string $operation, ?string $state, Set $set): void {
                    if ($operation === 'create') {
                        $set('slug', Str::slug((string) $state));
                    }
                }),
            TextInput::make('slug')
                ->label('Web address name')
                ->helperText('Used in links to the research gaps page. Changing it breaks links people have saved.')
                ->required()
                ->maxLength(255)
                ->alphaDash()
                ->unique(ignoreRecord: true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->searchable()->sortable()->weight('medium')
                    ->description(fn (ResearchDomain $record): string => $record->slug),
                TextColumn::make('research_gaps_count')->counts('researchGaps')->label('Gaps')->sortable()->alignCenter(),
                TextColumn::make('saturated_gaps_count')
                    ->label('Saturated')
                    ->counts(['researchGaps as saturated_gaps_count' => fn ($query) => $query->where('is_saturated', true)])
                    ->alignCenter()
                    ->toggleable(),
                TextColumn::make('emerging_gaps_count')
                    ->label('Emerging')
                    ->counts(['researchGaps as emerging_gaps_count' => fn ($query) => $query->where('is_emerging', true)])
                    ->alignCenter()
                    ->toggleable(),
                TextColumn::make('created_at')->label('Added')->date('j M Y')->sortable()->toggleable(),
            ])
            ->defaultSort('name')
            ->actions([
                Action::make('gaps')
                    ->label('View gaps')
                    ->icon('heroicon-o-light-bulb')
                    ->color('gray')
                    ->url(fn (ResearchDomain $record): string => route('gaps', ['domain' => $record->slug]))
                    ->openUrlInNewTab(),
                ActionGroup::make([
                    EditAction::make(),
                    Action::make('merge')
                        ->label('Merge into…')
                        ->icon('heroicon-o-arrows-pointing-in')
                        ->color('warning')
                        ->modalDescription('Moves every gap in this area to the area you choose, then removes this one. Use it for duplicates such as "Food Security" and "Food security in Zambia".')
                        ->form([
                            Select::make('target_id')
                                ->label('Keep this area')
                                ->options(fn (ResearchDomain $record): array => ResearchDomain::query()
                                    ->whereKeyNot($record->getKey())
                                    ->orderBy('name')
                                    ->pluck('name', 'id')
                                    ->all())
                                ->searchable()
                                ->required(),
                        ])
                        ->action(function (ResearchDomain $record, array $data): void {
                            $target = ResearchDomain::findOrFail($data['target_id']);

                            DB::transaction(function () use ($record, $target): void {
                                $record->researchGaps()->update(['domain_id' => $target->id]);
                                $record->delete();
                            });

                            Notification::make()->title("Merged into {$target->name}")->success()->send();
                        }),
                    DeleteAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ManageResearchDomains::route('/'),
        ];
    }
}
