<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Enums\ProjectStatus;
use App\Enums\ResearchCategory;
use App\Enums\SiteStatus;
use App\Enums\UserRole;
use App\Filament\Resources\ResearchSiteResource\Pages;
use App\Models\Project;
use App\Models\ResearchSite;
use App\Models\User;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables\Actions\Action;
use Filament\Tables\Actions\ActionGroup;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

/**
 * Sites where students collect data for research: field trials, labs, schools, clinics and communities.
 */
class ResearchSiteResource extends Resource
{
    protected static ?string $model = ResearchSite::class;

    protected static ?string $navigationIcon = 'heroicon-o-map-pin';

    protected static ?string $navigationGroup = 'Research Office';

    protected static ?string $navigationLabel = 'Research sites';

    protected static ?string $modelLabel = 'research site';

    protected static ?int $navigationSort = 4;

    public static function canViewAny(): bool
    {
        return auth()->user()?->managesResearchOffice() ?? false;
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Grid::make(2)->schema([
                TextInput::make('name')->required()->maxLength(255)->placeholder('Kabwe Water Quality Stations'),
                Select::make('category')->label('Subject')->options(ResearchCategory::options())->searchable()->required(),
                TextInput::make('focus')
                    ->label('What is studied')
                    ->required()
                    ->maxLength(255)
                    ->placeholder('Borehole and stream water quality'),
                TextInput::make('method')->label('Method')->maxLength(255)->placeholder('Monthly sampling, 6 stations'),
                TextInput::make('location')->maxLength(255),
                TextInput::make('period')->required()->maxLength(255)->placeholder('Semester 1, 2026'),
                Select::make('status')->options(SiteStatus::options())->default(SiteStatus::SETUP->value)->required(),
                Select::make('coordinator_id')
                    ->label('Coordinator')
                    ->options(fn (): array => User::role([UserRole::RESEARCH_DIRECTOR->value, UserRole::SUPERVISOR->value])->pluck('name', 'id')->all())
                    ->searchable(),
                DatePicker::make('started_on')->label('Data collection starts')->native(false),
                DatePicker::make('ends_on')->label('Data collection ends')->native(false)->afterOrEqual('started_on'),
                Select::make('project_id')
                    ->label('Linked research project')
                    ->helperText('Optional: a student study that uses this site\'s data.')
                    ->options(fn (): array => Project::query()
                        ->where('status', '!=', ProjectStatus::REJECTED->value)
                        ->orderBy('title')
                        ->pluck('title', 'id')
                        ->all())
                    ->searchable()
                    ->columnSpanFull(),
                Textarea::make('objectives')
                    ->label('Learning and research objectives')
                    ->rows(3)
                    ->columnSpanFull(),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->weight('medium')
                    ->description(fn (ResearchSite $record): string => $record->focus.($record->method ? " · {$record->method}" : '')),
                TextColumn::make('category')
                    ->label('Subject')
                    ->badge()
                    ->formatStateUsing(fn (ResearchCategory $state): string => $state->label())
                    ->color(fn (ResearchCategory $state): string => $state->color())
                    ->toggleable(),
                TextColumn::make('period')->toggleable(),
                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (SiteStatus $state): string => $state->label())
                    ->color(fn (SiteStatus $state): string => $state->color()),
                TextColumn::make('started_on')->label('Started')->date('j M Y')->sortable(),
                TextColumn::make('observations_count')->counts('observations')->label('Records')->alignCenter(),
                TextColumn::make('observations_max_observed_on')
                    ->max('observations', 'observed_on')
                    ->label('Last record')
                    ->date('j M Y')
                    ->placeholder('None yet'),
                TextColumn::make('coordinator.name')->label('Coordinator')->placeholder('Unassigned')->toggleable(),
            ])
            ->filters([
                SelectFilter::make('status')->options(SiteStatus::options()),
                SelectFilter::make('category')->label('Subject')->options(ResearchCategory::options()),
            ])
            ->defaultSort('started_on', 'desc')
            ->actions([
                Action::make('log')
                    ->label('Data log')
                    ->icon('heroicon-o-table-cells')
                    ->color('gray')
                    ->url(fn (ResearchSite $record): string => route('innovation.site', $record))
                    ->openUrlInNewTab(),
                ActionGroup::make([
                    Action::make('export')
                        ->label('Download CSV')
                        ->icon('heroicon-o-arrow-down-tray')
                        ->url(fn (ResearchSite $record): string => route('innovation.site.export', $record)),
                    EditAction::make(),
                    DeleteAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ManageResearchSites::route('/'),
        ];
    }
}
