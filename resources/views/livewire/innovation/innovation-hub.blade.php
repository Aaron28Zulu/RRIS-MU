<div>
    {{-- Office masthead --}}
    <section class="border-b border-line bg-canvas">
        <div class="container-page py-8 sm:py-14">
            <nav class="text-sm text-muted" aria-label="Breadcrumb">
                <a href="{{ route('home') }}" class="hover:text-forest-800">Home</a>
                <span class="mx-1.5" aria-hidden="true">/</span>
                <span class="text-ink">Research &amp; Innovation</span>
            </nav>
            <p class="eyebrow mt-6 text-forest-700">Mulungushi University Office of Research and Innovation</p>
            <h1 class="mt-3 max-w-3xl text-3xl font-bold tracking-tight sm:text-4xl">
                Advancing every discipline through research, innovation
                <span class="font-serif font-semibold text-forest-800">and knowledge sharing.</span>
            </h1>
            <p class="mt-4 max-w-2xl text-ink-soft">
                Enter the Research and Innovation Challenge, sign up for debates and workshops, and log data at research sites across the university's schools.
            </p>
            <div class="mt-6 flex flex-col gap-3 sm:flex-row sm:flex-wrap">
                <a href="#challenge" class="btn-primary">Innovation Challenge</a>
                <a href="#events" class="btn-secondary">Debates &amp; workshops</a>
                <a href="#sites" class="btn-secondary">Research sites</a>
            </div>
        </div>
    </section>

    <div class="container-page space-y-12 py-8 sm:space-y-16 sm:py-12">
        {{-- Research and Innovation Challenge --}}
        <section id="challenge" class="scroll-mt-24">
            <div class="flex flex-wrap items-end justify-between gap-4">
                <div>
                    <h2 class="text-xl font-bold tracking-tight sm:text-2xl">Research &amp; Innovation Challenge</h2>
                    <p class="mt-2 max-w-2xl text-ink-soft">A once-a-semester call for research proposals and innovative project ideas. A review panel scores every entry, shortlisted teams present, and selected projects get guidance from the office and are published with the university.</p>
                </div>
            </div>

            @if ($featuredChallenge)
                <article class="panel mt-6 grid grid-cols-1 gap-6 p-5 sm:gap-8 sm:p-8 lg:grid-cols-[1fr_16rem]">
                    <div class="min-w-0">
                        <div class="flex flex-wrap items-center gap-3">
                            <x-pill :color="$featuredChallenge->status->color()">{{ $featuredChallenge->status->label() }}</x-pill>
                            <span class="text-sm text-muted">{{ $featuredChallenge->edition }}</span>
                        </div>
                        <h3 class="record-title mt-3 text-xl sm:text-2xl">
                            <a href="{{ route('innovation.challenge', $featuredChallenge) }}" class="hover:text-forest-800 hover:underline">{{ $featuredChallenge->title }}</a>
                        </h3>
                        @if ($featuredChallenge->theme)
                            <p class="mt-2 text-sm font-medium text-forest-800">Theme: {{ $featuredChallenge->theme }}</p>
                        @endif
                        <p class="mt-3 line-clamp-3 leading-relaxed text-ink-soft">{{ $featuredChallenge->description }}</p>
                        <a href="{{ route('innovation.challenge', $featuredChallenge) }}" class="mt-5 inline-flex items-center gap-1 text-sm font-semibold text-forest-700 hover:underline">
                            {{ $featuredChallenge->isAcceptingEntries() ? 'Read the call and enter' : 'View the call' }} <x-glyph name="arrow-right" class="h-4 w-4" />
                        </a>
                    </div>
                    <dl class="grid grid-cols-1 gap-4 border-t border-line pt-5 text-sm min-[480px]:grid-cols-3 lg:grid-cols-1 lg:border-t-0 lg:border-l lg:pt-0 lg:pl-8">
                        <div>
                            <dt class="text-muted">Entries close</dt>
                            <dd class="mt-0.5 font-semibold">{{ $featuredChallenge->closes_on?->format('j M Y') ?? 'To be announced' }}</dd>
                        </div>
                        <div>
                            <dt class="text-muted">Shortlist presentations</dt>
                            <dd class="mt-0.5 font-semibold">{{ $featuredChallenge->presentation_on?->format('j M Y') ?? 'To be announced' }}</dd>
                        </div>
                        <div>
                            <dt class="text-muted">Entries received</dt>
                            <dd class="mt-0.5 font-semibold">{{ number_format($featuredChallenge->entries_count) }}</dd>
                        </div>
                    </dl>
                </article>
            @else
                <div class="panel mt-6 p-8 text-center text-sm text-muted">The next call for proposals has not been announced yet.</div>
            @endif

            @if ($recognised->isNotEmpty())
                <h3 class="mt-10 text-lg font-semibold">Recognised projects</h3>
                <ul class="mt-4 grid grid-cols-1 gap-4 md:grid-cols-2">
                    @foreach ($recognised as $entry)
                        <li class="panel p-5">
                            <p class="eyebrow text-ochre-700">{{ $entry->award ?? 'Recognised' }} &middot; {{ $entry->challenge->edition }}</p>
                            <p class="record-title mt-2">{{ $entry->title }}</p>
                            <p class="mt-1 text-sm text-ink-soft">{{ $entry->student->name }}@if ($entry->team_members) <span class="text-muted">with {{ $entry->team_members }}</span>@endif</p>
                        </li>
                    @endforeach
                </ul>
            @endif

            @if ($pastChallenges->isNotEmpty())
                <h3 class="mt-10 text-lg font-semibold">Earlier calls</h3>
                <ul class="mt-3 divide-y divide-line border-y border-line">
                    @foreach ($pastChallenges as $challenge)
                        <li class="flex flex-wrap items-center justify-between gap-3 py-3">
                            <a href="{{ route('innovation.challenge', $challenge) }}" class="min-w-0 font-medium hover:text-forest-800 hover:underline">{{ $challenge->title }}</a>
                            <span class="flex items-center gap-3 text-sm text-muted">{{ $challenge->edition }} <x-pill :color="$challenge->status->color()">{{ $challenge->status->label() }}</x-pill></span>
                        </li>
                    @endforeach
                </ul>
            @endif
        </section>

        {{-- Debates and workshops --}}
        <section id="events" class="scroll-mt-24">
            <h2 class="text-xl font-bold tracking-tight sm:text-2xl">Debates, workshops &amp; seminars</h2>
            <p class="mt-2 max-w-2xl text-ink-soft">Evidence-based discussion of Zambia's development questions, platform launches and skills sessions. Register to attend, or put your name forward to speak.</p>

            <div class="mt-6 grid grid-cols-1 gap-4 md:grid-cols-2">
                @forelse ($upcomingEvents as $event)
                    <a href="{{ route('innovation.event', $event) }}" class="panel group flex gap-4 p-4 transition hover:border-forest-300 sm:gap-5 sm:p-5">
                        <div class="flex w-14 shrink-0 flex-col sm:w-16 items-center justify-center rounded-md bg-forest-50 py-2 text-forest-800">
                            <span class="text-xs font-semibold uppercase">{{ $event->starts_at->format('M') }}</span>
                            <span class="text-2xl font-bold leading-none">{{ $event->starts_at->format('j') }}</span>
                        </div>
                        <div class="min-w-0">
                            <x-pill :color="$event->event_type->color()">{{ $event->event_type->label() }}</x-pill>
                            <p class="mt-2 font-semibold leading-snug group-hover:text-forest-800">{{ $event->title }}</p>
                            <p class="mt-1 text-sm text-muted">{{ $event->starts_at->format('D j M, H:i') }}@if ($event->venue) &middot; {{ $event->venue }}@endif</p>
                            <p class="mt-1 text-xs text-muted">{{ $event->attendees_count }} registered{{ $event->capacity ? ' of '.$event->capacity.' seats' : '' }}</p>
                        </div>
                    </a>
                @empty
                    <div class="panel p-8 text-center text-sm text-muted md:col-span-2">No upcoming events. Check back soon.</div>
                @endforelse
            </div>

            @if ($pastEvents->isNotEmpty())
                <h3 class="mt-8 text-lg font-semibold">Past events and their outcomes</h3>
                <ul class="mt-3 divide-y divide-line border-y border-line">
                    @foreach ($pastEvents as $event)
                        <li class="flex flex-wrap items-center justify-between gap-3 py-3">
                            <a href="{{ route('innovation.event', $event) }}" class="min-w-0 font-medium hover:text-forest-800 hover:underline">{{ $event->title }}</a>
                            <span class="text-sm text-muted">{{ $event->starts_at->format('j M Y') }}{{ $event->outcomes ? ' · Outcomes recorded' : '' }}</span>
                        </li>
                    @endforeach
                </ul>
            @endif
        </section>

        {{-- Research sites --}}
        <section id="sites" class="scroll-mt-24">
            <h2 class="text-xl font-bold tracking-tight sm:text-2xl">Research sites</h2>
            <p class="mt-2 max-w-2xl text-ink-soft">Field trials, labs, schools, clinics and community studies where students from every school log activities and measurements, building shared datasets for future research.</p>

            <div class="mt-6 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                @forelse ($sites as $site)
                    <a href="{{ route('innovation.site', $site) }}" class="panel group block p-5 transition hover:border-forest-300">
                        <div class="flex items-center justify-between gap-3">
                            <x-pill :color="$site->status->color()">{{ $site->status->label() }}</x-pill>
                            <span class="text-xs text-muted">{{ $site->period }}</span>
                        </div>
                        <p class="mt-3 font-semibold group-hover:text-forest-800">{{ $site->name }}</p>
                        <p class="mt-1 text-sm text-ink-soft">{{ $site->focus }}</p>
                        <p class="mt-3 text-xs text-muted">
                            {{ $site->category->label() }} &middot; {{ $site->observations_count }} {{ str('record')->plural($site->observations_count) }}
                        </p>
                    </a>
                @empty
                    <div class="panel p-8 text-center text-sm text-muted sm:col-span-2 lg:col-span-3">No research sites have been set up yet.</div>
                @endforelse
            </div>
        </section>
    </div>
</div>
