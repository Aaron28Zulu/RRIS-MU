<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Enums\ProjectStatus;
use App\Enums\ResearchCategory;
use App\Filament\Resources\RepositoryResource\Pages;
use App\Filament\Tables\Columns\TitleColumn;
use App\Models\Project;
use App\Models\ProjectManuscript;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Tables\Actions\Action;
use Filament\Tables\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;

/**
 * The repository account: every uploaded manuscript with its summary and a download link, for librarians
 * and senior staff preparing research for publication. The public repository stays metadata-only.
 * Deans see their own school's uploads; librarians, the research director and admin see every school.
 */
class RepositoryResource extends Resource
{
    protected static ?string $model = Project::class;

    protected static ?string $slug = 'repository-files';

    protected static ?string $navigationIcon = 'heroicon-o-archive-box-arrow-down';

    protected static ?string $navigationGroup = 'Repository';

    protected static ?string $navigationLabel = 'Uploaded research';

    protected static ?string $modelLabel = 'uploaded study';

    protected static ?string $pluralModelLabel = 'uploaded research';

    protected static ?int $navigationSort = 1;

    public static function canViewAny(): bool
    {
        return auth()->user()?->canBrowseRepository() ?? false;
    }

    public static function canView(Model $record): bool
    {
        return static::canViewAny() && Gate::allows('downloadFullContent', $record);
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

    public static function canDeleteAny(): bool
    {
        return false;
    }

    /**
     * Studies with at least one file on record, limited to a dean's own school.
     */
    public static function getEloquentQuery(): Builder
    {
        $user = auth()->user();

        $query = parent::getEloquentQuery()
            ->where(fn (Builder $files) => $files->whereNotNull('full_document_path')->orWhereHas('manuscripts'))
            ->with(['school', 'student:id,name', 'supervisor:id,name'])
            ->withCount('manuscripts')
            ->withMax('manuscripts', 'created_at');

        if ($user === null) {
            return $query->whereRaw('1 = 0');
        }

        // Librarians read every school; a librarian who is also a dean is not narrowed by the deanship.
        return $user->isLibrarian() ? $query : $query->withinOversightOf($user);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TitleColumn::make('title')
                    ->searchable(['title', 'abstract'])
                    ->sortable()
                    ->description(fn (Project $record): string => str($record->plainAbstract())->limit(140)->toString()),
                TextColumn::make('school.name')
                    ->label('School')
                    ->formatStateUsing(fn (string $state): string => (string) str($state)->after('School of '))
                    ->placeholder('Not set')
                    ->toggleable(),
                TextColumn::make('category')
                    ->label('Subject')
                    ->formatStateUsing(fn (ResearchCategory $state): string => $state->shortLabel())
                    ->tooltip(fn (Project $record): string => $record->category->label())
                    ->color('gray')
                    ->toggleable(),
                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (ProjectStatus $state): string => $state->label())
                    ->color(fn (ProjectStatus $state): string => $state->color()),
                TextColumn::make('student.name')->label('Author')->searchable(),
                TextColumn::make('completion_year')->label('Year')->sortable()->placeholder('In progress'),
                TextColumn::make('manuscripts_count')->label('Files')->numeric()->sortable(),
                TextColumn::make('manuscripts_max_created_at')->label('Last upload')->since()->sortable()->placeholder('—'),
            ])
            ->filters([
                SelectFilter::make('school_id')
                    ->label('School')
                    ->relationship('school', 'name')
                    ->visible(fn (): bool => auth()->user()?->isLibrarian() || auth()->user()?->oversightSchoolId() === null),
                SelectFilter::make('category')->label('Subject')->options(ResearchCategory::options()),
                SelectFilter::make('status')->options(collect(ProjectStatus::cases())->mapWithKeys(fn (ProjectStatus $s): array => [$s->value => $s->label()])->all()),
            ])
            ->defaultSort('updated_at', 'desc')
            ->actions([
                ViewAction::make()->label('Summary'),
                Action::make('download')
                    ->label('Download')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->visible(fn (Project $record): bool => filled($record->full_document_path))
                    ->url(fn (Project $record): string => route('projects.download', $record)),
            ]);
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([
            Section::make('Summary')
                ->columns(3)
                ->schema([
                    TextEntry::make('title')->columnSpanFull()->weight('bold'),
                    TextEntry::make('school.name')->label('School')->placeholder('Not set'),
                    TextEntry::make('category')->label('Subject')->formatStateUsing(fn (ResearchCategory $state): string => $state->label()),
                    TextEntry::make('status')->badge()->formatStateUsing(fn (ProjectStatus $state): string => $state->label())->color(fn (ProjectStatus $state): string => $state->color()),
                    TextEntry::make('student.name')->label('Author'),
                    TextEntry::make('supervisor.name')->label('Supervisor')->placeholder('Not assigned'),
                    TextEntry::make('completion_year')->label('Year')->placeholder('In progress'),
                    TextEntry::make('abstract')->state(fn (Project $record): string => $record->plainAbstract())->columnSpanFull(),
                    TextEntry::make('researchGaps.extracted_gap_text')->label('Research gaps')->listWithLineBreaks()->bulleted()->placeholder('None recorded')->columnSpanFull(),
                    TextEntry::make('citation')->state(fn (Project $record): string => $record->citation())->copyable()->columnSpanFull(),
                ]),
            Section::make('Files')
                ->schema([
                    TextEntry::make('full_document_path')
                        ->label('Final manuscript')
                        ->state(fn (Project $record): ?string => $record->full_document_path ? 'Download the final manuscript' : null)
                        ->url(fn (Project $record): ?string => $record->full_document_path ? route('projects.download', $record) : null)
                        ->color('primary')
                        ->placeholder('No final manuscript uploaded'),
                    RepeatableEntry::make('manuscripts')
                        ->label('Submitted versions')
                        ->columns(4)
                        ->schema([
                            TextEntry::make('version')->label('Version'),
                            TextEntry::make('file_name')
                                ->label('File')
                                ->url(fn (ProjectManuscript $record): string => route('projects.manuscripts.download', [$record->project_id, $record]))
                                ->color('primary'),
                            TextEntry::make('milestone_stage')->label('Stage')->formatStateUsing(fn ($state): string => is_object($state) && method_exists($state, 'label') ? $state->label() : (string) $state)->placeholder('—'),
                            TextEntry::make('created_at')->label('Uploaded')->date('j M Y'),
                        ])
                        ->placeholder('No versions uploaded'),
                ]),
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListRepositoryFiles::route('/'),
        ];
    }
}
