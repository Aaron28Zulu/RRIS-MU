<x-shell>
    {{-- Discovery hero --}}
    <section class="relative overflow-hidden border-b border-line bg-canvas">
        <svg class="pointer-events-none absolute -right-24 top-0 hidden h-full w-[640px] text-forest-200 lg:block" viewBox="0 0 640 480" fill="none" aria-hidden="true">
            @foreach (range(0, 11) as $i)
                <path d="M{{ 40 + $i * 14 }} 480 C {{ 120 + $i * 18 }} {{ 360 - $i * 10 }}, {{ 260 + $i * 12 }} {{ 420 - $i * 22 }}, {{ 360 + $i * 8 }} {{ 260 - $i * 14 }} S {{ 560 + $i * 4 }} {{ 120 - $i * 6 }}, 660 {{ 40 + $i * 6 }}" stroke="currentColor" stroke-width="1" opacity="{{ 0.9 - $i * 0.06 }}"/>
            @endforeach
        </svg>

        <div class="container-page relative py-10 sm:py-20 lg:py-24">
            <div class="max-w-3xl">
                <p class="eyebrow text-forest-700">Mulungushi University &middot; Research Repository Interface System</p>
                <h1 class="mt-4 text-3xl font-bold tracking-tight text-ink sm:text-5xl">
                    Every school's research, in one place
                    <span class="block font-serif font-semibold text-forest-800">for you to build on.</span>
                </h1>
                <p class="mt-5 max-w-2xl text-base leading-relaxed text-ink-soft sm:text-lg">
                    Search the university's catalogue of completed student research across every department, see where the open questions are, and register your own study without repeating work that's already been done.
                </p>
            </div>

            <form action="{{ route('repository') }}" method="GET" role="search" class="mt-8 max-w-3xl sm:mt-10">
                <label for="hero-search" class="sr-only">Search the archive</label>
                <div class="flex flex-col gap-2 rounded-xl border border-line-strong bg-white p-2 shadow-sm sm:flex-row sm:items-center">
                    <select name="field" aria-label="Search field" class="rounded-lg border-0 bg-canvas py-3 pr-9 pl-3 text-sm font-medium text-ink-soft focus:ring-2 focus:ring-forest-600/20">
                        <option value="all">All fields</option>
                        <option value="title">Title</option>
                        <option value="abstract">Abstract</option>
                        <option value="author">Author</option>
                        <option value="supervisor">Supervisor</option>
                    </select>
                    <div class="relative min-w-0 flex-1">
                        <x-glyph name="search" class="pointer-events-none absolute top-1/2 left-3 h-5 w-5 -translate-y-1/2 text-subtle" />
                        <input id="hero-search" type="search" name="search" placeholder="Try “mobile money”, “antenatal care” or a supervisor’s name" class="w-full rounded-lg border-0 py-3 pr-3 pl-10 text-base text-ink placeholder:text-subtle focus:ring-0 focus:outline-none" />
                    </div>
                    <button type="submit" class="btn-primary rounded-lg px-6 py-3">Search</button>
                </div>
            </form>

            <div class="mt-6 flex flex-wrap items-center gap-x-6 gap-y-3 text-sm">
                <a href="{{ route('repository') }}" class="inline-flex items-center gap-1.5 font-semibold text-forest-800 hover:underline">
                    Explore Archive <x-glyph name="arrow-right" class="h-4 w-4" />
                </a>
                <a href="{{ route('gaps') }}" class="font-medium text-ink-soft hover:text-ink">Browse research gaps</a>
                <a href="{{ auth()->user()?->isStudent() ? route('student.proposal.create') : route('login') }}" class="font-medium text-ink-soft hover:text-ink">Submit Research Proposal</a>
            </div>
        </div>
    </section>

    {{-- Archive at a glance --}}
    <section class="border-b border-line bg-white">
        <dl class="container-page grid grid-cols-2 gap-x-4 divide-line py-5 sm:grid-cols-4 sm:gap-x-0 sm:divide-x sm:py-6">
            <div class="py-2 sm:px-6 sm:first:pl-0">
                <dt class="text-xs text-muted sm:text-sm">Catalogued studies</dt>
                <dd class="mt-1 text-xl font-semibold tracking-tight sm:text-2xl">{{ number_format($totalProjects) }}</dd>
            </div>
            <div class="py-2 sm:px-6">
                <dt class="text-xs text-muted sm:text-sm">Research gaps indexed</dt>
                <dd class="mt-1 text-xl font-semibold tracking-tight sm:text-2xl">{{ number_format($totalGaps) }}</dd>
            </div>
            <div class="py-2 sm:px-6">
                <dt class="text-xs text-muted sm:text-sm">Thematic domains</dt>
                <dd class="mt-1 text-xl font-semibold tracking-tight sm:text-2xl">{{ number_format($totalDomains) }}</dd>
            </div>
            <div class="py-2 sm:px-6">
                <dt class="text-xs text-muted sm:text-sm">Studies in progress</dt>
                <dd class="mt-1 text-xl font-semibold tracking-tight sm:text-2xl">{{ number_format($activeStudies) }}</dd>
            </div>
        </dl>
    </section>

    {{-- Browse by subject --}}
    <section id="subjects" class="container-page scroll-mt-24 py-10 sm:py-16">
        <div class="flex items-end justify-between gap-4">
            <div>
                <h2 class="text-xl font-bold tracking-tight sm:text-2xl">Browse by subject</h2>
                <p class="mt-2 text-ink-soft">Start from a discipline in any of the university's schools and narrow down from there.</p>
            </div>
            <a href="{{ route('repository') }}" class="hidden text-sm font-semibold text-forest-800 hover:underline sm:inline">All subjects</a>
        </div>

        <div class="mt-6 grid grid-cols-1 gap-px overflow-hidden rounded-lg border border-line bg-line sm:grid-cols-2 lg:grid-cols-4">
            @foreach ($categories as $category)
                <a href="{{ route('repository', ['category' => $category->value]) }}" class="group flex flex-col bg-white p-5 transition hover:bg-forest-50 sm:p-6">
                    <span class="text-xs font-semibold tracking-wide text-forest-700 uppercase">{{ $category->school() }}</span>
                    <span class="mt-1 text-sm font-medium text-muted">{{ number_format($categoryCounts[$category->value] ?? 0) }} {{ str('study')->plural($categoryCounts[$category->value] ?? 0) }}</span>
                    <span class="mt-2 text-lg font-semibold leading-snug text-ink group-hover:text-forest-800">{{ $category->label() }}</span>
                    <span class="mt-2 text-sm leading-relaxed text-ink-soft">{{ $category->description() }}</span>
                    <span class="mt-5 inline-flex items-center gap-1 text-sm font-semibold text-forest-700">
                        Browse <x-glyph name="arrow-right" class="h-4 w-4 transition group-hover:translate-x-0.5" />
                    </span>
                </a>
            @endforeach
        </div>
    </section>

    {{-- Recent studies and emerging gaps --}}
    <section class="border-t border-line bg-canvas">
        <div class="container-page grid grid-cols-1 gap-10 py-10 sm:py-16 lg:grid-cols-3 lg:gap-12">
            <div class="lg:col-span-2">
                <div class="flex items-end justify-between gap-4">
                    <h2 class="text-xl font-bold tracking-tight sm:text-2xl">Recently catalogued</h2>
                    <a href="{{ route('repository', ['sort' => 'latest']) }}" class="shrink-0 text-sm font-semibold text-forest-800 hover:underline">View all</a>
                </div>

                <ol class="mt-6 divide-y divide-line rounded-lg border border-line bg-white">
                    @forelse ($recentProjects as $project)
                        <li class="p-5 sm:p-6">
                            <div class="flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-muted">
                                <span class="tag bg-forest-50 text-forest-800">Research project</span>
                                <span>{{ $project->category->label() }}</span>
                                <span aria-hidden="true">&middot;</span>
                                <span>{{ $project->completion_year ?? 'In progress' }}</span>
                            </div>
                            <h3 class="record-title mt-2 text-lg">
                                <a href="{{ route('repository.show', $project) }}" class="hover:text-forest-800 hover:underline">{{ $project->title }}</a>
                            </h3>
                            <p class="mt-1 text-sm text-ink-soft">
                                {{ $project->student?->name ?? 'Unknown author' }}
                                @if ($project->supervisor)
                                    <span class="text-muted">&middot; Supervised by {{ $project->supervisor->name }}</span>
                                @endif
                            </p>
                            <p class="mt-3 line-clamp-2 text-sm leading-relaxed text-ink-soft">{{ $project->plainAbstract() }}</p>

                            @if ($project->researchGaps->isNotEmpty())
                                <p class="mt-3 flex gap-2 text-sm text-ink-soft">
                                    <x-glyph name="light-bulb" class="mt-0.5 h-4 w-4 shrink-0 text-ochre-500" />
                                    <span><span class="font-medium text-ink">Open question:</span> {{ $project->researchGaps->first()->extracted_gap_text }}</span>
                                </p>
                            @endif

                            <a href="{{ route('repository.show', $project) }}" class="mt-4 inline-flex items-center gap-1 text-sm font-semibold text-forest-700 hover:underline">
                                View Abstract &amp; Gaps <x-glyph name="arrow-right" class="h-4 w-4" />
                            </a>
                        </li>
                    @empty
                        <li class="p-10 text-center text-sm text-muted">No completed studies have been catalogued yet.</li>
                    @endforelse
                </ol>
            </div>

            <aside>
                <div class="flex items-end justify-between gap-4">
                    <h2 class="text-xl font-bold tracking-tight sm:text-2xl">Emerging gaps</h2>
                    <a href="{{ route('gaps', ['signal' => 'emerging']) }}" class="shrink-0 text-sm font-semibold text-forest-800 hover:underline">Explore</a>
                </div>
                <p class="mt-2 text-sm text-ink-soft">Future directions flagged by recent studies, a good place to look for a topic.</p>

                <ul class="mt-6 space-y-3">
                    @forelse ($emergingGaps as $gap)
                        <li class="panel p-4">
                            <p class="eyebrow text-ochre-700">{{ $gap->domain?->name ?? 'General research' }}</p>
                            <p class="mt-2 text-sm leading-relaxed text-ink">{{ $gap->extracted_gap_text }}</p>
                            @if ($gap->project)
                                <a href="{{ route('repository.show', $gap->project_id) }}" class="mt-2 block truncate text-xs text-muted hover:text-forest-800">From: {{ $gap->project->title }}</a>
                            @endif
                        </li>
                    @empty
                        <li class="panel p-6 text-sm text-muted">Gaps appear here once completed studies are analysed.</li>
                    @endforelse
                </ul>
            </aside>
        </div>
    </section>

    {{-- Office of Research and Innovation --}}
    <section class="border-b border-line bg-forest-950 text-forest-100">
        <div class="container-page flex flex-col items-start gap-6 py-10 sm:py-12 lg:flex-row lg:items-center lg:justify-between">
            <div class="max-w-2xl">
                <p class="eyebrow text-forest-300">Office of Research and Innovation</p>
                <h2 class="mt-3 text-xl font-bold tracking-tight text-white sm:text-2xl">Take your idea further than the archive.</h2>
                <p class="mt-2 leading-relaxed">Enter the Research and Innovation Challenge, join debates and workshops, and log data at research sites run across the university's schools.</p>
            </div>
            <a href="{{ route('innovation') }}" class="btn shrink-0 bg-white text-forest-900 hover:bg-forest-50">Research &amp; Innovation <x-glyph name="arrow-right" class="h-4 w-4" /></a>
        </div>
    </section>

    {{-- How the archive works --}}
    <section class="container-page py-10 sm:py-16">
        <h2 class="text-xl font-bold tracking-tight sm:text-2xl">One archive, three ways in</h2>
        <div class="mt-6 grid grid-cols-1 gap-8 sm:mt-8 md:grid-cols-3 md:gap-10">
            <div>
                <x-glyph name="magnifying-glass" class="h-6 w-6 text-forest-700" />
                <h3 class="mt-4 font-semibold">Anyone can discover</h3>
                <p class="mt-2 text-sm leading-relaxed text-ink-soft">Titles, abstracts, subjects and identified research gaps of completed studies are open to search.</p>
            </div>
            <div>
                <x-glyph name="academic-cap" class="h-6 w-6 text-forest-700" />
                <h3 class="mt-4 font-semibold">Students register and submit</h3>
                <p class="mt-2 text-sm leading-relaxed text-ink-soft">Check for duplication, register a proposal, upload milestone drafts and receive your supervisor's feedback in one place.</p>
            </div>
            <div>
                <x-glyph name="shield-check" class="h-6 w-6 text-forest-700" />
                <h3 class="mt-4 font-semibold">Lecturers review with integrity</h3>
                <p class="mt-2 text-sm leading-relaxed text-ink-soft">Supervisors see only their advisees, run originality checks and record rubric decisions. Full texts never leave that circle.</p>
            </div>
        </div>
    </section>
</x-shell>
