<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Models\ResearchGap;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Database\Eloquent\Builder;

class EmergingGapsTable extends BaseWidget
{
    protected static ?string $heading = 'Emerging Research Opportunities & Gaps';

    protected static ?int $sort = 3;

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->query(
                ResearchGap::query()
                    ->where('is_emerging', true)
                    // A dean sees only gaps from their own school's studies.
                    ->whereHas('project', fn (Builder $projects) => $projects->withinOversightOf(auth()->user()))
                    ->with([
                        'domain:id,name,slug',
                        'project:id,title,category,completion_year',
                    ])
                    ->latest('id')
            )
            ->columns([
                TextColumn::make('domain.name')
                    ->label('Domain')
                    ->badge()
                    ->color('primary')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('extracted_gap_text')
                    ->label('Research gap')
                    ->wrap()
                    ->lineClamp(2)
                    ->tooltip(fn (ResearchGap $record): string => $record->extracted_gap_text)
                    ->searchable(),

                TextColumn::make('project.title')
                    ->label('Source study')
                    ->limit(40)
                    ->tooltip(fn (ResearchGap $record): ?string => $record->project?->title)
                    ->searchable(),

                TextColumn::make('project.completion_year')
                    ->label('Year')
                    ->sortable(),

                IconColumn::make('is_saturated')
                    ->label('Status')
                    ->boolean()
                    ->trueIcon('heroicon-s-fire')
                    ->falseIcon('heroicon-s-sparkles')
                    ->trueColor('warning')
                    ->falseColor('success')
                    ->tooltip(fn (ResearchGap $record): string => $record->is_saturated ? 'Saturated Domain' : 'Emerging Opportunity'),
            ])
            ->paginated([5, 10, 25])
            ->defaultPaginationPageOption(5);
    }
}
