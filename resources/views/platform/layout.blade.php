<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Ultramod Platform')</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    @stack('styles')
    <style>
        /* ---- navbar: platform-level only, apps live in the sidebar ---- */
        .platform-navbar {
            position: sticky;
            top: 0;
            z-index: 1030;
            background: linear-gradient(180deg, #1d2433, #171c28);
            border-bottom: 1px solid rgba(255, 255, 255, .07);
            box-shadow: 0 1px 16px rgba(15, 20, 30, .18);
        }

        @supports (backdrop-filter: blur(12px)) {
            .platform-navbar {
                background: linear-gradient(180deg, rgba(29, 36, 51, .88), rgba(23, 28, 40, .92));
                backdrop-filter: blur(12px);
            }
        }

        .platform-brand {
            display: inline-flex;
            align-items: center;
            gap: .6rem;
            font-weight: 600;
            letter-spacing: -0.01em;
            color: #fff;
            text-decoration: none;
        }

        .platform-brand:hover { color: #fff; }

        .platform-brand-mark {
            display: grid;
            place-items: center;
            width: 2rem;
            height: 2rem;
            border-radius: 30%;
            background: linear-gradient(150deg, #6366f1, #4338ca);
            box-shadow: 0 4px 10px -4px rgba(99, 102, 241, .9);
            font-size: 1rem;
            line-height: 1;
        }

        .platform-nav-link {
            display: block;
            padding: .38rem .8rem;
            border-radius: 999px;
            color: rgba(255, 255, 255, .72);
            font-size: .9rem;
            text-decoration: none;
            white-space: nowrap;
            transition: background-color .12s ease, color .12s ease;
        }

        .platform-nav-link:hover,
        .platform-nav-link:focus-visible {
            background: rgba(255, 255, 255, .09);
            color: #fff;
        }

        .platform-nav-link.is-active {
            background: rgba(255, 255, 255, .14);
            color: #fff;
            font-weight: 600;
        }

        .platform-nav-divider {
            width: 1px;
            height: 1.5rem;
            background: rgba(255, 255, 255, .14);
            margin-inline: .35rem;
        }

        .platform-user {
            display: inline-flex;
            align-items: center;
            gap: .55rem;
            padding: .28rem .5rem .28rem .3rem;
            border-radius: 999px;
            color: rgba(255, 255, 255, .85);
            text-decoration: none;
            font-size: .9rem;
        }

        .platform-user:hover, .platform-user:focus-visible, .show > .platform-user {
            background: rgba(255, 255, 255, .1);
            color: #fff;
        }

        .platform-avatar {
            display: grid;
            place-items: center;
            width: 1.9rem;
            height: 1.9rem;
            border-radius: 50%;
            background: linear-gradient(150deg, #22d3ee, #0284c7);
            color: #04222e;
            font-size: .75rem;
            font-weight: 700;
            line-height: 1;
        }

        .platform-sidebar-toggle {
            display: grid;
            place-items: center;
            width: 2.1rem;
            height: 2.1rem;
            border-radius: .7rem;
            border: 1px solid rgba(255, 255, 255, .18);
            background: rgba(255, 255, 255, .06);
            color: #fff;
            padding: 0;
        }

        .platform-sidebar-toggle:hover { background: rgba(255, 255, 255, .14); color: #fff; }

        .platform-navbar .navbar-toggler {
            border-color: rgba(255, 255, 255, .18);
            padding: .25rem .5rem;
        }

        @media (max-width: 991.98px) {
            .platform-nav-link { padding-inline: .65rem; }
            .platform-nav-divider { display: none; }
        }

        /* Apps live in the sidebar; the navbar is for the platform itself. */
        .platform-shell {
            display: flex;
            align-items: flex-start;
            gap: 1.5rem;
            max-width: 1320px;
            margin: 0 auto;
            padding: 0 1rem 3rem;
        }

        .platform-main { flex: 1 1 auto; min-width: 0; }

        /* Sizing only applies where the sidebar is a real column. Below the
           breakpoint Bootstrap turns it into an offcanvas, and overriding its
           position there would leave a hidden block holding open the page. */
        @media (min-width: 992px) {
            .app-sidebar {
                flex: 0 0 15rem;
                width: 15rem;
                position: sticky;
                top: 5.5rem;
            }
        }

        .app-sidebar .offcanvas-body { padding: 0; }

        .app-sidebar-inner {
            background: var(--bs-body-bg);
            border: 1px solid var(--bs-border-color);
            border-radius: 1rem;
            padding: .6rem;
        }

        .app-sidebar-title {
            font-size: .7rem;
            text-transform: uppercase;
            letter-spacing: .08em;
            color: var(--bs-secondary-color);
            padding: .35rem .55rem .5rem;
        }

        .app-link {
            display: flex;
            align-items: center;
            gap: .65rem;
            padding: .4rem .55rem;
            border-radius: .65rem;
            text-decoration: none;
            color: var(--bs-body-color);
            font-size: .9rem;
            line-height: 1.2;
        }

        .app-link:hover { background: var(--bs-tertiary-bg); color: var(--bs-body-color); }

        .app-link.is-active {
            background: var(--bs-tertiary-bg);
            font-weight: 600;
        }

        .app-link.is-active .app-link-icon { box-shadow: 0 0 0 2px var(--bs-body-bg), 0 0 0 4px var(--tile); }

        .app-link-icon {
            flex: 0 0 auto;
            display: grid;
            place-items: center;
            width: 1.85rem;
            height: 1.85rem;
            border-radius: 30%;
            background: var(--tile);
            color: #fff;
            font-size: .95rem;
            line-height: 1;
        }

        .app-link-label { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }

        @media (max-width: 991.98px) {
            .platform-shell { display: block; padding-inline: .75rem; }
        }
    </style>
</head>
<body class="bg-body-tertiary">

@php($showSidebar = ! ($hideSidebar ?? false) && ! empty($platformMenu))

<nav class="navbar navbar-expand-lg navbar-dark platform-navbar mb-4">
    <div class="container">
        @auth
            @if ($showSidebar)
                <button class="platform-sidebar-toggle d-lg-none me-2" type="button"
                        data-bs-toggle="offcanvas" data-bs-target="#appSidebar"
                        aria-controls="appSidebar" aria-label="Show apps">
                    <svg width="16" height="16" viewBox="0 0 16 16" fill="currentColor" aria-hidden="true">
                        <path d="M1 1h5v5H1V1zm9 0h5v5h-5V1zM1 10h5v5H1v-5zm9 0h5v5h-5v-5z"/>
                    </svg>
                </button>
            @endif
        @endauth

        <a class="platform-brand" href="{{ route('platform.index') }}">
            <span class="platform-brand-mark">U</span>
            <span>Ultramod</span>
        </a>

        <button class="navbar-toggler ms-auto" type="button" data-bs-toggle="collapse" data-bs-target="#platformNav"
                aria-controls="platformNav" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="platformNav">
            {{-- Platform-level navigation only. Installed apps are in the sidebar. --}}
            <div class="d-flex flex-column flex-lg-row align-items-lg-center gap-1 ms-lg-auto mt-3 mt-lg-0">
                @auth
                    <a class="platform-nav-link {{ request()->routeIs('platform.index') ? 'is-active' : '' }}"
                       href="{{ route('platform.index') }}">Home</a>

                    @can('platform.manage')
                        <a class="platform-nav-link {{ request()->routeIs('platform.apps.*') ? 'is-active' : '' }}"
                           href="{{ route('platform.apps.index') }}">Apps</a>
                        <a class="platform-nav-link {{ request()->routeIs('platform.users.*') ? 'is-active' : '' }}"
                           href="{{ route('platform.users.index') }}">Users</a>
                        <a class="platform-nav-link {{ request()->routeIs('platform.roles.*') ? 'is-active' : '' }}"
                           href="{{ route('platform.roles.index') }}">Roles</a>
                    @endcan

                    <span class="platform-nav-divider d-none d-lg-block"></span>

                    @php($user = auth()->user())
                    @php($initials = \Illuminate\Support\Str::of($user->name)
                        ->explode(' ')
                        ->filter()
                        ->take(2)
                        ->map(fn ($word) => \Illuminate\Support\Str::upper(\Illuminate\Support\Str::substr($word, 0, 1)))
                        ->implode(''))

                    <div class="dropdown">
                        <a class="platform-user dropdown-toggle" href="#" role="button"
                           data-bs-toggle="dropdown" aria-expanded="false">
                            <span class="platform-avatar">{{ $initials !== '' ? $initials : 'U' }}</span>
                            <span>{{ $user->name }}</span>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end shadow">
                            <li class="px-3 py-2">
                                <div class="fw-semibold">{{ $user->name }}</div>
                                <div class="small text-muted">{{ $user->email }}</div>
                            </li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="{{ route('profile.edit') }}">Profile</a></li>
                            <li><a class="dropdown-item" href="{{ route('profile.security') }}">Security</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li>
                                <form method="POST" action="{{ route('logout') }}">
                                    @csrf
                                    <button type="submit" class="dropdown-item text-danger">Sign out</button>
                                </form>
                            </li>
                        </ul>
                    </div>
                @else
                    <a class="platform-nav-link" href="{{ route('login') }}">Sign in</a>
                @endauth
            </div>
        </div>
    </div>
</nav>

<div class="platform-shell">
    @auth
        {{-- The launcher already is a list of apps; a sidebar repeating it
             would just say the same thing twice. --}}
        @if ($showSidebar)
            @php($currentApp = \Illuminate\Support\Str::before((string) request()->route()?->getName(), '.'))
            <aside class="app-sidebar offcanvas-lg offcanvas-start" tabindex="-1" id="appSidebar"
                   aria-labelledby="appSidebarLabel">
                <div class="offcanvas-header">
                    <h5 class="offcanvas-title" id="appSidebarLabel">Apps</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="offcanvas"
                            data-bs-target="#appSidebar" aria-label="Close"></button>
                </div>
                <div class="offcanvas-body">
                    <div class="app-sidebar-inner">
                        <div class="app-sidebar-title d-none d-lg-block">Apps</div>
                        @foreach ($platformMenu as $item)
                            @if (\Illuminate\Support\Facades\Route::has($item['route']))
                                <a class="app-link {{ $currentApp !== '' && \Illuminate\Support\Str::before($item['route'], '.') === $currentApp ? 'is-active' : '' }}"
                                   href="{{ route($item['route']) }}"
                                   style="--tile: {{ $item['color'] ?? '#6c757d' }}">
                                    <span class="app-link-icon">{{ $item['icon'] ?? '?' }}</span>
                                    <span class="app-link-label">{{ $item['label'] }}</span>
                                </a>
                            @endif
                        @endforeach
                    </div>
                </div>
            </aside>
        @endif
    @endauth

    <main class="platform-main">
        @if (session('status'))
            <div class="alert alert-success">{{ session('status') }}</div>
        @endif

        @if (session('error'))
            <div class="alert alert-danger">{{ session('error') }}</div>
        @endif

        @yield('content')
    </main>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
@stack('scripts')
</body>
</html>
