<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Repositories\GapAggregationRepository;
use Filament\Widgets\ChartWidget;

class SaturatedDomainsChart extends ChartWidget
{
    protected static ?string $heading = 'Research Domain Saturation Index';

    protected static ?string $description = 'Completed student projects per thematic domain. Gold indicates saturated areas (≥5 projects); green represents active exploration.';

    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = 'full';

    protected static ?string $maxHeight = '320px';

    /**
     * Get chart data from GapAggregationRepository.
     */
    protected function getData(): array
    {
        /** @var GapAggregationRepository $repository */
        $repository = app(GapAggregationRepository::class);

        return $repository->getDomainDistributionChartData(8, auth()->user()?->oversightSchoolId());
    }

    /**
     * Chart type: bar chart.
     */
    protected function getType(): string
    {
        return 'bar';
    }

    /**
     * Custom chart display options.
     */
    protected function getOptions(): array
    {
        return [
            'scales' => [
                'y' => [
                    'beginAtZero' => true,
                    'ticks' => [
                        'stepSize' => 1,
                    ],
                ],
            ],
            'plugins' => [
                'legend' => [
                    'display' => false,
                ],
            ],
        ];
    }
}
