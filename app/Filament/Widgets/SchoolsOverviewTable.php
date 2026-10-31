<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Enums\ProjectStatus;
use App\Enums\UserRole;
use App\Models\ResearchGap;
use App\Models\School;
use App\Models\User;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Database\Eloquent\Builder;

/**
 * University-wide comparison of the eight schools, for the research director and the administrator.
 * Deans see only their own school, so they do not get this view.
 */
class SchoolsOverviewTable extends BaseWidget
{
    protected static ?string $heading = 'Research across the schools';

    protected static ?int $sort = 1;

    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        return auth()->user()?->hasUniversityWideAccess() ?? false;
    }

    public function table(Table $table): Table
    {
        $inProgress = [
            ProjectStatus::PROPOSAL_SUBMITTED->value,
            ProjectStatus::UNDER_SUPERVISION->value,
            ProjectStatus::UNDER_REVIEW->value,
        ];

        return $table
            ->query(
                School::query()
                    ->withCount([
                        'projects as in_progress_count' => fn (Builder $query) => $query->whereIn('status', $inProgress),
                        'projects as completed_count' => fn (Builder $query) => $query->where('status', ProjectStatus::COMPLETED->value),
                    ])
                    ->addSelect([
                        'emerging_gaps_count' => ResearchGap::query()
                            ->selectRaw('count(*)')
                            ->join('projects', 'projects.id', '=', 'research_gaps.project_id')
                            ->whereColumn('projects.school_id', 'schools.id')
                            ->where('research_gaps.is_emerging', true),
                        'students_count' => User::query()
                            ->selectRaw('count(*)')
                            ->whereColumn('users.school_id', 'schools.id')
                            ->whereIn('users.role', [UserRole::STUDENT->value, UserRole::RESEARCHER->value]),
                        'dean_name' => User::query()
                            ->select('name')
                            ->whereColumn('users.school_id', 'schools.id')
                            ->where('users.role', UserRole::DEAN->value)
                            ->whereNull('users.deactivated_at')
                            ->limit(1),
                    ])
            )
            ->columns([
                TextColumn::make('name')
                    ->label('School')
                    ->formatStateUsing(fn (string $state): string => (string) str($state)->after('School of '))
                    ->weight('medium'),
                TextColumn::make('dean_name')->label('Dean')->placeholder('No dean assigned'),
                TextColumn::make('students_count')->label('Students')->numeric(),
                TextColumn::make('in_progress_count')->label('In progress')->numeric(),
                TextColumn::make('completed_count')->label('Completed')->numeric(),
                TextColumn::make('emerging_gaps_count')->label('Emerging gaps')->numeric(),
            ])
            ->paginated(false);
    }
}
