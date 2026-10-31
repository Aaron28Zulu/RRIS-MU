<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Enums\ProjectStatus;
use App\Enums\ResearchCategory;
use App\Enums\UserRole;
use App\Filament\Resources\ProjectResource\Pages;
use App\Filament\Tables\Columns\TitleColumn;
use App\Models\Project;
use App\Models\User;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables\Actions\BulkActionGroup;
use Filament\Tables\Actions\DeleteBulkAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ProjectResource extends Resource
{
    protected static ?string $model = Project::class;

    protected static ?string $navigationIcon = 'heroicon-o-academic-cap';

    protected static ?string $navigationGroup = 'School oversight';

    protected static ?string $navigationLabel = 'All projects';

    protected static ?string $modelLabel = 'research project';

    protected static ?int $navigationSort = 1;

    /**
     * Deans see their own school's register; the research director and administrators see every school.
     */
    public static function canViewAny(): bool
    {
        return auth()->user()?->hasOversight() ?? false;
    }

    public static function getNavigationLabel(): string
    {
        return auth()->user()?->oversightSchoolId() !== null ? 'School projects' : 'All projects';
    }

    public static function getNavigationBadge(): ?string
    {
        $awaiting = static::getEloquentQuery()
            ->whereIn('status', [ProjectStatus::PROPOSAL_SUBMITTED->value, ProjectStatus::UNDER_REVIEW->value])
            ->count();

        return $awaiting > 0 ? (string) $awaiting : null;
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return 'Awaiting a supervisor decision';
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Grid::make(2)->schema([
                TextInput::make('title')
                    ->required()
                    ->maxLength(255)
                    ->columnSpanFull(),

                Select::make('category')
                    ->label('Subject')
                    ->options(ResearchCategory::options())
                    ->searchable()
                    ->required(),

                Select::make('school_id')
                    ->label('School')
                    ->relationship('school', 'name')
                    ->preload()
                    // A dean files work under their own school only.
                    ->disabled(fn (): bool => auth()->user()?->oversightSchoolId() !== null)
                    ->dehydrated(fn (): bool => auth()->user()?->oversightSchoolId() === null)
                    ->placeholder('Follows the student\'s school'),

                Select::make('status')
                    ->options(collect(ProjectStatus::cases())->mapWithKeys(
                        fn (ProjectStatus $s) => [$s->value => $s->label()]
                    ))
                    ->required(),

                Select::make('student_id')
                    ->relationship('student', 'name', fn (Builder $query): Builder => static::limitToOwnSchool($query))
                    ->searchable()
                    ->preload()
                    ->required(),

                Select::make('supervisor_id')
                    ->relationship('supervisor', 'name')
                    ->searchable()
                    ->preload()
                    ->nullable(),

                TextInput::make('completion_year')
                    ->numeric()
                    ->minValue(1990)
                    ->maxValue((int) date('Y') + 1)
                    ->nullable(),

                RichEditor::make('abstract')
                    ->required()
                    ->columnSpanFull(),

                FileUpload::make('full_document_path')
                    ->label('Manuscript (PDF/DOCX)')
                    ->disk('secure_research')
                    ->directory('manuscripts')
                    ->acceptedFileTypes(['application/pdf', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'])
                    ->visible(fn () => auth()->user()?->hasAnyRole([
                        UserRole::ADMIN->value,
                        UserRole::DEAN->value,
                        UserRole::SUPERVISOR->value,
                    ])),

                FileUpload::make('raw_data_path')
                    ->label('Raw Data Assets')
                    ->disk('secure_research')
                    ->directory('raw_datasets')
                    ->visible(fn () => auth()->user()?->hasAnyRole([
                        UserRole::ADMIN->value,
                        UserRole::DEAN->value,
                        UserRole::SUPERVISOR->value,
                    ])),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TitleColumn::make('title')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('category')
                    ->label('Subject')
                    ->formatStateUsing(fn (ResearchCategory $state): string => $state->shortLabel())
                    ->tooltip(fn (Project $record): string => $record->category->label())
                    ->color('gray')
                    ->sortable(),

                TextColumn::make('school.name')
                    ->label('School')
                    ->formatStateUsing(fn (string $state): string => (string) str($state)->after('School of '))
                    ->placeholder('Not set')
                    ->visible(fn (): bool => auth()->user()?->oversightSchoolId() === null)
                    ->toggleable(),

                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (ProjectStatus $state): string => $state->label())
                    ->color(fn (ProjectStatus $state): string => $state->color())
                    ->sortable(),

                TextColumn::make('student.name')
                    ->label('Student')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('supervisor.name')
                    ->label('Supervisor')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('completion_year')
                    ->label('Year')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('school_id')
                    ->label('School')
                    ->relationship('school', 'name')
                    ->visible(fn (): bool => auth()->user()?->oversightSchoolId() === null),

                SelectFilter::make('category')
                    ->label('Subject')
                    ->options(ResearchCategory::options()),

                SelectFilter::make('status')
                    ->options(collect(ProjectStatus::cases())->mapWithKeys(
                        fn (ProjectStatus $s) => [$s->value => $s->label()]
                    )),

                SelectFilter::make('completion_year')
                    ->label('Year')
                    ->options(fn (): array => static::getEloquentQuery()
                        ->whereNotNull('completion_year')
                        ->distinct()
                        ->orderByDesc('completion_year')
                        ->pluck('completion_year', 'completion_year')
                        ->all()),

                SelectFilter::make('supervisor_id')
                    ->label('Supervisor')
                    ->relationship('supervisor', 'name'),
            ])
            ->defaultSort('updated_at', 'desc')
            ->actions([
                EditAction::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListProjects::route('/'),
            'create' => Pages\CreateProject::route('/create'),
            'edit' => Pages\EditProject::route('/{record}/edit'),
        ];
    }

    /**
     * The research director and administrator see every school; a dean sees only their own school.
     */
    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()->with('school');
        $user = auth()->user();

        if ($user === null) {
            return $query->whereRaw('1 = 0');
        }

        if ($user->hasOversight()) {
            return $query->withinOversightOf($user);
        }

        return $query->where('supervisor_id', $user->id);
    }

    /**
     * Narrows a people picker to the dean's own school.
     *
     * @param  Builder<User>  $query
     * @return Builder<User>
     */
    protected static function limitToOwnSchool(Builder $query): Builder
    {
        $schoolId = auth()->user()?->oversightSchoolId();

        return $schoolId === null ? $query : $query->where('school_id', $schoolId);
    }
}
