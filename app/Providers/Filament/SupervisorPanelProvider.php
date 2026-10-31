<?php

declare(strict_types=1);

namespace App\Providers\Filament;

use App\Filament\AvatarProviders\InitialsAvatarProvider;
use App\Filament\Resources\AssociationEventResource;
use App\Filament\Resources\ChallengeEntryResource;
use App\Filament\Resources\InnovationChallengeResource;
use App\Filament\Resources\ProjectResource;
use App\Filament\Resources\RepositoryResource;
use App\Filament\Resources\ResearchDomainResource;
use App\Filament\Resources\ResearchSiteResource;
use App\Filament\Resources\SchoolStudentResource;
use App\Filament\Resources\UserResource;
use App\Filament\Supervisor\Pages\Dashboard;
use App\Filament\Supervisor\Resources\SupervisorProjectResource;
use App\Filament\Supervisor\Widgets\ReviewQueueWidget;
use App\Filament\Supervisor\Widgets\SupervisorStatsOverviewWidget;
use App\Filament\Widgets\EmergingGapsTable;
use App\Filament\Widgets\ResearchOfficeStatsWidget;
use App\Filament\Widgets\SaturatedDomainsChart;
use App\Filament\Widgets\SchoolsOverviewTable;
use App\Http\Middleware\EnsureAccountIsActive;
use App\Http\Middleware\EnsureUserIsSupervisor;
use Filament\FontProviders\GoogleFontProvider;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\MenuItem;
use Filament\Navigation\NavigationGroup;
use Filament\Navigation\NavigationItem;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Support\Enums\MaxWidth;
use Filament\View\PanelsRenderHook;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\AuthenticateSession;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Blade;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use Illuminate\View\View;

class SupervisorPanelProvider extends PanelProvider
{
    /**
     * Configure the staff workspace: the lecturer portal (supervisors, deans, the research director and admins) and the repository workspace for librarians.
     */
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('supervisor')
            ->path('supervisor')
            ->brandName('RRIS Lecturer Portal')
            ->brandLogo(fn () => view('filament.brand.logo'))
            ->brandLogoHeight('2.25rem')
            ->favicon(asset('favicon.ico'))
            ->colors([
                // The archive's forest scale, shifted so buttons (shade 600) match its primary buttons.
                'primary' => [
                    50 => '#f1f6f2',
                    100 => '#e1ece4',
                    200 => '#c3d9ca',
                    300 => '#98bda4',
                    400 => '#689c7b',
                    500 => '#35674a',
                    600 => '#2a523c',
                    700 => '#224232',
                    800 => '#1a3428',
                    900 => '#12241b',
                    950 => '#0e1f17',
                ],
                // Faintly sage neutrals: the archive's canvas and hairlines in light mode, graphite in dark mode.
                'gray' => [
                    50 => '#f6f7f5',
                    100 => '#eef0ec',
                    200 => '#e2e5de',
                    300 => '#cfd4cb',
                    400 => '#9aa29c',
                    500 => '#6c756f',
                    600 => '#535b56',
                    700 => '#3a413d',
                    800 => '#262b28',
                    900 => '#191c1b',
                    950 => '#101211',
                ],
                'info' => Color::Sky,
                'success' => Color::hex('#35674a'),
                'warning' => Color::hex('#b07a24'),
                'danger' => Color::Red,
            ])
            ->font('Google Sans', provider: GoogleFontProvider::class)
            ->defaultAvatarProvider(InitialsAvatarProvider::class)
            ->maxContentWidth(MaxWidth::SevenExtraLarge)
            ->sidebarWidth('16.5rem')
            ->sidebarCollapsibleOnDesktop()
            ->databaseNotifications()
            ->databaseNotificationsPolling('60s')
            ->navigationGroups([
                NavigationGroup::make('Supervision'),
                NavigationGroup::make('Research Office'),
                NavigationGroup::make('Research intelligence'),
                NavigationGroup::make('School oversight'),
                NavigationGroup::make('Repository'),
                NavigationGroup::make('Archive'),
                NavigationGroup::make('Administration'),
            ])
            ->navigationItems([
                NavigationItem::make('Search the archive')
                    ->url(fn (): string => route('repository'))
                    ->icon('heroicon-o-building-library')
                    ->group('Archive')
                    ->sort(1),
                NavigationItem::make('Research gaps')
                    ->url(fn (): string => route('gaps'))
                    ->icon('heroicon-o-light-bulb')
                    ->group('Archive')
                    ->sort(2),
                NavigationItem::make('Innovation')
                    ->url(fn (): string => route('innovation'))
                    ->icon('heroicon-o-sparkles')
                    ->group('Archive')
                    ->sort(3),
            ])
            ->userMenuItems([
                MenuItem::make()
                    ->label('Back to the archive')
                    ->url(fn (): string => route('home'))
                    ->icon('heroicon-o-arrow-uturn-left'),
            ])
            // Layer the RRIS skin over Filament's own stylesheet (a viteTheme() would replace it).
            ->renderHook(
                PanelsRenderHook::HEAD_END,
                fn (): string => file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot'))
                    ? Blade::render("@vite('resources/css/filament/lecturer.css')")
                    : '',
            )
            ->renderHook(PanelsRenderHook::BODY_START, fn (): string => Blade::render('<x-impersonation-banner />'))
            ->renderHook(PanelsRenderHook::TOPBAR_START, fn (): View => view('filament.topbar.context'))
            ->renderHook(PanelsRenderHook::GLOBAL_SEARCH_BEFORE, fn (): View => view('filament.topbar.archive-link'))
            ->renderHook(PanelsRenderHook::USER_MENU_BEFORE, fn (): View => view('filament.topbar.identity'))
            ->resources([
                SupervisorProjectResource::class,
                ProjectResource::class,
                SchoolStudentResource::class,
                RepositoryResource::class,
                InnovationChallengeResource::class,
                ChallengeEntryResource::class,
                AssociationEventResource::class,
                ResearchSiteResource::class,
                ResearchDomainResource::class,
                UserResource::class,
            ])
            ->pages([
                Dashboard::class,
            ])
            ->widgets([
                ResearchOfficeStatsWidget::class,
                SchoolsOverviewTable::class,
                SupervisorStatsOverviewWidget::class,
                ReviewQueueWidget::class,
                SaturatedDomainsChart::class,
                EmergingGapsTable::class,
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                EnsureAccountIsActive::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                EnsureUserIsSupervisor::class,
            ]);
    }
}
