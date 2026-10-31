@props(['title' => null, 'description' => null])

@php
    $user = auth()->user();
    $isStudent = $user?->isStudent() ?? false;
    $isFaculty = $user?->isFaculty() ?? false;

    $navigation = array_filter([
        ['label' => 'Search', 'route' => 'repository', 'active' => ['repository', 'catalog', 'repository.show']],
        ['label' => 'Research gaps', 'route' => 'gaps', 'active' => ['gaps']],
        ['label' => 'Innovation', 'route' => 'innovation', 'active' => ['innovation', 'innovation.*']],
        $isStudent ? ['label' => 'My research', 'route' => 'student.dashboard', 'active' => ['student.dashboard', 'student.proposal.*']] : null,
        $isStudent ? ['label' => 'Submissions', 'route' => 'student.manuscripts', 'active' => ['student.manuscripts']] : null,
        $user ? ['label' => 'Folder', 'route' => 'folder', 'active' => ['folder']] : null,
    ]);
@endphp

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full scroll-smooth">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="description" content="{{ $description ?? 'The Research Repository Interface System (RRIS) is the research archive and project database of Mulungushi University. Search completed studies from every school, explore research gaps and manage supervised projects.' }}">

    <title>{{ $title ? $title.' · ' : '' }}RRIS · Mulungushi University</title>
    <link rel="icon" href="{{ asset('favicon.ico') }}">

    @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
        @fonts
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    @endif
    @livewireStyles
</head>
<body class="flex min-h-full flex-col bg-white font-sans text-ink">
    <a href="#main" class="sr-only focus:not-sr-only focus:absolute focus:top-2 focus:left-2 focus:z-50 focus:rounded focus:bg-white focus:px-3 focus:py-2 focus:text-sm focus:shadow">Skip to content</a>

    <x-impersonation-banner />

    {{-- Institutional utility bar --}}
    <div class="bg-forest-950 text-[13px] text-forest-100">
        <div class="container-page flex h-9 items-center justify-between gap-4">
            <p class="truncate">
                <span class="hidden sm:inline">Mulungushi University</span>
                <span class="sm:hidden">MU</span>
                <span class="mx-2 text-forest-400" aria-hidden="true">/</span>
                <span class="text-forest-300">Research Repository Interface System</span>
            </p>
            <div class="flex shrink-0 items-center gap-4">
                @if ($user?->usesStaffWorkspace())
                    <a href="{{ url('/supervisor') }}" class="font-medium text-white hover:underline">{{ $user->workspaceLabel() }}</a>
                @endif
                @auth
                    <span class="hidden text-forest-300 md:inline">{{ $user->role?->label() }}</span>
                @endauth
            </div>
        </div>
    </div>

    {{-- Primary masthead --}}
    <header x-data="{ open: false }" class="sticky top-0 z-40 border-b border-line bg-white/95 backdrop-blur">
        <div class="container-page flex h-14 items-center justify-between gap-4 sm:h-16 sm:gap-6">
            <div class="flex h-full min-w-0 items-center gap-10">
                <a href="{{ route('home') }}" class="flex shrink-0 items-center gap-3" aria-label="RRIS home">
                    <x-brand-mark class="h-8 w-8" />
                    <span class="leading-none">
                        <span class="block text-lg font-bold tracking-tight text-ink">RRIS</span>
                        <span class="mt-0.5 block text-[11px] font-medium tracking-wide text-muted">Research Repository</span>
                    </span>
                </a>

                <nav class="hidden h-full items-center gap-7 lg:flex" aria-label="Primary">
                    @foreach ($navigation as $item)
                        <a href="{{ route($item['route']) }}" @class(['nav-link', 'nav-link-active' => request()->routeIs(...$item['active'])]) @if (request()->routeIs(...$item['active'])) aria-current="page" @endif>
                            {{ $item['label'] }}
                        </a>
                    @endforeach
                </nav>
            </div>

            <div class="flex shrink-0 items-center gap-1 sm:gap-2">
                @auth
                    @if ($isStudent)
                        <a href="{{ route('student.dashboard') }}#updates" class="relative rounded-md p-2 text-ink-soft hover:bg-canvas hover:text-ink" aria-label="Supervisor updates{{ $unreadCount ? ', '.$unreadCount.' unread' : '' }}">
                            <x-glyph name="bell" class="h-5 w-5" />
                            @if ($unreadCount)
                                <span class="absolute top-1 right-1 flex h-4 min-w-4 items-center justify-center rounded-full bg-ochre-500 px-1 text-[10px] font-bold text-white">{{ $unreadCount }}</span>
                            @endif
                        </a>
                    @endif

                    <div x-data="{ menu: false }" class="relative hidden sm:block">
                        <button type="button" @click="menu = !menu" @click.outside="menu = false" class="flex items-center gap-2.5 rounded-md py-1.5 pr-2 pl-1.5 text-left hover:bg-canvas" :aria-expanded="menu">
                            <span class="flex h-8 w-8 items-center justify-center rounded-full bg-forest-100 text-xs font-bold text-forest-800">{{ $user->initials() }}</span>
                            <span class="hidden leading-tight md:block">
                                <span class="block text-sm font-medium text-ink">{{ $user->name }}</span>
                                <span class="block text-xs text-muted">{{ $user->department ?? 'Mulungushi University' }}</span>
                            </span>
                            <x-glyph name="chevron-down" class="h-4 w-4 text-subtle" />
                        </button>
                        <div x-cloak x-show="menu" x-transition.origin.top.right class="absolute right-0 mt-2 w-56 overflow-hidden rounded-lg border border-line bg-white py-1 shadow-lg">
                            @if ($isStudent)
                                <a href="{{ route('student.dashboard') }}" class="block px-4 py-2 text-sm text-ink-soft hover:bg-canvas">My research</a>
                            @endif
                            @if ($user->usesStaffWorkspace())
                                <a href="{{ url('/supervisor') }}" class="block px-4 py-2 text-sm text-ink-soft hover:bg-canvas">{{ $user->workspaceLabel() }}</a>
                            @endif
                            <a href="{{ route('folder') }}" class="block px-4 py-2 text-sm text-ink-soft hover:bg-canvas">Saved records</a>
                            <form action="{{ route('logout') }}" method="POST" class="border-t border-line">
                                @csrf
                                <button type="submit" class="block w-full px-4 py-2 text-left text-sm text-ink-soft hover:bg-canvas hover:text-red-700">Sign out</button>
                            </form>
                        </div>
                    </div>
                @else
                    <a href="{{ route('login') }}" class="btn-primary btn-sm hidden sm:inline-flex">Institutional Sign In</a>
                @endauth

                <button type="button" @click="open = !open" class="rounded-md p-2 text-ink-soft hover:bg-canvas lg:hidden" :aria-expanded="open" aria-label="Toggle navigation">
                    <x-glyph name="menu" class="h-5 w-5" x-show="!open" />
                    <x-glyph name="x" class="h-5 w-5" x-cloak x-show="open" />
                </button>
            </div>
        </div>

        {{-- Mobile navigation --}}
        <nav x-cloak x-show="open" x-transition @keydown.escape.window="open = false" class="max-h-[calc(100vh-3.5rem)] overflow-y-auto border-t border-line bg-white lg:hidden" aria-label="Mobile">
            <div class="container-page space-y-1 py-3">
                @auth
                    <div class="mb-2 flex items-center gap-3 border-b border-line px-3 pb-3 sm:hidden">
                        <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-forest-100 text-xs font-bold text-forest-800">{{ $user->initials() }}</span>
                        <span class="min-w-0 leading-tight">
                            <span class="block truncate text-sm font-medium text-ink">{{ $user->name }}</span>
                            <span class="block truncate text-xs text-muted">{{ $user->role?->label() }} &middot; {{ $user->department ?? 'Mulungushi University' }}</span>
                        </span>
                    </div>
                @endauth
                @foreach ($navigation as $item)
                    <a href="{{ route($item['route']) }}" @class([
                        'block rounded-md px-3 py-2.5 text-sm font-medium',
                        'bg-forest-50 text-forest-800' => request()->routeIs(...$item['active']),
                        'text-ink-soft hover:bg-canvas' => ! request()->routeIs(...$item['active']),
                    ])>{{ $item['label'] }}</a>
                @endforeach
                @auth
                    <form action="{{ route('logout') }}" method="POST" class="border-t border-line pt-1">
                        @csrf
                        <button type="submit" class="block w-full rounded-md px-3 py-2.5 text-left text-sm font-medium text-ink-soft hover:bg-canvas hover:text-red-700">Sign out</button>
                    </form>
                @else
                    <a href="{{ route('login') }}" class="btn-primary mt-2 w-full sm:hidden">Institutional Sign In</a>
                    <a href="{{ route('login') }}" class="hidden rounded-md px-3 py-2.5 text-sm font-semibold text-forest-700 sm:block">Sign in</a>
                @endauth
            </div>
        </nav>
    </header>

    @if (session('status') || session('success') || session('error'))
        <div x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 6000)" class="border-b border-line {{ session('error') ? 'bg-red-50 text-red-800' : 'bg-forest-50 text-forest-900' }}">
            <div class="container-page flex items-center justify-between gap-4 py-2.5 text-sm">
                <p class="min-w-0">{{ session('error') ?? session('success') ?? session('status') }}</p>
                <button type="button" @click="show = false" class="shrink-0 text-current opacity-60 hover:opacity-100" aria-label="Dismiss"><x-glyph name="x" class="h-4 w-4" /></button>
            </div>
        </div>
    @endif

    <main id="main" class="flex-1">
        {{ $slot }}
    </main>

    <footer class="mt-auto border-t border-line bg-canvas">
        <div class="container-page grid grid-cols-2 gap-8 py-10 sm:gap-10 sm:py-12 lg:grid-cols-4">
            <div class="col-span-2">
                <div class="flex items-center gap-3">
                    <x-brand-mark class="h-7 w-7" />
                    <span class="text-base font-bold tracking-tight">Research Repository Interface System</span>
                </div>
                <p class="mt-3 max-w-md text-sm leading-relaxed text-muted">
                    The research archive and project database of Mulungushi University, open to every school and department. Completed studies are catalogued for discovery; manuscripts, data and assessments stay with their authors, supervisors and faculty.
                </p>
            </div>
            <div>
                <h2 class="eyebrow">Discover</h2>
                <ul class="mt-3 space-y-2 text-sm">
                    <li><a href="{{ route('repository') }}" class="text-ink-soft hover:text-forest-800">Search the archive</a></li>
                    <li><a href="{{ route('gaps') }}" class="text-ink-soft hover:text-forest-800">Research gaps</a></li>
                    <li><a href="{{ route('innovation') }}" class="text-ink-soft hover:text-forest-800">Research &amp; Innovation Office</a></li>
                    <li><a href="{{ route('home') }}#subjects" class="text-ink-soft hover:text-forest-800">Browse subjects</a></li>
                </ul>
            </div>
            <div>
                <h2 class="eyebrow">Researchers</h2>
                <ul class="mt-3 space-y-2 text-sm">
                    <li><a href="{{ $isStudent ? route('student.dashboard') : route('login') }}" class="text-ink-soft hover:text-forest-800">Student workspace</a></li>
                    <li><a href="{{ url('/supervisor') }}" class="text-ink-soft hover:text-forest-800">Lecturer portal</a></li>
                    <li><a href="{{ route('folder') }}" class="text-ink-soft hover:text-forest-800">Saved records</a></li>
                </ul>
            </div>
        </div>
        <div class="border-t border-line">
            <div class="container-page flex flex-col gap-2 py-5 text-xs text-muted sm:flex-row sm:items-center sm:justify-between">
                <p>&copy; {{ date('Y') }} Mulungushi University. All rights reserved.</p>
                <p>Full texts are access-controlled under the university's academic integrity policy.</p>
            </div>
        </div>
    </footer>

    @livewireScripts
</body>
</html>
