<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Enums\ProjectStatus;
use App\Enums\UserRole;
use App\Filament\Resources\SchoolStudentResource\Pages;
use App\Models\School;
use App\Models\User;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Read-only list of student researchers: a dean sees only their own school's students, the research
 * director and administrator see every school.
 */
class SchoolStudentResource extends Resource
{
    protected static ?string $model = User::class;

    protected static ?string $slug = 'school-students';

    protected static ?string $navigationIcon = 'heroicon-o-user-group';

    protected static ?string $navigationGroup = 'School oversight';

    protected static ?string $navigationLabel = 'Students';

    protected static ?string $modelLabel = 'student';

    protected static ?int $navigationSort = 2;

    public static function canViewAny(): bool
    {
        return auth()->user()?->hasOversight() ?? false;
    }

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

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()
            ->role([UserRole::STUDENT->value, UserRole::RESEARCHER->value])
            ->with('school')
            ->withCount([
                'projectsAsStudent as projects_count',
                'projectsAsStudent as completed_count' => fn (Builder $projects) => $projects->where('status', ProjectStatus::COMPLETED->value),
                'projectsAsStudent as active_count' => fn (Builder $projects) => $projects->whereIn('status', [
                    ProjectStatus::PROPOSAL_SUBMITTED->value,
                    ProjectStatus::UNDER_SUPERVISION->value,
                    ProjectStatus::UNDER_REVIEW->value,
                ]),
            ]);

        $schoolId = auth()->user()?->oversightSchoolId();

        return $schoolId === null ? $query : $query->where('school_id', $schoolId);
    }

    public static function table(Table $table): Table
    {
        $isDean = fn (): bool => auth()->user()?->oversightSchoolId() !== null;

        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable(['name', 'email'])
                    ->sortable()
                    ->weight('medium')
                    ->description(fn (User $record): string => $record->email),
                TextColumn::make('school.name')
                    ->label('School')
                    ->formatStateUsing(fn (string $state): string => (string) str($state)->after('School of '))
                    ->placeholder('Not set')
                    ->hidden($isDean),
                TextColumn::make('department')->placeholder('Not set')->toggleable(),
                TextColumn::make('active_count')->label('In progress')->numeric()->sortable(),
                TextColumn::make('completed_count')->label('Completed')->numeric()->sortable(),
                TextColumn::make('projects_count')->label('All projects')->numeric()->sortable(),
            ])
            ->filters([
                SelectFilter::make('school_id')
                    ->label('School')
                    ->options(fn (): array => School::options())
                    ->hidden($isDean),
            ])
            ->defaultSort('name');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSchoolStudents::route('/'),
        ];
    }
}
