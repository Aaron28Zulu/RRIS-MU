<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Enums\EntryStatus;
use App\Enums\SiteStatus;
use App\Filament\Resources\AssociationEventResource;
use App\Filament\Resources\ChallengeEntryResource;
use App\Filament\Resources\ResearchSiteResource;
use App\Models\AssociationEvent;
use App\Models\ChallengeEntry;
use App\Models\ResearchSite;
use App\Models\SiteObservation;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * At-a-glance status of the Office of Research and Innovation's programmes.
 */
class ResearchOfficeStatsWidget extends BaseWidget
{
    protected static ?int $sort = 0;

    protected ?string $heading = 'Research Office';

    public static function canView(): bool
    {
        return auth()->user()?->managesResearchOffice() ?? false;
    }

    /**
     * @return array<Stat>
     */
    protected function getStats(): array
    {
        $nextEvent = AssociationEvent::query()->upcoming()->first();

        return [
            Stat::make('Entries to decide', ChallengeEntry::query()->where('status', EntryStatus::SUBMITTED->value)->count())
                ->description(ChallengeEntry::query()->where('status', EntryStatus::SHORTLISTED->value)->count().' shortlisted')
                ->icon('heroicon-o-trophy')
                ->url(ChallengeEntryResource::getUrl('index')),
            Stat::make('Upcoming events', AssociationEvent::query()->where('starts_at', '>=', now())->count())
                ->description($nextEvent ? 'Next on '.$nextEvent->starts_at->format('j M') : 'Nothing scheduled')
                ->icon('heroicon-o-megaphone')
                ->url(AssociationEventResource::getUrl('index')),
            Stat::make('Sites collecting data', ResearchSite::query()->where('status', SiteStatus::ACTIVE->value)->count())
                ->description(SiteObservation::query()->where('observed_on', '>=', now()->subDays(30))->count().' records in 30 days')
                ->icon('heroicon-o-map-pin')
                ->url(ResearchSiteResource::getUrl('index')),
        ];
    }
}
