<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\ResearchDomain;
use App\Models\ResearchGap;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class GapAggregationRepository
{
    /**
     * Minimum project threshold to consider a domain highly saturated.
     */
    protected const HIGH_SATURATION_THRESHOLD = 5;

    /**
     * Retrieve thematic domains ranked by completed project volume (most researched/saturated first).
     *
     * @return Collection<int, ResearchDomain>
     */
    public function getSaturatedDomains(int $limit = 8, ?int $schoolId = null): Collection
    {
        return ResearchDomain::query()
            ->withCount(['researchGaps as projects_count' => function ($query) use ($schoolId) {
                $query->select(DB::raw('count(distinct(project_id))'));

                // Limit the count to one school's studies (a dean's view).
                if ($schoolId !== null) {
                    $query->whereHas('project', fn ($projects) => $projects->where('school_id', $schoolId));
                }
            }])
            ->orderByDesc('projects_count')
            ->limit($limit)
            ->get();
    }

    /**
     * Retrieve chart dataset formatting for Chart.js visualization.
     *
     * @return array{
     *     labels: list<string>,
     *     datasets: list<array{
     *         label: string,
     *         data: list<int>,
     *         backgroundColor: list<string>,
     *         borderColor: list<string>,
     *         borderWidth: int
     *     }>
     * }
     */
    public function getDomainDistributionChartData(int $limit = 8, ?int $schoolId = null): array
    {
        $domains = $this->getSaturatedDomains($limit, $schoolId);

        $labels = [];
        $data = [];
        $backgroundColors = [];
        $borderColors = [];

        foreach ($domains as $domain) {
            $labels[] = $domain->name;
            $count = (int) $domain->projects_count;
            $data[] = $count;

            if ($count >= self::HIGH_SATURATION_THRESHOLD) {
                // Saturated / High volume: Amber/Orange accent
                $backgroundColors[] = 'rgba(176, 122, 36, 0.85)';
                $borderColors[] = 'rgb(176, 122, 36)';
            } else {
                // Emerging / Low volume: Emerald/Green accent
                $backgroundColors[] = 'rgba(53, 103, 74, 0.85)';
                $borderColors[] = 'rgb(53, 103, 74)';
            }
        }

        return [
            'labels' => $labels,
            'datasets' => [
                [
                    'label' => 'Projects Conducted',
                    'data' => $data,
                    'backgroundColor' => $backgroundColors,
                    'borderColor' => $borderColors,
                    'borderWidth' => 1,
                ],
            ],
        ];
    }

    /**
     * Retrieve newly identified research opportunities in under-explored domains.
     *
     * @return Collection<int, ResearchGap>
     */
    public function getEmergingOpportunities(int $limit = 10): Collection
    {
        return ResearchGap::query()
            ->with([
                'domain:id,name,slug',
                'project:id,title,category,completion_year',
            ])
            ->where('is_emerging', true)
            ->where('is_saturated', false)
            ->latest('id')
            ->limit($limit)
            ->get();
    }
}
