@php
    $user = auth()->user();
    $org = $currentOrganization ?? $user->resolveCurrentOrganization();
    $project = $project ?? null;
    $projects = $org ? $org->projects()->orderBy('name')->get(['id', 'name', 'platform', 'organization_id']) : collect();
    $orgs = $user->is_super_admin ? \App\Models\Organization::orderBy('name')->get(['id', 'name']) : $user->organizations()->orderBy('name')->get(['organizations.id', 'organizations.name']);
@endphp
<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ isset($title) ? $title.' · ' : '' }}{{ $project?->name ? $project->name.' · ' : '' }}Loggerxo</title>
    <link rel="icon" href="/favicon.svg?v=2" type="image/svg+xml">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="h-full" x-data="{ sidebar: false }">
<div class="flex min-h-full">
    {{-- Sidebar --}}
    <aside class="fixed inset-y-0 left-0 z-40 flex w-[248px] flex-col border-r border-ink-800 bg-ink-900/95 backdrop-blur transition-transform lg:static lg:translate-x-0" :class="sidebar ? 'translate-x-0' : '-translate-x-full'">
        <div class="flex h-14 items-center gap-2 border-b border-ink-800 px-4">
            <a href="{{ route('app.dashboard') }}" class="flex items-center gap-2">
                <x-logo class="h-7 w-7" />
                <span class="text-[15px] font-semibold tracking-tight text-white">Loggerxo</span>
            </a>
        </div>

        {{-- Organization switcher --}}
        <div class="px-3 pt-3" x-data="{ open: false }" @click.outside="open = false">
            <button @click="open = !open" class="flex w-full items-center gap-2 rounded-lg border border-ink-700 bg-ink-850 px-2.5 py-2 text-left hover:border-ink-600">
                <span class="flex h-6 w-6 items-center justify-center rounded-md bg-brand-600/20 text-[11px] font-bold text-brand-300">{{ mb_strtoupper(mb_substr($org?->name ?? 'O', 0, 1)) }}</span>
                <span class="flex-1 truncate text-[13px] font-medium text-ink-100">{{ $org?->name ?? 'No organization' }}</span>
                <x-icon name="chevron-down" class="h-3.5 w-3.5 text-ink-400" />
            </button>
            <div x-cloak x-show="open" x-transition.opacity class="absolute left-3 right-3 z-50 mt-1 rounded-lg border border-ink-700 bg-ink-850 p-1 shadow-2xl">
                @foreach ($orgs as $o)
                    <a href="{{ route('organization.switch', $o) }}" class="flex items-center justify-between rounded-md px-2.5 py-1.5 text-[13px] hover:bg-ink-800 {{ $org?->id === $o->id ? 'text-white' : 'text-ink-300' }}">
                        <span class="truncate">{{ $o->name }}</span>
                        @if ($org?->id === $o->id) <x-icon name="check" class="h-3.5 w-3.5 text-brand-400" /> @endif
                    </a>
                @endforeach
                <div class="my-1 border-t border-ink-700"></div>
                <a href="{{ route('organizations.create') }}" class="flex items-center gap-2 rounded-md px-2.5 py-1.5 text-[13px] text-ink-300 hover:bg-ink-800"><x-icon name="plus" class="h-3.5 w-3.5" /> New organization</a>
                <a href="{{ route('organization.settings') }}" class="flex items-center gap-2 rounded-md px-2.5 py-1.5 text-[13px] text-ink-300 hover:bg-ink-800"><x-icon name="users" class="h-3.5 w-3.5" /> Members &amp; settings</a>
            </div>
        </div>

        <nav class="flex-1 overflow-y-auto px-3 py-3">
            <a href="{{ route('app.dashboard') }}" class="nav-item {{ request()->routeIs('app.dashboard') ? 'active' : '' }}"><x-icon name="home" /> Overview</a>

            @if ($project)
                <div class="mt-4 mb-1 flex items-center justify-between px-2.5">
                    <span class="text-[11px] font-semibold uppercase tracking-wider text-ink-500">{{ $project->name }}</span>
                    <span class="badge border-ink-700 bg-ink-800 text-ink-400 normal-case">{{ $project->platformLabel() }}</span>
                </div>
                <a href="{{ route('projects.show', $project) }}" class="nav-item {{ request()->routeIs('projects.show') ? 'active' : '' }}"><x-icon name="activity" /> Project overview</a>
                <a href="{{ route('issues.index', $project) }}" class="nav-item {{ request()->routeIs('issues.*') ? 'active' : '' }}"><x-icon name="bug" /> Issues</a>
                <a href="{{ route('logs.index', $project) }}" class="nav-item {{ request()->routeIs('logs.*') ? 'active' : '' }}"><x-icon name="list" /> Logs &amp; console</a>
                <a href="{{ route('performance.index', $project) }}" class="nav-item {{ request()->routeIs('performance.*') ? 'active' : '' }}"><x-icon name="gauge" /> Performance</a>
                <a href="{{ route('projects.settings', $project) }}" class="nav-item {{ request()->routeIs('projects.settings') ? 'active' : '' }}"><x-icon name="settings" /> Settings &amp; setup</a>
            @endif

            <div class="mt-4 mb-1 flex items-center justify-between px-2.5">
                <span class="text-[11px] font-semibold uppercase tracking-wider text-ink-500">Projects</span>
                <a href="{{ route('projects.create') }}" class="text-ink-500 hover:text-ink-200" title="New project"><x-icon name="plus" class="h-3.5 w-3.5" /></a>
            </div>
            @forelse ($projects as $p)
                <a href="{{ route('projects.show', $p) }}" class="nav-item {{ $project?->id === $p->id ? 'text-white' : '' }}">
                    <span class="flex h-5 w-5 items-center justify-center rounded bg-ink-800 text-[10px] font-bold uppercase text-ink-300">{{ mb_substr($p->name, 0, 1) }}</span>
                    <span class="truncate">{{ $p->name }}</span>
                </a>
            @empty
                <a href="{{ route('projects.create') }}" class="nav-item text-brand-400"><x-icon name="plus" /> Create first project</a>
            @endforelse
        </nav>

        <div class="border-t border-ink-800 p-3" x-data="{ open: false }" @click.outside="open = false">
            <div x-cloak x-show="open" x-transition.opacity class="mb-2 rounded-lg border border-ink-700 bg-ink-850 p-1 shadow-2xl">
                <a href="{{ route('account.settings') }}" class="flex items-center gap-2 rounded-md px-2.5 py-1.5 text-[13px] text-ink-300 hover:bg-ink-800"><x-icon name="user" class="h-3.5 w-3.5" /> Account</a>
                <a href="{{ route('docs') }}" class="flex items-center gap-2 rounded-md px-2.5 py-1.5 text-[13px] text-ink-300 hover:bg-ink-800"><x-icon name="book" class="h-3.5 w-3.5" /> Documentation</a>
                @if ($user->is_super_admin)
                    <a href="{{ route('admin.index') }}" class="flex items-center gap-2 rounded-md px-2.5 py-1.5 text-[13px] text-ink-300 hover:bg-ink-800"><x-icon name="shield" class="h-3.5 w-3.5" /> Admin</a>
                @endif
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="flex w-full items-center gap-2 rounded-md px-2.5 py-1.5 text-left text-[13px] text-ink-300 hover:bg-ink-800"><x-icon name="logout" class="h-3.5 w-3.5" /> Sign out</button>
                </form>
            </div>
            <button @click="open = !open" class="flex w-full items-center gap-2.5 rounded-lg px-2 py-1.5 text-left hover:bg-ink-800">
                <span class="flex h-7 w-7 items-center justify-center rounded-full bg-ink-700 text-[11px] font-semibold text-ink-100">{{ $user->initials() }}</span>
                <span class="min-w-0 flex-1">
                    <span class="block truncate text-[13px] font-medium text-ink-100">{{ $user->name }}</span>
                    <span class="block truncate text-[11px] text-ink-500">{{ $user->email }}</span>
                </span>
                <x-icon name="chevron-down" class="h-3.5 w-3.5 text-ink-500" />
            </button>
        </div>
    </aside>

    <div x-cloak x-show="sidebar" @click="sidebar = false" class="fixed inset-0 z-30 bg-black/60 lg:hidden"></div>

    {{-- Main --}}
    <div class="flex min-w-0 flex-1 flex-col">
        <header class="sticky top-0 z-20 flex h-14 items-center gap-3 border-b border-ink-800 bg-ink-950/80 px-4 backdrop-blur lg:px-6">
            <button @click="sidebar = true" class="btn-ghost -ml-2 px-2 lg:hidden"><x-icon name="list" /></button>
            <div class="flex min-w-0 flex-1 items-center gap-2 text-[13px] text-ink-400">
                @if ($project)
                    <a href="{{ route('projects.show', $project) }}" class="truncate hover:text-ink-100">{{ $project->name }}</a>
                    <x-icon name="chevron-right" class="h-3.5 w-3.5 shrink-0" />
                @endif
                <span class="truncate font-medium text-ink-100">{{ $title ?? 'Overview' }}</span>
            </div>
            <div class="flex items-center gap-2">
                {{ $actions ?? '' }}
            </div>
        </header>

        <main class="flex-1 px-4 py-5 lg:px-6">
            @if (session('status'))
                <div class="mb-4 flex items-center gap-2 rounded-lg border border-emerald-500/30 bg-emerald-500/10 px-4 py-2.5 text-[13px] text-emerald-200"><x-icon name="check" class="h-4 w-4" /> {{ session('status') }}</div>
            @endif
            @if (session('error'))
                <div class="mb-4 flex items-center gap-2 rounded-lg border border-red-500/30 bg-red-500/10 px-4 py-2.5 text-[13px] text-red-200"><x-icon name="warning" class="h-4 w-4" /> {{ session('error') }}</div>
            @endif
            {{ $slot }}
        </main>
    </div>
</div>
</body>
</html>
