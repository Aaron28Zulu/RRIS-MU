<div>
    <section class="border-b border-line bg-canvas">
        <div class="container-page py-6 sm:py-8">
            <nav class="text-sm text-muted" aria-label="Breadcrumb">
                <a href="{{ route('innovation') }}" class="hover:text-forest-800">Research &amp; Innovation</a>
                <span class="mx-1.5" aria-hidden="true">/</span>
                <span class="text-ink">{{ $challenge->edition }}</span>
            </nav>
            <div class="mt-4 flex flex-wrap items-center gap-3">
                <x-pill :color="$challenge->status->color()">{{ $challenge->status->label() }}</x-pill>
                <span class="text-sm text-muted">Research &amp; Innovation Challenge &middot; {{ $challenge->edition }}</span>
            </div>
            <h1 class="record-title mt-3 text-2xl sm:text-4xl">{{ $challenge->title }}</h1>
            @if ($challenge->theme)
                <p class="mt-2 font-medium text-forest-800">Theme: {{ $challenge->theme }}</p>
            @endif
        </div>
    </section>

    <div class="container-page grid grid-cols-1 gap-8 py-6 sm:py-10 lg:grid-cols-[1fr_18rem] lg:gap-10">
        <div class="min-w-0 space-y-10">
            <section>
                <h2 class="text-lg font-semibold">About this call</h2>
                <div class="mt-3 space-y-3 leading-relaxed whitespace-pre-line text-ink-soft">{{ $challenge->description }}</div>
            </section>

            <section>
                <h2 class="text-lg font-semibold">How entries are judged</h2>
                @if ($challenge->criteria)
                    <div class="mt-3 leading-relaxed whitespace-pre-line text-ink-soft">{{ $challenge->criteria }}</div>
                @endif
                <ul class="mt-4 grid grid-cols-1 gap-3 sm:grid-cols-2">
                    @foreach (\App\Models\EntryReview::CRITERIA as $criterion)
                        <li class="panel flex gap-3 p-4 text-sm"><x-glyph name="check-circle" class="h-5 w-5 shrink-0 text-forest-600" /> {{ $criterion }}</li>
                    @endforeach
                </ul>
                <p class="mt-3 text-sm text-muted">Each panel member scores every criterion from 1 to 5. Shortlisted entries present to the panel, and outstanding proposals are recognised and supported by the office.</p>
            </section>

            @if ($myEntries->isNotEmpty())
                <section>
                    <h2 class="text-lg font-semibold">Your entries</h2>
                    <ul class="mt-4 space-y-3">
                        @foreach ($myEntries as $entry)
                            <li wire:key="my-entry-{{ $entry->id }}" class="panel p-5">
                                <div class="flex flex-wrap items-center justify-between gap-3">
                                    <p class="min-w-0 font-semibold">{{ $entry->title }}</p>
                                    <x-pill :color="$entry->status->color()">{{ $entry->status->label() }}{{ $entry->award ? ' · '.$entry->award : '' }}</x-pill>
                                </div>
                                <p class="mt-1 text-sm text-muted">{{ $entry->entry_type->label() }} &middot; {{ $entry->category->label() }} &middot; Submitted {{ $entry->created_at->format('j M Y') }}</p>
                                @if ($entry->mentor)
                                    <p class="mt-3 text-sm"><span class="font-medium">Mentor:</span> {{ $entry->mentor->name }}</p>
                                @endif
                                @if ($entry->office_feedback)
                                    <div class="mt-3 rounded-md bg-canvas p-3 text-sm leading-relaxed text-ink-soft">
                                        <p class="font-medium text-ink">Feedback from the office</p>
                                        <p class="mt-1 whitespace-pre-line">{{ $entry->office_feedback }}</p>
                                    </div>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                </section>
            @endif

            @if ($canEnter)
                <section id="enter" class="scroll-mt-24">
                    <h2 class="text-lg font-semibold">Submit an entry</h2>
                    <p class="mt-1 text-sm text-ink-soft">Entries close {{ $challenge->closes_on?->format('j F Y') ?? 'when the office closes the call' }}. Teams submit once, under the lead student's name.</p>

                    <form wire:submit="submit" class="panel mt-5 space-y-5 p-5 sm:p-8">
                        <div>
                            <label for="title" class="field-label">Project title <span class="text-red-700">*</span></label>
                            <input id="title" type="text" wire:model.live.debounce.600ms="title" class="field" placeholder="e.g. Solar-powered drip kits for smallholder vegetable growers" />
                            @error('title') <p class="field-error">{{ $message }}</p> @enderror

                            @if ($similarRecords->isNotEmpty())
                                <div class="mt-3 rounded-md border border-ochre-100 bg-ochre-50 p-3 text-sm">
                                    <p class="font-medium text-ochre-700">Similar studies already in the archive</p>
                                    <ul class="mt-1.5 space-y-1">
                                        @foreach ($similarRecords as $record)
                                            <li><a href="{{ route('repository.show', $record) }}" target="_blank" class="link">{{ $record->title }}</a> <span class="text-muted">({{ $record->completion_year ?? 'n.d.' }})</span></li>
                                        @endforeach
                                    </ul>
                                    <p class="mt-1.5 text-xs text-muted">Check these so your entry builds on earlier work instead of repeating it.</p>
                                </div>
                            @endif
                        </div>

                        <div class="grid grid-cols-1 gap-5 md:grid-cols-2">
                            <div>
                                <label for="entry_type" class="field-label">Entry type <span class="text-red-700">*</span></label>
                                <select id="entry_type" wire:model="entry_type" class="field">
                                    <option value="">Select a type</option>
                                    @foreach ($entryTypes as $type)
                                        <option value="{{ $type->value }}">{{ $type->label() }}</option>
                                    @endforeach
                                </select>
                                @error('entry_type') <p class="field-error">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <label for="category" class="field-label">Subject <span class="text-red-700">*</span></label>
                                <select id="category" wire:model="category" class="field">
                                    <option value="">Select a subject</option>
                                    @foreach ($schools as $school => $subjects)
                                        <optgroup label="{{ $school }}">
                                            @foreach ($subjects as $cat)
                                                <option value="{{ $cat->value }}">{{ $cat->label() }}</option>
                                            @endforeach
                                        </optgroup>
                                    @endforeach
                                </select>
                                @error('category') <p class="field-error">{{ $message }}</p> @enderror
                            </div>
                        </div>

                        <div>
                            <label for="problem_statement" class="field-label">Problem statement <span class="text-red-700">*</span></label>
                            <textarea id="problem_statement" wire:model="problem_statement" rows="4" class="field leading-relaxed" placeholder="What challenge in your field or community does this address, and who is affected?"></textarea>
                            @error('problem_statement') <p class="field-error">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label for="proposed_solution" class="field-label">Proposed research or solution <span class="text-red-700">*</span></label>
                            <textarea id="proposed_solution" wire:model="proposed_solution" rows="4" class="field leading-relaxed" placeholder="Your research approach or the innovation you will build, and how you will test it"></textarea>
                            @error('proposed_solution') <p class="field-error">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label for="expected_impact" class="field-label">Expected impact <span class="text-red-700">*</span></label>
                            <textarea id="expected_impact" wire:model="expected_impact" rows="3" class="field leading-relaxed" placeholder="How does this contribute to national development?"></textarea>
                            @error('expected_impact') <p class="field-error">{{ $message }}</p> @enderror
                        </div>

                        <div class="grid grid-cols-1 gap-5 md:grid-cols-2">
                            <div>
                                <label for="team_members" class="field-label">Team members <span class="font-normal text-muted">(optional)</span></label>
                                <input id="team_members" type="text" wire:model="team_members" class="field" placeholder="Names of other students on the team" />
                                @error('team_members') <p class="field-error">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <label for="attachment" class="field-label">Concept note <span class="font-normal text-muted">(optional PDF, 10 MB)</span></label>
                                <input id="attachment" type="file" wire:model="attachment" accept="application/pdf" class="field min-w-0 py-2 text-sm file:mr-3 file:rounded file:border-0 file:bg-forest-50 file:px-3 file:py-1 file:text-forest-800" />
                                @error('attachment') <p class="field-error">{{ $message }}</p> @enderror
                            </div>
                        </div>

                        <div class="flex justify-end border-t border-line pt-5">
                            <button type="submit" class="btn-primary w-full sm:w-auto" wire:loading.attr="disabled" wire:target="submit,attachment">Submit entry</button>
                        </div>
                    </form>
                </section>
            @elseif ($challenge->isAcceptingEntries())
                <section class="panel p-6 text-sm text-ink-soft">
                    @auth
                        Entries are open to registered students of every school at Mulungushi University.
                    @else
                        <a href="{{ route('login') }}" class="link font-semibold">Sign in</a> with your student account to submit an entry.
                    @endauth
                </section>
            @endif

            @if ($shortlist->isNotEmpty())
                <section>
                    <h2 class="text-lg font-semibold">Shortlisted and recognised projects</h2>
                    <ul class="mt-4 divide-y divide-line border-y border-line">
                        @foreach ($shortlist as $entry)
                            <li class="py-5">
                                <div class="flex flex-wrap items-center gap-3">
                                    <x-pill :color="$entry->status->color()">{{ $entry->award ?? $entry->status->label() }}</x-pill>
                                    <span class="text-xs text-muted">{{ $entry->entry_type->label() }} &middot; {{ $entry->category->label() }}</span>
                                </div>
                                <p class="record-title mt-2 text-lg">{{ $entry->title }}</p>
                                <p class="mt-1 text-sm text-ink-soft">{{ $entry->student->name }}@if ($entry->team_members) <span class="text-muted">with {{ $entry->team_members }}</span>@endif</p>
                                <p class="mt-2 line-clamp-3 text-sm leading-relaxed text-ink-soft">{{ $entry->problem_statement }}</p>
                            </li>
                        @endforeach
                    </ul>
                </section>
            @endif
        </div>

        <aside class="space-y-6">
            <div class="panel p-5">
                <h2 class="eyebrow">Timeline</h2>
                <ol class="mt-4 space-y-4 text-sm">
                    <li><p class="text-muted">Call opens</p><p class="font-semibold">{{ $challenge->opens_on?->format('j M Y') ?? 'Now' }}</p></li>
                    <li><p class="text-muted">Entries close</p><p class="font-semibold">{{ $challenge->closes_on?->format('j M Y') ?? 'To be announced' }}</p></li>
                    <li><p class="text-muted">Shortlist presentations</p><p class="font-semibold">{{ $challenge->presentation_on?->format('j M Y') ?? 'To be announced' }}</p></li>
                </ol>
                @if ($canEnter)
                    <a href="#enter" class="btn-primary mt-5 w-full">Enter the challenge</a>
                @endif
            </div>
            <div class="panel p-5 text-sm text-ink-soft">
                <h2 class="eyebrow">Coordinated by</h2>
                <p class="mt-3 font-medium text-ink">Office of Research and Innovation</p>
                @if ($challenge->coordinator)
                    <p class="mt-1">{{ $challenge->coordinator->name }}</p>
                @endif
                <p class="mt-3">Selected projects receive guidance from the office and are published in collaboration with the university.</p>
            </div>
        </aside>
    </div>
</div>
