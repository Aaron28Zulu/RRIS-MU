<x-shell title="Sign in">
    <div class="bg-canvas">
        <div class="container-page grid min-h-[calc(100svh-14rem)] grid-cols-1 items-center gap-12 py-8 sm:py-14 lg:grid-cols-2">
            <div class="hidden lg:block">
                <p class="eyebrow text-forest-700">Research Repository Interface System</p>
                <h1 class="mt-4 text-4xl font-bold tracking-tight">Sign in to your research workspace</h1>
                <p class="mt-4 max-w-md text-lg leading-relaxed text-ink-soft">Students track proposals and drafts. Lecturers review advisees in the lecturer portal. Deans and the research office oversee research across every school.</p>
                <ul class="mt-8 space-y-3 text-sm text-ink-soft">
                    <li class="flex gap-3"><x-glyph name="check-circle" class="h-5 w-5 shrink-0 text-forest-700" /> Save records to your folder while you search</li>
                    <li class="flex gap-3"><x-glyph name="check-circle" class="h-5 w-5 shrink-0 text-forest-700" /> Get notified when your supervisor reviews a submission</li>
                    <li class="flex gap-3"><x-glyph name="check-circle" class="h-5 w-5 shrink-0 text-forest-700" /> Open full texts you're entitled to</li>
                </ul>
            </div>

            <div class="mx-auto w-full max-w-md">
                <div class="panel p-6 shadow-sm sm:p-8">
                    <h2 class="text-xl font-bold tracking-tight">Institutional sign in</h2>
                    <p class="mt-1 text-sm text-muted">Use your university email address.</p>
                    <p class="mt-3 text-sm text-ink-soft lg:hidden">Students track proposals and drafts, lecturers review advisees, and the research office oversees every school.</p>

                    @if ($errors->any())
                        <div class="mt-5 rounded-md bg-red-50 p-3 text-sm text-red-800" role="alert">{{ $errors->first() }}</div>
                    @endif

                    <form action="{{ route('login.attempt') }}" method="POST" class="mt-6 space-y-5">
                        @csrf
                        <div>
                            <label for="email" class="field-label">Email address</label>
                            <input id="email" name="email" type="email" autocomplete="email" required autofocus value="{{ old('email') }}" class="field" placeholder="name@rris.mu.ac.zm" />
                        </div>
                        <div>
                            <label for="password" class="field-label">Password</label>
                            <input id="password" name="password" type="password" autocomplete="current-password" required class="field" />
                        </div>
                        <label class="flex cursor-pointer items-center gap-2 text-sm text-ink-soft">
                            <input type="checkbox" name="remember" class="h-4 w-4 rounded border-line-strong text-forest-700 focus:ring-forest-600" />
                            Keep me signed in
                        </label>
                        <button type="submit" class="btn-primary w-full">Sign in</button>
                    </form>

                    @if (app()->environment('local'))
                        <div class="mt-8 border-t border-line pt-6">
                            <p class="eyebrow">Demo accounts (local only)</p>
                            <div class="mt-3 grid grid-cols-1 gap-2 min-[400px]:grid-cols-2">
                                @foreach ([
                                    ['kelvin@rris.mu.ac.zm', 'Student', 'Kelvin Mwape'],
                                    ['lukundo@rris.mu.ac.zm', 'Researcher', 'Lukundo Bwalya'],
                                    ['cmulenga@rris.mu.ac.zm', 'Lecturer', 'Dr. C. Mulenga'],
                                    ['dean@rris.mu.ac.zm', 'Dean', 'Dr. M. Phiri'],
                                    ['director@rris.mu.ac.zm', 'Research Director', 'Prof. E. Banda'],
                                    ['librarian@rris.mu.ac.zm', 'Librarian', 'Mrs. G. Chileshe'],
                                ] as [$demoEmail, $demoRole, $demoName])
                                    <a href="{{ route('login.quick', ['email' => $demoEmail]) }}" class="rounded-md border border-line px-3 py-2.5 transition hover:border-forest-400 hover:bg-forest-50">
                                        <span class="block text-sm font-semibold text-ink">{{ $demoRole }}</span>
                                        <span class="block truncate text-xs text-muted">{{ $demoName }}</span>
                                    </a>
                                @endforeach
                            </div>
                            <p class="mt-3 text-xs text-muted">Seeded password: <code class="font-mono">password</code></p>
                        </div>
                    @endif
                </div>
                <p class="mt-6 text-center text-sm">
                    <a href="{{ route('repository') }}" class="font-medium text-forest-700 hover:underline">Continue to the archive without signing in</a>
                </p>
            </div>
        </div>
    </div>
</x-shell>
